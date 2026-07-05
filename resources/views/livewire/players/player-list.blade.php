<div class="mx-auto max-w-[1080px] px-6 py-14">

    <div class="mb-3 font-mono text-[11px] font-semibold tracking-[0.34em] text-hud-cyan">// PLAYERS</div>
    <h1 class="text-4xl font-bold uppercase leading-[0.98] text-hud-text-bright tp:text-5xl">Global Leaderboard</h1>
    <div class="mt-4 flex flex-wrap gap-2.5">
        <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ number_format($stats['totalPlayers']) }} PLAYERS</span>
        <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ number_format($stats['active30d']) }} ACTIVE 30D</span>
        <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ number_format($stats['recordsSet']) }} RECORDS SET</span>
        <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ $stats['avgMapsPerPlayer'] }} AVG MAPS/PLAYER</span>
    </div>
    <p class="mt-4 max-w-2xl font-mono text-[13px] leading-relaxed text-hud-text-dim">
        Ranked by Global Score — consistency and breadth across maps beat raw lap volume.
    </p>

    <!-- Top 3 -->
    <div class="mt-10">
        <div class="mb-4 font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// TOP 3</div>
        @include('livewire.partials.podium', ['podium' => $podium])
    </div>

    <!-- Global Leaderboard table (rank 4+) -->
    <div class="mt-10">
        <!-- desktop table -->
        <div class="hidden tp:block">
            <div class="grid grid-cols-[52px_1fr_90px_90px_70px_100px_90px] gap-3 border-b border-hud-green/16 px-3.5 py-2.5 font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">
                <span>#</span><span>PLAYER</span><span class="text-right">SCORE</span><span class="text-right">RECORDS</span><span class="text-right">MAPS</span><span class="text-right">ACTIVE</span><span class="text-right">TREND</span>
            </div>
            @foreach ($rest as $player)
                <a href="{{ route('players.show', ['playerId' => $player['id']]) }}"
                   class="grid grid-cols-[52px_1fr_90px_90px_70px_100px_90px] items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 transition hover:border-l-hud-green hover:bg-hud-green/7">
                    <span class="text-[15px] font-bold text-hud-text-dim">{{ $player['rank'] }}</span>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-hud-text">{{ $player['name'] }}</div>
                        <div class="mt-px font-mono text-[9px] text-hud-text-dim">{{ $player['tag'] }}</div>
                    </div>
                    <span class="text-right font-mono text-[14px] font-bold text-hud-text-bright">{{ $player['score'] }}</span>
                    <span class="text-right font-mono text-[12px] text-hud-text-dim">{{ $player['records'] }}</span>
                    <span class="text-right font-mono text-[12px] text-hud-text-dim">{{ $player['maps'] }}</span>
                    <span class="text-right font-mono text-[10px] text-hud-text-dim">{{ strtoupper($player['active']) }}</span>
                    <span class="text-right">@include('livewire.partials.trend-indicator', ['direction' => $player['trendDirection'], 'delta' => $player['trendDelta']])</span>
                </a>
            @endforeach
            <div class="flex items-center justify-between px-3.5 py-4 font-mono text-[10px] tracking-[0.1em] text-hud-text-dim">
                <span>SHOWING {{ count($rest) + count($podium) }} / {{ $stats['totalPlayers'] }} PLAYERS</span>
            </div>
        </div>

        <!-- mobile list -->
        <div class="tp:hidden">
            @foreach ($rest as $player)
                <a href="{{ route('players.show', ['playerId' => $player['id']]) }}"
                   class="flex items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 transition hover:border-l-hud-green hover:bg-hud-green/7">
                    <span class="w-6.5 flex-none text-[15px] font-bold text-hud-text-dim">{{ $player['rank'] }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold text-hud-text">{{ $player['name'] }}</div>
                        <div class="mt-0.5 font-mono text-[9px] text-hud-text-dim">{{ $player['records'] }} RECORDS · {{ $player['maps'] }} MAPS</div>
                    </div>
                    <div class="flex-none text-right">
                        <div class="font-mono text-sm font-bold text-hud-text-bright">{{ $player['score'] }}</div>
                        @include('livewire.partials.trend-indicator', ['direction' => $player['trendDirection'], 'delta' => $player['trendDelta']])
                    </div>
                </a>
            @endforeach
        </div>
    </div>

</div>
