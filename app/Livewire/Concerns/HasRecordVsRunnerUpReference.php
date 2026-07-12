<?php

namespace App\Livewire\Concerns;

use App\Models\LapTime;

/**
 * Shared "map record, or that map's runner-up if you ARE the record holder" comparison
 * reference. Used by any component whose rows carry
 * ['lapId', 'mapId', 'recordLapId', 'recordHolder', 'recordTime'] — currently ServerShow's
 * Latest Laps and PlayerShow's Performance by Map. Extracted once PlayerShow needed the exact
 * same logic ServerShow already had, per docs/coding-standards.md's "extract on the second
 * genuine duplicate" rule.
 */
trait HasRecordVsRunnerUpReference
{
    /**
     * @param  array{lapId: int, mapId: int, recordLapId: ?int, recordHolder: string, recordTime: string}|null  $lap
     * @return array{lapId: int, name: string, time: string, label: string}|null
     */
    private function resolveComparisonReference(?array $lap): ?array
    {
        if (! $lap || ! $lap['recordLapId']) {
            return null;
        }

        if ($lap['lapId'] !== $lap['recordLapId']) {
            return [
                'lapId' => $lap['recordLapId'],
                'name' => $lap['recordHolder'],
                'time' => $lap['recordTime'],
                'label' => 'MAP RECORD',
            ];
        }

        $runnerUp = LapTime::with('player')
            ->where('map_id', $lap['mapId'])
            ->whereHas('server')
            ->where('id', '!=', $lap['lapId'])
            ->orderBy('time')->oldest()
            ->first();

        if (! $runnerUp) {
            return null;
        }

        return [
            'lapId' => $runnerUp->id,
            'name' => $runnerUp->player->name,
            'time' => $runnerUp->formattedTime(),
            'label' => 'RUNNER-UP',
        ];
    }
}
