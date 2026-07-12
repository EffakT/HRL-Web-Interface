<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

// PERF-02 audit follow-up (docs/performance.md) — indexes chosen from real EXPLAIN evidence.

uses(LazilyRefreshDatabase::class);

it('indexes lap_times for the hot best-time lookup and per-server recency queries', function () {
    $columns = collect(Schema::getIndexes('lap_times'))->pluck('columns', 'name');

    expect($columns)->toHaveKey('lap_times_server_id_map_id_player_id_time_index')
        ->and($columns['lap_times_server_id_map_id_player_id_time_index'])->toBe(['server_id', 'map_id', 'player_id', 'time'])
        ->and($columns)->toHaveKey('lap_times_server_id_created_at_index')
        ->and($columns['lap_times_server_id_created_at_index'])->toBe(['server_id', 'created_at']);
});

it('uniquely indexes players on (hash, name) — the real identity key, confirmed with the user — for the hot firstOrCreate lookup', function () {
    $indexes = collect(Schema::getIndexes('players'))->keyBy('name');

    expect($indexes)->toHaveKey('players_hash_name_unique')
        ->and($indexes['players_hash_name_unique']['columns'])->toBe(['hash', 'name'])
        ->and($indexes['players_hash_name_unique']['unique'])->toBeTrue()
        ->and($indexes)->not->toHaveKey('players_hash_index');
});
