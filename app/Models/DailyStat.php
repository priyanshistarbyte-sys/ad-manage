<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (connection, customer, campaign, country, date) — the daily,
 * per-country report grain. Revenue buckets are stored raw; TROAS / TOTAL_REV /
 * CPI / trial-convert-% are derived here so every read is consistent.
 */
class DailyStat extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date'              => 'date',
        'geo_id'            => 'integer',
        'cost'              => 'float',
        'impressions'       => 'integer',
        'clicks'            => 'integer',
        'conversions'       => 'float',
        'conversions_value' => 'float',
        'install'           => 'float',
        'trial'             => 'float',
        'trial_convert'     => 'float',
        'repeat_count'      => 'float',
        'ad_rev'            => 'float',
        'convert_rev'       => 'float',
        'renew_rev'         => 'float',
        'synced_at'         => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    // ── Derived report metrics ──────────────────────────────────────────
    /** Ad revenue = Conv. Value straight from the report (conversions_value). */
    public function getAdRevAttribute(): float
    {
        return (float) $this->conversions_value;
    }

    /** CONVERT_REV is excluded from the report (unreliable mapping) → always 0. */
    public function getConvertRevAttribute(): float
    {
        return 0.0;
    }

    public function getTotalRevAttribute(): float
    {
        return (float) $this->ad_rev + (float) $this->renew_rev;
    }

    /** TROAS (%) = Conv. Value ÷ Cost × 100. */
    public function getTroasAttribute(): float
    {
        return $this->cost > 0 ? (float) $this->conversions_value / $this->cost * 100 : 0;
    }

    /** Cost per install. */
    public function getCpiAttribute(): float
    {
        return $this->install > 0 ? $this->cost / $this->install : 0;
    }

    /** Trial → paid conversion rate, as a percentage. */
    public function getTrialConvertPercAttribute(): float
    {
        return $this->trial > 0 ? $this->trial_convert / $this->trial * 100 : 0;
    }

    public function getCountryNameAttribute(): string
    {
        return Country::nameFor($this->geo_id);
    }

    public function getCountryCodeAttribute(): string
    {
        return Country::codeFor($this->geo_id);
    }
}
