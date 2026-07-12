<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `lap_times.created_at`/`updated_at` were imported as DATE columns — day precision only, no
 * time-of-day (see docs/database.md's "Known constraint" section). This widens them to
 * DATETIME so future lap submissions (once the webhook pipeline exists) capture the actual
 * time they were received, which is close enough to "when the lap happened" for this purpose.
 * Existing historical rows are unaffected — MySQL preserves their stored date, just represented
 * with a 00:00:00 time component; there is no way to recover a time-of-day that was never
 * captured for past laps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lap_times', function (Blueprint $table) {
            $table->timestamp('created_at')->nullable()->change();
            $table->timestamp('updated_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('lap_times', function (Blueprint $table) {
            $table->date('created_at')->nullable()->change();
            $table->date('updated_at')->nullable()->change();
        });
    }
};
