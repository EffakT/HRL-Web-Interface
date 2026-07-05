<div>
    <div class="mx-auto max-w-[1080px] px-6 py-14">

        <div class="mb-3 font-mono text-[11px] font-semibold tracking-[0.34em] text-hud-cyan">// PLAYER</div>
        <div class="flex items-baseline gap-3">
            <h1 class="text-4xl font-bold uppercase leading-[0.98] text-hud-text-bright tp:text-5xl">{{ $playerName }}</h1>
            <span class="font-mono text-[13px] text-hud-text-dim">{{ $playerTag }}</span>
        </div>
        <div class="mt-4 flex flex-wrap gap-2.5">
            <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">GLOBAL RANK #{{ $playerInfo['globalRank'] }}</span>
            <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ number_format($playerInfo['globalScore']) }} SCORE</span>
        </div>

        <!-- Stats Card -->
        <div class="mt-10">
            <div class="mb-4 font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// STATS</div>
            <div class="flex flex-wrap gap-2.5">
                <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ $statsCard['numRecords'] }} RECORDS</span>
                <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ $statsCard['top3Finishes'] }} TOP 3 FINISHES</span>
                <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ $statsCard['mapsCompleted'] }} MAPS COMPLETED</span>
                <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ $statsCard['serversPlayed'] }} SERVERS PLAYED</span>
                <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ number_format($statsCard['totalValidLaps']) }} TOTAL LAPS</span>
                <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">FIRST SEEN {{ $statsCard['firstSeen'] }}</span>
                <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">LAST ACTIVE {{ strtoupper($statsCard['lastActive']) }}</span>
            </div>
        </div>

        <!-- Best Performance (curated achievements, not raw top-3 laps — see docs/decisions.md) -->
        <div class="mt-10">
            <div class="mb-4 font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// BEST PERFORMANCE</div>
            <div class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
                <ul class="space-y-3 font-mono text-[12px] leading-relaxed text-hud-text">
                    @foreach ($achievements as $achievement)
                        <li class="flex gap-2"><span class="text-hud-green">▸</span> {{ $achievement }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <!-- Performance by Map -->
        <div class="mt-10">
            <div class="mb-4 font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// PERFORMANCE BY MAP</div>

            <!-- desktop table -->
            <div class="hidden tp:block">
                <div class="grid grid-cols-[1fr_120px_90px_80px_1fr] gap-3 border-b border-hud-green/16 px-3.5 py-2.5 font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">
                    <span>MAP</span><span class="text-right">PB</span><span class="text-right">MAP RANK</span><span class="text-right">POINTS</span><span>SERVER</span>
                </div>
                @foreach ($laps as $index => $lap)
                    <button type="button" wire:click="openLap({{ $index }})"
                            class="grid w-full grid-cols-[1fr_120px_90px_80px_1fr] items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 text-left transition hover:border-l-hud-green hover:bg-hud-green/7">
                        <span class="text-sm font-semibold tracking-[0.02em] text-hud-text">{{ $lap['map'] }}</span>
                        <span class="text-right font-mono text-[14px] font-bold text-hud-text-bright">{{ $lap['time'] }}</span>
                        <span class="text-right font-mono text-[13px] font-bold text-hud-text">#{{ $lap['mapRank'] }}</span>
                        <span class="text-right font-mono text-[12px] text-hud-text-dim">{{ $lap['points'] }}</span>
                        <span class="truncate font-mono text-[11px] text-hud-text-dim">{{ $lap['server'] }}</span>
                    </button>
                @endforeach
            </div>

            <!-- mobile list -->
            <div class="tp:hidden">
                @foreach ($laps as $index => $lap)
                    <button type="button" wire:click="openLap({{ $index }})"
                            class="w-full border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 text-left transition hover:border-l-hud-green hover:bg-hud-green/7">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-hud-text">{{ $lap['map'] }}</span>
                            <span class="font-mono text-sm font-bold text-hud-text-bright">{{ $lap['time'] }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between font-mono text-[9px] text-hud-text-dim">
                            <span>{{ $lap['server'] }}</span>
                            <span>RANK #{{ $lap['mapRank'] }} · {{ $lap['points'] }} PTS</span>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Fav Servers -->
        <div class="mt-10">
            <div class="mb-4 font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// FAV SERVERS</div>
            <div class="hidden tp:block">
                <div class="grid grid-cols-[1fr_120px_120px] gap-3 border-b border-hud-green/16 px-3.5 py-2.5 font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">
                    <span>SERVER</span><span class="text-right">LAPS</span><span class="text-right">BEST RANK</span>
                </div>
                @foreach ($favServers as $server)
                    <a href="{{ route('servers.show', ['serverId' => $server['serverId']]) }}"
                       class="grid grid-cols-[1fr_120px_120px] items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 transition hover:border-l-hud-green hover:bg-hud-green/7">
                        <span class="truncate text-sm font-semibold text-hud-text">{{ $server['server'] }}</span>
                        <span class="text-right font-mono text-[13px] font-bold text-hud-text">{{ number_format($server['laps']) }}</span>
                        <span class="text-right font-mono text-[13px] font-bold text-hud-text-bright">#{{ $server['bestRank'] }}</span>
                    </a>
                @endforeach
            </div>
            <div class="tp:hidden">
                @foreach ($favServers as $server)
                    <a href="{{ route('servers.show', ['serverId' => $server['serverId']]) }}"
                       class="flex items-center justify-between gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 transition hover:border-l-hud-green hover:bg-hud-green/7">
                        <span class="min-w-0 flex-1 truncate text-sm font-semibold text-hud-text">{{ $server['server'] }}</span>
                        <div class="flex-none text-right">
                            <div class="font-mono text-sm font-bold text-hud-text-bright">#{{ $server['bestRank'] }}</div>
                            <div class="mt-0.5 font-mono text-[9px] text-hud-text-dim">{{ number_format($server['laps']) }} LAPS</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Recent Laps: bounded feed, reuses the same array/index as Performance by Map above
             (see PlayerShow::mount() note — at real scale this would be a distinct, larger,
             chronologically-sorted dataset rather than one row per map). -->
        <div class="mt-10">
            <div class="mb-4 font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// RECENT LAPS</div>

            <!-- desktop table -->
            <div class="hidden tp:block">
                <div class="grid grid-cols-[1fr_1fr_130px_120px] gap-3 border-b border-hud-green/16 px-3.5 py-2.5 font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">
                    <span>MAP</span><span>SERVER</span><span class="text-right">TIME</span><span class="text-right">DATE</span>
                </div>
                @foreach ($laps as $index => $lap)
                    <button type="button" wire:click="openLap({{ $index }})"
                            class="grid w-full grid-cols-[1fr_1fr_130px_120px] items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 text-left transition hover:border-l-hud-green hover:bg-hud-green/7">
                        <span class="truncate text-sm font-semibold tracking-[0.02em] text-hud-text">{{ $lap['map'] }}</span>
                        <span class="truncate font-mono text-[11px] text-hud-text-dim">{{ $lap['server'] }}</span>
                        <span class="text-right font-mono text-[14px] font-bold text-hud-text-bright">{{ $lap['time'] }}</span>
                        <span class="text-right font-mono text-[10px] text-hud-text-dim">{{ $lap['date'] }}</span>
                    </button>
                @endforeach
            </div>

            <!-- mobile list -->
            <div class="tp:hidden">
                @foreach ($laps as $index => $lap)
                    <button type="button" wire:click="openLap({{ $index }})"
                            class="w-full border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 text-left transition hover:border-l-hud-green hover:bg-hud-green/7">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-hud-text">{{ $lap['map'] }}</span>
                            <span class="font-mono text-sm font-bold text-hud-text-bright">{{ $lap['time'] }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between font-mono text-[9px] text-hud-text-dim">
                            <span>{{ $lap['server'] }}</span>
                            <span>{{ $lap['date'] }}</span>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

    </div>

    @include('livewire.partials.lap-vs-record-modal')
</div>
