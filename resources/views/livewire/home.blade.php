<div class="mx-auto max-w-[1080px] px-6 py-14">

    <div class="mb-3 font-mono text-[11px] font-semibold tracking-[0.34em] text-hud-cyan">// HOME</div>
    <h1 class="max-w-xl text-4xl font-bold uppercase leading-[0.98] text-hud-text-bright tp:text-5xl">
        Halo Race Leaderboard
    </h1>
    <p class="mt-4 max-w-2xl font-mono text-[13px] leading-relaxed text-hud-text-dim">
        Halo Race Leaderboard is a fully public leaderboard that any Halo server can opt in to have track their times.
    </p>

    <!-- Quick Stats: always shown, simple all-time counts -->
    <div class="mt-8">
        <div class="mb-4 font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// QUICK STATS</div>
        <div class="flex flex-wrap gap-2.5">
            <span class="border border-hud-green/16 bg-hud-green/4 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.12em] text-hud-text-muted">{{ number_format($quickStats['players']) }} PLAYERS</span>
            <span class="border border-hud-green/16 bg-hud-green/4 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.12em] text-hud-text-muted">{{ number_format($quickStats['servers']) }} SERVERS</span>
            <span class="border border-hud-green/16 bg-hud-green/4 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.12em] text-hud-text-muted">{{ number_format($quickStats['laps']) }} LAPS</span>
        </div>
    </div>

    <!-- Latest Highlights: up to 3 of 6 candidate blocks, selection logic lives in Home::mount().
         Column count matches however many actually qualified this round — 2 cards split 50/50
         rather than sitting in a 3-column grid with a dangling empty third column. -->
    <div class="mt-12">
        <div class="mb-4 font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// LATEST HIGHLIGHTS</div>
        @php
            $highlightColumns = match (count($highlights)) {
                1 => 'tl:grid-cols-1',
                2 => 'tl:grid-cols-2',
                default => 'tl:grid-cols-3',
            };
        @endphp
        <div class="grid grid-cols-1 gap-5 {{ $highlightColumns }}">
            @foreach ($highlights as $highlight)
                @include('livewire.partials.highlights.' . $highlight['type'], ['data' => $highlight['data']])
            @endforeach
        </div>
    </div>

    <!-- Quick Links: always shown -->
    <div class="mt-6 grid grid-cols-1 gap-5 tp:grid-cols-3">
        <a href="{{ route('servers.index') }}" class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6 transition hover:-translate-y-0.5 hover:border-hud-green/50">
            <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// SERVERS</div>
            <div class="mt-3 text-lg font-bold text-hud-text-bright">Browse Servers</div>
        </a>
        <a href="{{ route('maps.index') }}" class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6 transition hover:-translate-y-0.5 hover:border-hud-green/50">
            <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// MAPS</div>
            <div class="mt-3 text-lg font-bold text-hud-text-bright">Browse Maps</div>
        </a>
        <a href="{{ route('players.index') }}" class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6 transition hover:-translate-y-0.5 hover:border-hud-green/50">
            <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// PLAYERS</div>
            <div class="mt-3 text-lg font-bold text-hud-text-bright">Global Leaderboard</div>
        </a>
    </div>

    <!-- Divider: everything below is static site/project info, not live community data -->
    <div class="mt-16 flex items-center gap-4">
        <span class="h-px flex-1 bg-hud-green/12"></span>
        <span class="font-mono text-[10px] font-semibold tracking-[0.3em] text-hud-text-faint">ABOUT &amp; CHANGELOG</span>
        <span class="h-px flex-1 bg-hud-green/12"></span>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-5 tp:grid-cols-2">

        <div class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
            <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// KNOWN ISSUES</div>
            <ul class="mt-4 space-y-3 font-mono text-[12px] leading-relaxed text-hud-text">
                <li class="flex gap-2"><span class="text-hud-green">▸</span> The final lap per map is not currently recorded</li>
            </ul>
        </div>

        <div class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
            <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// FUTURE PLANS</div>
            <ul class="mt-4 space-y-3 font-mono text-[12px] leading-relaxed text-hud-text">
                <li class="flex gap-2"><span class="text-hud-green">▸</span> Full re-design of the app</li>
                <li class="flex gap-2"><span class="text-hud-green">▸</span> Conversion to a Progressive Web App</li>
                <li class="flex gap-2"><span class="text-hud-green">▸</span> Ability to "grid" a map, on chat "grind", remove the lap limit, on run again, add it back. If player leaves while griding, detect if players still in game, show "grind will end in X, say grind to continue". Othersiwse, clear the grind.</li>
                <li class="flex gap-2"><span class="text-hud-green">▸</span> Enhanced lag detection, particularly ping stability measures (maybe EMA?)</li>
                <li class="flex gap-2"><span class="text-hud-green">▸</span> Server admin lap deletion</li>
                <li class="flex gap-2"><span class="text-hud-green">▸</span> Ability for players to delete their own laps</li>
                <li class="flex gap-2"><span class="text-hud-green">▸</span> Client-side tracking (Chimera, Maybe Optic support via HAC2) - Currently in progress</li>
                <li class="flex gap-2"><span class="text-hud-green">▸</span> Support for Halo Custom Edition - testing process</li>
                <li class="flex gap-2"><span class="text-hud-green">▸</span> Record-break notifications via email, opt-in per server/map - is email the best solution? maybe push notifications via API?</li>
            </ul>
        </div>

    </div>

    <div class="mt-12">
        <div class="mb-4 font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// CHANGELOG</div>
        <div class="hud-clip border border-hud-green/14 bg-hud-panel">
            @foreach ([
                ['date' => '23 FEB 2026', 'note' => 'Lap leaderboard rows now open a details modal with split times and +/- vs the #1 lap'],
                ['date' => '20 DEC 2020', 'note' => 'Notification feature added for server accessibility issues'],
                ['date' => '05 JUL 2020', 'note' => 'Player alias removal and claiming functionality introduced'],
                ['date' => '15 MAR 2020', 'note' => 'Ping threshold adjusted from 200 to 100'],
                ['date' => '17 APR 2020', 'note' => 'Fixed 404 errors on maps and players pages'],
                ['date' => '13 APR 2020', 'note' => 'Developer API launched with token generation in profiles'],
                ['date' => '12 APR 2020', 'note' => 'Last lap time added to servers page'],
                ['date' => '10 APR 2020', 'note' => 'Lap time migration date copying corrected'],
                ['date' => '04 APR 2020', 'note' => 'User creation, server management, and ping spike detection added'],
                ['date' => '30 SEP 2018', 'note' => 'Gameservers.com notes, encryption moved to web server, player leaderboards added'],
                ['date' => '23 SEP 2018', 'note' => 'Download link fixed'],
                ['date' => '03 MAR 2018', 'note' => 'Recording lap times without vehicles enabled'],
                ['date' => '25 FEB 2018', 'note' => 'Special character handling in player names resolved'],
            ] as $entry)
                <div class="grid grid-cols-[100px_1fr] gap-4 border-b border-hud-green/8 px-5 py-3.5 last:border-b-0 tp:grid-cols-[130px_1fr]">
                    <span class="font-mono text-[10px] font-semibold tracking-[0.08em] text-hud-text-dim">{{ $entry['date'] }}</span>
                    <span class="font-mono text-[12px] leading-relaxed text-hud-text">{{ $entry['note'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

</div>
