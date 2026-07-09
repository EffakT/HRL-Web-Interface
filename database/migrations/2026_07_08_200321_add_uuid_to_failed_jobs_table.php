<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The real `failed_jobs` table predates this rebuild (the original app's own migration created
 * it) and lacks the `uuid` column Laravel 13's `database-uuids` failed-job driver
 * (`config('queue.failed.driver')`) requires — every failed queue job was silently unrecordable
 * (`SQLSTATE[42S02]: Unknown column 'uuid'`) until this. Nullable since existing legacy rows have
 * no uuid to backfill; every job that fails from here on gets one via the driver itself.
 *
 * Guarded with `hasColumn()`: a fresh environment (CI, the Pest SQLite DB) builds `failed_jobs`
 * from the stock `0001_01_01_000002_create_jobs_table` migration, which already includes `uuid`
 * — this migration only has real work to do against the real, pre-existing legacy MySQL table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('failed_jobs', 'uuid')) {
            return;
        }

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->string('uuid')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('failed_jobs', 'uuid')) {
            return;
        }

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
