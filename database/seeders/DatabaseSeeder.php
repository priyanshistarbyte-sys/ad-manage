<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed reference data. The admin user is created on first visit to /login.
     */
    public function run(): void
    {
        $this->call(CountrySeeder::class);
    }
}
