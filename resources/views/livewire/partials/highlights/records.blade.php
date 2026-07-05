{{-- Expects: $data — list of ['map', 'time', 'player', 'server', 'ago'] --}}
<div class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
    <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// LATEST RECORDS</div>
    <ul class="mt-4 space-y-3.5">
        @foreach ($data as $record)
            <li class="border-b border-white/5 pb-3.5 last:border-b-0 last:pb-0">
                <div class="text-sm font-semibold text-hud-text-bright">{{ $record['map'] }}</div>
                <div class="mt-1 font-mono text-[11px] text-hud-text-dim">
                    <span class="font-bold text-hud-green">{{ $record['time'] }}</span> by {{ $record['player'] }} on {{ $record['server'] }}
                </div>
                <div class="mt-1 font-mono text-[9px] tracking-[0.1em] text-hud-text-faint">{{ strtoupper($record['ago']) }}</div>
            </li>
        @endforeach
    </ul>
</div>
