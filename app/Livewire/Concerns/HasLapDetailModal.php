<?php

namespace App\Livewire\Concerns;

/**
 * Shared lap-detail-modal state/behavior for any component that renders a
 * ranked lap table (Maps\MapLeaderboard global view, Servers\ServerMapLeaderboard nested view).
 */
trait HasLapDetailModal
{
    public ?int $selectedPlayerIndex = null;

    public function openLap(int $index): void
    {
        $this->selectedPlayerIndex = $index;
    }

    public function closeLap(): void
    {
        $this->selectedPlayerIndex = null;
    }

    /**
     * Mock per-checkpoint comparison vs. the #1 lap — same sample split regardless of selected player until real data is wired up.
     * 14 rows — deliberately more than 3 to exercise the modal's scrollable split-rows area on high-split-count maps.
     *
     * NOTE: barW is a percentage of HALF the track's width — the bar is drawn from a center
     * reference line out to one edge (see lap-detail-modal.blade.php's `left-1/2`/`right-1/2`
     * positioning), so the maximum valid value is 50, not 100. See LapTimeSplit::compare() for
     * the real (non-mock) equivalent, which computes this correctly from actual deltas.
     */
    public function getComparisonProperty(): array
    {
        return [
            // ['label' => 'CP 1', 'myTime' => '5.241', 'refTime' => '5.200', 'delta' => '+0.041', 'deltaValue' => 0.041, 'running' => '+0.041', 'faster' => false, 'barW' => 6, 'colorClass' => 'text-hud-red', 'hasReference' => true],
            // ['label' => 'CP 2', 'myTime' => '5.322', 'refTime' => '5.350', 'delta' => '−0.028', 'deltaValue' => -0.028, 'running' => '+0.013', 'faster' => true, 'barW' => 4, 'colorClass' => 'text-hud-green', 'hasReference' => true],
            // ['label' => 'CP 3', 'myTime' => '5.002', 'refTime' => '4.950', 'delta' => '+0.052', 'deltaValue' => 0.052, 'running' => '+0.065', 'faster' => false, 'barW' => 8, 'colorClass' => 'text-hud-red', 'hasReference' => true],
            // ['label' => 'CP 4', 'myTime' => '5.581', 'refTime' => '5.600', 'delta' => '−0.019', 'deltaValue' => -0.019, 'running' => '+0.046', 'faster' => true, 'barW' => 3, 'colorClass' => 'text-hud-green', 'hasReference' => true],
            // ['label' => 'CP 5', 'myTime' => '5.138', 'refTime' => '5.100', 'delta' => '+0.038', 'deltaValue' => 0.038, 'running' => '+0.084', 'faster' => false, 'barW' => 6, 'colorClass' => 'text-hud-red', 'hasReference' => true],
            // ['label' => 'CP 6', 'myTime' => '5.511', 'refTime' => '5.450', 'delta' => '+0.061', 'deltaValue' => 0.061, 'running' => '+0.145', 'faster' => false, 'barW' => 9, 'colorClass' => 'text-hud-red', 'hasReference' => true],
            // ['label' => 'CP 7', 'myTime' => '4.806', 'refTime' => '4.850', 'delta' => '−0.044', 'deltaValue' => -0.044, 'running' => '+0.101', 'faster' => true, 'barW' => 7, 'colorClass' => 'text-hud-green', 'hasReference' => true],
            // ['label' => 'CP 8', 'myTime' => '5.372', 'refTime' => '5.300', 'delta' => '+0.072', 'deltaValue' => 0.072, 'running' => '+0.173', 'faster' => false, 'barW' => 11, 'colorClass' => 'text-hud-red', 'hasReference' => true],
            // ['label' => 'CP 9', 'myTime' => '5.029', 'refTime' => '5.050', 'delta' => '−0.021', 'deltaValue' => -0.021, 'running' => '+0.152', 'faster' => true, 'barW' => 3, 'colorClass' => 'text-hud-green', 'hasReference' => true],
            // ['label' => 'CP 10', 'myTime' => '5.555', 'refTime' => '5.500', 'delta' => '+0.055', 'deltaValue' => 0.055, 'running' => '+0.207', 'faster' => false, 'barW' => 8, 'colorClass' => 'text-hud-red', 'hasReference' => true],
            // ['label' => 'CP 11', 'myTime' => '4.948', 'refTime' => '4.900', 'delta' => '+0.048', 'deltaValue' => 0.048, 'running' => '+0.255', 'faster' => false, 'barW' => 7, 'colorClass' => 'text-hud-red', 'hasReference' => true],
            // ['label' => 'CP 12', 'myTime' => '5.367', 'refTime' => '5.400', 'delta' => '−0.033', 'deltaValue' => -0.033, 'running' => '+0.222', 'faster' => true, 'barW' => 5, 'colorClass' => 'text-hud-green', 'hasReference' => true],
            // ['label' => 'CP 13', 'myTime' => '5.179', 'refTime' => '5.150', 'delta' => '+0.029', 'deltaValue' => 0.029, 'running' => '+0.251', 'faster' => false, 'barW' => 5, 'colorClass' => 'text-hud-red', 'hasReference' => true],
            // ['label' => 'CP 14', 'myTime' => '5.575', 'refTime' => '5.283', 'delta' => '+0.292', 'deltaValue' => 0.292, 'running' => '+0.543', 'faster' => false, 'barW' => 45, 'colorClass' => 'text-hud-red', 'hasReference' => true],
        ];
    }

    /**
     * Which player/lap the "vs RECORD" line should name — defaults to player #1 for components
     * that expose a `$players` array (lap-detail-modal.blade.php's rank-based consumers:
     * MapLeaderboard's mock, ServerMapLeaderboard's real override). Components that don't use
     * that shape (e.g. PlayerShow, still mock, uses `$laps` + lap-vs-record-modal.blade.php)
     * get null here — that partial falls back to the selected lap's own recordHolder/recordTime
     * fields in that case, which is what it always did before this property existed.
     * ServerShow overrides this with its own real #record-vs-runner-up fallback.
     */
    public function getComparisonReferenceProperty(): ?array
    {
        if (! property_exists($this, 'players') || empty($this->players[0])) {
            return null;
        }

        return [...$this->players[0], 'label' => 'RECORD'];
    }
}
