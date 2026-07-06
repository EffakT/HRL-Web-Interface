# Homepage

**Status: built, fully wired to real data (2026-07-06)** — every highlight block is real, including Latest/Current Records (the last one, via `App\Models\RecordHistory`'s historical replay). See [decisions.md](decisions.md) for the implementation notes.

## App hierarchy (applies app-wide, not just this page)

- **Map = Performance** — where skill is measured.
- **Server = Community** — where people gather and race together.
- **Player = Mastery + Consistency** — who's actually good, over time (this is exactly what [global-ranking.md](global-ranking.md) is designed to measure).

This framing should inform copy/tone across the app generally, not just the homepage — worth reflecting in [scope.md](scope.md) as a standing product-pillars reference, not something that only lives in this one doc.

## Goal

Make the homepage feel alive and current, not static — via curated, genuinely interesting signals ("what's interesting right now"), not another raw leaderboard dump.

## Latest Highlights (3 of 6 candidate blocks, chosen dynamically)

Six possible highlight block types exist. **Only 3 show at once** — the goal is a clean, relevant page, not all six stacked. Selection is a **fixed priority order**, not a computed per-load score: rank the 6 types below, skip any with nothing to show, take the first 3 that qualify.

**Priority order** (highest first):
1. Latest / Current Records
2. Most Active Server
3. Fastest Improvements / New Breaks
4. New Maps / Servers
5. Player Achievements
6. Live Stats Snapshot

In practice, blocks 1–3 will usually all have *something* to show, so they'll typically be the 3 displayed — blocks 4–6 mostly exist as fallbacks for when one of the top 3 is empty (e.g. no new records this week).

**Recency window, needs confirming**: each block needs some window defining "recent enough to show" / "empty." Defaulting to **7 days** ("this week," matching the Fastest Improvements block's own wording) as a consistent default across all recency-based blocks, unless a block-specific reason says otherwise — flagged as an assumption, not confirmed per-block.

### 1. Latest / Current Records

**Status: implemented for real (2026-07-06)**, via `App\Models\RecordHistory::recent()` — see [decisions.md](decisions.md) for the chronological-replay mechanism and real-data verification (19 historical events found, oldest from 2020).

Format: `{Map} - {Time} by {Player} on {Server} ({relative time} ago)`.

**This block is the strongest evidence yet for resolving the historical-vs-current-state "records" ambiguity** raised in [server-single.md](server-single.md), [players-list.md](players-list.md), and [roadmap.md](roadmap.md): to show *when* a record was set as a discrete recent event, this genuinely requires the **historical record-breaking-events** reading (a point-in-time derivation of "was this lap a new best at the moment it was submitted"), not just "which maps currently have this as their best time." There's no way to build this specific block on the current-state reading alone. This doesn't retroactively decide the other still-open instances of that question, but it's a real data point: at least one planned feature *needs* the historical interpretation to exist.

### 2. Most Active Server

**Status: implemented for real (2026-07-06).** Only servers with genuine activity (`totalScore > 0` — see [most-active-server.md](most-active-server.md)) qualify; shows fewer than 3 (or none) rather than padding with inactive servers.

Reuses [most-active-server.md](most-active-server.md)'s scoring, shown **top-3 podium style** — a third real use case for the podium visual pattern (after the Map Leaderboard and [players-list.md](players-list.md)'s Global Leaderboard), further reinforcing the podium-partial-extraction note already flagged in [coding-standards.md](coding-standards.md).

Per server: Server name, Activity Rank, Unique Players (both 30-day *and* 90-day counts shown together), Last active time. Note the 30d/90d unique-player pair here is a **display-only** stat, independent from the Activity Score's own single 90-day window base metric — computing "unique players in the last 30 days" separately for display doesn't change or feed back into the score itself.

### 3. Fastest Improvements / New Breaks

**Status: implemented for real (2026-07-06)**, via `App\Models\GlobalRanking`'s new `$excludeLapId` parameter and `mapRank()` helper — see [decisions.md](decisions.md) for the exact mechanism and real-data verification. Shows up to one item per sub-item below (not one per player); a sub-item is simply omitted if nothing in the window qualifies. **"Biggest improvement" and "largest rank jump" both require the resulting rank to actually earn points** (`GlobalRanking::pointsForRank() > 0`, i.e. rank ≤50) — without this, a new player could sandbag a deliberately bad first lap (trivially becomes their "PB," nothing existed to beat) then submit a merely-average one to fake a huge-looking improvement/jump despite an uncompetitive result. Found on real data, not hypothetical — see [decisions.md](decisions.md).

Three sub-items:
- Biggest PB improvement this week — e.g. "PlayerX improved Timberland time by −3.2s (new position)."
- Largest rank jump on a map this week.
- New entries into the global Top 10 / Top 3 this week — e.g. "PlayerY entered global Top 10 for the first time."

**Good news on the "needs history" question**: unlike the Trending indicator ([players-list.md](players-list.md), still genuinely unresolved), "rank jump" and "new Top 10 entry" **don't actually require stored historical snapshots**. Both are derivable by re-running the current ranking computation with the player's most recent qualifying lap excluded, then comparing that "before" state to the real current state — a stateless, derived-read comparison, not a point-in-time snapshot dependency. Worth being precise about this distinction: "needs a before/after comparison" doesn't automatically mean "needs stored history," and this block is the proof — the Trending indicator's problem is specifically that it wants comparison against an arbitrary *past* point (e.g. 7 days ago), not "the state one submission ago," which is what makes it different and harder.

### 4. New Maps / Servers

**Status: implemented for real (2026-07-06).** Any map or server created within the recency window — straightforward `created_at` check (`maps`/`servers` tables already have timestamps — see [database.md](database.md)). Real `created_at` values are preserved from the original data (not bulk-import artifacts — verified per-row dates ranging 2018–2026), so this is a genuine "was this actually added recently" check, not a false positive waiting to happen.

### 5. Player Achievements (lightweight)

**Status: implemented for real, all three sub-items (the first one landed 2026-07-06 once `App\Models\RecordHistory` existed).**

- First record for a player (their first-ever course record, of any kind) — **implemented**, via `App\Models\RecordHistory` — checks whether one of this week's laps is that player's earliest-ever record-breaking event.
- Lap count milestones (e.g. crossing 1,000 total laps) — **implemented**, but the milestone list is calibrated to this project's real (small) scale: `[10, 25, 50, 100, 250, 500, 1000]`, since the most laps any real player has ever raced is in the dozens as of 2026-07-06, not the thousands the doc's original example assumed.
- First appearance in Top 10 / Top 3 (global) — **implemented**, same technique as block 3's equivalent item. The same real event can legitimately surface in both blocks if both are selected on the same load — not deduplicated across blocks, see [decisions.md](decisions.md).

Same "compare against state minus the latest event" pattern as block 3 applies here too — no stored snapshots needed to detect "did their most recent lap push them over a milestone or into the Top 10."

### 6. Live Stats Snapshot

**Status: implemented for real (2026-07-06).**

- Total laps (all-time).
- Active players (30d / 90d).
- Active servers (30d / 90d).
- Maps raced today / this week.

**Confirmed distinct from Quick Stats below** (not redundant) — this block is windowed/recency-focused (30d/90d/today/this-week figures), while Quick Stats is simple all-time totals. Both exist; they're not the same thing despite the visual/conceptual similarity.

## Quick Stats (always shown, not one of the 3 dynamic slots)

Total players, total servers, total laps — simple all-time counts, a fixed section independent of the Latest Highlights selection above.

## Quick Links (always shown)

Static navigation: Servers, Maps, Players.

## Open items

- Confirm the 7-day default recency window per highlight block (see above) — currently an assumption, not block-by-block confirmed.
- Product-pillars framing (Map/Server/Player) should be reflected in [scope.md](scope.md), not left living only in this page-specific doc.
- Podium partial extraction (see [coding-standards.md](coding-standards.md)) is now motivated by a third use case here — still not done, just increasingly overdue once any of these pages get built.
