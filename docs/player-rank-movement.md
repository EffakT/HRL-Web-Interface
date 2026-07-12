# Player Rank Movement ("Last Move") Implementation Plan

**Status:** approved direction, not implemented.  
**Initial scope:** global Players leaderboard (`/players`) only.  
**Decision date:** 13 July 2026.

## Goal

Add an honest up/down/unchanged indicator to the global player leaderboard despite HRL sometimes going months without a new lap.

Calendar-window trends (daily/weekly/monthly snapshots) are a poor fit: they would usually compare identical states and show stale dashes. A recent-activity proxy is also misleading because submitting or improving a lap does not necessarily change rank, while an inactive player can move down when someone else improves.

The feature will therefore report a player's **last actual rank movement**, driven by accepted lap events rather than elapsed time. The UI label is **Last Move**, not Trend.

## Product semantics

- `▲ 3`: the player gained three global positions in their most recent non-zero movement.
- `▼ 2`: the player lost two global positions in their most recent non-zero movement.
- `NEW`: the player entered the global ranking for the first time after tracking began.
- `—`: no recorded movement exists yet.
- Preserve the last non-zero movement when a later accepted lap causes no rank change.
- Show the movement's age in a tooltip/accessible description, e.g. “Moved up 3 positions on 13 July 2026.”
- Colour is supplementary: arrows, numbers, and accessible text must convey the meaning independently.

This describes the last move, not a sustained performance trend. Do not silently rename it to Trend unless a multi-event aggregation is designed later.

## Important baseline rule

Deployment must not label every existing player `NEW`.

Before enabling movement recording, capture one baseline of the current global ranking. That baseline establishes `last_known_rank` without creating visible movement. Only subsequent accepted laps can produce `NEW`, `▲`, or `▼`.

## Proposed storage

Use one current-state row per player rather than daily snapshots or an unbounded event log:

```text
player_rank_movements
- player_id                 bigint, primary/foreign key
- last_known_rank           unsigned integer
- last_known_score          unsigned integer
- last_movement_from_rank   unsigned integer, nullable
- last_movement_to_rank     unsigned integer, nullable
- last_movement_delta       signed integer, nullable
- triggering_lap_id         bigint, nullable/foreign key
- moved_at                  timestamp, nullable
- created_at
- updated_at
```

Define positive delta as improvement:

```php
$delta = $oldRank - $newRank;
```

`last_known_rank` is advanced after every successfully processed ranking change, including zero movement. The `last_movement_*`, `triggering_lap_id`, and `moved_at` fields change only for a non-zero move or a genuine post-baseline entrant.

The first version needs no generic `leaderboard_key`: it supports exactly one global Players leaderboard. Generalise only when a second real scope (server or map) is approved.

## Write path

Integrate after an accepted lap has committed and the new global ranking can be calculated:

1. Load all existing `player_rank_movements` rows keyed by player ID.
2. Compute the new ranking once with `GlobalRanking::scores()`.
3. For each currently ranked player:
   - no tracking row during normal operation: create `NEW` (`from_rank = null`, `to_rank = current rank`, `delta = null`);
   - existing row with changed rank: record from/to/delta, triggering lap, and timestamp;
   - existing row with unchanged rank: update only last-known rank/score as needed;
   - do not erase a previous non-zero movement on an unchanged result.
4. Update all rows in a transaction, using upserts/bulk writes rather than one query per player.
5. Only then dispatch the normal leaderboard update event.

The movement update must be idempotent by `triggering_lap_id`: retrying the same accepted submission must not manufacture or overwrite movement. Store a small processing marker or make the updater explicitly return early when the latest processed lap ID is already applied. Decide the exact constraint while implementing after reviewing the transaction boundary in `ProcessNewLap`.

### Concurrency

Two accepted laps can finish close together. Serialize global movement updates with a database-backed lock, not a cache-only correctness lock. The implementation should lock a dedicated singleton row (or equivalent database advisory mechanism), then compute and persist movement in lap commit order. A test must prove concurrent updates cannot compare both new rankings against the same stale baseline.

Do not reuse the scheduler's former `withoutOverlapping()` pattern; that incident demonstrated why an expiring/stale cache mutex is unsuitable as the only correctness mechanism.

## Read path and UI

`PlayerList::loadPlayers()` should bulk-load movement rows once and add a `lastMove` value to each player array. No per-row queries.

Restore a shared partial such as:

```text
resources/views/livewire/partials/rank-movement-indicator.blade.php
```

Desktop:

- Add a `LAST MOVE` column after Score (or at the right edge if the existing table width reads better during visual verification).
- Render `▲ n` in HUD green, `▼ n` in a warning/red token, `NEW` in cyan/gold, and `—` dimmed.

Mobile:

- Place the compact indicator beside the score; do not add a separate wide column.

Accessibility:

- Include screen-reader text such as “Up 3 positions” or an `aria-label` on the indicator.
- Add exact movement date through accessible tooltip text; relative age alone is ambiguous and changes over time.
- Never rely on green/red colour alone.

The podium can omit Last Move in the first iteration. Add it only if the table treatment proves useful and the podium remains visually clear.

## Baseline command

Add an idempotent command, for example:

```text
php artisan leaderboard:baseline-player-movements
```

It should:

- refuse to overwrite existing tracking rows unless an explicit safe option is provided;
- compute `GlobalRanking::scores()` once;
- insert current rank/score with all visible movement fields null;
- report inserted/skipped counts;
- be run after migration and before enabling the write hook.

Document the deployment order. A missed baseline would incorrectly mark the entire current population as new.

## Testing plan

### Unit/domain tests

- rank 10 → 7 produces `▲ 3` and delta `+3`;
- rank 7 → 9 produces `▼ 2` and delta `-2`;
- unchanged rank preserves the prior non-zero movement;
- a post-baseline entrant produces `NEW`;
- a missing baseline does not get presented as a normal historical movement;
- retrying the same triggering lap is idempotent;
- score-only change with unchanged rank does not create movement.

### Feature/database tests

- baseline command inserts neutral rows and is safe to rerun;
- one player's improvement records both their rise and every displaced player's fall;
- movements are derived from the configured Global Score variant and existing tie-break rules;
- soft-deleted servers remain excluded exactly as in `GlobalRanking`;
- the Players page loads all indicators without N+1 queries;
- a failed lap transaction creates no movement;
- concurrent accepted laps are serialized and produce the same final state as sequential processing.

### Browser/accessibility tests

- desktop and mobile display each state correctly;
- screen-reader labels contain direction and magnitude;
- exact dates are available without colour dependence;
- the live Echo/Livewire refresh shows the new movement without a page reload.

## Rollout

1. Add migration/model and movement-domain tests.
2. Add the baseline command and verify it against the staging database.
3. Run the baseline before activating movement writes.
4. Add the serialized, idempotent post-lap updater.
5. Add the Player List read path and UI partial.
6. Run `composer check`, the production frontend build, focused concurrency tests, and browser tests.
7. Submit a controlled real lap that changes rank and verify the mover plus displaced players.
8. Observe several real leaderboard events before considering other scopes.

## Explicitly out of scope

- Calendar-based daily/weekly/monthly snapshots.
- Treating inactivity as downward movement.
- Inferring movement from PBs or lap volume.
- Server, map, and server-map Last Move indicators in the first version.
- A sparkline or multi-event trend score.
- Reconstructing movement before the feature's baseline date.

## Future extension

If Last Move proves useful, retain a bounded event history (for example, the last five non-zero movements per player) and derive a sparkline or momentum summary. That is a separate feature: it changes storage from current state to history and needs its own product definition.

