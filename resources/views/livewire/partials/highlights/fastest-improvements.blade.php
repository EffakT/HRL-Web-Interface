{{-- Expects: $data — list of ['text'] describing a PB improvement, rank jump, or new Top 10 entry --}}
<div class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
    <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// FASTEST IMPROVEMENTS</div>
    <ul class="mt-4 space-y-3 font-mono text-[12px] leading-relaxed text-hud-text">
        @foreach ($data as $item)
            <li class="flex gap-2"><span class="text-hud-green">▸</span> {{ $item['text'] }}</li>
        @endforeach
    </ul>
</div>
