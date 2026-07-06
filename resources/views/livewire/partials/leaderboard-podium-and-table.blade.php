{{-- Expects: $players, $rankedPlayers paginator, $splits, $totalLaps.
     Table below only lists rank 4+ — top 3 remain visible in the podium on every page. --}}

<!-- podium -->
@php $p = [1 => $players[1] ?? null, 0 => $players[0] ?? null, 2 => $players[2] ?? null]; @endphp
<div class="mt-8 grid grid-cols-1 items-end gap-3.5 tp:grid-cols-[1fr_1.28fr_1fr]">
    <!-- 2nd -->
    @if ($p[1])
        <div wire:click="openLap(1)" class="hud-clip-sm order-2 cursor-pointer border border-hud-cyan/20 bg-gradient-to-b from-[#101915] to-[#0b120f] px-4 py-4.5 transition hover:-translate-y-1 hover:border-hud-cyan/55 tp:order-1">
            <div class="mb-3.5 text-2xl font-bold text-hud-cyan">{{ $p[1]['rank'] }}</div>
            <div class="text-base font-semibold tracking-[0.03em] text-hud-text">{{ $p[1]['name'] }}</div>
            <div class="mt-0.5 font-mono text-[10px] text-hud-text-dim">{{ $p[1]['subtitle'] }}</div>
            <div class="mt-3.5 font-mono text-2xl font-bold text-hud-text-bright">{{ $p[1]['time'] }}</div>
            <div class="mt-1 font-mono text-[11px] font-semibold text-hud-cyan">{{ $p[1]['gap'] }}</div>
        </div>
    @endif

    <!-- 1st -->
    @if ($p[0])
        <div wire:click="openLap(0)" class="hud-clip order-1 cursor-pointer border border-hud-green/50 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-5 py-5.5 shadow-[0_0_54px_-12px_theme(colors.hud-green/55%)] transition hover:-translate-y-1 tp:order-2">
            <div class="mb-4 flex items-center justify-between">
                <span class="border border-hud-green/32 bg-hud-green/12 px-2.5 py-1 font-mono text-[9px] font-semibold tracking-[0.2em] text-hud-green"><span class="-translate-y-px inline-block">◆</span> COURSE RECORD</span>
                <span class="text-3xl font-bold text-hud-green [text-shadow:0_0_22px_rgba(52,227,155,.6)]">{{ $p[0]['rank'] }}</span>
            </div>
            <div class="text-xl font-bold tracking-[0.02em] text-white">{{ $p[0]['name'] }}</div>
            <div class="mt-1 font-mono text-[10px] text-hud-text-dim">{{ $p[0]['subtitle'] }}</div>
            <div class="mt-4 font-mono text-4xl font-extrabold leading-none text-hud-text-bright [text-shadow:0_0_26px_rgba(52,227,155,.4)]">{{ $p[0]['time'] }}</div>
            {{-- Split pace sparkline — scales to any split count (some maps have up to 14). Times aren't
                 spelled out statically here (no room at 14 splits); hover a segment to see one. --}}
            @php $avgSplitTime = collect($splits)->avg(fn ($s) => (float) $s['time']); @endphp
            <div class="mt-4.5">
                <div class="mb-2 flex items-center justify-between font-mono text-[8px] font-semibold tracking-[0.12em] text-hud-text-dim">
                    <span>SPLIT PACE</span>
                    <span>{{ count($splits) }} CHECKPOINTS</span>
                </div>
                @if (count($splits) > 0)
                    <div class="flex h-2.5 gap-0.5 border border-hud-green/14 bg-white/5 p-0.5">
                        @foreach ($splits as $split)
                            @php $isFaster = (float) $split['time'] <= $avgSplitTime; @endphp
                            <div class="group relative h-full" style="flex-grow: {{ (float) $split['time'] }}; flex-basis: 0;">
                                <div class="h-full w-full {{ $isFaster ? 'bg-hud-green' : 'bg-hud-gold' }}"></div>
                                <div x-anchor.top.offset.6="$el.parentElement"
                                     class="pointer-events-none z-20 hidden whitespace-nowrap border border-hud-green/40 bg-hud-bg px-2 py-1 font-mono text-[9px] font-semibold tracking-[0.04em] text-hud-text-bright shadow-[0_4px_16px_-4px_rgba(0,0,0,.8)] group-hover:block">
                                    {{ $split['label'] }} · <span class="{{ $isFaster ? 'text-hud-green' : 'text-hud-gold' }}">{{ $split['time'] }}s</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="border border-hud-green/14 bg-white/5 px-2 py-2 text-center font-mono text-[9px] text-hud-text-dim">
                        No split data available for this lap.
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- 3rd -->
    @if ($p[2])
        <div wire:click="openLap(2)" class="hud-clip-sm order-3 cursor-pointer border border-hud-gold/22 bg-gradient-to-b from-[#171410] to-[#0e0b08] px-4 py-4.5 transition hover:-translate-y-1 hover:border-hud-gold/55">
            <div class="mb-3.5 text-2xl font-bold text-hud-gold">{{ $p[2]['rank'] }}</div>
            <div class="text-base font-semibold tracking-[0.03em] text-[#e6ddc9]">{{ $p[2]['name'] }}</div>
            <div class="mt-0.5 font-mono text-[10px] text-[#7c6e52]">{{ $p[2]['subtitle'] }}</div>
            <div class="mt-3.5 font-mono text-2xl font-bold text-[#f6eede]">{{ $p[2]['time'] }}</div>
            <div class="mt-1 font-mono text-[11px] font-semibold text-hud-gold">{{ $p[2]['gap'] }}</div>
        </div>
    @endif
</div>

<!-- desktop table -->
<div class="mt-8 hidden tp:block">
    <div class="grid grid-cols-[52px_1fr_96px_130px_100px] gap-3 border-b border-hud-green/16 px-3.5 py-2.5 font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">
        <span>#</span><span>PLAYER</span><span>GAP</span><span class="text-right">LAP TIME</span><span class="text-right">DATE</span>
    </div>
    @foreach ($rankedPlayers as $index => $player)
        <button type="button" wire:click="openLap({{ $index }})"
                class="grid w-full grid-cols-[52px_1fr_96px_130px_100px] items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 text-left transition hover:border-l-hud-green hover:bg-hud-green/7">
            <span class="text-[15px] font-bold text-hud-text-dim">{{ $player['rank'] }}</span>
            <div>
                <div class="text-sm font-semibold tracking-[0.02em] text-hud-text">{{ $player['name'] }}</div>
                <div class="mt-px font-mono text-[9px] text-hud-text-dim">{{ $player['subtitle'] }}</div>
            </div>
            <span class="font-mono text-xs font-semibold text-hud-gold">{{ $player['gap'] }}</span>
            <span class="text-right font-mono text-[15px] font-bold text-hud-text-bright">{{ $player['time'] }}</span>
            <span class="text-right font-mono text-[10px] text-hud-text-dim">
                <span class="group relative inline-block">
                    {{ $player['date'] }}
                    <span x-anchor.top.offset.6="$el.parentElement"
                          class="pointer-events-none z-20 hidden whitespace-nowrap border border-hud-green/40 bg-hud-bg px-2 py-1 font-mono text-[9px] font-semibold tracking-[0.04em] text-hud-text-bright shadow-[0_4px_16px_-4px_rgba(0,0,0,.8)] group-hover:block">
                        {{ $player['dateExact'] }}
                    </span>
                </span>
            </span>
        </button>
    @endforeach
    <div class="flex items-center justify-between px-3.5 py-4 font-mono text-[10px] tracking-[0.1em] text-hud-text-dim">
        @if (count($players) > 3)
            <span>SHOWING RANKS {{ $rankedPlayers->firstItem() + 3 }}–{{ $rankedPlayers->lastItem() + 3 }} / {{ count($players) }} DRIVERS · {{ $totalLaps }} LAPS</span>
            <div class="flex items-center gap-3">
                <button type="button" wire:click="previousPage('page')" @disabled($rankedPlayers->onFirstPage())
                        class="cursor-pointer tracking-[0.14em] disabled:cursor-not-allowed disabled:text-hud-text-faint {{ $rankedPlayers->onFirstPage() ? '' : 'text-hud-text hover:text-hud-green' }}">
                    ‹ PREV
                </button>
                <span>{{ $rankedPlayers->currentPage() }} / {{ $rankedPlayers->lastPage() }}</span>
                <button type="button" wire:click="nextPage('page')" @disabled(! $rankedPlayers->hasMorePages())
                        class="cursor-pointer tracking-[0.14em] disabled:cursor-not-allowed disabled:text-hud-text-faint {{ $rankedPlayers->hasMorePages() ? 'text-hud-text hover:text-hud-green' : '' }}">
                    NEXT ›
                </button>
            </div>
        @else
            <span>SHOWING {{ count($players) }} DRIVERS · {{ $totalLaps }} LAPS</span>
        @endif
    </div>
</div>

<!-- mobile list -->
<div class="mt-8 tp:hidden">
    @foreach ($rankedPlayers as $index => $player)
        <button type="button" wire:click="openLap({{ $index }})"
                class="flex w-full items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 text-left transition hover:border-l-hud-green hover:bg-hud-green/7">
            <span class="w-6.5 text-[15px] font-bold text-hud-text-dim">{{ $player['rank'] }}</span>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold text-hud-text">{{ $player['name'] }}</div>
                <div class="mt-0.5 font-mono text-[10px] font-semibold text-hud-gold">{{ $player['gap'] }}</div>
            </div>
            <span class="font-mono text-sm font-bold text-hud-text-bright">{{ $player['time'] }}</span>
        </button>
    @endforeach
</div>

<!-- mobile pagination -->
@if (count($players) > 3)
    <div class="flex items-center justify-between px-3.5 py-4 font-mono text-[10px] tracking-[0.1em] text-hud-text-dim tp:hidden">
        <span>RANKS {{ $rankedPlayers->firstItem() + 3 }}–{{ $rankedPlayers->lastItem() + 3 }} / {{ count($players) }}</span>
        <div class="flex items-center gap-3">
            <button type="button" wire:click="previousPage('page')" @disabled($rankedPlayers->onFirstPage())
                    class="cursor-pointer tracking-[0.14em] disabled:cursor-not-allowed disabled:text-hud-text-faint {{ $rankedPlayers->onFirstPage() ? '' : 'text-hud-text hover:text-hud-green' }}">
                ‹ PREV
            </button>
            <span>{{ $rankedPlayers->currentPage() }} / {{ $rankedPlayers->lastPage() }}</span>
            <button type="button" wire:click="nextPage('page')" @disabled(! $rankedPlayers->hasMorePages())
                    class="cursor-pointer tracking-[0.14em] disabled:cursor-not-allowed disabled:text-hud-text-faint {{ $rankedPlayers->hasMorePages() ? 'text-hud-text hover:text-hud-green' : '' }}">
                NEXT ›
            </button>
        </div>
    </div>
@endif
