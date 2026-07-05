{{-- Expects: $direction — 'up'|'down'|'flat', $delta — rank positions moved (0 if flat) --}}
@php
    [$symbol, $colorClass] = match ($direction) {
        'up' => ['▲', 'text-hud-green'],
        'down' => ['▼', 'text-hud-red'],
        default => ['–', 'text-hud-text-faint'],
    };
@endphp
<span class="inline-flex items-center gap-1 font-mono text-[11px] font-semibold {{ $colorClass }}">
    {{ $symbol }}@if ($delta > 0) {{ $delta }} @endif
</span>
