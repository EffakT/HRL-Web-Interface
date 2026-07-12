{{-- Expects: $data — list of ['player', 'note'] --}}
<div class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
    <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// PLAYER ACHIEVEMENTS</div>
    <ul class="mt-4 space-y-3 font-mono text-[12px] leading-relaxed text-hud-text">
        @foreach ($data as $achievement)
            <li class="flex gap-2"><span class="text-hud-green">▸</span> <span class="font-semibold text-hud-text-bright">{{ $achievement['player'] }}</span>&nbsp;— {{ $achievement['note'] }}</li>
        @endforeach
    </ul>
</div>
