<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Converts a legacy default (email/password) users table to the PIN-auth
 * schema. Idempotent: on a fresh install the base migration already creates the
 * PIN schema, so this becomes a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the default Laravel auth columns if they linger from an older schema.
        foreach (['email', 'email_verified_at', 'password', 'remember_token'] as $col) {
            if (Schema::hasColumn('users', $col)) {
                Schema::table('users', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }

        // Add the PIN-auth columns if they are missing.
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'pin_hash')) {
                $table->string('pin_hash', 255)->after('name');
            }
            if (!Schema::hasColumn('users', 'pin2_hash')) {
                $table->string('pin2_hash', 255)->nullable()->after('pin_hash')
                      ->comment('second PIN hash — 2-step verification for admins');
            }
            if (!Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false);
            }
            if (!Schema::hasColumn('users', 'active')) {
                $table->boolean('active')->default(true);
            }
        });

        // The legacy password_reset_tokens table is unused by PIN auth.
        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        // Non-reversible in a meaningful way; leave the PIN schema in place.
    }
};
