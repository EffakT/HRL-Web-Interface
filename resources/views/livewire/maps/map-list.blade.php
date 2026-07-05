<div class="mx-auto max-w-[1080px] px-6 py-14">

    <div class="mb-3 font-mono text-[11px] font-semibold tracking-[0.34em] text-hud-cyan">// MAPS</div>
    <h1 class="text-4xl font-bold uppercase leading-[0.98] text-hud-text-bright tp:text-5xl">All Maps</h1>

    <!-- desktop table -->
    <div class="mt-8 hidden tp:block">
        <div class="grid grid-cols-[1fr_140px_140px] gap-3 border-b border-hud-green/16 px-3.5 py-2.5 font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">
            <span>MAP</span><span class="text-right">LAPS</span><span class="text-right">BEST LAP</span>
        </div>
        @foreach ($maps as $map)
            <a href="{{ route('maps.show', ['mapId' => $map['id']]) }}"
               class="grid grid-cols-[1fr_140px_140px] items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 transition hover:border-l-hud-green hover:bg-hud-green/7">
                <div class="text-sm font-semibold tracking-[0.02em] text-hud-text">{{ $map['name'] }}</div>
                <span class="text-right font-mono text-[13px] font-bold text-hud-text">{{ $map['laps'] }}</span>
                <span class="text-right font-mono text-[14px] font-bold text-hud-text-bright">{{ $map['best'] }}</span>
            </a>
        @endforeach
        <div class="flex items-center justify-between px-3.5 py-4 font-mono text-[10px] tracking-[0.1em] text-hud-text-dim">
            <span>SHOWING {{ count($maps) }} / {{ count($maps) }} MAPS</span>
        </div>
    </div>

    <!-- mobile list -->
    <div class="mt-8 tp:hidden">
        @foreach ($maps as $map)
            <a href="{{ route('maps.show', ['mapId' => $map['id']]) }}"
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
