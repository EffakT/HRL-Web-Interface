{{-- Expects: $players, $selectedPlayerIndex, $this->comparison, $this->comparisonReference
     (HasLapDetailModal — the player actually being compared against, which for #1 is #2, not
     #1 itself; read dynamically here rather than assuming it's always player #1, so the "vs
     X" label never contradicts what getComparisonProperty() actually compared). $players can
     legitimately be empty (a real server+map with no laps yet) — guarded the same way as
     lap-vs-record-modal.blade.php. --}}

@php $sel = $players[$selectedPlayerIndex] ?? $players[0] ?? null; $reference = $this->comparisonReference; @endphp
@if ($sel)
<div class="fixed inset-0 z-50 flex items-center justify-center p-5"
     wire:key="lap-detail-modal"
     x-show="$wire.selectedPlayerIndex !== null"
     x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <div class="absolute inset-0 bg-[radial-gradient(120%_90%_at_50%_45%,rgba(6,11,9,.7),rgba(4,7,6,.9))] backdrop-blur-sm" wire:click="closeLap"></div>

        <div class="hud-clip relative flex max-h-[85vh] w-full max-w-[640px] flex-col border border-hud-green/50 bg-gradient-to-b from-hud-modal-start to-hud-modal-end px-6 py-6 shadow-[0_40px_80px_-20px_rgba(0,0,0,.85),0_0_76px_-14px_theme(colors.hud-green/40%)] tp:px-7.5"
             x-transition:enter="transition ease-out duration-300 hud-flicker-in"
             x-transition:enter-start="opacity-0 scale-75 -translate-y-8"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-75 -translate-y-8">
            <div class="flex flex-none items-start justify-between">
                <div>
                    <div class="font-mono text-[10px] font-semibold tracking-[0.3em] text-hud-cyan">// LAP DETAIL</div>
                    <div class="mt-3 flex flex-wrap items-baseline gap-3">
                        <span class="text-3xl font-bold text-hud-text-dim">{{ $sel['rank'] }}</span>
                        <span class="text-2xl font-bold tracking-[0.02em] text-white">{{ $sel['name'] }}</span>
                        <span class="font-mono text-[11px] text-hud-text-dim">{{ $sel['subtitle'] }}</span>
                    </div>
                </div>
                <button type="button" wire:click="closeLap"
                        class="hud-clip-sm flex h-7.5 w-7.5 flex-none cursor-pointer items-center justify-center border border-hud-green/30 text-hud-text-muted transition hover:border-hud-red/60 hover:text-hud-red"
                        aria-label="Close">
                    ✕
                </button>
            </div>

            <div class="mt-5 flex flex-none items-end justify-between gap-4 border-b border-hud-green/14 pb-4.5">
                <div>
                    <div class="mb-2 font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">LAP TIME</div>
                    <div class="font-mono text-3xl font-extrabold leading-none text-hud-text-bright tp:text-4xl">{{ $sel['time'] }}</div>
                </div>
                @php
                    // Gap color: "gap to RUNNER-UP" is the record holder's lead over #2 — a good
                    // thing, colored green like every other "faster"/positive indicator in this
                    // app. "Gap to RECORD" for everyone else is a deficit — stays red.
                    $gapColorClass = ($reference['label'] ?? null) === 'RUNNER-UP' ? 'text-hud-green' : 'text-hud-red';
                @endphp
                <div class="text-right">
                    <div class="mb-2 font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">GAP TO {{ $reference['label'] ?? 'RECORD' }}</div>
                    <div class="font-mono text-2xl font-extrabold leading-none {{ $gapColorClass }}">{{ $sel['gap'] }}</div>
                    <div class="mt-2 font-mono text-[10px] text-hud-text-dim">
                        SET
                        <span class="group relative inline-block">
                            {{ $sel['date'] }}
                            <span x-anchor.top.offset.6="$el.parentElement"
                                  class="pointer-events-none z-20 hidden whitespace-nowrap border border-hud-green/40 bg-hud-bg px-2 py-1 font-mono text-[9px] font-semibold tracking-[0.04em] text-hud-text-bright shadow-[0_4px_16px_-4px_rgba(0,0,0,.8)] group-hover:block">
                                {{ $sel['dateExact'] }}
                            </span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="mb-3 mt-4.5 flex flex-none flex-wrap items-center gap-2.5">
                <span class="font-mono text-[10px] font-semibold tracking-[0.16em] text-hud-cyan">SPLIT COMPARISON</span>
                @if ($reference)
                    <span class="font-mono text-[10px] text-hud-text-dim">vs {{ $reference['label'] }} · {{ $reference['name'] }} {{ $reference['time'] }}</span>
                @endif
            </div>

            {{-- Scrollable split rows — some maps have up to 14 checkpoints, so this area scrolls independently
                 while the lap-time header above and final-delta/buttons footer below stay pinned. --}}
            {{-- TODO: issue with missing padding in table left/right --}}
            <div class="min-h-0 flex-1 overflow-y-auto">
                @if (count($this->comparison) > 0)
                    <div class="sticky top-0 z-10 grid grid-cols-[50px_1fr_60px] gap-3 bg-hud-modal-start px-0.5 py-2.5 font-mono text-[9px] font-semibold tracking-[0.14em] text-hud-text-dim tp:grid-cols-[58px_98px_78px_1fr_80px]">
                        <span>CP</span><span class="hidden tp:inline">SPLIT</span><span class="hidden tp:inline">+/−</span><span class="hidden text-center tp:inline">DELTA</span><span class="text-right">GAP</span>
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
                            This lap has no split data, but the reference lap does — showing raw splits, no delta comparison.
                        </div>
                    @elseif (collect($this->comparison)->every(fn ($c) => ! $c['hasReference'] && ! $c['usingReferenceSplits']))
                        <div class="border-t border-white/6 py-3 text-center font-mono text-[10px] text-hud-text-dim">
                            This lap has split data, but the reference lap doesn't — showing raw splits, no delta comparison.
                        </div>
                    @endif
                @else
                    <div class="border-t border-white/6 py-8 text-center font-mono text-[11px] text-hud-text-dim">
                        No split data available for this lap.
                    </div>
                @endif
            </div>

            @php
                // Biggest real gain/loss checkpoint, derived from the actual comparison rows rather
                // than hardcoded — empty $this->comparison (no real split data, or mock pages
                // with nothing to derive from) falls back to no summary line at all. Sorts on
                // the signed numeric 'deltaValue', never the formatted 'delta' string (e.g.
                // "+0.041" vs "−0.028" sort lexicographically, not numerically) — and only rows
                // that actually have a reference to compare against, since 'deltaValue' is null otherwise.
                $comparableRows = collect($this->comparison)->filter(fn ($c) => $c['hasReference']);
                $gainedRow = $comparableRows->sortBy('deltaValue')->first();
                $lostRow = $comparableRows->sortByDesc('deltaValue')->first();
            @endphp
            <div class="mt-3.5 flex flex-none items-center justify-between border-t border-hud-green/16 pt-3.5">
                @if ($gainedRow && $lostRow)
                    <span class="hidden font-mono text-[10px] text-hud-text-dim tp:inline">
                        <span class="text-hud-green">▲ Gained</span> in {{ $gainedRow['label'] }} · <span class="text-hud-red">▼ Lost most</span> in {{ $lostRow['label'] }}
                    </span>
                @endif
                <div class="ml-auto flex items-baseline gap-2.5">
                    <span class="font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">FINAL DELTA</span>
                    <span class="font-mono text-xl font-extrabold {{ $gapColorClass }}">{{ $sel['gap'] }}</span>
                </div>
            </div>

            <div class="mt-5 flex flex-none flex-wrap gap-2.5">
                @if ($sel['playerId'] ?? null)
                    <a href="{{ route('players.show', ['playerId' => $sel['playerId']]) }}"
                       class="hud-clip-sm flex-1 cursor-pointer bg-hud-green py-3 text-center font-mono text-[11px] font-semibold tracking-[0.14em] text-[#04140d] transition hover:-translate-y-0.5 hover:shadow-[0_0_26px_-4px_theme(colors.hud-green/80%)]">
                        VIEW PLAYER
                    </a>
                @else
                    <div class="hud-clip-sm flex-1 cursor-pointer bg-hud-green py-3 text-center font-mono text-[11px] font-semibold tracking-[0.14em] text-[#04140d] transition hover:-translate-y-0.5 hover:shadow-[0_0_26px_-4px_theme(colors.hud-green/80%)]">
                        VIEW PLAYER
                    </div>
                @endif
                @if ($sel['serverId'] ?? null)
                    <a href="{{ route('servers.show', ['serverId' => $sel['serverId']]) }}"
                       class="hud-clip-sm min-w-[130px] flex-1 cursor-pointer border border-hud-green/22 py-3 text-center font-mono text-[11px] font-semibold tracking-[0.14em] text-hud-text-muted transition hover:border-hud-green/50 hover:text-hud-text">
                        VIEW SERVER
                    </a>
                @endif
                <button type="button" wire:click="closeLap"
                        class="hud-clip-sm min-w-[130px] flex-1 cursor-pointer border border-hud-green/22 py-3 text-center font-mono text-[11px] font-semibold tracking-[0.14em] text-hud-text-muted transition hover:border-hud-green/50 hover:text-hud-text">
                    CLOSE
                </button>
            </div>
        </div>
    </div>
@endif
