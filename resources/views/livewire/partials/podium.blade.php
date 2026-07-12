{{-- Expects: $podium — indexed 0 (1st), 1 (2nd), 2 (3rd), each:
     ['title', 'subtitle', 'value', 'meta' => null, 'badge' => null, 'href' => null]
     Shared visual structure with the Map Leaderboard podium (leaderboard-podium-and-table.blade.php)
     — same rank colors/sizing/layout — but without lap-detail-modal click behavior or the split
     sparkline, which are specific to that richer widget. Each card renders as an <a> when
     'href' is set (e.g. linking to the player's page), otherwise a plain <div>. --}}
<div class="grid grid-cols-1 items-end gap-3.5 tp:grid-cols-[1fr_1.28fr_1fr]">
    <!-- 2nd -->
    @if ($podium[1] ?? null)
        @php $classes2nd = 'hud-clip-sm order-2 border border-hud-cyan/20 bg-gradient-to-b from-[#101915] to-[#0b120f] px-4 py-4.5 transition hover:-translate-y-1 hover:border-hud-cyan/55 tp:order-1'; @endphp
        @if ($podium[1]['href'] ?? null)
            <a href="{{ $podium[1]['href'] }}" class="{{ $classes2nd }}">
        @else
            <div class="{{ $classes2nd }}">
        @endif
            <div class="mb-3.5 text-2xl font-bold text-hud-cyan">2</div>
            <div class="text-base font-semibold tracking-[0.03em] text-hud-text">{{ $podium[1]['title'] }}</div>
            <div class="mt-0.5 font-mono text-[10px] text-hud-text-dim">{{ $podium[1]['subtitle'] }}</div>
            <div class="mt-3.5 font-mono text-2xl font-bold text-hud-text-bright">{{ $podium[1]['value'] }}</div>
            @if ($podium[1]['meta'] ?? null)
                <div class="mt-1 font-mono text-[11px] font-semibold text-hud-cyan">{{ $podium[1]['meta'] }}</div>
            @endif
        @if ($podium[1]['href'] ?? null)
            </a>
        @else
            </div>
        @endif
    @endif

    <!-- 1st -->
    @if ($podium[0] ?? null)
        @php $classes1st = 'hud-clip order-1 border border-hud-green/50 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-5 py-5.5 shadow-[0_0_54px_-12px_theme(colors.hud-green/55%)] transition hover:-translate-y-1 tp:order-2'; @endphp
        @if ($podium[0]['href'] ?? null)
            <a href="{{ $podium[0]['href'] }}" class="{{ $classes1st }}">
        @else
            <div class="{{ $classes1st }}">
        @endif
            <div class="mb-4 flex items-center justify-between">
                @if ($podium[0]['badge'] ?? null)
                    <span class="border border-hud-green/32 bg-hud-green/12 px-2.5 py-1 font-mono text-[9px] font-semibold tracking-[0.2em] text-hud-green"><span class="-translate-y-px inline-block">◆</span> {{ $podium[0]['badge'] }}</span>
                @else
                    <span></span>
                @endif
                <span class="text-3xl font-bold text-hud-green [text-shadow:0_0_22px_rgba(52,227,155,.6)]">1</span>
            </div>
            <div class="text-xl font-bold tracking-[0.02em] text-white">{{ $podium[0]['title'] }}</div>
            <div class="mt-1 font-mono text-[10px] text-hud-text-dim">{{ $podium[0]['subtitle'] }}</div>
            <div class="mt-4 font-mono text-4xl font-extrabold leading-none text-hud-text-bright [text-shadow:0_0_26px_rgba(52,227,155,.4)]">{{ $podium[0]['value'] }}</div>
            @if ($podium[0]['meta'] ?? null)
                <div class="mt-1 font-mono text-[11px] font-semibold text-hud-green">{{ $podium[0]['meta'] }}</div>
            @endif
        @if ($podium[0]['href'] ?? null)
            </a>
        @else
            </div>
        @endif
    @endif

    <!-- 3rd -->
    @if ($podium[2] ?? null)
        @php $classes3rd = 'hud-clip-sm order-3 border border-hud-gold/22 bg-gradient-to-b from-[#171410] to-[#0e0b08] px-4 py-4.5 transition hover:-translate-y-1 hover:border-hud-gold/55'; @endphp
        @if ($podium[2]['href'] ?? null)
            <a href="{{ $podium[2]['href'] }}" class="{{ $classes3rd }}">
        @else
            <div class="{{ $classes3rd }}">
        @endif
            <div class="mb-3.5 text-2xl font-bold text-hud-gold">3</div>
            <div class="text-base font-semibold tracking-[0.03em] text-[#e6ddc9]">{{ $podium[2]['title'] }}</div>
            <div class="mt-0.5 font-mono text-[10px] text-[#7c6e52]">{{ $podium[2]['subtitle'] }}</div>
            <div class="mt-3.5 font-mono text-2xl font-bold text-[#f6eede]">{{ $podium[2]['value'] }}</div>
            @if ($podium[2]['meta'] ?? null)
                <div class="mt-1 font-mono text-[11px] font-semibold text-hud-gold">{{ $podium[2]['meta'] }}</div>
            @endif
        @if ($podium[2]['href'] ?? null)
            </a>
        @else
            </div>
        @endif
    @endif
</div>
