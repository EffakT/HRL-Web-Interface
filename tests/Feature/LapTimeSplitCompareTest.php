<?php

use App\Models\LapTime;
use App\Models\LapTimeSplit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

// LapTimeSplit::compare() has four real branches — real split coverage is sparse (~4% of laps,
// see docs/database.md), so "one side has no splits" isn't an edge case, it's the common case.
// This covers the fix from docs/decisions.md: compare() used to only handle "selected lap has
// splits, reference doesn't" — "reference has splits, selected lap doesn't" silently returned [].
uses(LazilyRefreshDatabase::class);

it('returns an empty array when the two lap ids are the same', function () {
    expect(LapTimeSplit::compare(1, 1))->toBe([]);
});

it('returns an empty array when neither lap has splits', function () {
    $lap = LapTime::factory()->create();
    $reference = LapTime::factory()->create();

    expect(LapTimeSplit::compare($lap->id, $reference->id))->toBe([]);
});

it('returns a real comparison when both laps have matching checkpoints', function () {
    $lap = LapTime::factory()->create();
    $reference = LapTime::factory()->create();

    LapTimeSplit::factory()->create(['lap_time_id' => $lap->id, 'checkpoint_id' => 1, 'duration' => 5.5]);
    LapTimeSplit::factory()->create(['lap_time_id' => $reference->id, 'checkpoint_id' => 1, 'duration' => 5.0]);

    $rows = LapTimeSplit::compare($lap->id, $reference->id);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['hasReference'])->toBeTrue()
        ->and($rows[0]['usingReferenceSplits'])->toBeFalse()
        ->and($rows[0]['myTime'])->toBe('5.500')
        ->and($rows[0]['refTime'])->toBe('5.000')
        ->and($rows[0]['delta'])->toBe('+0.500')
        ->and($rows[0]['faster'])->toBeFalse();
});

it('returns raw rows for the selected lap when the reference lap has no splits', function () {
    $lap = LapTime::factory()->create();
    $reference = LapTime::factory()->create();

    LapTimeSplit::factory()->create(['lap_time_id' => $lap->id, 'checkpoint_id' => 1, 'duration' => 5.5]);

    $rows = LapTimeSplit::compare($lap->id, $reference->id);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['hasReference'])->toBeFalse()
        ->and($rows[0]['usingReferenceSplits'])->toBeFalse()
        ->and($rows[0]['myTime'])->toBe('5.500')
        ->and($rows[0]['refTime'])->toBeNull();
});

it('returns the reference lap\'s raw rows when the selected lap has no splits', function () {
    $lap = LapTime::factory()->create();
    $reference = LapTime::factory()->create();

    LapTimeSplit::factory()->create(['lap_time_id' => $reference->id, 'checkpoint_id' => 1, 'duration' => 5.0]);

    $rows = LapTimeSplit::compare($lap->id, $reference->id);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['hasReference'])->toBeFalse()
        ->and($rows[0]['usingReferenceSplits'])->toBeTrue()
        ->and($rows[0]['myTime'])->toBe('5.000')
        ->and($rows[0]['refTime'])->toBeNull();
});
