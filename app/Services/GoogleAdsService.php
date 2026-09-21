<?php

namespace App\Services;

use App\Models\App;
use App\Models\CampaignStat;
use App\Models\Connection;
use App\Models\Country;
use App\Models\DailyStat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Google Ads REST client (no SDK). "Sync All" flow:
 *   1. exchange each connection's refresh token for an access token,
 *   2. list every account the credentials can access,
 *   3. expand managers (MCC) into their client accounts,
 *   4. pull all campaigns + totals for the date range into campaign_stats.
 *
 * Cost → cost, Conversions → conversions, Conv. value → conversions_value.
 */
class GoogleAdsService
{
    private string $version = 'v18';
    private string $base    = 'https://googleads.googleapis.com/v18';

    /** Countries are refreshed once per Sync All run, not per account. */
    private bool $countriesSynced = false;

    /** Tracked-app lookup, built once: normalised store App ID => apps.id */
    private ?array $appByPackage = null;

    /** Current account's campaign_id => store App ID (from app_campaign_setting). */
    private array $campaignAppMap = [];

    private function setVersion(string $v): void
    {
        $this->version = $v;
        $this->base    = "https://googleads.googleapis.com/{$v}";
    }

    /** Newest→oldest versions to probe; cached/env version is tried first. */
    private function candidateVersions(): array
    {
        $preferred = array_filter([getSetting('google_ads_api_version'), env('GOOGLE_ADS_API_VERSION')]);
        // Newest first so we land on the current supported version; non-existent
        // or sunset versions simply 404 and are skipped.
        $list = ['v25', 'v24', 'v23', 'v22', 'v21', 'v20', 'v19', 'v18', 'v17'];
        return array_values(array_unique(array_merge($preferred, $list)));
    }

    /**
     * Sync every active, configured connection for [$start, $end].
     * Returns ['campaigns' => int, 'accounts' => int, 'errors' => string[]].
     */
    /**
     * Test a single connection's credentials: sign in, then list accessible
     * accounts. Returns ['ok' => bool, 'message' => string, 'accounts' => int].
     */
    public function testConnection(Connection $conn): array
    {
        try {
            $token    = $this->accessToken($conn);
            $accounts = $this->listAccessibleCustomers($token, $conn->developer_token);
            return [
                'ok'       => true,
                'accounts' => count($accounts),
                'message'  => 'Signed in OK · ' . count($accounts) . ' accessible account(s).',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'accounts' => 0, 'message' => $e->getMessage()];
        }
    }

    public function syncAll(string $start, string $end): array
    {
        $summary = ['campaigns' => 0, 'accounts' => 0, 'daily' => 0, 'errors' => [],
                    'debug' => [], 'debug_discovery' => []];

        // Many accessible accounts = many API calls; don't let PHP time out mid-sync.
        @set_time_limit(600);

        $this->countriesSynced = false;
        $this->appByPackage = null;

        $connections = Connection::where('active', true)->get()
            ->filter(fn ($c) => $c->isConfigured());

        if ($connections->isEmpty()) {
            $summary['errors'][] = 'No configured Ad Accounts. Add credentials on the Ad Accounts page first.';
            return $summary;
        }

        foreach ($connections as $conn) {
            try {
                $this->syncConnection($conn, $start, $end, $summary);
            } catch (\Throwable $e) {
                $summary['errors'][] = "{$conn->name}: " . $e->getMessage();
            }
        }

        return $summary;
    }

    private function syncConnection(Connection $conn, string $start, string $end, array &$summary): void
    {
        $token   = $this->accessToken($conn);
        $devToken = $conn->developer_token;

        // 1. Accounts the credentials can access.
        $accessible = $this->listAccessibleCustomers($token, $devToken);
        if (empty($accessible)) {
            throw new \RuntimeException('No accessible Google Ads accounts for these credentials.');
        }

        // 2. Discover a usable Manager (login-customer-id). Google requires
        //    login-customer-id to be a MANAGER that manages the target account,
        //    so we probe candidates — previously-found managers and the configured
        //    Login Customer ID first, then every accessible account — and
        //    enumerate the accounts each working manager manages.
        $configured = preg_replace('/\D/', '', (string) $conn->login_customer_id);
        $cacheKey   = 'gads_managers_' . $conn->id;
        $cached     = array_filter(explode(',', (string) getSetting($cacheKey)));

        $candidates = array_values(array_unique(array_filter(array_merge(
            $cached,
            $configured !== '' ? [$configured] : [],
            $accessible
        ))));

        $targets       = [];   // customerId => ['login' => managerId, 'name' => ?]
        $foundManagers = [];
        $managerIds    = [];   // every manager id seen — excluded from metric queries
        foreach ($candidates as $m) {
            // Already covered as a client of a discovered manager — skip the probe.
            if (isset($targets[$m]) && $targets[$m]['login'] !== $m) {
                continue;
            }
            try {
                $clients = $this->expandLeafAccounts($token, $devToken, $m, $managerIds);
            } catch (\Throwable $e) {
                continue; // not usable as a login-customer-id (client account / no access)
            }
            $foundManagers[] = $m;
            foreach ($clients as $leaf) {
                $targets[$leaf['id']] = ['login' => $m, 'name' => $leaf['name']];
            }
            // NB: the manager itself is NOT added as a target — manager accounts
            // have no metrics (REQUESTED_METRICS_FOR_MANAGER), only their clients do.
        }

        if (!empty($foundManagers)) {
            setSetting($cacheKey, implode(',', array_slice($foundManagers, 0, 20)));
        }

        // Also try EVERY accessible account DIRECTLY (no login-customer-id header).
        // This is the correct method for accounts your login accesses directly —
        // setting login-customer-id to a non-manager is what triggers PERMISSION_DENIED.
        foreach ($accessible as $cid) {
            $targets[$cid] ??= ['login' => '', 'name' => null]; // '' => omit login-customer-id
        }

        // Drop manager accounts — querying metrics on them just errors out.
        foreach (array_keys($targets) as $tid) {
            if (isset($managerIds[$tid])) {
                unset($targets[$tid]);
            }
        }

        // Discovery snapshot for the debug panel.
        $summary['debug_discovery'][] = [
            'connection'      => $conn->name,
            'accessible'      => $accessible,
            'found_managers'  => $foundManagers,
            'target_count'    => count($targets),
            'target_ids'      => array_keys($targets),
        ];

        // 3. Pull campaigns per account — one failing account (permissions, etc.)
        //    must NOT abort the rest of the sync.
        foreach ($targets as $customerId => $meta) {
            $summary['accounts']++;
            $dbg = [
                'customer_id' => (string) $customerId,
                'login'       => $meta['login'] === '' ? '(direct)' : $meta['login'],
                'name'        => $meta['name'],
                'campaigns'   => 0,
                'daily'       => 0,
                'status'      => 'ok',
                'error'       => null,
            ];
            $dailyBefore = $summary['daily'] ?? 0;

            // Give each account its own time budget so a large (but steadily
            // progressing) multi-account sync can't trip the max-execution-time
            // fatal mid-way. Resets the counter on each iteration.
            @set_time_limit(120);

            // Refresh the country map once from the first reachable account.
            if (!$this->countriesSynced) {
                try {
                    $this->syncCountries($token, $devToken, $meta['login'], $customerId);
                    $this->countriesSynced = true;
                } catch (\Throwable $e) {
                    // non-fatal — the seeded curated list still resolves names
                }
            }

            try {
                $campaigns = $this->fetchCampaigns($token, $devToken, $meta['login'], $customerId, $start, $end);
            } catch (\Throwable $e) {
                $summary['errors'][] = "acct {$customerId}: " . $e->getMessage();
                $dbg['status'] = 'skipped';
                $dbg['error']  = $e->getMessage();
                $summary['debug'][] = $dbg;
                continue;
            }

            // Map this account's campaigns to their target store App ID so the
            // daily rows can be attached to the matching tracked App by package.
            $this->campaignAppMap = [];
            foreach ($campaigns as $c) {
                if (!empty($c['app_id'])) {
                    $this->campaignAppMap[$c['id']] = $c['app_id'];
                }
            }

            // Daily / per-country / bucketed rows for the Excel-style report.
            try {
                $daily = $this->fetchDailyGeoStats($token, $devToken, $meta['login'], $customerId, $start, $end);
                $this->persistDailyStats($conn, $customerId, $meta['name'], $daily, $summary);
            } catch (\Throwable $e) {
                $summary['errors'][] = "acct {$customerId} (daily): " . $e->getMessage();
                $dbg['status'] = 'daily-error';
                $dbg['error']  = $e->getMessage();
            }

            $dbg['daily'] = ($summary['daily'] ?? 0) - $dailyBefore;

            foreach ($campaigns as $c) {
                // Skip campaigns that don't promote a tracked App (matched by App ID).
                if ($this->resolveAppId($c['id']) === null) {
                    continue;
                }

                CampaignStat::updateOrCreate(
                    ['connection_id' => $conn->id, 'customer_id' => $customerId, 'campaign_id' => $c['id']],
                    [
                        'account_name'      => $meta['name'],
                        'campaign_name'     => $c['name'],
                        'status'            => $c['status'],
                        'channel_type'      => $c['channel'],
                        'period_start'      => $start,
                        'period_end'        => $end,
                        'cost'              => $c['cost'],
                        'conversions'       => $c['conversions'],
                        'conversions_value' => $c['conversions_value'],
                        'impressions'       => $c['impressions'],
                        'clicks'            => $c['clicks'],
                        'synced_at'         => now(),
                    ]
                );
                $summary['campaigns']++;
                $dbg['campaigns']++;
            }

            $summary['debug'][] = $dbg;
        }
    }

    /**
     * Pull daily, per-country, conversion-action-bucketed stats for one account.
     * Two GAQL queries against geographic_view (cost can't be segmented by
     * conversion action, so buckets come from a second pass), combined by
     * (campaign, date, country).
     *
     * @return array<string,array> keyed by "campaignId|date|geoId"
     */
    private function fetchDailyGeoStats(string $token, string $devToken, string $loginId, string $customerId, string $start, string $end, ?string $campaignId = null): array
    {
        $rows = [];

        // Optional single-campaign filter (targeted per-app sync).
        $campaignFilter = $campaignId ? " AND campaign.id = {$campaignId}" : '';

        // 1. Base spend/engagement per campaign × date × country.
        $baseQuery = "SELECT campaign.id, campaign.name,
                             segments.date, geographic_view.country_criterion_id,
                             metrics.cost_micros, metrics.impressions, metrics.clicks,
                             metrics.conversions, metrics.conversions_value
                      FROM geographic_view
                      WHERE segments.date BETWEEN '{$start}' AND '{$end}'
                        AND geographic_view.location_type = 'LOCATION_OF_PRESENCE'{$campaignFilter}";

        foreach ($this->searchStream($token, $devToken, $loginId, $customerId, $baseQuery) as $r) {
            $key = $this->dailyKey($r);
            if ($key === null) continue;
            $rows[$key] ??= $this->emptyDailyRow($r);
            $rows[$key]['cost']              += ((float) data_get($r, 'metrics.costMicros', 0)) / 1_000_000;
            $rows[$key]['impressions']       += (int) data_get($r, 'metrics.impressions', 0);
            $rows[$key]['clicks']            += (int) data_get($r, 'metrics.clicks', 0);
            $rows[$key]['conversions']       += (float) data_get($r, 'metrics.conversions', 0);
            $rows[$key]['conversions_value'] += (float) data_get($r, 'metrics.conversionsValue', 0);
        }

        // 2. Conversion-action buckets per campaign × date × country.
        $mapper = new ConversionActionMapper();
        $actionQuery = "SELECT campaign.id, campaign.name,
                               segments.date, geographic_view.country_criterion_id,
                               segments.conversion_action_name,
                               metrics.conversions, metrics.conversions_value
                        FROM geographic_view
                        WHERE segments.date BETWEEN '{$start}' AND '{$end}'
                          AND geographic_view.location_type = 'LOCATION_OF_PRESENCE'{$campaignFilter}";

        foreach ($this->searchStream($token, $devToken, $loginId, $customerId, $actionQuery) as $r) {
            $key = $this->dailyKey($r);
            if ($key === null) continue;
            $rows[$key] ??= $this->emptyDailyRow($r);

            $action = (string) data_get($r, 'segments.conversionActionName', '');
            $count  = (float) data_get($r, 'metrics.conversions', 0);
            $value  = (float) data_get($r, 'metrics.conversionsValue', 0);

            foreach ($mapper->apply($action, $count, $value) as $bucket => $amount) {
                $rows[$key][$bucket] = ($rows[$key][$bucket] ?? 0) + $amount;
            }
        }

        return $rows;
    }

    private function dailyKey(array $r): ?string
    {
        $campaign = (string) data_get($r, 'campaign.id');
        $date     = (string) data_get($r, 'segments.date');
        if ($campaign === '' || $date === '') return null;
        $geo = preg_replace('/\D/', '', (string) data_get($r, 'geographicView.countryCriterionId'));
        return "{$campaign}|{$date}|{$geo}";
    }

    private function emptyDailyRow(array $r): array
    {
        return [
            'campaign_id'       => (string) data_get($r, 'campaign.id'),
            'campaign_name'     => data_get($r, 'campaign.name'),
            'date'              => (string) data_get($r, 'segments.date'),
            // 0 = unknown country. Kept as a concrete value (not null) so the
            // (connection,customer,campaign,geo,date) unique key dedupes on re-sync.
            'geo_id'            => (int) preg_replace('/\D/', '', (string) data_get($r, 'geographicView.countryCriterionId')),
            'cost'              => 0, 'impressions' => 0, 'clicks' => 0,
            'conversions'       => 0, 'conversions_value' => 0,
            'install'           => 0, 'trial' => 0, 'trial_convert' => 0, 'repeat_count' => 0,
            'ad_rev'            => 0, 'convert_rev' => 0, 'renew_rev' => 0,
        ];
    }

    /**
     * Upsert daily rows + append an immutable history snapshot per row.
     * Batched: one bulk upsert + one bulk insert per chunk instead of two DB
     * round-trips per row, so a full-month × many-country sync doesn't time out.
     */
    private function persistDailyStats(Connection $conn, string $customerId, ?string $accountName, array $rows, array &$summary): void
    {
        if (empty($rows)) {
            return;
        }

        $now = now();
        $daily   = [];
        $history = [];

        foreach ($rows as $row) {
            $appId = $this->resolveAppId($row['campaign_id']);

            // Only store data for campaigns that belong to a tracked App (matched
            // by App ID). Everything else from the account is ignored.
            if ($appId === null) {
                continue;
            }

            $totalRev = $row['ad_rev'] + $row['convert_rev'] + $row['renew_rev'];

            $daily[] = [
                'connection_id'     => $conn->id,
                'app_id'            => $appId,
                'customer_id'       => $customerId,
                'account_name'      => $accountName,
                'campaign_id'       => $row['campaign_id'],
                'campaign_name'     => $row['campaign_name'],
                'geo_id'            => $row['geo_id'],
                'date'              => $row['date'],
                'cost'              => $row['cost'],
                'impressions'       => $row['impressions'],
                'clicks'            => $row['clicks'],
                'conversions'       => $row['conversions'],
                'conversions_value' => $row['conversions_value'],
                'install'           => $row['install'],
                'trial'             => $row['trial'],
                'trial_convert'     => $row['trial_convert'],
                'repeat_count'      => $row['repeat_count'],
                'ad_rev'            => $row['ad_rev'],
                'convert_rev'       => $row['convert_rev'],
                'renew_rev'         => $row['renew_rev'],
                'synced_at'         => $now,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];

            $history[] = [
                'connection_id' => $conn->id,
                'customer_id'   => $customerId,
                'campaign_id'   => $row['campaign_id'],
                'geo_id'        => $row['geo_id'],
                'date'          => $row['date'],
                'cost'          => $row['cost'],
                'install'       => $row['install'],
                'trial'         => $row['trial'],
                'trial_convert' => $row['trial_convert'],
                'repeat_count'  => $row['repeat_count'],
                'ad_rev'        => $row['ad_rev'],
                'convert_rev'   => $row['convert_rev'],
                'renew_rev'     => $row['renew_rev'],
                'total_rev'     => $totalRev,
                'captured_at'   => $now,
            ];
        }

        // Columns refreshed when a (connection,customer,campaign,geo,date) row
        // already exists — everything except the unique key + created_at.
        $updateCols = [
            'app_id', 'account_name', 'campaign_name', 'cost', 'impressions', 'clicks',
            'conversions', 'conversions_value', 'install', 'trial', 'trial_convert',
            'repeat_count', 'ad_rev', 'convert_rev', 'renew_rev', 'synced_at', 'updated_at',
        ];

        foreach (array_chunk($daily, 300) as $chunk) {
            DailyStat::upsert($chunk, ['connection_id', 'customer_id', 'campaign_id', 'geo_id', 'date'], $updateCols);
        }
        foreach (array_chunk($history, 500) as $chunk) {
            DB::table('daily_stat_history')->insert($chunk);
        }

        $summary['daily'] += count($daily);
    }

    /**
     * Map a campaign to a tracked App by store App ID: the campaign's target app
     * (campaign.app_campaign_setting.app_id) matched to apps.package_id. Returns
     * null for campaigns with no app target or no matching tracked App.
     */
    private function resolveAppId(string $campaignId): ?int
    {
        $storeAppId = $this->campaignAppMap[$campaignId] ?? null;
        if ($storeAppId === null) {
            return null;
        }

        if ($this->appByPackage === null) {
            $this->appByPackage = [];
            foreach (App::whereNotNull('package_id')->get() as $app) {
                $this->appByPackage[$this->normPackage($app->package_id)] = $app->id;
            }
        }

        return $this->appByPackage[$this->normPackage($storeAppId)] ?? null;
    }

    /** Normalise a store App ID / package for case-insensitive matching. */
    private function normPackage(string $value): string
    {
        return strtolower(trim($value));
    }

    /** Refresh the geo-target-constant → country map from the API. */
    private function syncCountries(string $token, string $devToken, string $loginId, string $customerId): void
    {
        $query = "SELECT geo_target_constant.id, geo_target_constant.name,
                         geo_target_constant.country_code
                  FROM geo_target_constant
                  WHERE geo_target_constant.target_type = 'Country'
                    AND geo_target_constant.status = 'ENABLED'";

        $rows = $this->searchStream($token, $devToken, $loginId, $customerId, $query);
        if (empty($rows)) return;

        $upserts = [];
        foreach ($rows as $r) {
            $geoId = (int) preg_replace('/\D/', '', (string) data_get($r, 'geoTargetConstant.id'));
            if ($geoId === 0) continue;
            $upserts[] = [
                'geo_id'     => $geoId,
                'code'       => data_get($r, 'geoTargetConstant.countryCode'),
                'name'       => data_get($r, 'geoTargetConstant.name'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if (!empty($upserts)) {
            foreach (array_chunk($upserts, 500) as $chunk) {
                Country::upsert($chunk, ['geo_id'], ['code', 'name', 'updated_at']);
            }
            Country::forget();
        }
    }

    private function accessToken(Connection $conn): string
    {
        $resp = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id'     => $conn->client_id,
            'client_secret' => $conn->client_secret,
            'refresh_token' => $conn->refresh_token,
            'grant_type'    => 'refresh_token',
        ]);

        if (!$resp->ok() || !$resp->json('access_token')) {
            $err = $resp->json('error_description') ?? $resp->json('error') ?? $resp->body();
            throw new \RuntimeException('sign-in failed (check Client ID / Secret / Refresh Token): ' . $err);
        }
        return $resp->json('access_token');
    }

    /**
     * List accessible customers — also resolves & caches the API version.
     * Probes candidate versions until one doesn't 404 (i.e. still exists).
     * @return string[] customer IDs (digits only)
     */
    private function listAccessibleCustomers(string $token, string $devToken): array
    {
        $tried = [];
        foreach ($this->candidateVersions() as $v) {
            $this->setVersion($v);
            $tried[] = $v;

            $resp = Http::withToken($token)
                ->withHeaders(['developer-token' => $devToken])
                ->get("{$this->base}/customers:listAccessibleCustomers");

            // 404 = this API version no longer exists; try the next one.
            if ($resp->status() === 404) {
                continue;
            }
            // Any other non-OK is a real error (auth, permission, dev-token) — surface it.
            if (!$resp->ok()) {
                throw new \RuntimeException($this->apiError($resp));
            }

            setSetting('google_ads_api_version', $v); // remember the working version
            return array_map(
                fn ($rn) => preg_replace('/\D/', '', $rn),
                (array) $resp->json('resourceNames', [])
            );
        }

        throw new \RuntimeException(
            'No supported Google Ads API version responded (tried ' . implode(', ', $tried) .
            '). Set GOOGLE_ADS_API_VERSION in .env to the current version.'
        );
    }

    /**
     * Expand a (possibly manager) account into its non-manager client accounts.
     * Manager account ids found (including the account itself when it is a
     * manager) are collected into $managerIds — metrics can't be queried on a
     * manager, so callers must exclude them from the query targets.
     */
    private function expandLeafAccounts(string $token, string $devToken, string $managerId, array &$managerIds = []): array
    {
        $query = "SELECT customer_client.id, customer_client.descriptive_name, customer_client.manager
                  FROM customer_client
                  WHERE customer_client.status = 'ENABLED'";

        $rows = $this->searchStream($token, $devToken, $managerId, $managerId, $query);

        $leaves        = [];
        $managesOthers = false;
        foreach ($rows as $r) {
            $id = preg_replace('/\D/', '', (string) data_get($r, 'customerClient.id'));
            if ($id === '') {
                continue;
            }
            // The self-row (the queried account) is ignored — whether it is a
            // manager is decided below by whether it lists any other account.
            if ($id === $managerId) {
                continue;
            }
            $managesOthers = true; // it lists another account → it is a manager
            if (data_get($r, 'customerClient.manager') === true) {
                $managerIds[$id] = true; // sub-manager — no queryable metrics
                continue;
            }
            $leaves[] = ['id' => $id, 'name' => data_get($r, 'customerClient.descriptiveName')];
        }

        // An account that manages other accounts is a manager itself, so its own
        // metrics can't be queried (REQUESTED_METRICS_FOR_MANAGER). Flag it so the
        // caller drops it from the query targets.
        if ($managesOthers) {
            $managerIds[$managerId] = true;
        }

        return $leaves;
    }

    private function fetchCampaigns(string $token, string $devToken, string $loginId, string $customerId, string $start, string $end, ?string $campaignId = null): array
    {
        $campaignFilter = $campaignId ? " AND campaign.id = {$campaignId}" : '';

        $query = "SELECT campaign.id, campaign.name, campaign.status,
                         campaign.advertising_channel_type,
                         campaign.app_campaign_setting.app_id,
                         metrics.cost_micros, metrics.conversions, metrics.conversions_value,
                         metrics.impressions, metrics.clicks
                  FROM campaign
                  WHERE segments.date BETWEEN '{$start}' AND '{$end}'{$campaignFilter}";

        $rows = $this->searchStream($token, $devToken, $loginId, $customerId, $query);

        // Sum across the date rows into one total per campaign.
        $out = [];
        foreach ($rows as $r) {
            $id = (string) data_get($r, 'campaign.id');
            if ($id === '') continue;
            $out[$id] ??= [
                'id' => $id, 'name' => data_get($r, 'campaign.name'),
                'status' => data_get($r, 'campaign.status'),
                'channel' => data_get($r, 'campaign.advertisingChannelType'),
                // Store App ID this campaign promotes (App campaigns only); used to
                // match the campaign's stats to a tracked App by package.
                'app_id' => data_get($r, 'campaign.appCampaignSetting.appId'),
                'cost' => 0, 'conversions' => 0, 'conversions_value' => 0, 'impressions' => 0, 'clicks' => 0,
            ];
            $out[$id]['cost']              += ((float) data_get($r, 'metrics.costMicros', 0)) / 1_000_000;
            $out[$id]['conversions']       += (float) data_get($r, 'metrics.conversions', 0);
            $out[$id]['conversions_value'] += (float) data_get($r, 'metrics.conversionsValue', 0);
            $out[$id]['impressions']       += (int) data_get($r, 'metrics.impressions', 0);
            $out[$id]['clicks']            += (int) data_get($r, 'metrics.clicks', 0);
        }
        return array_values($out);
    }

    /** POST a GAQL query to searchStream; returns a flat array of result rows. */
    private function searchStream(string $token, string $devToken, string $loginId, string $customerId, string $query): array
    {
        $headers = ['developer-token' => $devToken];
        $login = preg_replace('/\D/', '', $loginId);
        if ($login !== '') {
            $headers['login-customer-id'] = $login;
        }

        $resp = Http::withToken($token)->withHeaders($headers)
            ->post("{$this->base}/customers/{$customerId}/googleAds:searchStream", ['query' => $query]);

        if (!$resp->ok()) {
            throw new \RuntimeException($this->apiError($resp));
        }

        $results = [];
        foreach ((array) $resp->json() as $batch) {
            foreach (($batch['results'] ?? []) as $row) {
                $results[] = $row;
            }
        }
        return $results;
    }

    private function apiError($resp): string
    {
        $json  = $resp->json();
        $error = data_get($json, '0.error') ?? data_get($json, 'error');

        if ($error) {
            // Dig into the GoogleAdsFailure for the specific error code + Google's
            // own guidance message (far more useful than the generic top-level one).
            foreach ((array) data_get($error, 'details', []) as $d) {
                foreach ((array) data_get($d, 'errors', []) as $e) {
                    $code = '';
                    foreach ((array) data_get($e, 'errorCode', []) as $k => $v) {
                        $code = is_scalar($v) ? (string) $v : $k;
                        break;
                    }
                    $msg = data_get($e, 'message');
                    if ($msg) {
                        return trim(($code ? "[{$code}] " : '') . $msg);
                    }
                }
            }
            $status = data_get($error, 'status');
            return trim(($status ? "{$status}: " : '') . data_get($error, 'message', 'Unknown API error'));
        }

        return 'HTTP ' . $resp->status() . ' ' . $resp->body();
    }
}
