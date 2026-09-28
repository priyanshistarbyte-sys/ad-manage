<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * History snapshots need Conv. Value (conversions_value) — the report's AD_REV
 * and TROAS are built from it, so without it View History showed TROAS 0%.
 *
 * Backfill: the snapshot written by a row's most recent sync has
 * captured_at = daily_stats.synced_at, so copy the value across for exactly
 * those snapshots. Older snapshots stay NULL — their value was never recorded,
 * and View History shows them as "not recorded" rather than a fake 0%.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_stat_history', function (Blueprint $table) {
            $table->decimal('conversions_value', 16, 2)->nullable()->after('cost');
        });

        DB::statement('
            UPDATE daily_stat_history h
            JOIN daily_stats d
              ON d.connection_id = h.connection_id
             AND d.customer_id   = h.customer_id
             AND d.campaign_id   = h.campaign_id
             AND d.date          = h.date
             AND d.geo_id <=> h.geo_id
             AND d.synced_at     = h.captured_at
            SET h.conversions_value = d.conversions_value
        ');
    }

    public function down(): void
    {
        Schema::table('daily_stat_history', function (Blueprint $table) {
            $table->dropColumn('conversions_value');
        });
    }
};
