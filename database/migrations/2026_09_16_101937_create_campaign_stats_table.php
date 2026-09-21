<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `campaign_stats` — one row per campaign discovered by "Sync All", holding the
 * latest pulled totals for the synced date range. Rebuilt on each sync
 * (upsert on connection + customer + campaign).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->string('customer_id', 20);
            $table->string('account_name', 191)->nullable();
            $table->string('campaign_id', 30);
            $table->string('campaign_name', 191)->nullable();
            $table->string('status', 30)->nullable();
            $table->string('channel_type', 40)->nullable();

            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            $table->decimal('cost', 16, 2)->default(0);
            $table->decimal('conversions', 14, 2)->default(0);
            $table->decimal('conversions_value', 16, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'customer_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_stats');
    }
};
