<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `connections` — one row per Google Ads Manager (MCC) account, each with its
 * own API credentials. Apps reference a connection instead of sharing one
 * global credential set, so many MCC accounts can be managed side by side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->comment('label, e.g. "Agency MCC" or client name');
            $table->string('developer_token', 191)->nullable();
            $table->string('client_id', 191)->nullable();
            $table->string('client_secret', 191)->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('login_customer_id', 20)->nullable()->comment('the Manager account 10-digit ID');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('apps', function (Blueprint $table) {
            $table->foreignId('connection_id')->nullable()->after('id')
                  ->constrained('connections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('connection_id');
        });
        Schema::dropIfExists('connections');
    }
};
