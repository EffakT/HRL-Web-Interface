<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SEC-04 review follow-up (docs/security.md) — closes the gap where every real map's
 * `checkpoint_count` baseline was still `null` (only ever set by `ProcessNewLap::resolveMap()`
 * on a map's *next* split-bearing submission), leaving the concurrency-safe CAS logic with
 * nothing to protect until then. Derives each map's baseline directly from its own historical
 * `lap_time_splits`, confirmed here (not just assumed from docs/database.md's prior spot-check)
 * to be a stable, contiguous `1..N` set for every map that has any split data at all — a map
 * whose historical splits DON'T form a clean `1..N` set is left `null` rather than backfilled
 * with a guess, so it's still learned fresh (and safely, via the CAS path) from its next
 * real submission.
 */
return new class extends Migration
{
    public function up(): void
    {
        $maps = DB::table('maps')->whereNull('checkpoint_count')->get(['id', 'name']);

        foreach ($maps as $map) {
            $checkpointIds = DB::table('lap_time_splits')
                ->join('lap_times', 'lap_times.id', '=', 'lap_time_splits.lap_time_id')
                ->where('lap_times.map_id', $map->id)
                ->distinct()
                ->pluck('lap_time_splits.checkpoint_id')
                ->sort()
                ->values()
                ->all();

            if ($checkpointIds === []) {
                continue;
            }

            if ($checkpointIds !== range(1, count($checkpointIds))) {
                Log::warning('Skipping checkpoint_count backfill: historical splits are not a clean 1..N sequence', [
                    'map_id' => $map->id,
                    'map_name' => $map->name,
                    'checkpoint_ids' => $checkpointIds,
                ]);

                continue;
            }

            DB::table('maps')->where('id', $map->id)->update(['checkpoint_count' => count($checkpointIds)]);
        }
    }

    public function down(): void
    {
        // Deliberately a no-op: this only fills in previously-null baselines from real historical
        // data. Reverting it would just re-null values ProcessNewLap would otherwise re-derive
        // identically from the same data on the map's next split-bearing submission.
    }
};
