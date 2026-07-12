<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEC-01 audit follow-up (docs/security.md) — durable lap idempotency (see the
 * add_submission_id_to_lap_times_table migration) is scoped by `server_id`, but without this
 * constraint, `ProcessNewLap`'s `Server::firstOrCreate(['ip' => ..., 'port' => ...], ...)` isn't
 * atomic: two concurrent first-ever submissions for a brand-new ip:port can both pass the SELECT
 * before either INSERT commits, creating two distinct `Server` rows for the same real game
 * server and fragmenting its identity (laps split across two "servers", each server-scoped
 * guarantee weaker than intended). Audit confirmed no duplicate (ip, port) pairs exist in the
 * real data today, so this is safe to add directly rather than needing a cleanup pass first.
 *
 * Three approaches were tried before this one:
 * - Plain `unique(ip, port)` — permanently blocks that ip:port from ever being reused by a
 *   genuinely new server once the old one is archived (soft-deleted).
 * - `unique(ip, port, deleted_at)` directly — doesn't work: SQL's `NULL != NULL` means every
 *   active row (`deleted_at IS NULL`) is treated as distinct from every other, so the
 *   constraint silently never fires for active rows at all (confirmed failing in a test).
 * - A generated column referencing this row's own `id` for the "soft-deleted" branch — MySQL
 *   explicitly rejects generated columns that reference an AUTO_INCREMENT column (error 3109);
 *   this app's real database is MySQL, not just SQLite (SQLite is test/local-only).
 *
 * This version: a generated `active_since` column, `COALESCE(deleted_at, '2000-01-01 00:00:00')`
 * — every active row collapses to the SAME sentinel value, so two active rows sharing (ip, port)
 * correctly collide; a soft-deleted row keeps its real (virtually always distinct) `deleted_at`
 * timestamp, so archived rows don't collide with each other or with a new active row reusing
 * that (ip, port). `COALESCE` with a literal is deterministic and MySQL-safe (unlike
 * `UNIX_TIMESTAMP()`/`NOW()`, which MySQL rejects in generated columns as time-zone-dependent).
 * The sentinel is an arbitrary year-2000 date, not `1970-01-01`, which was tried first and
 * rejected: MySQL's `TIMESTAMP` type's valid range starts at `1970-01-01 00:00:01 UTC`, and
 * whatever `time_zone` this connection evaluates the literal in pushed even that value out of
 * range (confirmed failing against the real MySQL dev DB); any date safely inside the
 * 1970–2038 range and nowhere near either edge, like this one, sidesteps the issue entirely.
 * Residual, accepted edge case: two DIFFERENT servers sharing the same (ip, port) both archived
 * within the same second would collide on this constraint — vanishingly unlikely for a manual
 * admin action, and self-resolving on retry a moment later; not worth more schema complexity to
 * close entirely. `virtualAs()`, not `storedAs()`: SQLite (this project's test driver) cannot
 * add a STORED generated column via `ALTER TABLE ADD COLUMN`, only VIRTUAL — sufficient here
 * since this column is only ever used for indexing, never read as an application-facing value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('active_since')
                ->virtualAs("COALESCE(deleted_at, '2000-01-01 00:00:00')");
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->unique(['ip', 'port', 'active_since']);
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropUnique(['ip', 'port', 'active_since']);
            $table->dropColumn('active_since');
        });
    }
};
