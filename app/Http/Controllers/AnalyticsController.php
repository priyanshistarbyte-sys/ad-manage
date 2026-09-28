<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsNote;
use App\Models\DailyStat;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Analytics — per-app blocks listing the loss-making countries (cost > threshold
 * and TROAS < threshold) with Cost / TROAS / Loss for the selected date, the last
 * 30 days and the last 90 days.
 */
class AnalyticsController extends Controller
{
    /** Timezone the Notes popup shows times (and groups days) in. */
    private const NOTES_TZ = 'Asia/Kolkata';

    public function __construct(private ReportService $reports)
    {
    }

    public function index(Request $request)
    {
        // Date range (from–to). Default = the latest day we have data for, else yesterday.
        // An older ?date= link still works as a single-day range.
        $latest  = DailyStat::whereNotNull('app_id')->max('date');
        $default = $latest ?: Carbon::yesterday()->toDateString();
        $parse   = function ($v) use ($default) {
            try {
                return $v ? Carbon::parse($v)->toDateString() : $default;
            } catch (\Throwable) {
                return $default;
            }
        };
        $to   = $parse($request->query('to') ?: $request->query('date'));
        $from = $parse($request->query('from') ?: $request->query('date') ?: $to);
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $minCost  = is_numeric($request->query('min_cost'))  ? (float) $request->query('min_cost')  : 100.0;
        $maxTroas = is_numeric($request->query('max_troas')) ? (float) $request->query('max_troas') : 100.0;
        // Which window(s) must breach the thresholds — default: all three (date, 30d, 90d).
        $basis    = in_array($request->query('basis'), ['all', 'any', 'date', '30', '90'], true) ? $request->query('basis') : 'all';

        return view('analytics.index', [
            'activePage' => 'analytics',
            'pageTitle'  => 'Analytics',
            'from'       => $from,
            'to'         => $to,
            'latest'     => $latest,
            'minCost'    => $minCost,
            'maxTroas'   => $maxTroas,
            'basis'      => $basis,
            'data'       => $this->reports->lossAnalytics($from, $to, $minCost, $maxTroas, $basis),
            'status'     => AnalyticsNote::latestByPair(),
        ]);
    }

    /** Notes popup: status + date-wise note log for one app + country (JSON). */
    public function notes(Request $request)
    {
        $data = $request->validate([
            'app_id' => ['required', 'integer', 'exists:apps,id'],
            'geo_id' => ['nullable', 'integer'],
        ]);

        return response()->json($this->notesPayload((int) $data['app_id'], $data['geo_id'] ?? null));
    }

    /** Save a note (and the Solved / Read state) for one app + country. */
    public function storeNote(Request $request)
    {
        $data = $request->validate([
            'app_id' => ['required', 'integer', 'exists:apps,id'],
            'geo_id' => ['nullable', 'integer'],
            'note'   => ['nullable', 'string', 'max:20000'],
            'solved' => ['required', 'boolean'],
            'read'   => ['required', 'boolean'],
        ]);
        $appId = (int) $data['app_id'];
        $geoId = $data['geo_id'] ?? null;
        $note  = sanitizeHtml($data['note'] ?? '');   // rich-text HTML from the editor

        // Only a status change may be saved without text.
        $prev = $this->pairQuery($appId, $geoId)->latest('id')->first();
        $statusChanged = !$prev
            ? ($data['solved'] || $data['read'])
            : ($prev->solved !== (bool) $data['solved'] || $prev->read !== (bool) $data['read']);
        if ($note === '' && !$statusChanged) {
            return response()->json(['message' => 'Write a note or change Solved / Read.'], 422);
        }

        AnalyticsNote::create([
            'app_id'    => $appId,
            'geo_id'    => $geoId,
            'note'      => $note !== '' ? $note : null,
            'solved'    => (bool) $data['solved'],
            'read'      => (bool) $data['read'],
            'user_name' => currentUserName() ?: null,
        ]);

        return response()->json($this->notesPayload($appId, $geoId));
    }

    private function pairQuery(int $appId, ?int $geoId)
    {
        return AnalyticsNote::query()
            ->where('app_id', $appId)
            ->when($geoId === null, fn ($q) => $q->whereNull('geo_id'), fn ($q) => $q->where('geo_id', $geoId));
    }

    private function notesPayload(int $appId, ?int $geoId): array
    {
        $notes  = $this->pairQuery($appId, $geoId)->orderByDesc('id')->get();
        $latest = $notes->first();
        // Stored in the app timezone (UTC); shown — and grouped by day — in India time.
        $ist    = fn ($n) => $n->created_at->copy()->timezone(self::NOTES_TZ);

        return [
            'solved' => (bool) $latest?->solved,
            'read'   => (bool) $latest?->read,
            // Newest day first; entries within a day newest first.
            'days'   => $notes->groupBy(fn ($n) => $ist($n)->format('Y-m-d'))
                ->map(fn ($day, $d) => [
                    'date'  => Carbon::parse($d)->format('d-m-Y'),
                    'notes' => $day->map(fn ($n) => [
                        'time'   => $ist($n)->format('h:i A'),
                        'user'   => $n->user_name,
                        // Re-cleaned on the way out too; it is rendered as HTML in the popup.
                        'note'   => sanitizeHtml($n->note),
                        'solved' => $n->solved,
                        'read'   => $n->read,
                    ])->values(),
                ])->values(),
        ];
    }
}
