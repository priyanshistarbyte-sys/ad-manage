<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignStat extends Model
{
    protected $guarded = [];

    protected $casts = [
        'period_start'      => 'date',
        'period_end'        => 'date',
        'cost'              => 'float',
        'conversions'       => 'float',
        'conversions_value' => 'float',
        'impressions'       => 'integer',
        'clicks'            => 'integer',
        'synced_at'         => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    /** Conv. value ÷ Cost as a percentage (Google Ads ROAS). */
    public function getRoasAttribute(): float
    {
        return $this->cost > 0 ? $this->conversions_value / $this->cost * 100 : 0;
    }
}
