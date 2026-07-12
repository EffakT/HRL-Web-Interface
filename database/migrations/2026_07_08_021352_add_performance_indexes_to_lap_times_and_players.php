<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERF-02 audit follow-up (docs/performance.md) — composite indexes chosen from real `EXPLAIN`
 * evidence against the actual dev DB, not guessed from the schema alone. Only `lap_times` and
 * `players` gained new indexes here: `servers` and `maps` already have equivalent composite
 * indexes from the SEC-01/SEC-04 unique-constraint work.
 *
 * Two candidate composite indexes were tried and deliberately NOT kept: `(map_id, time,
 * created_at, id)` and `(map_id, server_id, time, created_at, id)`, meant for the map/server
 * leaderboard "best lap per player" queries (`GlobalRanking`, `MapLeaderboard`,
 * `ServerMapLeaderboard`, `RecordHistory`, `HasRecordVsRunnerUpReference`). `EXPLAIN` showed
 * MySQL's optimizer converting those queries' `whereHas('server')` into a semijoin hash-join
 * that bypasses any index on `lap_times` for the sort, confirmed by a `FORCE INDEX` test that
 * did eliminate the filesort the plain index never did. Fixing that needs rewriting those
 * queries away from `whereHas` (a real behavioral change across five call sites), not just an
 * index add, and at today's real scale (1668 rows, ~162-row filesorts) it isn't a measured
 * bottleneck — logged as a follow-up in docs/roadmap.md rather than shipped as a dormant index.
 * A plain `(player_id, created_at)` index — the one the original audit named, for
 * `PlayerList`/`GlobalRanking::scores()`'s unscoped aggregates — was tried for the same reason
 * and dropped for the same reason: the optimizer drives those from `servers` (8 rows) first, so
 * the index sat unused.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lap_times', function (Blueprint $table) {
            // ProcessNewLap's best-time lookup (`where server_id, map_id, player_id -> min(time)`,
            // every webhook submission) previously ran as an index_merge across three
            // single-column FK indexes; this collapses it to one ref lookup (confirmed via
            // EXPLAIN: "Select tables optimized away"). Also backs leaderboardPosition()'s
            // `where server_id, map_id -> groupBy player_id -> min(time)`, removing its "Using
            // temporary" table (confirmed: "Using index", no temp table).
            $table->index(['server_id', 'map_id', 'player_id', 'time']);

            // ServerShow's Latest Laps pagination (`where server_id -> orderBy created_at desc`)
            // and MostActiveServer's rolling-window/most-recent-lap queries (`where server_id ->
            // created_at >= ...`) — both were "Using filesort"; confirmed removed ("Backward
            // index scan" / "Using index condition" respectively).
            $table->index(['server_id', 'created_at']);
        });

        Schema::table('players', function (Blueprint $table) {
            // Player::firstOrCreate(['hash' => ...]) runs on every webhook submission and was a
            // full table scan (no index at all on `hash`, confirmed via EXPLAIN: type "ALL").
            // Deliberately NOT unique: the real DB has 820 player rows for only 246 distinct
            // hashes (up to 137 rows sharing one hash) — a pre-existing data-quality issue raised
            // separately, not something to silently enforce a constraint over. See
            // docs/security.md and docs/performance.md.
            $table->index('hash');
        });
    }

    public function down(): void
    {
        Schema::table('lap_times', function (Blueprint $table) {
            $table->dropIndex(['server_id', 'map_id', 'player_id', 'time']);
            $table->dropIndex(['server_id', 'created_at']);
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropIndex(['hash']);
        });
    }
};
