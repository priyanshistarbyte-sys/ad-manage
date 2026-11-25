<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apps are matched to campaigns by App ID across every connection, so the old
 * apps.connection_id link (never set by the UI) is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('apps', 'connection_id')) {
            Schema::table('apps', function (Blueprint $table) {
                $table->dropConstrainedForeignId('connection_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->foreignId('connection_id')->nullable()->after('id')
                  ->constrained('connections')->nullOnDelete();
        });
    }
};
