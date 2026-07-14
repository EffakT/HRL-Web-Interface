<x-layout title="API" active="api-docs" description="Public read API reference for the Halo Race Leaderboard.">
    <div class="mx-auto max-w-[1080px] px-6 py-14">

        <div class="mb-3 font-mono text-[11px] font-semibold tracking-[0.34em] text-hud-cyan">// API</div>
        <h1 class="max-w-xl text-4xl font-bold uppercase leading-[0.98] text-hud-text-bright tp:text-5xl">
            API Reference
        </h1>
        <p class="mt-4 max-w-2xl font-mono text-[13px] leading-relaxed text-hud-text-dim">
            A public, read-only, versioned API over the same data this site shows — no account, no API key.
        </p>

        <div class="mt-8 flex flex-wrap gap-2.5">
            <span class="border border-hud-green/16 bg-hud-green/4 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.12em] text-hud-text-muted">NO AUTH REQUIRED</span>
            <span class="border border-hud-green/16 bg-hud-green/4 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.12em] text-hud-text-muted">60 REQ/MIN PER IP</span>
            <span class="border border-hud-green/16 bg-hud-green/4 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.12em] text-hud-text-muted">JSON</span>
            <span class="border border-hud-green/16 bg-hud-green/4 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.12em] text-hud-text-muted">/API/V1</span>
        </div>

        <div class="mt-8 hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-5">
            <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// BASE URL</div>
            <div class="mt-2 font-mono text-[13px] font-semibold text-hud-text-bright">{{ url('/api/v1') }}</div>
        </div>

        <!-- Endpoints -->
        <div class="mt-14">
            <div class="mb-4 font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// ENDPOINTS</div>

            <div class="space-y-5">

                <div class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 font-mono text-[13px] font-bold">
                        <span class="text-hud-green">GET</span>
                        <span class="text-hud-text-bright">/servers</span>
                    </div>
                    <p class="mt-3 font-mono text-[12px] leading-relaxed text-hud-text">
                        Every active (non-archived) server, with real derived stats.
                    </p>

                    <x-code-block label="QUERY PARAMETERS">page        int   optional, default 1
per_page    int   optional, default 50, capped at 100</x-code-block>

                    <x-code-block label="EXAMPLE REQUEST">GET /api/v1/servers</x-code-block>

                    <x-code-block label="EXAMPLE RESPONSE">{
  "data": [
    {
      "id": 7,
      "name": "EffakT's Server",
      "total_laps": 342,
      "total_players": 58,
      "maps_played": 6,
      "last_active_at": "2026-07-14T09:12:03+00:00"
    }
  ],
  "links": { "first": "…?page=1", "last": "…?page=1", "prev": null, "next": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 50, "total": 1 }
}</x-code-block>
                </div>

                <div class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 font-mono text-[13px] font-bold">
                        <span class="text-hud-green">GET</span>
                        <span class="text-hud-text-bright">/maps</span>
                    </div>
                    <p class="mt-3 font-mono text-[12px] leading-relaxed text-hud-text">
                        Every map, paginated. Useful for discovering a map's <code class="rounded-sm bg-hud-green/8 px-1 py-0.5 text-hud-text-bright">id</code>/<code class="rounded-sm bg-hud-green/8 px-1 py-0.5 text-hud-text-bright">name</code> before calling the leaderboard endpoint below.
                    </p>

                    <x-code-block label="QUERY PARAMETERS">page        int   optional, default 1
per_page    int   optional, default 50, capped at 100</x-code-block>

                    <x-code-block label="EXAMPLE REQUEST">GET /api/v1/maps?per_page=2</x-code-block>

                    <x-code-block label="EXAMPLE RESPONSE">{
  "data": [
    { "id": 1, "name": "bloodgulch", "label": "Blood Gulch", "checkpoint_count": 5, "total_laps": 342 },
    { "id": 2, "name": "dangercanyon", "label": "Danger Canyon", "checkpoint_count": 6, "total_laps": 118 }
  ],
  "links": { "first": "…?page=1", "last": "…?page=7", "prev": null, "next": "…?page=2" },
  "meta": { "current_page": 1, "last_page": 7, "per_page": 2, "total": 14 }
}</x-code-block>
                </div>

                <div class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 font-mono text-[13px] font-bold">
                        <span class="text-hud-green">GET</span>
                        <span class="text-hud-text-bright">/maps/{map}/leaderboard</span>
                    </div>
                    <p class="mt-3 font-mono text-[12px] leading-relaxed text-hud-text">
                        The global leaderboard for one map — every player's single best lap across all active servers, ranked (earliest lap wins a tie).
                        <code class="rounded-sm bg-hud-green/8 px-1 py-0.5 text-hud-text-bright">{map}</code> accepts either the numeric <code class="rounded-sm bg-hud-green/8 px-1 py-0.5 text-hud-text-bright">id</code> or the map's real <code class="rounded-sm bg-hud-green/8 px-1 py-0.5 text-hud-text-bright">name</code> (e.g. <code class="rounded-sm bg-hud-green/8 px-1 py-0.5 text-hud-text-bright">bloodgulch</code>).
                    </p>

                    <x-code-block label="QUERY PARAMETERS">server      int   optional — scope to one server's nested leaderboard instead of the global one
port        int   optional — identify yourself as a game server by ip:port (your request's own
                  IP, plus this port); resolves to your own nested leaderboard and takes
                  precedence over `server`. 404s if no registered server matches
page        int   optional, default 1
per_page    int   optional, default 50, capped at 100</x-code-block>

                    <x-code-block label="EXAMPLE REQUEST">GET /api/v1/maps/bloodgulch/leaderboard
GET /api/v1/maps/bloodgulch/leaderboard?server=7
GET /api/v1/maps/bloodgulch/leaderboard?port=2302</x-code-block>

                    <x-code-block label="EXAMPLE RESPONSE">{
  "data": [
    {
      "rank": 1,
      "lap_id": 4821,
      "player": { "id": 12, "name": "EffakT" },
      "server": { "id": 7, "name": "EffakT's Server" },
      "time": 68.066666666667,
      "time_formatted": "1:08.07",
      "gap": 0,
      "set_at": "2026-07-09T20:14:05+00:00",
      "splits": []
    }
  ],
  "links": { "first": "…?page=1", "last": "…?page=1", "prev": null, "next": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 50, "total": 1 }
}</x-code-block>
                </div>

                <div class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 font-mono text-[13px] font-bold">
                        <span class="text-hud-green">GET</span>
                        <span class="text-hud-text-bright">/players</span>
                    </div>
                    <p class="mt-3 font-mono text-[12px] leading-relaxed text-hud-text">
                        The Global Leaderboard — every player with at least one real lap, ranked by Global Score. Same data as the
                        <a href="{{ route('players.index') }}" class="font-semibold text-hud-text-bright underline decoration-hud-green/50 underline-offset-2 transition hover:decoration-hud-green">Players List</a>
                        page.
                    </p>

                    <x-code-block label="QUERY PARAMETERS">page        int   optional, default 1
per_page    int   optional, default 50, capped at 100</x-code-block>

                    <x-code-block label="EXAMPLE REQUEST">GET /api/v1/players?per_page=2</x-code-block>

                    <x-code-block label="EXAMPLE RESPONSE">{
  "data": [
    {
      "id": 12,
      "rank": 1,
      "name": "EffakT",
      "score": 1845,
      "records": 6,
      "maps_played": 11,
      "total_laps": 342,
      "last_active_at": "2026-07-14T09:12:03+00:00"
    },
    {
      "id": 31,
      "rank": 2,
      "name": "RunnerUp",
      "score": 1720,
      "records": 2,
      "maps_played": 10,
      "total_laps": 210,
      "last_active_at": "2026-07-13T22:40:11+00:00"
    }
  ],
  "links": { "first": "…?page=1", "last": "…?page=44", "prev": null, "next": "…?page=2" },
  "meta": { "current_page": 1, "last_page": 44, "per_page": 2, "total": 87 }
}</x-code-block>
                </div>

                <div class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 font-mono text-[13px] font-bold">
                        <span class="text-hud-green">GET</span>
                        <span class="text-hud-text-bright">/laps/{lapTime}</span>
                    </div>
                    <p class="mt-3 font-mono text-[12px] leading-relaxed text-hud-text">
                        One specific submitted lap's full detail, by its <code class="rounded-sm bg-hud-green/8 px-1 py-0.5 text-hud-text-bright">id</code>. Not scoped to active servers — a lap's historical existence doesn't depend on whether its server was later archived.
                    </p>

                    <x-code-block label="EXAMPLE REQUEST">GET /api/v1/laps/4821</x-code-block>

                    <x-code-block label="EXAMPLE RESPONSE">{
  "data": {
    "id": 4821,
    "time": 68.066666666667,
    "time_formatted": "1:08.07",
    "player": { "id": 12, "name": "EffakT" },
    "map": { "id": 1, "label": "Blood Gulch" },
    "server": { "id": 7, "name": "EffakT's Server" },
    "set_at": "2026-07-09T20:14:05+00:00",
    "splits": [
      { "checkpoint_id": 1, "duration": 9.3 },
      { "checkpoint_id": 2, "duration": 12.4 }
    ]
  }
}</x-code-block>
                </div>

                <div class="hud-clip border border-hud-gold/22 bg-gradient-to-b from-[#171410] to-[#0e0b08] px-6 py-6">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 font-mono text-[13px] font-bold">
                        <span class="text-hud-gold">POST</span>
                        <span class="text-hud-text-bright">/laps</span>
                    </div>
                    <p class="mt-3 font-mono text-[12px] leading-relaxed text-[#e6ddc9]">
                        The lap-submission webhook — how a Halo game server reports a completed lap. Not intended for general use; if you're setting up a server to report to HRL, see the
                        <a href="{{ route('opt-in') }}" class="font-semibold text-[#f6eede] underline decoration-hud-gold/50 underline-offset-2 transition hover:decoration-hud-gold">Opt-In guide</a>
                        instead — this endpoint's request/response shape is tied to the HRL Lua script, not documented here.
                    </p>
                </div>

            </div>
        </div>

        <!-- Auth & rate limiting -->
        <div class="mt-8 grid grid-cols-1 gap-5 tp:grid-cols-2">

            <div class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
                <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// AUTH</div>
                <p class="mt-3 font-mono text-[12px] leading-relaxed text-hud-text">
                    None. HRL is already a fully public leaderboard with no login system, so the read endpoints above expose nothing the site itself doesn't already show.
                </p>
            </div>

            <div class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
                <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// RATE LIMITING</div>
                <p class="mt-3 font-mono text-[12px] leading-relaxed text-hud-text">
                    60 requests/minute per IP on the read endpoints above.
                </p>
            </div>

        </div>

        <!-- Errors -->
        <div class="mt-6 hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
            <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// ERRORS</div>
            <p class="mt-3 font-mono text-[12px] leading-relaxed text-hud-text">
                The requested map, lap, or server (via <code class="rounded-sm bg-hud-green/8 px-1 py-0.5 text-hud-text-bright">?port=</code>) doesn't exist:
            </p>
            <x-code-block>HTTP/1.1 404 Not Found

{
  "message": "No query results for map bloodgulch2"
}</x-code-block>

            <p class="mt-4 font-mono text-[12px] leading-relaxed text-hud-text">Rate limit exceeded:</p>
            <x-code-block>HTTP/1.1 429 Too Many Requests

{
  "message": "Too Many Attempts."
}</x-code-block>
        </div>

    </div>
</x-layout>
