@props(['title' => null, 'active' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ? $title . ' | Halo Race Leaderboard' : 'Halo Race Leaderboard' }}</title>

        @if ($description ?? null)
            <meta name="description" content="{{ $description }}">
        @endif

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body x-data="{ mobileNavOpen: false }" class="min-h-screen font-sans text-hud-text">

        @php
            $navLinks = [
                ['label' => 'HOME', 'href' => route('home'), 'key' => 'home'],
                ['label' => 'SERVERS', 'href' => route('servers.index'), 'key' => 'servers'],
                ['label' => 'MAPS', 'href' => route('maps.index'), 'key' => 'maps'],
                ['label' => 'PLAYERS', 'href' => route('players.index'), 'key' => 'players'],
                ['label' => 'OPT-IN', 'href' => route('opt-in'), 'key' => 'opt-in'],
                ['label' => 'CONTACT', 'href' => route('contact'), 'key' => 'contact'],
            ];
            $disabledLinks = ['API', 'LOGIN', 'REGISTER'];
        @endphp

        <nav class="relative z-30 flex items-center justify-between gap-4 border-b border-hud-green/15 bg-gradient-to-b from-[#0c1310] to-[#080d0b] px-6 py-4">
            <div class="flex items-center gap-6">
                <a href="{{ route('home') }}" class="text-base font-bold tracking-[0.24em] text-hud-green">HRL</a>
                <div class="hidden items-center gap-5 font-mono text-[10px] font-semibold tracking-[0.14em] tp:flex">
                    @foreach ($navLinks as $link)
                        <a href="{{ $link['href'] }}"
                           class="border-b-2 py-1 {{ $active === $link['key'] ? 'border-hud-green text-hud-green' : 'border-transparent text-hud-text-dim transition hover:text-hud-text' }}">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                    @foreach ($disabledLinks as $label)
                        <span class="cursor-not-allowed border-b-2 border-transparent py-1 text-hud-text-faint" title="Not built yet">{{ $label }}</span>
                    @endforeach
                </div>
            </div>
            <div class="flex items-center gap-4 font-mono text-[11px] tracking-[0.08em] text-hud-text-dim">
                <span class="inline-flex items-center gap-1.5 text-hud-green">
                    <span class="-mt-px h-1.5 w-1.5 rounded-full bg-hud-green shadow-[0_0_10px_theme(colors.hud-green)]"></span>
                    LIVE
                </span>
                <button type="button" @click="mobileNavOpen = true" class="flex h-7 w-7 items-center justify-center border border-hud-green/25 text-hud-text-muted tp:hidden" aria-label="Open menu">
                    <span class="text-base leading-none">≡</span>
                </button>
            </div>
        </nav>

        <!-- Mobile nav overlay -->
        <div x-show="mobileNavOpen"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-40 bg-hud-bg/97 tp:hidden"
             @keydown.escape.window="mobileNavOpen = false">
            <div class="hud-scanlines pointer-events-none absolute inset-0"></div>

            <div class="relative flex items-center justify-between border-b border-hud-green/15 px-6 py-4">
                <span class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// MENU</span>
                <button type="button" @click="mobileNavOpen = false"
                        class="hud-clip-sm flex h-8 w-8 items-center justify-center border border-hud-green/30 text-hud-text-muted transition hover:border-hud-red/60 hover:text-hud-red"
                        aria-label="Close menu">
                    ✕
                </button>
            </div>

            <div class="relative flex flex-col px-6 py-8">
                @foreach ($navLinks as $link)
                    <a href="{{ $link['href'] }}" @click="mobileNavOpen = false"
                       class="border-b border-hud-green/10 py-4 text-2xl font-bold uppercase tracking-wide {{ $active === $link['key'] ? 'text-hud-green' : 'text-hud-text-bright' }}">
                        {{ $link['label'] }}
                    </a>
                @endforeach
                @foreach ($disabledLinks as $label)
                    <span class="cursor-not-allowed border-b border-hud-green/10 py-4 text-2xl font-bold uppercase tracking-wide text-hud-text-faint">
                        {{ $label }}
                    </span>
                @endforeach
            </div>
        </div>

        <main>
            {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>
