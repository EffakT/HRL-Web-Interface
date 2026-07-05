<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row per checkpoint per lap — see docs/database.md ("Splits are per-checkpoint"). */
#[Fillable(['lap_time_id', 'checkpoint_id', 'duration', 'start_time', 'end_time'])]
class LapTimeSplit extends Model
{
    /** @use HasFactory<\Database\Factories\LapTimeSplitFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'duration' => 'float',
            'start_time' => 'float',
            'end_time' => 'float',
        ];
    }

    /** @return BelongsTo<LapTime, $this> */
    public function lapTime(): BelongsTo
    {
        return $this->belongsTo(LapTime::class);
    }

    /**
     * Real per-checkpoint comparison between two laps — shared by every Lap Detail modal
     * consumer that has real split data (currently ServerShow, ServerMapLeaderboard). Returns
     * rows shaped for lap-vs-record-modal.blade.php/leaderboard-podium-and-table.blade.php's
     * split table, or an empty array only if *neither* lap has recorded splits (real coverage
     * is sparse — see docs/database.md), or the two lap ids are the same (nothing to compare
     * against itself — callers should pick a different reference lap in that case, e.g. the
     * #2 lap when viewing #1).
     *
     * Whichever side lacks splits, the other side's raw times are still returned (no
     * delta/running/bar fields) so the view can show *something* rather than nothing:
     * - Selected lap has splits, reference doesn't: rows carry the selected lap's own times,
     *   `hasReference: false`, `usingReferenceSplits: false`.
     * - Selected lap has no splits, reference does: rows carry the *reference's* times instead
     *   (there's nothing else to show), `hasReference: false`, `usingReferenceSplits: true` —
     *   callers/views must check this flag to render the correct explanatory message, since
     *   `hasReference: false` alone doesn't say which side the raw times actually belong to.
     *
     * @return array<int, array{label: string, myTime: string, refTime: ?string, delta: ?string, deltaValue: ?float, running: ?string, faster: ?bool, absDelta: ?float, colorClass: string, barW: ?int, hasReference: bool, usingReferenceSplits: bool}>
     */
    public static function compare(int $lapId, int $referenceLapId): array
    {
        if ($lapId === $referenceLapId) {
            return [];
        }

        $mySplits = self::where('lap_time_id', $lapId)->orderBy('checkpoint_id')->get();

        if ($mySplits->isEmpty()) {
            $refSplitsRaw = self::where('lap_time_id', $referenceLapId)->orderBy('checkpoint_id')->get();

            if ($refSplitsRaw->isEmpty()) {
                return [];
            }

            return $refSplitsRaw->values()->map(fn (self $split, int $index): array => [
                'label' => 'CP '.($index + 1),
                'myTime' => number_format($split->duration, 3),
                'refTime' => null,
                'delta' => null,
                'deltaValue' => null,
                'running' => null,
                'faster' => null,
                'absDelta' => null,
                'colorClass' => 'text-hud-text-dim',
                'barW' => null,
                'hasReference' => false,
                'usingReferenceSplits' => true,
            ])->all();
        }

        $refSplits = self::where('lap_time_id', $referenceLapId)->get()->keyBy('checkpoint_id');

        if ($refSplits->isEmpty()) {
            return $mySplits->values()->map(fn (self $split, int $index): array => [
                'label' => 'CP '.($index + 1),
                'myTime' => number_format($split->duration, 3),
                'refTime' => null,
                'delta' => null,
                'deltaValue' => null,
                'running' => null,
                'faster' => null,
                'absDelta' => null,
                'colorClass' => 'text-hud-text-dim',
                'barW' => null,
                'hasReference' => false,
                'usingReferenceSplits' => false,
            ])->all();
        }

        $rows = [];
        $running = 0.0;

        foreach ($mySplits as $index => $split) {
            $ref = $refSplits[$split->checkpoint_id] ?? null;

            if (! $ref) {
                continue;
            }

            $delta = $split->duration - $ref->duration;
            $running += $delta;

            $rows[] = [
                'label' => 'CP '.($index + 1),
                'myTime' => number_format($split->duration, 3),
                'refTime' => number_format($ref->duration, 3),
                'delta' => ($delta >= 0 ? '+' : '−').number_format(abs($delta), 3),
                // Signed numeric delta, for callers that need to sort/compare (e.g. "biggest
                // gain/loss checkpoint") — the formatted `delta` string above is for display only
                // and must never be sorted on directly (string-sorts "+0.041" vs "−0.028"
                // lexicographically, not numerically).
                'deltaValue' => $delta,
                'running' => ($running >= 0 ? '+' : '−').number_format(abs($running), 3),
                'faster' => $delta < 0,
                'absDelta' => abs($delta),
                'colorClass' => $delta < 0 ? 'text-hud-green' : 'text-hud-red',
                'hasReference' => true,
                'usingReferenceSplits' => false,
            ];
        }

        if (empty($rows)) {
            return [];
        }

        // Bar width scaled relative to the largest delta in *this* comparison. The bar is drawn
        // from a center reference line outward to either edge (see the "faster"/"slower" split
        // in the blade partials: `left-1/2`/`right-1/2` positioning) — that's only HALF the
        // track's total width, so the maximum valid bar width is 50%, not 100%. A width of 100%
        // starting from the center line would overflow past the track's edge by 50%. Capping at
        // 50 keeps every bar symmetric around the zero/center line and never overflowing.
        $maxAbsDelta = max(array_column($rows, 'absDelta')) ?: 1;

        return array_map(fn (array $row): array => [
            ...$row,
            'barW' => (int) round(min(50, $row['absDelta'] / $maxAbsDelta * 50)),
        ], $rows);
    }
}
