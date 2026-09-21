<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An Android app tracked in the panel. Google Ads spend/install data is later
 * fetched per app using its connection's credentials + its own
 * google_ads_customer_id (and optional campaign).
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
