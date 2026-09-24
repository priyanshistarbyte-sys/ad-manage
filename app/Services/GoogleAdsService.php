<?php

namespace App\Services;

use App\Models\App;
use App\Models\CampaignStat;
use App\Models\Connection;
use App\Models\Country;
use App\Models\DailyStat;
use Illuminate\Http\Client\Pool;
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
            $conn->recordSignin(true);
            return [
                'ok'       => true,
                'accounts' => count($accounts),
                'message'  => 'Signed in OK · ' . count($accounts) . ' accessible account(s).',
            ];
        } catch (\Throwable $e) {
            $conn->recordSignin(false, $e->getMessage());
            return ['ok' => false, 'accounts' => 0, 'message' => $e->getMessage()];
        }
    }

    public function syncAll(string $start, string $end): array
    {
        $summary = ['campaigns' => 0, 'accounts' => 0, 'daily' => 0, 'errors' => [],
                    'probe_campaigns' => 0, 'skipped_untracked' => 0, 'tracked_accounts' => 0,
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

    /**
     * Sync a SINGLE ad account (connection) for [$start, $end] — same per-account
     * flow as Sync All, just scoped to one connection. Returns the same summary
     * shape as syncAll().
     */
    public function syncOne(Connection $conn, string $start, string $end): array
    {
        $summary = ['campaigns' => 0, 'accounts' => 0, 'daily' => 0, 'errors' => [],
                    'debug' => [], 'debug_discovery' => []];

        @set_time_limit(600);
        $this->countriesSynced = false;
        $this->appByPackage = null;

        if (!$conn->isConfigured()) {
            $summary['errors'][] = 'This ad account has incomplete credentials.';
            return $summary;
        }

        try {
            $this->syncConnection($conn, $start, $end, $summary);
        } catch (\Throwable $e) {
            $summary['errors'][] = "{$conn->name}: " . $e->getMessage();
        }

        return $summary;
    }

    private function syncConnection(Connection $conn, string $start, string $end, array &$summary): void
    {
        // Sign in first and record the outcome so the Ad Accounts page can flag an
        // expired/revoked token with a "Reconnect" badge (see Connection::recordSignin).
        try {
            $token = $this->accessToken($conn);
        } catch (\Throwable $e) {
            $conn->recordSignin(false, $e->getMessage());
            throw $e;
        }
        $conn->recordSignin(true);

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

        // Probe every candidate as a manager CONCURRENTLY: list the client accounts
        // each one manages. A candidate that isn't a usable login-customer-id just
        // errors and is skipped. (Replaces the old one-at-a-time expandLeafAccounts
        // loop — the main source of slowness on many-account logins.)
        $clientQuery = "SELECT customer_client.id, customer_client.descriptive_name, customer_client.manager
                        FROM customer_client
                        WHERE customer_client.status = 'ENABLED'";
        $discoveryReqs = [];
        foreach ($candidates as $m) {
            $discoveryReqs[$m] = ['login' => $m, 'customer' => $m, 'query' => $clientQuery];
        }

        foreach ($this->searchStreamPool($token, $devToken, $discoveryReqs) as $m => $rows) {
            if (!is_array($rows) || isset($rows['__error'])) {
                continue; // not usable as a login-customer-id (client account / no access)
            }

            $managesOthers = false;
            $leaves        = [];
            foreach ($rows as $r) {
                $id = preg_replace('/\D/', '', (string) data_get($r, 'customerClient.id'));
                if ($id === '' || $id === (string) $m) {
                    continue; // ignore the self-row
                }
                $managesOthers = true; // it lists another account → it is a manager
                if (data_get($r, 'customerClient.manager') === true) {
                    $managerIds[$id] = true; // sub-manager — no queryable metrics
                    continue;
                }
                $leaves[$id] = data_get($r, 'customerClient.descriptiveName');
            }

            // Only a real manager (one that lists other accounts) contributes
            // targets and gets cached / flagged as a manager id.
            if ($managesOthers) {
                $managerIds[$m]  = true;
                $foundManagers[] = $m;
                foreach ($leaves as $id => $name) {
                    $targets[$id] = ['login' => (string) $m, 'name' => $name];
                }
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

        // 3a. Cheap probe of EVERY target account CONCURRENTLY: attributes-only
        //     campaign→package map (no metrics, no date). This is how we detect
        //     which accounts hold a tracked App without paying for heavy queries,
        //     and running them in parallel is the main speedup for many-account
        //     logins (previously one sequential probe per account).
        $probeQuery = "SELECT campaign.id, campaign.app_campaign_setting.app_id
                       FROM campaign";
        $probeReqs = [];
        foreach ($targets as $customerId => $meta) {
            $probeReqs[$customerId] = ['login' => $meta['login'], 'customer' => $customerId, 'query' => $probeQuery];
        }
        $probeResults = $this->searchStreamPool($token, $devToken, $probeReqs);

        // 3b. Work out which accounts are worth fetching (have a tracked App).
        $trackedByAccount = [];   // customerId => [tracked campaign ids]
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

            $rows = $probeResults[$customerId] ?? ['__error' => 'no response'];
            if (!is_array($rows) || isset($rows['__error'])) {
                $dbg['status'] = 'skipped';
                $dbg['error']  = is_array($rows) ? ($rows['__error'] ?? 'probe failed') : 'probe failed';
                $summary['errors'][] = "acct {$customerId}: " . $dbg['error'];
                $summary['debug'][] = $dbg;
                continue;
            }

            $appMap    = [];   // campaign_id => store App ID (app campaigns only)
            $campaigns = 0;    // total campaigns seen in this account (any type)
            foreach ($rows as $r) {
                $id    = (string) data_get($r, 'campaign.id');
                if ($id === '') continue;
                $campaigns++;
                $appId = data_get($r, 'campaign.appCampaignSetting.appId');
                if (!empty($appId)) {
                    $appMap[$id] = $appId;
                }
            }
            $summary['probe_campaigns'] += $campaigns;

            $tracked = array_values(array_filter(
                array_keys($appMap),
                fn ($cid) => $this->resolveAppId($cid, $appMap) !== null
            ));

            // No tracked apps here → skip entirely (no campaign_stats, no geo).
            if (empty($tracked)) {
                $dbg['status'] = 'no-tracked-apps';
                // Distinguish "account has app campaigns but none match a tracked
                // App" from "no app campaigns at all" — the former means the App
                // ID on the Apps page doesn't match the campaign's target package.
                if (!empty($appMap)) {
                    $summary['skipped_untracked']++;
                }
                $summary['debug'][] = $dbg;
                continue;
            }

            $summary['tracked_accounts']++;
            $trackedByAccount[$customerId] = ['tracked' => $tracked, 'appMap' => $appMap, 'meta' => $meta, 'dbg' => $dbg];
        }

        // 3c. Pull metrics + daily/geo for the tracked accounts only — one failing
        //     account (permissions, etc.) must NOT abort the rest of the sync.
        foreach ($trackedByAccount as $customerId => $info) {
            $meta               = $info['meta'];
            $dbg                = $info['dbg'];
            $trackedCampaignIds = $info['tracked'];
            $this->campaignAppMap = $info['appMap'];
            $dailyBefore        = $summary['daily'] ?? 0;

            // Give each account its own time budget so a large (but steadily
            // progressing) sync can't trip the max-execution-time fatal mid-way.
            @set_time_limit(120);

            // Refresh the country map once from the first tracked account.
            if (!$this->countriesSynced) {
                try {
                    $this->syncCountries($token, $devToken, $meta['login'], $customerId);
                    $this->countriesSynced = true;
                } catch (\Throwable $e) {
                    // non-fatal — the seeded curated list still resolves names
                }
            }

            // Metric totals for the tracked campaigns only → campaign_stats.
            try {
                $campaigns = $this->fetchCampaigns($token, $devToken, $meta['login'], $customerId, $start, $end, $trackedCampaignIds);
            } catch (\Throwable $e) {
                $summary['errors'][] = "acct {$customerId}: " . $e->getMessage();
                $dbg['status'] = 'skipped';
                $dbg['error']  = $e->getMessage();
                $summary['debug'][] = $dbg;
                continue;
            }

            // Daily / per-country / bucketed rows for the Excel-style report,
            // restricted to the tracked campaigns.
            try {
                $daily = $this->fetchDailyGeoStats($token, $devToken, $meta['login'], $customerId, $start, $end, $trackedCampaignIds);
                $this->persistDailyStats($conn, $customerId, $meta['name'], $daily, $summary);
            } catch (\Throwable $e) {
                $summary['errors'][] = "acct {$customerId} (daily): " . $e->getMessage();
                $dbg['status'] = 'daily-error';
                $dbg['error']  = $e->getMessage();
            }

            $dbg['daily'] = ($summary['daily'] ?? 0) - $dailyBefore;

            foreach ($campaigns as $c) {
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
    private function fetchDailyGeoStats(string $token, string $devToken, string $loginId, string $customerId, string $start, string $end, array $campaignIds = []): array
    {
        $rows = [];

        // Restrict to the given campaigns (tracked apps only). With no ids we'd
        // fetch the whole account, so an empty list means "nothing to pull".
        if (empty($campaignIds)) {
            return $rows;
        }
        $idList = implode(', ', array_map(fn ($id) => (int) $id, $campaignIds));
        $campaignFilter = " AND campaign.id IN ({$idList})";

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
            // Store everything the account returns; app_id is set when the campaign's
            // App ID matches a tracked App, and left null otherwise. The report only
            // *displays* tracked apps (app_id not null), so untracked data is kept but
            // hidden until a matching App is added.
            $appId    = $this->resolveAppId($row['campaign_id']);
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
    private function resolveAppId(string $campaignId, ?array $map = null): ?int
    {
        $map ??= $this->campaignAppMap;
        $storeAppId = $map[$campaignId] ?? null;
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

    private function fetchCampaigns(string $token, string $devToken, string $loginId, string $customerId, string $start, string $end, array $campaignIds = []): array
    {
        // Restrict the metric pull to the given campaigns (tracked apps only).
        $campaignFilter = '';
        if (!empty($campaignIds)) {
            $idList = implode(', ', array_map(fn ($id) => (int) $id, $campaignIds));
            $campaignFilter = " AND campaign.id IN ({$idList})";
        }

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

    /**
     * Run many GAQL searchStream queries CONCURRENTLY (Laravel Http::pool).
     * The big win for multi-account logins: dozens of sequential round-trips
     * become a few parallel batches.
     *
     * Requests that fail with a transient error (rate limit / 5xx / connection
     * drop) are retried with exponential backoff — running in parallel makes
     * RESOURCE_EXHAUSTED more likely, so the retry keeps a rate-limited account
     * from being lost to a manual re-sync. Non-retryable errors (permissions,
     * bad query) fail immediately.
     *
     * @param array<string,array{login:string,customer:string,query:string}> $requests keyed by caller id
     * @param int $concurrency  max requests in flight per batch (Google rate-limit friendly)
     * @param int $maxAttempts  total tries per request (1 initial + retries)
     * @return array<string,array> per key: the flat result rows, or ['__error' => message] on failure
     */
    private function searchStreamPool(string $token, string $devToken, array $requests, int $concurrency = 12, int $maxAttempts = 3): array
    {
        $out     = [];
        $pending = $requests;   // key => req; shrinks as requests succeed / give up

        for ($attempt = 1; $attempt <= $maxAttempts && !empty($pending); $attempt++) {
            $retry    = [];
            $lastTry  = $attempt >= $maxAttempts;

            foreach (array_chunk($pending, $concurrency, true) as $chunk) {
                $keys = array_keys($chunk);

                $responses = Http::pool(function (Pool $pool) use ($token, $devToken, $chunk) {
                    $calls = [];
                    foreach ($chunk as $key => $req) {
                        $headers = ['developer-token' => $devToken];
                        $login   = preg_replace('/\D/', '', (string) $req['login']);
                        if ($login !== '') {
                            $headers['login-customer-id'] = $login;
                        }
                        $calls[] = $pool->as((string) $key)
                            ->withToken($token)
                            ->withHeaders($headers)
                            ->post("{$this->base}/customers/{$req['customer']}/googleAds:searchStream", ['query' => $req['query']]);
                    }
                    return $calls;
                });

                foreach ($keys as $key) {
                    $resp = $responses[(string) $key] ?? null;

                    // Transport-level failure (timeout / connection reset) — transient.
                    if ($resp instanceof \Throwable) {
                        if ($lastTry) {
                            $out[$key] = ['__error' => $resp->getMessage()];
                        } else {
                            $retry[$key] = $pending[$key];
                        }
                        continue;
                    }

                    if ($resp && $resp->ok()) {
                        $rows = [];
                        foreach ((array) $resp->json() as $batch) {
                            foreach (($batch['results'] ?? []) as $row) {
                                $rows[] = $row;
                            }
                        }
                        $out[$key] = $rows;
                        continue;
                    }

                    // HTTP error: retry transient ones (rate limit / 5xx), fail the rest.
                    if ($resp && !$lastTry && $this->isRetryable($resp)) {
                        $retry[$key] = $pending[$key];
                        continue;
                    }
                    $out[$key] = ['__error' => $resp ? $this->apiError($resp) : 'no response'];
                }
            }

            $pending = $retry;

            // Exponential backoff with jitter before retrying the failed subset.
            if (!empty($pending) && !$lastTry) {
                $delayMs = 250 * (2 ** ($attempt - 1));           // 250ms, 500ms, 1000ms, …
                usleep(($delayMs + random_int(0, 150)) * 1000);
            }
        }

        return $out;
    }

    /** Whether a failed HTTP response is worth retrying (rate limit / transient). */
    private function isRetryable($resp): bool
    {
        if (in_array($resp->status(), [429, 500, 502, 503, 504], true)) {
            return true;
        }

        $body = strtoupper((string) $resp->body());
        foreach (['RESOURCE_EXHAUSTED', 'RATE_EXCEEDED', 'RATE_LIMIT', 'DEADLINE_EXCEEDED', 'INTERNAL_ERROR'] as $needle) {
            if (str_contains($body, $needle)) {
                return true;
            }
        }
        return false;
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
