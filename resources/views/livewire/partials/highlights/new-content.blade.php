{{-- Expects: $data — list of ['type' => 'map'|'server', 'name', 'ago'] --}}
<div class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
    <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// NEW MAPS &amp; SERVERS</div>
    <ul class="mt-4 space-y-3.5">
        @foreach ($data as $entry)
            <li class="flex items-center justify-between gap-3 border-b border-white/5 pb-3.5 last:border-b-0 last:pb-0">
                <div class="min-w-0">
                    <span class="mr-2 border border-hud-green/32 bg-hud-green/12 px-2 py-0.5 font-mono text-[9px] font-semibold tracking-[0.14em] text-hud-green">{{ strtoupper($entry['type']) }}</span>
                    <span class="text-sm font-semibold text-hud-text-bright">{{ $entry['name'] }}</span>
                </div>
                <span class="flex-none font-mono text-[9px] tracking-[0.1em] text-hud-text-faint">{{ strtoupper($entry['ago']) }}</span>
            </li>
        @endforeach
    </ul>
</div>
