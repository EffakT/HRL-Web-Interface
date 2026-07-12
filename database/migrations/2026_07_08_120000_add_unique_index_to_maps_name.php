<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEC-04 review follow-up (docs/security.md) — `maps.name` had no unique constraint at all, so
 * two concurrent `Map::firstOrCreate(['name' => $mapName], ...)` calls (the base map in
 * `ProcessNewLap::resolveMap()`, or two concurrent mismatched submissions racing to create the
 * same `{map_name}-splits-{count}` variant) could both pass `firstOrCreate()`'s SELECT before
 * either INSERTs, creating two rows for what should be one map identity. `ProcessNewLap` now
 * catches the resulting `UniqueConstraintViolationException` and retries, the same pattern
 * already used for `servers`' identity race (see add_unique_ip_port_index_to_servers_table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maps', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('maps', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
