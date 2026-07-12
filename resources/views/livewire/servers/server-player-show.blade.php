<div>
    <div class="mx-auto max-w-[1080px] px-6 py-14">

        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <div class="mb-3 font-mono text-[11px] font-semibold tracking-[0.34em] text-hud-cyan">// {{ $serverName }} <span class="text-hud-text-faint">({{ $serverIp }}:{{ $serverPort }})</span></div>
                <h1 class="text-4xl font-bold uppercase leading-[0.98] text-hud-text-bright tp:text-5xl">{{ $playerName }}</h1>
            </div>
            <a href="{{ route('players.show', ['playerId' => $playerId]) }}"
               class="font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-cyan transition hover:text-hud-green">
                VIEW GLOBAL PROFILE ›
            </a>
        </div>

        <div class="mt-4 flex flex-wrap gap-2.5">
            <span class="border border-hud-green/32 bg-hud-green/8 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-green">SERVER RANK #{{ $playerInfo['serverRank'] }}</span>
            <span class="border border-hud-green/32 bg-hud-green/8 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-green">{{ number_format($playerInfo['serverScore']) }} SERVER SCORE</span>
            <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">GLOBAL RANK #{{ $playerInfo['globalRank'] }}</span>
            <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ number_format($playerInfo['globalScore']) }} GLOBAL SCORE</span>
        </div>

        <!-- Stats Card (isolated to this server — see App\Models\PlayerProfile) -->
        <div class="mt-10">
            <div class="mb-4 font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// STATS ON THIS SERVER</div>
            <div class="flex flex-wrap gap-2.5">
                <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ $statsCard['numRecords'] }} RECORDS</span>
                <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ $statsCard['top3Finishes'] }} TOP 3 FINISHES</span>
                <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ $statsCard['mapsCompleted'] }} MAPS COMPLETED</span>
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

        <!-- Performance by Map (this server only) -->
        <div class="mt-10">
            <div class="mb-4 font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// PERFORMANCE BY MAP</div>

            <!-- desktop table -->
            <div class="hidden tp:block">
                <div class="grid grid-cols-[1fr_120px_90px_80px] gap-3 border-b border-hud-green/16 px-3.5 py-2.5 font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">
                    <span>MAP</span><span class="text-right">PB</span><span class="text-right">MAP RANK</span><span class="text-right">POINTS</span>
                </div>
                @foreach ($performanceKeys as $key)
                    @php $lap = $laps[$key]; @endphp
                    <button type="button" wire:click="openLap({{ $key }})"
                            class="grid w-full grid-cols-[1fr_120px_90px_80px] items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 text-left transition hover:border-l-hud-green hover:bg-hud-green/7">
                        <span class="text-sm font-semibold tracking-[0.02em] text-hud-text">{{ $lap['map'] }}</span>
                        <span class="text-right font-mono text-[14px] font-bold text-hud-text-bright">{{ $lap['time'] }}</span>
                        <span class="text-right font-mono text-[13px] font-bold text-hud-text">#{{ $lap['mapRank'] }}</span>
                        <span class="text-right font-mono text-[12px] text-hud-text-dim">{{ $lap['points'] }}</span>
                    </button>
                @endforeach
            </div>

            <!-- mobile list -->
            <div class="tp:hidden">
                @foreach ($performanceKeys as $key)
                    @php $lap = $laps[$key]; @endphp
                    <button type="button" wire:click="openLap({{ $key }})"
                            class="w-full border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 text-left transition hover:border-l-hud-green hover:bg-hud-green/7">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-hud-text">{{ $lap['map'] }}</span>
                            <span class="font-mono text-sm font-bold text-hud-text-bright">{{ $lap['time'] }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between font-mono text-[9px] text-hud-text-dim">
                            <span>RANK #{{ $lap['mapRank'] }}</span>
                            <span>{{ $lap['points'] }} PTS</span>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Recent Laps: this player's actual last attempts on this server, reverse-chronological
             — a genuinely distinct dataset from Performance by Map above, not the same rows
             reordered (see App\Models\PlayerProfile). Addresses into the same shared $laps array
             via $recentLapKeys so the Lap Detail modal opens the right row regardless of which
             table triggered it. -->
        <div class="mt-10">
            <div class="mb-4 font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// RECENT LAPS</div>

            <!-- desktop table -->
            <div class="hidden tp:block">
                <div class="grid grid-cols-[1fr_130px_120px] gap-3 border-b border-hud-green/16 px-3.5 py-2.5 font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">
                    <span>MAP</span><span class="text-right">TIME</span><span class="text-right">DATE</span>
                </div>
                @foreach ($recentLapKeys as $key)
                    @php $lap = $laps[$key]; @endphp
                    <button type="button" wire:click="openLap({{ $key }})"
                            class="grid w-full grid-cols-[1fr_130px_120px] items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 text-left transition hover:border-l-hud-green hover:bg-hud-green/7">
                        <span class="truncate text-sm font-semibold tracking-[0.02em] text-hud-text">{{ $lap['map'] }}</span>
                        <span class="text-right font-mono text-[14px] font-bold text-hud-text-bright">{{ $lap['time'] }}</span>
                        <span class="text-right font-mono text-[10px] text-hud-text-dim">{{ $lap['date'] }}</span>
                    </button>
                @endforeach
            </div>

            <!-- mobile list -->
            <div class="tp:hidden">
                @foreach ($recentLapKeys as $key)
                    @php $lap = $laps[$key]; @endphp
                    <button type="button" wire:click="openLap({{ $key }})"
                            class="w-full border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 text-left transition hover:border-l-hud-green hover:bg-hud-green/7">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-hud-text">{{ $lap['map'] }}</span>
                            <span class="font-mono text-sm font-bold text-hud-text-bright">{{ $lap['time'] }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between font-mono text-[9px] text-hud-text-dim">
                            <span>{{ $lap['date'] }}</span>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

    </div>

    @include('livewire.partials.lap-vs-record-modal')
</div>
