<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matches the real (already-imported) `lap_times` table — full history, never pruned, never
 * upserted (see docs/database.md). `created_at`/`updated_at` are created here as full
 * TIMESTAMPs directly (unlike the real dev DB, which had them as DATE until the
 * widen_lap_times_timestamps_to_datetime migration widened them) — a fresh environment has no
 * historical DATE-only rows to preserve, so there's no reason to recreate that constraint here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lap_times', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->unsignedInteger('map_id');
            $table->unsignedInteger('player_id');
            $table->decimal('time', 10, 2);
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers');
            $table->foreign('map_id')->references('id')->on('maps');
            $table->foreign('player_id')->references('id')->on('players');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lap_times');
    }
};
