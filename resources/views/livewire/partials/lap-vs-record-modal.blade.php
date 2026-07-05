{{-- Expects: $laps — indexed array, each ['map', 'server', 'time', 'date', 'mapId',
     'recordHolder', 'recordTime', 'ping' => null, 'recordTag' => null], $selectedPlayerIndex,
     $this->comparison (HasLapDetailModal). Shows one lap in isolation against its map's
     course record — distinct from lap-detail-modal.blade.php, which shows a player's position
     within one map's ranked leaderboard (rank/gap-to-leader), not a single lap on its own.
     $laps can legitimately be empty (e.g. a server/player with no real laps yet) — $sel guards
     against that rather than assuming index 0 always exists; the modal can never actually be
     opened with nothing to show (nothing renders openLap() for an empty list), but the markup
     below still has to compile/render safely either way.

     $this->comparisonReference (optional) names whichever lap was actually used for the split
     comparison — falls back to $sel's own recordHolder/recordTime when the component doesn't
     define it (e.g. PlayerShow, still mock — every mock lap's own recordHolder fields are
     already correct there, no dynamic reference needed). ServerShow overrides the property for
     the one case that DOES need it: viewing the record lap itself, where the real comparison
     silently swaps to the map's runner-up — the label needs to say so, not keep claiming
     "vs MAP RECORD · {the exact lap being viewed}". --}}
@php
    $sel = $laps[$selectedPlayerIndex] ?? $laps[0] ?? null;
    $reference = $this->comparisonReference ?? ($sel ? [
        'name' => $sel['recordHolder'] ?? null,
        'time' => $sel['recordTime'] ?? null,
        'label' => 'MAP RECORD',
    ] : null);
@endphp
@if ($sel)
<div class="fixed inset-0 z-50 flex items-center justify-center p-5"
     wire:key="lap-vs-record-modal"
     x-show="$wire.selectedPlayerIndex !== null"
     x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <div class="absolute inset-0 bg-[radial-gradient(120%_90%_at_50%_45%,rgba(6,11,9,.7),rgba(4,7,6,.9))] backdrop-blur-sm" wire:click="closeLap"></div>

    <div class="hud-clip relative flex max-h-[85vh] w-full max-w-[520px] flex-col border border-hud-green/50 bg-gradient-to-b from-hud-modal-start to-hud-modal-end px-6 py-6 shadow-[0_40px_80px_-20px_rgba(0,0,0,.85),0_0_76px_-14px_theme(colors.hud-green/40%)]"
         x-transition:enter="transition ease-out duration-300 hud-flicker-in"
         x-transition:enter-start="opacity-0 scale-75 -translate-y-8"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-75 -translate-y-8">
        <div class="flex flex-none items-start justify-between">
            <div>
                <div class="font-mono text-[10px] font-semibold tracking-[0.3em] text-hud-cyan">// LAP DETAIL</div>
                <div class="mt-3 text-xl font-bold tracking-[0.02em] text-white">{{ $sel['map'] }}</div>
                <div class="mt-1 font-mono text-[11px] text-hud-text-dim">{{ $sel['server'] }}</div>
            </div>
            <button type="button" wire:click="closeLap"
                    class="hud-clip-sm flex h-7.5 w-7.5 flex-none items-center justify-center border border-hud-green/30 text-hud-text-muted transition hover:border-hud-red/60 hover:text-hud-red"
                    aria-label="Close">
                ✕
            </button>
        </div>

        <div class="mt-5 flex-none border-t border-hud-green/14 pt-4.5">
            <div class="mb-2 font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">LAP TIME</div>
            <div class="font-mono text-4xl font-extrabold leading-none text-hud-text-bright [text-shadow:0_0_26px_rgba(52,227,155,.4)]">{{ $sel['time'] }}</div>
            <div class="mt-3 font-mono text-[10px] text-hud-text-dim">
                SET {{ $sel['date'] }}
                @if ($sel['ping'] ?? null)
                    · PING {{ $sel['ping'] }}ms
                @endif
            </div>
        </div>

        <div class="mb-3 mt-4.5 flex flex-none flex-wrap items-center gap-2.5">
            <span class="font-mono text-[10px] font-semibold tracking-[0.16em] text-hud-cyan">SPLIT COMPARISON</span>
            @if ($reference)
                <span class="font-mono text-[10px] text-hud-text-dim">vs {{ $reference['label'] }} · {{ $reference['name'] }} {{ $reference['time'] }}</span>
            @endif
        </div>

        {{-- Scrollable split rows — some maps have up to 14 checkpoints, so this scrolls independently
             while the lap-time header above and buttons footer below stay pinned. Comparison rows
             are real per-checkpoint data where both this lap and the map's record lap have splits
             recorded (only ~4% of real laps do — see docs/database.md) — HasLapDetailModal's mock
             fallback (identical rows regardless of selection) is still used by pages not yet
             wired to real splits. --}}
        <div class="min-h-0 flex-1 overflow-y-auto">
            @if (count($this->comparison) > 0)
                <div class="sticky top-0 z-10 grid grid-cols-[50px_1fr_60px] gap-3 bg-hud-modal-start px-0.5 py-2.5 font-mono text-[9px] font-semibold tracking-[0.14em] text-hud-text-dim tp:grid-cols-[58px_98px_78px_1fr_80px]">
                    <span>CHECKPOINT</span><span class="hidden tp:inline">SPLIT</span><span class="hidden tp:inline">+/−</span><span class="hidden text-center tp:inline">DELTA</span><span class="text-right">GAP</span>
                </div>
                @foreach ($this->comparison as $c)
                    <div class="grid grid-cols-[50px_1fr_60px] items-center gap-3 border-t border-white/6 py-3 tp:grid-cols-[58px_98px_78px_1fr_80px]">
                        <span class="text-sm font-bold text-hud-text">{{ $c['label'] }}</span>
                        <div class="hidden tp:block">
                            @if ($c['usingReferenceSplits'])
                                {{-- These are the *reference* lap's own raw times — this lap has none. Render
                                     them as the muted "REC" line, not the bold primary slot, so it's never
                                     mistaken for this lap's own data (see docs/decisions.md). --}}
                                <div class="font-mono text-[15px] font-bold text-hud-text-faint">—</div>
                                <div class="mt-0.5 font-mono text-[9px] text-hud-text-dim">REC {{ $c['myTime'] }}</div>
                            @else
                                <div class="font-mono text-[15px] font-bold text-hud-text-bright">{{ $c['myTime'] }}</div>
                                @if ($c['hasReference'])
                                    <div class="mt-0.5 font-mono text-[9px] text-hud-text-dim">REC {{ $c['refTime'] }}</div>
                                @endif
                            @endif
                        </div>
                        @if ($c['hasReference'])
                            <span class="hidden font-mono text-xs font-bold {{ $c['colorClass'] }} tp:inline">{{ $c['delta'] }}</span>
                            <div class="relative hidden h-2.5 border border-hud-green/10 bg-white/5 tp:block">
                                <div class="absolute -top-0.5 -bottom-0.5 left-1/2 w-px bg-white/22"></div>
                                @if ($c['faster'])
                                    <div class="absolute right-1/2 top-0 bottom-0 bg-hud-green" style="width: {{ $c['barW'] }}%"></div>
                                @else
                                    <div class="absolute left-1/2 top-0 bottom-0 bg-hud-red" style="width: {{ $c['barW'] }}%"></div>
                                @endif
                            </div>
                            <span class="text-right font-mono text-xs font-bold {{ $c['colorClass'] }}">{{ $c['running'] }}</span>
                        @else
                            <span class="hidden tp:inline"></span>
                            <div class="relative hidden h-2.5 border border-hud-green/10 bg-white/5 tp:block"></div>
                            <span class="text-right font-mono text-xs text-hud-text-faint">—</span>
                        @endif
                    </div>
                @endforeach
                @if (collect($this->comparison)->every(fn ($c) => $c['usingReferenceSplits']))
                    <div class="border-t border-white/6 py-3 text-center font-mono text-[10px] text-hud-text-dim">
                        This lap has no split data, but {{ $reference['name'] ?? 'the comparison lap' }}'s does — showing raw splits, no delta comparison.
                    </div>
                @elseif (collect($this->comparison)->every(fn ($c) => ! $c['hasReference'] && ! $c['usingReferenceSplits']))
                    <div class="border-t border-white/6 py-3 text-center font-mono text-[10px] text-hud-text-dim">
                        This lap has split data, but {{ $reference['name'] ?? 'the comparison lap' }}'s doesn't — showing raw splits, no delta comparison.
                    </div>
                @endif
            @else
                <div class="border-t border-white/6 py-8 text-center font-mono text-[11px] text-hud-text-dim">
                    No split data available for this lap.
                </div>
            @endif
        </div>

        <div class="mt-5 flex flex-none gap-2.5">
            <a href="{{ route('maps.show', ['mapId' => $sel['mapId']]) }}"
               class="hud-clip-sm flex-1 cursor-pointer bg-hud-green py-3 text-center font-mono text-[11px] font-semibold tracking-[0.14em] text-[#04140d] transition hover:-translate-y-0.5 hover:shadow-[0_0_26px_-4px_theme(colors.hud-green/80%)]">
                VIEW MAP LEADERBOARD
            </a>
            <button type="button" wire:click="closeLap"
                    class="hud-clip-sm flex-1 border border-hud-green/22 py-3 text-center font-mono text-[11px] font-semibold tracking-[0.14em] text-hud-text-muted transition hover:border-hud-green/50 hover:text-hud-text">
                CLOSE
            </button>
        </div>
    </div>
</div>
@endif
