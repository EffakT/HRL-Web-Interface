<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap item 19 — a scheduled job periodically queries each active server over the UDP
 * `QueryServer` protocol (see docs/database.md) and stores the result here rather than
 * live-fetching on every page request. Nullable throughout: a server that's never been
 * successfully queried yet (or is currently unreachable) simply has nulls here, and consumers
 * fall back to the existing lap-history-derived proxies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            // unsignedInteger, not foreignId (which is unsignedBigInteger) — `maps.id` is
            // `increments()` (unsigned int), matching every other FK to it in this schema
            // (see lap_times.map_id).
            $table->unsignedInteger('current_map_id')->nullable()->after('notify_outage_last');
            $table->unsignedSmallInteger('live_player_count')->nullable()->after('current_map_id');
            $table->timestamp('queried_at')->nullable()->after('live_player_count');
            $table->boolean('query_successful')->nullable()->after('queried_at');

            $table->foreign('current_map_id')->references('id')->on('maps')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropForeign(['current_map_id']);
            $table->dropColumn(['current_map_id', 'live_player_count', 'queried_at', 'query_successful']);
        });
    }
};
