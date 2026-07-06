<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEC-01 audit follow-up: persists the canonical content fingerprint (App\Helpers\
 * LapSubmissionHash) alongside each lap, so a reused `submission_id` can be checked for a
 * content mismatch even after the cache-based idempotency guard's copy has expired, been
 * evicted, or the app restarted — see LapSubmissionController/ProcessNewLap and
 * docs/security.md. Nullable because existing laps and laps submitted without a
 * `submission_id` have no fingerprint to compare against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lap_times', function (Blueprint $table) {
            $table->char('submission_hash', 64)->nullable()->after('submission_id');
        });
    }

    public function down(): void
    {
        Schema::table('lap_times', function (Blueprint $table) {
            $table->dropColumn('submission_hash');
        });
    }
};
