# Players List Page

The page listing all players (`/players`, `App\Livewire\Players\PlayerList` — see [architecture.md](architecture.md)). **Status: built, wired to real data (2026-07-06)** — the old simple name/laps/best-lap table has been fully replaced with the Global Leaderboard described below, backed by `App\Models\GlobalRanking`. See [decisions.md](decisions.md).

## Design principle

Explicitly **not** ranked by raw lap count — a player who grinds volume without skill would dominate a raw-count ranking, which reflects effort rather than ability and dilutes what the leaderboard means. Prioritize **score, records, consistency, and activity** instead. This is exactly why [global-ranking.md](global-ranking.md) already uses position-based points (capped per map, once per map) rather than raw lap volume — this page is a direct, validating application of that existing design goal, not a new principle.

## Info card (top of page)

Four stats, meant to make the page feel alive/current rather than static:

- **Total players** — count of distinct players with a Global Score (i.e. at least one recorded lap), all-time.
- **Active in last 30 days** — count of distinct players with at least one lap in the last 30 days. Note this reuses the same "windowed recency" pattern as [most-active-server.md](most-active-server.md)'s recency bonus — worth implementing with a shared helper/query rather than a second copy of "count distinct players active since X."
- **Total records set** — **resolved and implemented (2026-07-06)**: the historical reading, via `App\Models\RecordHistory::events()` — a real, ever-growing count of record-breaking events across every player/map (19 as of this writing), not "how many maps currently have at least one lap." See [roadmap.md](roadmap.md)'s "Number of records set" open question (now resolved) and [decisions.md](decisions.md).
- **Average maps per player** — total (player, map) participations ÷ total players. Reuses the same "distinct (player, map) participation" counting principle already established in [most-active-server.md](most-active-server.md)'s "Valid Laps" definition, for the same reason: it's a meaningful per-player average precisely because it isn't raw lap volume.

## Top 3 podium

Reuses the existing podium visual style already built for the Map Leaderboard (`resources/views/livewire/partials/leaderboard-podium-and-table.blade.php`'s podium section — clipped-corner 1st/2nd/3rd cards) — but showing the **top 3 players by Global Score**, not top 3 lap times on a map. This is a second real use case for "podium of 3" that isn't lap-specific, which is exactly the trigger point (per [coding-standards.md](coding-standards.md)'s "extract on the second genuine duplicate, not before") for pulling the podium markup out into its own reusable partial, independent from the lap leaderboard table it currently lives alongside. Flagged as an implementation-time refactor, not decided/done yet.

## Table columns

| Column | Definition |
|---|---|
| Position | Rank by Global Score, descending. |
| Player | Player name. (No clan/tag — dropped 2026-07-06 when wiring real data; no `tag` column exists in the real schema, see [decisions.md](decisions.md).) |
| Score | Global Score — see [global-ranking.md](global-ranking.md). |
| Records | Records currently held by this player — count of maps where their best lap is the current global course record. Uses the "current state" reading (cheap: compare each of the player's per-map bests against that map's global #1) — **not** the historical reading being considered for the info card's "Total records set" above. These two stats can legitimately use different definitions of "record" (one per-player current state, one system-wide historical count) — don't assume they need to match. |
| Maps | "Maps with PBs" — count of distinct maps this player has a recorded lap on (every map a player has raced necessarily has a personal-best-for-that-player by definition, so this is equivalent to "maps played" / maps contributing to their Global Score, not a stricter subset). |
| Laps | **Added 2026-07-06**, per explicit request to keep the same "# Records, # Laps, # Maps" stat set on every ranked-player display (see [server-single.md](server-single.md)'s Top Players, which shows the identical three). Total real laps (every attempt, any active server) — not deduplicated, a plain count. |
| Active | Last activity date — most recent lap timestamp across all maps/servers for this player. |
| ~~Trend~~ | **Removed from the table (2026-07-06)** when wiring real data — its mechanism (see below) is still undecided, and there's no real signal to show honestly in the meantime. Re-add once decided; see [decisions.md](decisions.md). |

## Open item: Trending indicator

This is the one column that doesn't reduce to "run a query against current data" — a trend is inherently a comparison against some *earlier* state, which sits in tension with the project's broader "fully recalculable from current data, no historical dependency" philosophy (see [database.md](database.md), [global-ranking.md](global-ranking.md)). Two genuinely different implementation paths:

1. **Periodic rank/score snapshots** — a scheduled job stores each player's rank/Global Score at intervals (e.g. daily), and Trend compares current standing to the most recent snapshot (e.g. 7 days back). Precise and literal, but introduces a new stored-history mechanism that nothing else in this project currently has — the ranking *calculation* itself stays stateless/derivable, but the *trend comparison* would depend on point-in-time snapshots existing.
2. **Proxy from recent activity** — approximate "trending up" from recent lap data directly (e.g. "set a new PB on any map in the last 7 days" → up; "no activity in 30+ days" → down/neutral), without needing actual historical rank snapshots. Simpler, stays fully within the derived-reads philosophy, but is a proxy for trend rather than literally "did their rank move."

**Not decided.** Leaning toward option 2 for consistency with the rest of the project's architecture, but this needs a real decision before implementation, not a silent default.

## Open items (summary)

- Trending indicator mechanism (see above) — the main open question on this page.
- ~~"Total records set" (info card) vs. "Records" (table column)~~ — both now implemented and real (2026-07-06), deliberately using different definitions (historical count vs. current-state per-player) — both live on the same page (19 vs. individual per-player counts) and this reads fine in practice, not confusing.
- Podium partial extraction (cosmetic/implementation detail, not a design question).
