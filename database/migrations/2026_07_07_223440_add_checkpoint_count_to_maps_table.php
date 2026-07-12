<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEC-04 audit follow-up (docs/security.md) — the fixed, physical checkpoint layout for a given
 * map file, learned from the first lap submission that actually includes splits (most real
 * submissions don't — see docs/database.md's sparse-splits note, so this stays null until one
 * does). `App\Jobs\ProcessNewLap` compares every later split-bearing submission's checkpoint
 * count against this; a mismatch is forked into its own `{map_name}-splits-{count}` map
 * identity rather than corrupting the original leaderboard or being rejected outright — maps
 * are only ever added, never redesigned in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maps', function (Blueprint $table) {
            $table->unsignedInteger('checkpoint_count')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('maps', function (Blueprint $table) {
            $table->dropColumn('checkpoint_count');
        });
    }
};
