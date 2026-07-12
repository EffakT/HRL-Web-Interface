# Glossary

**Player** — the data entity name for a person who has raced (matches the old API's terminology). UI copy displays "Driver" instead in several places (table headers, badges) — this is a display-label choice, not a renaming of the entity. Don't rename the `players` table/model to `drivers`.

**Server** — a Halo dedicated server that has opted into HRL, tracked via lap submissions.

**Map** — a race course. Has one or more **splits/sectors**.

**Split / Sector** — a checkpoint segment of a map's course. Stored per-lap in `lap_time_splits`, keyed by `checkpoint_id`. Split count varies per map — some have as few as 3, some up to 14. Not to be confused with "lap" (a full circuit).

**Lap** / **Lap time** — one full attempt at a map, stored as one row in `lap_times`. History is **never pruned** — every lap ever submitted is kept forever (see [database.md](database.md)).

**Personal Best (PB)** — a player's fastest recorded lap on a given map+server (or map, globally). **Derived** via a `MIN(time)` query — not a stored/upserted row anywhere.

**Course Record** — the fastest lap on a map, either scoped to one server (nested leaderboard) or across all servers (global leaderboard). Also derived, not stored.

**Nested leaderboard** — the server-scoped leaderboard, reached via `/servers/{id}/maps/{id}`. Scoped to laps set on that specific server only.

**Global leaderboard** — the all-servers leaderboard for a map, reached via `/maps/{id}`. Aggregates laps across every server that runs that map.

**Lap Detail** (modal / popup) — the UI surface showing one specific lap's time, gap-to-record, and per-split comparison. Never its own route — always a modal/state layered over whichever leaderboard or player page opened it.

**Split Comparison** — the per-sector table inside Lap Detail, comparing a selected lap's split times against a reference (the map/server record, or the overall record for the player's own lap popup).

**Claim-code system** — the old app's mechanism (`users_players`/`users_servers` tables) letting a registered `user` claim ownership of a `player` profile or `server` via a generated code. Preserved in the schema, not being built on top of currently (see [scope.md](scope.md)).

**HUD** (styleguide shorthand) — the "tactical HUD" dark visual theme: clipped-corner cards, scanline overlays, mono/uppercase labels, green/cyan/gold accent glows. See [architecture.md](architecture.md).

**Mock data** — hardcoded sample arrays standing in for real database queries during the frontend-first build phase. Always commented with a `// TODO: replace ... once backend integration is wired up` note. See [coding-standards.md](coding-standards.md).

**Nested vs. Sparkline vs. Split Pace** — "Nested" refers to routing (server-scoped leaderboard). "Sparkline" / "Split Pace" refers to the podium's compact multi-segment bar visualizing relative split durations — unrelated concepts that sound similar; don't conflate them.

**Global Player Ranking** — a cross-map ranking system distinct from any single map's leaderboard (nested or global) — measures overall player performance across every map, not one. Not to be confused with the **Global leaderboard** (above), which is still scoped to one map. See [global-ranking.md](global-ranking.md).

**Global Score** — a player's total points in the Global Player Ranking: the sum of their per-map Ranking Points across every map they've raced. Not an average.

**Ranking Points** — points awarded to a player for their position on a single map's (global) leaderboard, per the table in [global-ranking.md](global-ranking.md). Summed across maps to produce a player's Global Score.

**Activity Score** — a server's computed engagement score (Unique Players × 10 + Valid Laps × 1 + Maps Played × 20, over a rolling 90-day window), used to determine the "Most Active" featured server. See [most-active-server.md](most-active-server.md). Not to be confused with **Global Score** (above), which is a per-player, not per-server, metric.

**Valid Laps** — this exact phrase means **different things on different pages**, and both are unrelated to any actual anti-cheat check (none exists yet — see [most-active-server.md](most-active-server.md), deferred). On the Most Active Server score ([most-active-server.md](most-active-server.md)), it means "distinct (player, map) participations" — deliberately deduplicated to prevent gaming that *score*. On the Player Single page ([player-single.md](player-single.md)), "Total valid laps" means a **plain raw lap count**, not deduplicated — there's no score being protected there, just a factual stat. Check which doc/context you're in before assuming which definition applies.

**Map Rank** vs. **Best Rank** ([player-single.md](player-single.md)) — "Map Rank" (in Performance by Map) is this player's **global** leaderboard position for a map. "Best Rank" (in Fav Servers) is this player's best **server-scoped/nested** leaderboard position on a given server. Same word "rank," two different leaderboard scopes — don't conflate them.

**Server Score** — the same Ranking Points formula as Global Score, but applied to one server's nested-leaderboard positions and summed only across maps played on that server. Used for the Server Single page's "Top Players" section. See [server-single.md](server-single.md) and [global-ranking.md](global-ranking.md). Not to be confused with **Activity Score** (above), which measures a *server's* engagement, not a *player's* performance on that server.
