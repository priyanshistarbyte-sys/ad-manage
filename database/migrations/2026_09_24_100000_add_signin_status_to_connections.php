<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track the outcome of the last Google sign-in per connection so the Ad Accounts
 * page can flag an account whose refresh token has expired / been revoked with a
 * "Reconnect" badge, instead of the failure only showing up at sync time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->boolean('last_signin_ok')->nullable()->after('active');
            $table->string('last_signin_error', 500)->nullable()->after('last_signin_ok');
            $table->timestamp('last_signin_at')->nullable()->after('last_signin_error');
        });
    }

    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->dropColumn(['last_signin_ok', 'last_signin_error', 'last_signin_at']);
        });
    }
};
