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

        // Only a clean success response — per-account errors (manager accounts,
        // disabled accounts, etc.) are expected noise and are not surfaced.
        if (!empty($r['campaigns']) || !empty($r['daily'])) {
            $back->with('flash', sprintf(
                'Synced %d campaign(s) + %d daily row(s) for %s → %s.',
                $r['campaigns'] ?? 0, $r['daily'] ?? 0, $start, $end
            ));
        } else {
            $back->with('flash', "Sync finished — no campaigns found for {$start} → {$end}.");
        }

        return $back;
    }
}
