<x-layout title="404" description="Page not found — Halo Race Leaderboard.">
    <div class="mx-auto flex min-h-[70vh] max-w-[1080px] flex-col items-center justify-center px-6 py-14 text-center">

        <div class="mb-3 font-mono text-[11px] font-semibold tracking-[0.34em] text-hud-cyan">// ERROR 404</div>

        <div class="font-mono text-8xl font-bold leading-none text-hud-green drop-shadow-[0_0_40px_theme(colors.hud-green/50%)] tp:text-9xl">
            404
        </div>

        <h1 class="mt-4 text-3xl font-bold uppercase leading-[0.98] text-hud-text-bright tp:text-4xl">
            Off The Track
        </h1>

        <p class="mx-auto mt-4 max-w-md font-mono text-[13px] leading-relaxed text-hud-text-dim">
            This page doesn't exist, or the route's been retired. Head back to base, or jump straight to a leaderboard below.
        </p>

        <a href="{{ route('home') }}"
           class="hud-clip-sm mt-8 inline-block bg-hud-green px-5.5 py-3.5 font-mono text-[11px] font-semibold tracking-[0.14em] text-[#04140d] transition hover:-translate-y-0.5 hover:shadow-[0_0_26px_-4px_theme(colors.hud-green/80%)]">
            RETURN TO BASE ►
        </a>

        <!-- Same width/gap as Home's own Quick Links grid, so these cards render at the exact same size. -->
        <div class="mt-12 grid w-full grid-cols-1 gap-5 tp:grid-cols-3">
            <a href="{{ route('servers.index') }}" class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6 text-left transition hover:-translate-y-0.5 hover:border-hud-green/50">
                <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// SERVERS</div>
                <div class="mt-3 text-lg font-bold text-hud-text-bright">Browse Servers</div>
            </a>
            <a href="{{ route('maps.index') }}" class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6 text-left transition hover:-translate-y-0.5 hover:border-hud-green/50">
                <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// MAPS</div>
                <div class="mt-3 text-lg font-bold text-hud-text-bright">Browse Maps</div>
            </a>
            <a href="{{ route('players.index') }}" class="hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6 text-left transition hover:-translate-y-0.5 hover:border-hud-green/50">
                <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// PLAYERS</div>
                <div class="mt-3 text-lg font-bold text-hud-text-bright">Global Leaderboard</div>
            </a>
        </div>

    </div>
</x-layout>
