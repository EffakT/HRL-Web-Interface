<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERF-02/security follow-up (docs/security.md's "players.hash race condition" note).
 *
 * `hash` alone looked like a real-identity key with a large, alarming amount of duplication
 * (820 `players` rows for only 246 distinct hashes) — but the user confirmed `hash` is no
 * longer guaranteed unique per player upstream (the game client stopped manufacturing one
 * distinct hash per player at some point, so unrelated players can share it) and the real
 * identity key is the (`name`, `hash`) pair together. Confirmed against the real dev DB before
 * writing this: zero existing rows share both `name` and `hash` — so this constraint can be
 * added directly, with no merge/dedup decision needed (unlike a plain `hash` unique index,
 * which would have required deciding how to collapse hundreds of genuinely-different players).
 *
 * Ordered (`hash`, `name`) rather than (`name`, `hash`) so this index's leftmost prefix
 * (`hash` alone) also serves the hot `Player::firstOrCreate` lookup that the plain
 * `players_hash_index` (added the same day, PERF-02) existed for — that now-redundant index is
 * dropped here rather than left as dead weight.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropIndex(['hash']);
            $table->unique(['hash', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropUnique(['hash', 'name']);
            $table->index('hash');
        });
    }
};
