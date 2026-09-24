<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\CampaignStat;
use App\Models\Connection;
use App\Services\GoogleAdsService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SyncAllController extends Controller
{
    public function index()
    {
        $stats = CampaignStat::with('connection')
            ->orderByDesc('cost')->get()
            ->groupBy(fn ($s) => optional($s->connection)->name . ' · ' . ($s->account_name ?: $s->customer_id));

        return view('sync-all', [
            'activePage'   => 'sync-all',
            'pageTitle'    => 'Sync All',
            'grouped'      => $stats,
            'connections'  => Connection::where('active', true)->get(),
            'apps'         => App::with('connection')->orderBy('name')->get(),
            'lastSynced'   => CampaignStat::max('synced_at'),
            'periodStart'  => Carbon::now()->startOfMonth()->toDateString(),
            'periodEnd'    => Carbon::now()->toDateString(),
        ]);
    }

    public function run(Request $request, GoogleAdsService $ads)
    {
        [$start, $end] = $this->syncDateRange($request);

        $r = $ads->syncAll($start, $end);

        // Return to whichever page triggered the sync (Sync All or Ad Accounts).
        $back = redirect()->back(302, [], url('/sync-all'));

        if (!empty($r['campaigns']) || !empty($r['daily'])) {
            return $back->with('flash', sprintf(
                'Synced %d campaign(s) + %d daily row(s) for %s → %s.',
                $r['campaigns'] ?? 0, $r['daily'] ?? 0, $start, $end
            ));
        }

        // Nothing synced — explain WHY instead of a bare "no campaigns found".
        $accounts  = (int) ($r['accounts'] ?? 0);
        $probe     = (int) ($r['probe_campaigns'] ?? 0);
        $untracked = (int) ($r['skipped_untracked'] ?? 0);
        $errors    = array_slice(array_values(array_unique($r['errors'] ?? [])), 0, 3);

        if ($accounts === 0 && !empty($errors)) {
            // No account was reachable at all — surface the API error.
            return $back->with('flash_error', 'Sync failed: ' . implode(' · ', $errors));
        }

        if ($probe > 0) {
            // Campaigns exist in the accounts, but none resolved to an App on the
            // Apps page — either the App ID doesn't match the campaign's target
            // package, or the campaigns aren't App campaigns.
            $hint = $untracked > 0
                ? 'Some are App campaigns whose App ID does not match any app on your Apps page.'
                : 'None expose a matchable App ID (they may not be App campaigns).';
            $msg = sprintf(
                'Scanned %d account(s) and found %d campaign(s), but none match an app on your Apps page. %s '
                . 'Add each app with the exact App ID (target package, e.g. com.example.app) its campaigns promote, then sync again.',
                $accounts, $probe, $hint
            );
            if (!empty($errors)) {
                $msg .= ' Notes: ' . implode(' · ', $errors);
            }
            return $back->with('flash_error', $msg);
        }

        $msg = "Sync finished — no campaigns found for {$start} → {$end}.";
        if (!empty($errors)) {
            $msg .= ' Notes: ' . implode(' · ', $errors);
        }
        return $back->with('flash', $msg);
    }
}
