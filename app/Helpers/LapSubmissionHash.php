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
        // Validated request input can carry numeric fields as either a native type or a numeric
        // string (Laravel's `numeric`/`integer` rules accept "42.5"/"2" without coercing them) —
        // cast every one to a canonical type before hashing so two semantically identical
        // submissions (SEC-01 audit follow-up) can't hash differently just because one arrived
        // as a JSON number and the other as a string.
        $splits = collect($data['splits'] ?? [])
            ->map(fn (array $split): array => [
                'checkpoint_id' => (int) $split['checkpoint_id'],
                'duration' => (float) $split['duration'],
                'startTime' => isset($split['startTime']) ? (float) $split['startTime'] : null,
                'endTime' => isset($split['endTime']) ? (float) $split['endTime'] : null,
            ])
            ->sortBy('checkpoint_id')
            ->values()
            ->all();

        return hash('sha256', json_encode([
            $data['player_hash'],
            $data['player_name'],
            $data['map_name'],
            (int) $data['race_type'],
            (float) $data['player_time'],
            $data['hrl_token'] ?? null,
            $splits,
        ]));
    }
}
