<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Google Ads Manager (MCC) account connection with its own API credentials.
 */
class Connection extends Model
{
    protected $guarded = [];

    protected $hidden = ['client_secret', 'refresh_token'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function apps(): HasMany
    {
        return $this->hasMany(App::class);
    }

    /** True once the core credentials needed to call the API are present. */
    public function isConfigured(): bool
    {
        return filled($this->developer_token)
            && filled($this->client_id)
            && filled($this->client_secret)
            && filled($this->refresh_token);
    }
}
