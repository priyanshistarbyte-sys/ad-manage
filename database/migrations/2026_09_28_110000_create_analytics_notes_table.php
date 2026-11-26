<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Analytics page Notes: a date-wise log of notes per (app, country). Each entry
 * also records the Solved / Read state set when it was saved — the latest entry
 * for a pair is that country's current status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->unsignedBigInteger('geo_id')->nullable()->comment('country geo target id, null = unknown');
            $table->text('note')->nullable();
            $table->boolean('solved')->default(false);
            $table->boolean('read')->default(false);
            $table->string('user_name', 100)->nullable();
            $table->timestamps();

            $table->index(['app_id', 'geo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_notes');
    }
};
