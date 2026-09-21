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

    /** Sync just one selected app (its Ad Account + customer/campaign). */
    public function runApp(Request $request, GoogleAdsService $ads)
    {
        $data = $request->validate(['app_id' => ['required', 'exists:apps,id']]);
        $app  = App::findOrFail($data['app_id']);

        $start = Carbon::now()->startOfMonth()->toDateString();
        $end   = Carbon::now()->toDateString();

        $r = $ads->syncApp($app, $start, $end);

        $back = redirect('/sync-all');

        if (!empty($r['campaigns']) || !empty($r['daily'])) {
            $back->with('flash', sprintf(
                'Synced “%s”: %d campaign(s) + %d daily row(s) for %s → %s.',
                $app->name, $r['campaigns'] ?? 0, $r['daily'] ?? 0, $start, $end
            ));
        } elseif (empty($r['errors'])) {
            $back->with('flash', "Sync finished for “{$app->name}” — no campaigns found this month.");
        }

        if (!empty($r['errors'])) {
            $back->with('flash_error', "“{$app->name}” — " . implode(' | ', array_unique($r['errors'])));
        }

        return $back;
    }

    public function run(GoogleAdsService $ads)
    {
        $start = Carbon::now()->startOfMonth()->toDateString();
        $end   = Carbon::now()->toDateString();

        $r = $ads->syncAll($start, $end);

        $back = redirect('/sync-all');

        if (!empty($r['campaigns']) || !empty($r['daily'])) {
            $back->with('flash', sprintf(
                'Synced %d campaign(s) + %d daily row(s) across %d account(s) for %s → %s.',
                $r['campaigns'] ?? 0, $r['daily'] ?? 0, $r['accounts'], $start, $end
            ));
        }
        if (!empty($r['errors'])) {
            $errs  = array_values(array_unique($r['errors']));
            $shown = array_slice($errs, 0, 4);
            $more  = count($errs) - count($shown);
            $msg   = implode(' | ', $shown) . ($more > 0 ? " | (+{$more} more account(s) skipped)" : '');
            $back->with('flash_error', $msg);
        }
        if (empty($r['campaigns']) && empty($r['daily']) && empty($r['errors'])) {
            $back->with('flash', 'Sync finished — no campaigns found for this month.');
        }

        // Full per-account debug trail for the panel (kept in the session flash
        // so it survives the redirect back to the Sync All page).
        $back->with('sync_debug', [
            'range'     => "{$start} → {$end}",
            'campaigns' => $r['campaigns'] ?? 0,
            'daily'     => $r['daily'] ?? 0,
            'accounts'  => $r['accounts'] ?? 0,
            'discovery' => $r['debug_discovery'] ?? [],
            'accounts_detail' => $r['debug'] ?? [],
            'errors'    => array_values(array_unique($r['errors'] ?? [])),
        ]);

        return $back;
    }
}
