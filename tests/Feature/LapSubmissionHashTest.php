<?php

use App\Helpers\LapSubmissionHash;

it('includes race_type, player_name, and normalized splits in the canonical content fingerprint (SEC-01 audit follow-up)', function () {
    $base = [
        'player_hash' => 'abc123',
        'player_name' => 'Effakt',
        'map_name' => 'bloodgulch',
        'race_type' => 0,
        'player_time' => 42.5,
        'hrl_token' => null,
        'splits' => null,
    ];

    $baseline = LapSubmissionHash::compute($base);

    expect(LapSubmissionHash::compute([...$base, 'race_type' => 1]))->not->toBe($baseline);
    expect(LapSubmissionHash::compute([...$base, 'player_name' => 'SomeoneElse']))->not->toBe($baseline);

    $splitsInOrder = [...$base, 'splits' => [
        ['checkpoint_id' => 1, 'duration' => 1.0, 'startTime' => null, 'endTime' => null],
        ['checkpoint_id' => 2, 'duration' => 2.0, 'startTime' => null, 'endTime' => null],
    ]];
    $splitsOutOfOrder = [...$base, 'splits' => [
        ['checkpoint_id' => 2, 'duration' => 2.0, 'startTime' => null, 'endTime' => null],
        ['checkpoint_id' => 1, 'duration' => 1.0, 'startTime' => null, 'endTime' => null],
    ]];

    // Splits are normalized by checkpoint_id order, so equivalent payloads submitted with
    // differently-ordered arrays hash identically (SEC-01 audit follow-up).
    expect(LapSubmissionHash::compute($splitsInOrder))->toBe(LapSubmissionHash::compute($splitsOutOfOrder));
    expect(LapSubmissionHash::compute($splitsInOrder))->not->toBe($baseline);
});

it('changes the fingerprint when a split field or hrl_token differs (mutation-testing follow-up, 2026-07-09)', function () {
    // pestphp/pest-plugin-mutate found these fields were normalized into the hash but never
    // actually asserted to matter — a mutant that dropped 'duration'/'startTime'/'endTime' from
    // the split shape, or dropped hrl_token from the encoded payload, still passed every test.
    $base = [
        'player_hash' => 'abc123',
        'player_name' => 'Effakt',
        'map_name' => 'bloodgulch',
        'race_type' => 0,
        'player_time' => 42.5,
        'hrl_token' => 'token-a',
        'splits' => [
            ['checkpoint_id' => 1, 'duration' => 1.0, 'startTime' => 0.0, 'endTime' => 1.0],
        ],
    ];

    $baseline = LapSubmissionHash::compute($base);

    expect(LapSubmissionHash::compute([
        ...$base,
        'splits' => [['checkpoint_id' => 1, 'duration' => 2.0, 'startTime' => 0.0, 'endTime' => 1.0]],
    ]))->not->toBe($baseline);

    expect(LapSubmissionHash::compute([
        ...$base,
        'splits' => [['checkpoint_id' => 1, 'duration' => 1.0, 'startTime' => 5.0, 'endTime' => 1.0]],
    ]))->not->toBe($baseline);

    expect(LapSubmissionHash::compute([
        ...$base,
        'splits' => [['checkpoint_id' => 1, 'duration' => 1.0, 'startTime' => 0.0, 'endTime' => 9.0]],
    ]))->not->toBe($baseline);

    expect(LapSubmissionHash::compute([...$base, 'hrl_token' => 'token-b']))->not->toBe($baseline);
    expect(LapSubmissionHash::compute([...$base, 'hrl_token' => null]))->not->toBe($baseline);
});

it('hashes numeric fields identically whether they arrive as native numbers or numeric strings (SEC-01 audit follow-up)', function () {
    $native = [
        'player_hash' => 'abc123',
        'player_name' => 'Effakt',
        'map_name' => 'bloodgulch',
        'race_type' => 0,
        'player_time' => 42.5,
        'hrl_token' => null,
        'splits' => [
            ['checkpoint_id' => 2, 'duration' => 1.5, 'startTime' => 0.0, 'endTime' => 1.5],
        ],
    ];

    $stringified = [
        ...$native,
        'race_type' => '0',
        'player_time' => '42.5',
        'splits' => [
            ['checkpoint_id' => '2', 'duration' => '1.5', 'startTime' => '0.0', 'endTime' => '1.5'],
        ],
    ];

    // Laravel's `numeric`/`integer` validation rules accept a numeric string without coercing
    // it — a request that validates cleanly can still reach this helper with either shape, so
    // both must hash identically.
    expect(LapSubmissionHash::compute($stringified))->toBe(LapSubmissionHash::compute($native));
});
