<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Google geo-target-constant (country level) → readable name/ISO code.
 * `geo_id` is the numeric id from `segments.geo_target_country`.
 */
class Country extends Model
{
    protected $primaryKey = 'geo_id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $guarded = [];

    /** In-request cache of geo_id => ['code','name'] to avoid per-row queries. */
    private static ?array $map = null;

    public static function map(): array
    {
        if (self::$map === null) {
            self::$map = self::query()->get(['geo_id', 'code', 'name'])
                ->keyBy('geo_id')
                ->map(fn ($c) => ['code' => $c->code, 'name' => $c->name])
                ->toArray();
        }
        return self::$map;
    }

    /** Display name for a geo id, falling back to the raw id when unmapped. */
    public static function nameFor(?int $geoId): string
    {
        if (!$geoId) return 'Unknown';
        $m = self::map();
        return $m[$geoId]['name'] ?? ('Geo ' . $geoId);
    }

    public static function codeFor(?int $geoId): string
    {
        if (!$geoId) return '—';
        $m = self::map();
        return $m[$geoId]['code'] ?? (string) $geoId;
    }

    public static function forget(): void
    {
        self::$map = null;
    }
}
