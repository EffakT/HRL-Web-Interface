<x-layout title="Opt-In" active="opt-in">
    <div class="mx-auto max-w-[720px] px-6 py-14">

        <div class="mb-3 font-mono text-[11px] font-semibold tracking-[0.34em] text-hud-cyan">// OPT-IN</div>
        <h1 class="text-4xl font-bold uppercase leading-[0.98] text-hud-text-bright tp:text-5xl">Opt Your Server In</h1>
        <p class="mt-4 font-mono text-[13px] leading-relaxed text-hud-text-dim">
            To participate in HRL, server administrators must install a Lua script on their Halo dedicated server.
        </p>

        <a href="https://hrl.effakt.info/files/files.zip"
           class="hud-clip-sm mt-6 inline-block bg-hud-green px-5.5 py-3.5 font-mono text-[11px] font-semibold tracking-[0.14em] text-[#04140d] transition hover:-translate-y-0.5 hover:shadow-[0_0_26px_-4px_theme(colors.hud-green/80%)]">
            DOWNLOAD HRL FILES ►
        </a>

        <div class="mt-10 hud-clip border border-hud-green/20 bg-gradient-to-b from-[#0f1d16] to-[#0a140f] px-6 py-6">
            <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// REQUIREMENTS</div>
            <ul class="mt-4 space-y-2.5 font-mono text-[12px] leading-relaxed text-hud-text">
                <li class="flex gap-2"><span class="text-hud-green">▸</span> Halo Dedicated Server</li>
                <li class="flex gap-2"><span class="text-hud-green">▸</span> Halo Server App (SAPP)</li>
            </ul>
        </div>

        <div class="mt-6">
            <div class="mb-4 font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-cyan">// SETUP STEPS</div>
            <div class="hud-clip border border-hud-green/14 bg-hud-panel">
                @foreach ([
                    'Download the HRL files above',
                    'Copy hrl_api.dll to your exe folder (where your haloded.exe file is)',
                    'Copy the lua folder to your exe folder',
                    'Copy hrl.lua to your lua folder (inside your SAPP configuration)',
                    'If using a non-standard port, modify hrl.lua and adjust the server_port variable accordingly',
                    'You may need to update your init.txt file to contain "lua 1" if it does not already contain this',
                    'Update your init.txt file to contain "lua_load hrl" after "lua 1"',
                ] as $index => $step)
                    <div class="flex gap-4 border-b border-hud-green/8 px-5 py-3.5 last:border-b-0">
                        <span class="flex-none font-mono text-[13px] font-bold text-hud-green">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="font-mono text-[12px] leading-relaxed text-hud-text">{{ $step }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-10 hud-clip border border-hud-gold/22 bg-gradient-to-b from-[#171410] to-[#0e0b08] px-6 py-6">
            <div class="font-mono text-[10px] font-semibold tracking-[0.2em] text-hud-gold">// IMPORTANT NOTES</div>
            <ul class="mt-4 space-y-2.5 font-mono text-[12px] leading-relaxed text-[#e6ddc9]">
                <li class="flex gap-2"><span class="text-hud-gold">▸</span> Your server may take up to 15 minutes after the first lap is completed to appear on the leaderboard</li>
                <li class="flex gap-2"><span class="text-hud-gold">▸</span> GameServers.com hosting customers may need to contact support for installation assistance</li>
                <li class="flex gap-2"><span class="text-hud-gold">▸</span> <span>If your server hasn't appeared 15 minutes after your first recorded lap, <a href="{{ route('contact') }}" class="font-semibold text-[#f6eede] underline decoration-hud-gold/50 underline-offset-2 transition hover:decoration-hud-gold">get in touch</a></span></li>
            </ul>
        </div>

    </div>
</x-layout>
