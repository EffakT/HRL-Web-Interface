{{-- Expects: $data — ['totalLaps', 'activePlayers30d', 'activePlayers90d', 'activeServers30d', 'activeServers90d', 'mapsToday', 'mapsThisWeek'] --}}
<div class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
    <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// LIVE STATS SNAPSHOT</div>
    <div class="mt-4 grid grid-cols-2 gap-4">
        <div>
            <div class="font-mono text-xl font-bold text-hud-text-bright">{{ number_format($data['totalLaps']) }}</div>
            <div class="mt-1 font-mono text-[9px] tracking-[0.1em] text-hud-text-dim">TOTAL LAPS</div>
        </div>
        <div>
            <div class="font-mono text-xl font-bold text-hud-text-bright">{{ $data['activePlayers30d'] }} / {{ $data['activePlayers90d'] }}</div>
            <div class="mt-1 font-mono text-[9px] tracking-[0.1em] text-hud-text-dim">ACTIVE PLAYERS 30D/90D</div>
        </div>
        <div>
            <div class="font-mono text-xl font-bold text-hud-text-bright">{{ $data['activeServers30d'] }} / {{ $data['activeServers90d'] }}</div>
            <div class="mt-1 font-mono text-[9px] tracking-[0.1em] text-hud-text-dim">ACTIVE SERVERS 30D/90D</div>
        </div>
        <div>
            <div class="font-mono text-xl font-bold text-hud-text-bright">{{ $data['mapsToday'] }} / {{ $data['mapsThisWeek'] }}</div>
            <div class="mt-1 font-mono text-[9px] tracking-[0.1em] text-hud-text-dim">MAPS TODAY/WEEK</div>
        </div>
    </div>
</div>
