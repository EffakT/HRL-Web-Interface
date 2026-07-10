<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SEC-04 review follow-up (docs/security.md) — a prerequisite for the next migration's unique
 * constraint on `maps.name`. The real dev/prod database has one pre-existing duplicate: map id 1
 * (`bloodgulch`, label "Bloodgulch", 193 lap_times) and id 10 (`bloodgulch`, label "Bloodgulch -
 * Any Order", 0 lap_times) — confirmed dead legacy debris from `ProcessNewLap.php-legacy`'s
 * race-type-suffixed label handling, not a live use of any current feature (docs/decisions.md
 * already documents `race_type` as label-only, never its own Map identity). For each duplicate
 * `name`, this keeps the row with the most `lap_times` (ties broken by lowest id) and folds any
 * other rows' `servers_maps` pivots into the survivor before deleting them.
 *
 * Generic over any future duplicate found, not hardcoded to id 1/10 — but refuses to touch a
 * group where a "losing" row actually has lap_times, since that would mean real leaderboard data
 * would need manual reconciliation rather than an automatic merge.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicateNames = DB::table('maps')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('count(*) > 1')
            ->pluck('name');

        foreach ($duplicateNames as $name) {
            $rows = DB::table('maps')
                ->where('name', $name)
                ->get(['id'])
                ->map(fn ($row) => (object) [
                    'id' => $row->id,
                    'lap_times' => DB::table('lap_times')->where('map_id', $row->id)->count(),
                ])
                ->sortByDesc('lap_times')
                ->values();

            $survivor = $rows->first();
            $losers = $rows->slice(1);

            throw_if($losers->contains(fn ($row) => $row->lap_times > 0), RuntimeException::class, "Duplicate map name '{$name}' has lap_times on more than one row — needs manual reconciliation, not an automatic merge.");

            foreach ($losers as $loser) {
                // MySQL rejects a subquery on `servers_maps` that also targets `servers_maps` in
                // the same UPDATE's FROM clause (error 1093) — read the survivor's existing
                // server_ids into PHP first instead.
                $survivorServerIds = DB::table('servers_maps')
                    ->where('map_id', $survivor->id)
                    ->pluck('server_id')
                    ->all();

                DB::table('servers_maps')
                    ->where('map_id', $loser->id)
                    ->whereNotIn('server_id', $survivorServerIds)
                    ->update(['map_id' => $survivor->id]);

                DB::table('servers_maps')->where('map_id', $loser->id)->delete();
                DB::table('maps')->where('id', $loser->id)->delete();
            }
        }
    }

    public function down(): void
    {
        // Irreversible — the merged rows' original ids/label/pivot rows aren't recoverable.
    }
};
