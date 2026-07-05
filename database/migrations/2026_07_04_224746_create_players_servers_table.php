<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matches the real (already-imported) `players_servers` pivot table. No unique constraint on
 * (player_id, server_id) — same duplicate-rows situation as `servers_maps`, see docs/database.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players_servers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('player_id');
            $table->unsignedInteger('server_id');
            $table->timestamps();

            $table->foreign('player_id')->references('id')->on('players');
            $table->foreign('server_id')->references('id')->on('servers');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players_servers');
    }
};
