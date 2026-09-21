<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An Android app tracked in the panel — just a name + its store App ID
 * (package_id, e.g. com.example.app). On sync, Google Ads campaigns are matched
 * to the app by App ID (campaign.app_campaign_setting.app_id → package_id); no
 * per-app customer/campaign IDs are entered.
 */
class App extends Model
{
    protected $table = 'apps';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
