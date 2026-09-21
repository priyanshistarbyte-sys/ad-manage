<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `apps`     — one row per Android app tracked in the panel.
 * `settings` — key/value store for global config (Google Ads API credentials).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('package_id', 191)->comment('Play Store package, e.g. com.example.app');
            $table->string('google_ads_customer_id', 20)->nullable()
                  ->comment('10-digit Google Ads account ID running the campaigns');
            $table->string('google_ads_campaign_id', 30)->nullable()
                  ->comment('optional: restrict fetch to a single campaign');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('setting_key', 100)->primary();
            $table->text('setting_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apps');
        Schema::dropIfExists('settings');
    }
};
