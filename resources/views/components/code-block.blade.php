@props(['label' => null])
<div {{ $attributes->class(['mt-4 hud-clip overflow-hidden border border-hud-green/14 bg-hud-panel']) }}>
    @if ($label)
        <div class="border-b border-hud-green/10 px-5 py-2 font-mono text-[10px] font-semibold tracking-[0.16em] text-hud-cyan">{{ $label }}</div>
    @endif
    <pre class="overflow-x-auto px-5 py-4 font-mono text-[11.5px] leading-relaxed text-hud-text"><code>{{ $slot }}</code></pre>
</div>
