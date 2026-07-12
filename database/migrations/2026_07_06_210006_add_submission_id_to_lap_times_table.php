<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEC-01 audit follow-up (docs/security.md) — durable, transactional idempotency for the
 * lap-submission webhook's optional client-supplied `submission_id`, so a duplicate can't slip
 * through if the cache-based idempotency guard's entry is ever gone (restart, eviction, a very
 * late retry). Nullable — laps submitted without a `submission_id` (older Lua scripts) simply
 * have null here and rely on the pre-existing cache-only guard, unchanged. A unique index on
 * (server_id, submission_id) rather than submission_id alone: it's scoped per server so two
 * different game servers coincidentally generating the same submission_id value never collide
 * (SQLite/MySQL/Postgres all treat multiple NULLs in a unique index as distinct, so any number
 * of null-`submission_id` rows are unaffected).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lap_times', function (Blueprint $table) {
            $table->string('submission_id')->nullable()->after('player_id');

            $table->unique(['server_id', 'submission_id']);
        });
    }

    public function down(): void
    {
        Schema::table('lap_times', function (Blueprint $table) {
            $table->dropUnique(['server_id', 'submission_id']);
            $table->dropColumn('submission_id');
        });
    }
};
