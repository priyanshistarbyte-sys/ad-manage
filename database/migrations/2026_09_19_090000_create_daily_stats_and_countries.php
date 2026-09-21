<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily, per-country, per-campaign report data — the backbone of the Excel-style
 * report (DATE, COST, TROAS, revenue buckets, INSTALL, …).
 *
 *  - `countries`          geo-target-constant id → ISO code + display name.
 *  - `daily_stats`        one row per (connection, customer, campaign, country, date)
 *                         holding the latest pulled + bucketed metrics.
 *  - `daily_stat_history` append-only snapshot written on every sync so a day's
 *                         numbers can be tracked as Google Ads keeps updating them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->unsignedBigInteger('geo_id')->primary()->comment('Google geo target constant id');
            $table->string('code', 4)->nullable()->index()->comment('ISO 2-letter country code');
            $table->string('name', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->foreignId('app_id')->nullable()->constrained('apps')->nullOnDelete();

            $table->string('customer_id', 20);
            $table->string('account_name', 191)->nullable();
            $table->string('campaign_id', 30);
            $table->string('campaign_name', 191)->nullable();
            $table->unsignedBigInteger('geo_id')->nullable()->index()->comment('country geo target id, null = unknown');

            $table->date('date');

            // Google Ads spend + engagement
            $table->decimal('cost', 16, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('conversions', 14, 2)->default(0);
            $table->decimal('conversions_value', 16, 2)->default(0);

            // Bucketed conversion-action metrics (counts)
            $table->decimal('install', 14, 2)->default(0);
            $table->decimal('trial', 14, 2)->default(0);
            $table->decimal('trial_convert', 14, 2)->default(0);
            $table->decimal('repeat_count', 14, 2)->default(0);

            // Bucketed conversion-action metrics (revenue values)
            $table->decimal('ad_rev', 16, 2)->default(0);
            $table->decimal('convert_rev', 16, 2)->default(0);
            $table->decimal('renew_rev', 16, 2)->default(0);

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'customer_id', 'campaign_id', 'geo_id', 'date'], 'daily_stats_unique');
            $table->index(['date', 'app_id']);
        });

        Schema::create('daily_stat_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->string('customer_id', 20);
            $table->string('campaign_id', 30);
            $table->unsignedBigInteger('geo_id')->nullable();
            $table->date('date');

            $table->decimal('cost', 16, 2)->default(0);
            $table->decimal('install', 14, 2)->default(0);
            $table->decimal('trial', 14, 2)->default(0);
            $table->decimal('trial_convert', 14, 2)->default(0);
            $table->decimal('repeat_count', 14, 2)->default(0);
            $table->decimal('ad_rev', 16, 2)->default(0);
            $table->decimal('convert_rev', 16, 2)->default(0);
            $table->decimal('renew_rev', 16, 2)->default(0);
            $table->decimal('total_rev', 16, 2)->default(0);

            $table->timestamp('captured_at')->nullable()->index();

            $table->index(['date', 'campaign_id', 'geo_id'], 'history_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_stat_history');
        Schema::dropIfExists('daily_stats');
        Schema::dropIfExists('countries');
    }
};
