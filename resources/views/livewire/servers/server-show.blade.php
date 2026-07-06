<div>
<div class="mx-auto max-w-[1080px] px-6 py-14">

    <div class="mb-3 font-mono text-[11px] font-semibold tracking-[0.34em] text-hud-cyan">// SERVER</div>
    <h1 class="text-4xl font-bold uppercase leading-[0.98] text-hud-text-bright tp:text-5xl">{{ $serverName }}</h1>
    <div class="mt-4 flex flex-wrap gap-2.5">
        <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ number_format($stats['totalLaps']) }} LAPS</span>
        <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ number_format($stats['laps30d']) }} LAPS · 30D</span>
        <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ number_format($stats['laps90d']) }} LAPS · 90D</span>
        <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ number_format($stats['totalPlayers']) }} PLAYERS</span>
        <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ number_format($stats['activePlayers30d']) }} ACTIVE · 30D</span>
        <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ number_format($stats['activePlayers90d']) }} ACTIVE · 90D</span>
    </div>

    <!-- Top Players (Server Score) -->
    <div class="mt-10">
        <div class="mb-4 font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// TOP PLAYERS · SERVER SCORE</div>
        @include('livewire.partials.podium', ['podium' => $topPlayers])

        <!-- Ranked table (rank 4+), same structure as the Global Leaderboard, scoped to this server -->
        @if (count($players) > 3)
            <div class="mt-8 hidden tp:block">
                <div class="grid grid-cols-[52px_1fr_90px_90px_70px_70px] gap-3 border-b border-hud-green/16 px-3.5 py-2.5 font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">
                    <span>#</span><span>PLAYER</span><span class="text-right">SCORE</span><span class="text-right">RECORDS</span><span class="text-right">MAPS</span><span class="text-right">LAPS</span>
                </div>
                @foreach ($rankedPlayers as $player)
                    <a href="{{ route('players.show', ['playerId' => $player['id']]) }}"
                       class="grid grid-cols-[52px_1fr_90px_90px_70px_70px] items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 transition hover:border-l-hud-green hover:bg-hud-green/7">
                        <span class="text-[15px] font-bold text-hud-text-dim">{{ $player['rank'] }}</span>
                        <div class="min-w-0 truncate text-sm font-semibold text-hud-text">{{ $player['name'] }}</div>
                        <span class="text-right font-mono text-[14px] font-bold text-hud-text-bright">{{ $player['score'] }}</span>
                        <span class="text-right font-mono text-[12px] text-hud-text-dim">{{ $player['records'] }}</span>
                        <span class="text-right font-mono text-[12px] text-hud-text-dim">{{ $player['maps'] }}</span>
                        <span class="text-right font-mono text-[12px] text-hud-text-dim">{{ $player['laps'] }}</span>
                    </a>
                @endforeach
                <div class="flex items-center justify-between px-3.5 py-4 font-mono text-[10px] tracking-[0.1em] text-hud-text-dim">
                    <span>SHOWING RANKS {{ $rankedPlayers->firstItem() + 3 }}–{{ $rankedPlayers->lastItem() + 3 }} / {{ count($players) }} PLAYERS</span>
                    <div class="flex items-center gap-3">
                        <button type="button" wire:click="previousPage('players')" @disabled($rankedPlayers->onFirstPage())
                                class="cursor-pointer tracking-[0.14em] disabled:cursor-not-allowed disabled:text-hud-text-faint {{ $rankedPlayers->onFirstPage() ? '' : 'text-hud-text hover:text-hud-green' }}">
                            ‹ PREV
                        </button>
                        <span>{{ $rankedPlayers->currentPage() }} / {{ $rankedPlayers->lastPage() }}</span>
                        <button type="button" wire:click="nextPage('players')" @disabled(! $rankedPlayers->hasMorePages())
                                class="cursor-pointer tracking-[0.14em] disabled:cursor-not-allowed disabled:text-hud-text-faint {{ $rankedPlayers->hasMorePages() ? 'text-hud-text hover:text-hud-green' : '' }}">
                            NEXT ›
                        </button>
                    </div>
                </div>
            </div>

            <!-- mobile list -->
            <div class="mt-8 tp:hidden">
                @foreach ($rankedPlayers as $player)
                    <a href="{{ route('players.show', ['playerId' => $player['id']]) }}"
                       class="flex items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 transition hover:border-l-hud-green hover:bg-hud-green/7">
                        <span class="w-6.5 flex-none text-[15px] font-bold text-hud-text-dim">{{ $player['rank'] }}</span>
                        <div class="min-w-0 flex-1 truncate text-sm font-semibold text-hud-text">{{ $player['name'] }}</div>
                        <div class="flex-none text-right">
                            <div class="font-mono text-sm font-bold text-hud-text-bright">{{ $player['score'] }}</div>
                            <div class="mt-0.5 font-mono text-[9px] text-hud-text-dim">{{ $player['records'] }} RECORDS · {{ $player['maps'] }} MAPS · {{ $player['laps'] }} LAPS</div>
                        </div>
                    </a>
                @endforeach
                <div class="flex items-center justify-between px-3.5 py-4 font-mono text-[10px] tracking-[0.1em] text-hud-text-dim">
                    <span>RANKS {{ $rankedPlayers->firstItem() + 3 }}–{{ $rankedPlayers->lastItem() + 3 }} / {{ count($players) }}</span>
                    <div class="flex items-center gap-3">
                        <button type="button" wire:click="previousPage('players')" @disabled($rankedPlayers->onFirstPage())
                                class="cursor-pointer tracking-[0.14em] disabled:cursor-not-allowed disabled:text-hud-text-faint {{ $rankedPlayers->onFirstPage() ? '' : 'text-hud-text hover:text-hud-green' }}">
                            ‹ PREV
                        </button>
                        <span>{{ $rankedPlayers->currentPage() }} / {{ $rankedPlayers->lastPage() }}</span>
                        <button type="button" wire:click="nextPage('players')" @disabled(! $rankedPlayers->hasMorePages())
                                class="cursor-pointer tracking-[0.14em] disabled:cursor-not-allowed disabled:text-hud-text-faint {{ $rankedPlayers->hasMorePages() ? 'text-hud-text hover:text-hud-green' : '' }}">
                            NEXT ›
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Maps (unchanged) -->
    <div class="mt-10">
        <div class="mb-4 font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// MAPS</div>

        <!-- desktop table -->
        <div class="hidden tp:block">
            <div class="grid grid-cols-[1fr_140px_140px] gap-3 border-b border-hud-green/16 px-3.5 py-2.5 font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">
                <span>MAP</span><span class="text-right">LAPS</span><span class="text-right">BEST LAP</span>
            </div>
            @foreach ($maps as $map)
                <a href="{{ route('servers.maps.show', ['serverId' => $serverId, 'mapId' => $map['id']]) }}"
                   class="grid grid-cols-[1fr_140px_140px] items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 transition hover:border-l-hud-green hover:bg-hud-green/7">
                    <div class="text-sm font-semibold tracking-[0.02em] text-hud-text">{{ $map['name'] }}</div>
                    <span class="text-right font-mono text-[13px] font-bold text-hud-text">{{ $map['laps'] }}</span>
                    <span class="text-right font-mono text-[14px] font-bold text-hud-text-bright">{{ $map['best'] }}</span>
                </a>
            @endforeach
        </div>

        <!-- mobile list -->
        <div class="tp:hidden">
            @foreach ($maps as $map)
                <a href="{{ route('servers.maps.show', ['serverId' => $serverId, 'mapId' => $map['id']]) }}"
                   class="flex items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 transition hover:border-l-hud-green hover:bg-hud-green/7">
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold text-hud-text">{{ $map['name'] }}</div>
                    </div>
                    <div class="flex-none text-right">
                        <div class="font-mono text-[13px] font-bold text-hud-text-bright">{{ $map['best'] }}</div>
                        <div class="mt-0.5 font-mono text-[9px] text-hud-text-muted">{{ $map['laps'] }} LAPS</div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    <!-- Latest Laps (one row per distinct player+map, paginated) -->
    <div class="mt-10">
        <div class="mb-4 font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// LATEST LAPS</div>

        <!-- desktop table -->
        <div class="hidden tp:block">
            <div class="grid grid-cols-[1fr_1fr_140px_170px] gap-3 border-b border-hud-green/16 px-3.5 py-2.5 font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">
                <span>PLAYER</span><span>MAP</span><span class="text-right">TIME</span><span class="text-right">DATE</span>
            </div>
            @foreach ($laps as $index => $lap)
                <button type="button" wire:click="openLap({{ $index }})"
                        class="grid w-full grid-cols-[1fr_1fr_140px_170px] items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 text-left transition hover:border-l-hud-green hover:bg-hud-green/7">
                    <span class="truncate text-sm font-semibold text-hud-text">{{ $lap['player'] }}</span>
                    <span class="truncate font-mono text-[12px] text-hud-text-dim">{{ $lap['map'] }}</span>
                    <span class="text-right font-mono text-[13px] font-bold text-hud-text-bright">{{ $lap['time'] }}</span>
                    <span class="text-right font-mono text-[10px] text-hud-text-dim">
                        <span class="group relative inline-block">
                            {{ $lap['date'] }}
                            <span x-anchor.top.offset.6="$el.parentElement"
                                  class="pointer-events-none z-20 hidden whitespace-nowrap border border-hud-green/40 bg-hud-bg px-2 py-1 font-mono text-[9px] font-semibold tracking-[0.04em] text-hud-text-bright shadow-[0_4px_16px_-4px_rgba(0,0,0,.8)] group-hover:block">
                                {{ $lap['dateExact'] }}
                            </span>
                        </span>
                    </span>
                </button>
            @endforeach
        </div>

        <!-- mobile list -->
        <div class="tp:hidden">
            @foreach ($laps as $index => $lap)
                <button type="button" wire:click="openLap({{ $index }})"
                        class="flex w-full items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 text-left transition hover:border-l-hud-green hover:bg-hud-green/7">
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold text-hud-text">{{ $lap['player'] }}</div>
                        <div class="mt-0.5 truncate font-mono text-[9px] text-hud-text-dim">
                            {{ $lap['map'] }} ·
                            <span class="group relative inline-block">
                                {{ $lap['date'] }}
                                <span x-anchor.top.offset.6="$el.parentElement"
                                      class="pointer-events-none z-20 hidden whitespace-nowrap border border-hud-green/40 bg-hud-bg px-2 py-1 font-mono text-[9px] font-semibold tracking-[0.04em] text-hud-text-bright shadow-[0_4px_16px_-4px_rgba(0,0,0,.8)] group-hover:block">
                                    {{ $lap['dateExact'] }}
                                </span>
                            </span>
                        </div>
                    </div>
                    <span class="flex-none font-mono text-sm font-bold text-hud-text-bright">{{ $lap['time'] }}</span>
                </button>
            @endforeach
        </div>

        <!-- pagination -->
        <div class="flex items-center justify-between px-3.5 py-4 font-mono text-[10px] tracking-[0.1em] text-hud-text-dim">
            <span>SHOWING {{ $laps->firstItem() ?? 0 }}–{{ $laps->lastItem() ?? 0 }} / {{ $laps->total() }} LAPS</span>
            <div class="flex items-center gap-3">
                <button type="button" wire:click="previousPage('page')" @disabled($laps->onFirstPage())
                        class="cursor-pointer tracking-[0.14em] disabled:cursor-not-allowed disabled:text-hud-text-faint {{ $laps->onFirstPage() ? '' : 'text-hud-text hover:text-hud-green' }}">
                    ‹ PREV
                </button>
                <span>{{ $laps->currentPage() }} / {{ $laps->lastPage() }}</span>
                <button type="button" wire:click="nextPage('page')" @disabled(! $laps->hasMorePages())
                        class="cursor-pointer tracking-[0.14em] disabled:cursor-not-allowed disabled:text-hud-text-faint {{ $laps->hasMorePages() ? 'text-hud-text hover:text-hud-green' : '' }}">
                    NEXT ›
                </button>
            </div>
        </div>
    </div>

</div>

@include('livewire.partials.lap-vs-record-modal', ['laps' => $latestLaps])
</div>
