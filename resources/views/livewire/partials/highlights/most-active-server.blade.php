{{-- Expects: $data — list of ['rank', 'name', 'ip', 'port', 'players30d', 'players90d', 'lastActive'], top 3 --}}
<div class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
    <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// MOST ACTIVE SERVER</div>
    <ul class="mt-4 space-y-3.5">
        @foreach ($data as $server)
            @php
                $rankColor = match ($server['rank']) {
                    1 => 'text-hud-green',
                    2 => 'text-hud-cyan',
                    default => 'text-hud-gold',
                };
            @endphp
            <li class="flex items-center justify-between gap-3 border-b border-white/5 pb-3.5 last:border-b-0 last:pb-0">
                <div class="flex items-center gap-3">
                    <span class="text-lg font-bold {{ $rankColor }}">{{ $server['rank'] }}</span>
                    <div>
                        <div class="text-sm font-semibold text-hud-text-bright">{{ $server['name'] }}</div>
                        <div class="mt-0.5 font-mono text-[9px] text-hud-text-dim">{{ strtoupper($server['lastActive']) }}</div>
                    </div>
                </div>
                <div class="flex-none text-right font-mono text-[10px] text-hud-text-dim">
                    <div>{{ $server['players30d'] }} <span class="text-hud-text-faint">/ 30D</span></div>
                    <div>{{ $server['players90d'] }} <span class="text-hud-text-faint">/ 90D</span></div>
                </div>
            </li>
        @endforeach
    </ul>
</div>
