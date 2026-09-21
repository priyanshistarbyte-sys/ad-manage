<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * Seed the curated geo-target-constant → country map. The full authoritative
 * list is refreshed from the Google Ads API by GoogleAdsService::syncCountries()
 * on each Sync All; this just gives sensible names before the first sync.
 */
class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $rows = require resource_path('data/geo_countries.php');

        foreach ($rows as $geoId => [$code, $name]) {
            Country::updateOrCreate(
                ['geo_id' => $geoId],
                ['code' => $code, 'name' => $name]
            );
        }
    }
}
