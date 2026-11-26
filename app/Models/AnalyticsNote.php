<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A note on the Analytics page for one app + country. The latest note of a pair
 * carries its current Solved / Read status.
 */
class AnalyticsNote extends Model
{
    protected $table = 'analytics_notes';

    protected $guarded = [];

    protected $casts = [
        'solved' => 'boolean',
        'read'   => 'boolean',
    ];

    /** Current status per pair, keyed "app_id|geo_id" => latest note. */
    public static function latestByPair(): Collection
    {
        $ids = self::query()->selectRaw('MAX(id) as id')->groupBy('app_id', 'geo_id')->pluck('id');

        return self::query()->whereIn('id', $ids)->get()
            ->keyBy(fn ($n) => $n->app_id . '|' . ($n->geo_id ?? ''));
    }
}
