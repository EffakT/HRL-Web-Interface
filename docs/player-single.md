# Player Single Page

The page showing one player's full profile (`/players/{playerId}`, `App\Livewire\Players\PlayerShow` — see [architecture.md](architecture.md)). **Status: built (mock data).** All sections below are live. See [decisions.md](decisions.md).

## Player Info (header)

- **Player Name** (+ tag) — already shown.
- **Global Rank** — this player's position in the Global Leaderboard, i.e. rank by Global Score. See [players-list.md](players-list.md).
- **Current Score** — Global Score. See [global-ranking.md](global-ranking.md).

## Stats Card

- **Num Records** — same "Records" definition as [players-list.md](players-list.md): count of maps where this player's best lap is the current global course record (current-state reading, not the historical one — see the still-open "number of records set" question in [server-single.md](server-single.md) and [roadmap.md](roadmap.md), which is about a *different* stat).
- **Top 3 finishes** — direct reuse of an already-defined concept: this is literally tie-break rule 2 from [global-ranking.md](global-ranking.md) ("most top-3 finishes across all maps"), surfaced here as a visible stat rather than a hidden tiebreaker input.
- **Maps completed** — count of distinct maps this player has a recorded lap on. Same underlying count as [players-list.md](players-list.md)'s "Maps with PBs" column — different label, same definition, don't implement twice.
- **Servers Played** — count of distinct servers this player has a recorded lap on.
- **Total valid laps** — **terminology clash to be aware of**: "Valid Laps" was redefined in [most-active-server.md](most-active-server.md) to mean "distinct (player, map) participations" specifically to prevent gaming a *score*. That redefinition doesn't apply here — there's no score being protected by capping this number, so **"Total valid laps" on this page means the plain raw lap count** for this player, all servers, all-time (same "raw count, not deduplicated" precedent as [server-single.md](server-single.md)'s "Total Laps" stat). "Valid" itself still means "any recorded lap" pending the deferred anti-cheat mechanism (see [most-active-server.md](most-active-server.md)) — not a new validity concept. See the glossary entry for this same warning.
- **First Seen / Last Active** — this player's earliest and most recent lap timestamps, derived purely from `lap_times` (not tied to any account/claim-code creation date — that system is out of scope, see [scope.md](scope.md)).

## Best Performance

**Decided: curated achievements list (option 2)**, not raw top-3 laps (option 1) — option 1 would have the same cross-map raw-time-comparison problem that got Server Single's "Top 3 Fastest Laps" removed (see [decisions.md](decisions.md)); a player's fastest lap on a 60s map and their fastest lap on a 120s map aren't comparable any more than two different servers' were. Shows a short list of notable achievements (records held, strong Map Rank finishes, standout maps) — editorial rather than a simple top-N query. Mock content only for now; a real "biggest achievements" selection algorithm isn't designed.

## Performance by Map

Every map this player has raced, one row each:

| Column | Definition |
|---|---|
| Map | Map name. |
| PB | This player's personal best on that map (global scope — their single best lap across any server for that map). |
| Map Rank | This player's position on that map's **global** leaderboard (not a server-scoped/nested rank — matches Points below, which is derived from this same global position). |
| Points | This player's Ranking Points contribution to their Global Score from this map — i.e. the points value for their Map Rank, per the table in [global-ranking.md](global-ranking.md). |
| Server | The specific server on which this player's PB lap was actually recorded (`lap_times.server_id` for that lap) — useful context since the PB itself is a global-scope figure but was necessarily set on one particular server. |

## Fav[orite] Servers

Servers this player races on most, one row each:

| Column | Definition |
|---|---|
| Server | Server name. |
| Laps | Raw lap count for this player on that server (same "plain count" convention as "Total valid laps" above). |
| Best Rank | This player's best (numerically lowest) **nested/server-scoped** leaderboard position achieved on *any* map on that server — this one *is* server-scoped, unlike "Map Rank" above, since it's specifically about standing on that particular server. |

**Assumption, not explicitly specified**: "favorite" = sorted by lap count descending (most-raced servers first) — the natural proxy given we track laps, not session time/duration.

## Recent Laps

A **limited, reverse-chronological feed** of this player's most recent laps — deliberately not the same thing as [server-single.md](server-single.md)'s "All Laps" table (which is a full, paginated, unbounded history for one server). "Recent" implies a feed (e.g. last 10–20), not full history. A full "all laps for this player" view isn't requested here — flagged as a gap if it turns out to be wanted later.

**Implementation note**: rows here (and plausibly "Best Performance" if it goes with option 1 above) should open the **existing** Lap Detail popup already built on this page (`HasLapDetailModal`, split comparison vs. map record) — this page already has that interaction working; new lap-listing sections should plug into it, not build a second lap-detail mechanism.

## Sequencing

Like [players-list.md](players-list.md), most of this page (Global Rank/Score, Points, Map Rank) depends on [global-ranking.md](global-ranking.md) being implemented first — see [roadmap.md](roadmap.md).

## Open items

- Whether a full (not just "recent") lap-history view for one player is ever wanted — not requested, just flagging the gap.
- "Fav[orite] Servers" sort definition (assumed: by lap count) — confirm.
- A real "biggest achievements" selection algorithm for Best Performance — mock content only today, no design yet for how this would be generated from real data.
- "Recent Laps" currently reuses the same one-row-per-map array as "Performance by Map" for mock purposes (see [decisions.md](decisions.md)) — at real scale these need to become genuinely distinct queries (one row per lap submission, reverse-chronological, vs. one row per map's PB).
