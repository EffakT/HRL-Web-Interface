<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matches the real (already-imported) `servers_maps` pivot table.
 *
 * NOTE: no unique constraint on (server_id, map_id) — the real data has many duplicate rows
 * for the same pair (see docs/database.md's "Duplicate pivot rows" section). Adding a unique
 * constraint here would make this schema stricter than the real data actually is, which would
 * be misleading for anyone testing against it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers_maps', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->unsignedInteger('map_id');
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers');
            $table->foreign('map_id')->references('id')->on('maps');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers_maps');
    }
};
