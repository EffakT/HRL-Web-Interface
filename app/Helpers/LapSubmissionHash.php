<?php

namespace App\Helpers;

/**
 * Canonical content fingerprint for a lap submission (SEC-01 audit follow-up) — identifies
 * "the fields that make two submissions the same lap," independent of the client-supplied
 * `submission_id`. Shared by LapSubmissionController (cache-based idempotency key/conflict
 * detection) and ProcessNewLap (durable database-backed conflict detection via
 * `lap_times.submission_hash`), so both layers agree on what counts as "the same content."
 */
class LapSubmissionHash
{
    /**
     * @param  array{map_name: string, player_hash: string, player_name: string, player_time: float, race_type: int, hrl_token?: string|null, splits?: array<int, array{checkpoint_id: int, duration: float, startTime: float|null, endTime: float|null}>|null}  $data
     */
    public static function compute(array $data): string
    {
        $splits = collect($data['splits'] ?? [])
            ->map(fn (array $split): array => [
                'checkpoint_id' => $split['checkpoint_id'],
                'duration' => $split['duration'],
                'startTime' => $split['startTime'] ?? null,
                'endTime' => $split['endTime'] ?? null,
            ])
            ->sortBy('checkpoint_id')
            ->values()
            ->all();

        return hash('sha256', json_encode([
            $data['player_hash'],
            $data['player_name'],
            $data['map_name'],
            $data['race_type'],
            $data['player_time'],
            $data['hrl_token'] ?? null,
            $splits,
        ]));
    }
}
