<div class="mx-auto max-w-[1080px] px-6 py-14">

        <div class="mb-3 font-mono text-[11px] font-semibold tracking-[0.34em] text-hud-cyan">// SERVERS</div>
        <div class="flex flex-wrap items-end justify-between gap-5">
            <h1 class="text-4xl font-bold uppercase leading-[0.98] text-hud-text-bright tp:text-5xl">Opted-In Servers</h1>
            <div class="flex flex-wrap gap-2.5">
                <span class="border border-hud-green/16 bg-hud-green/4 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.12em] text-hud-text-muted">
                    {{ $onlineCount }} ONLINE
                </span>
                <span class="border border-hud-green/16 bg-hud-green/4 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.12em] text-hud-text-muted">{{ number_format($totalPlayers) }} PLAYERS</span>
                <span class="border border-hud-green/16 bg-hud-green/4 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.12em] text-hud-text-muted">{{ number_format($lapsToday) }} LAPS TODAY</span>
            </div>
        </div>

        <!-- featured server -->
        <div class="hud-clip relative mt-6 flex flex-col gap-6 border border-hud-green/50 bg-[linear-gradient(115deg,#0f1d16,#0a140f)] px-6 py-5.5 shadow-[0_0_54px_-14px_theme(colors.hud-green/50%)] tp:flex-row tp:items-center tp:justify-between">
            <div class="min-w-0">
                <div class="mb-3 flex flex-wrap items-center gap-2.5">
                    <span class="border border-hud-green/32 bg-hud-green/12 px-2.5 py-1 font-mono text-[9px] font-semibold tracking-[0.2em] text-hud-green"><span class="-translate-y-px inline-block">◆</span> MOST ACTIVE</span>
                    @if ($featured['online'] ?? false)
                        <span class="inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold tracking-[0.1em] text-hud-green">
                            <span class="-mt-px h-1.5 w-1.5 rounded-full bg-hud-green shadow-[0_0_10px_theme(colors.hud-green)]"></span>
                            ACTIVE
                        </span>
                    @endif
                </div>
                <div class="truncate text-3xl font-bold tracking-[0.02em] text-white">{{ $featured['name'] }}</div>
                <div class="mt-2 font-mono text-[11px] text-hud-text-dim">NOW PLAYING · <span class="text-hud-cyan">{{ $featured['map'] }}</span></div>
            </div>
            <div class="flex flex-wrap items-start gap-y-4 gap-x-7">
                <div class="flex flex-wrap items-start gap-y-4 gap-x-7">
                    <div>
                        <div class="mb-2 font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">PLAYERS</div>
                        <div class="font-mono text-2xl font-extrabold text-hud-text-bright">{{ $featured['players'] }}</div>
                        <div class="mt-2 h-1.5 w-[120px] bg-white/8">
                            <div class="h-full bg-hud-green" style="width: {{ $featured['playersPct'] }}%"></div>
                        </div>
                    </div>
                    <div class="max-mm:w-full">
                        <div class="mb-2 font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">BEST LAP</div>
                        <div class="font-mono text-2xl font-extrabold text-hud-text-bright">{{ $featured['best'] }}</div>
                    </div>
                </div>
                <a href="{{ route('servers.show', ['serverId' => $featured['id']]) }}"
                   class="hud-clip-sm self-center whitespace-nowrap bg-hud-green px-5.5 py-3.5 text-center font-mono text-[11px] font-semibold tracking-[0.14em] text-[#04140d] transition hover:-translate-y-0.5 hover:shadow-[0_0_26px_-4px_theme(colors.hud-green/80%)] max-ml:w-full">
                    VIEW BOARD
                </a>
            </div>
        </div>

        <!-- desktop table -->
        <div class="mt-6 hidden tp:block">
            <div class="grid grid-cols-[40px_1fr_168px_140px_84px_118px] gap-3 border-b border-hud-green/16 px-3.5 py-2.5 font-mono text-[9px] font-semibold tracking-[0.16em] text-hud-text-dim">
                <span></span><span>SERVER</span><span>NOW PLAYING</span><span>PLAYERS</span><span class="text-right">LAPS</span><span class="text-right">BEST LAP</span>
            </div>
            @foreach ($servers as $server)
                <a href="{{ route('servers.show', ['serverId' => $server['id']]) }}"
                   class="grid grid-cols-[40px_1fr_168px_140px_84px_118px] items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 transition hover:border-l-hud-green hover:bg-hud-green/7">
                    <span class="mx-auto h-2.5 w-2.5 rounded-full {{ $server['online'] ? 'bg-hud-green shadow-[0_0_8px_rgba(52,227,155,.7)]' : 'bg-hud-text-faint' }}"></span>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold tracking-[0.02em] text-hud-text">{{ $server['name'] }}</div>
                    </div>
                    <span class="truncate font-mono text-[11px] {{ $server['online'] ? 'text-hud-cyan' : 'text-hud-text-faint' }}">{{ $server['map'] }}</span>
                    <div class="flex items-center gap-2.5">
                        <div class="h-1.5 w-20 bg-white/8">
                            <div class="h-full {{ $server['online'] ? 'bg-hud-green' : 'bg-hud-text-faint' }}" style="width: {{ $server['playersPct'] }}%"></div>
                        </div>
                        <span class="min-w-9 font-mono text-[11px] font-semibold text-hud-text-muted">{{ $server['players'] }}</span>
                    </div>
                    <span class="text-right font-mono text-[13px] font-bold text-hud-text">{{ $server['laps'] }}</span>
                    <span class="text-right font-mono text-[14px] font-bold text-hud-text-bright">{{ $server['best'] }}</span>
                </a>
            @endforeach
            <div class="flex items-center justify-between px-3.5 py-4 font-mono text-[10px] tracking-[0.1em] text-hud-text-dim">
                <span>SHOWING {{ count($servers) }} / {{ count($servers) }} SERVERS</span>
            </div>
        </div>

        <!-- mobile list -->
        <div class="mt-6 tp:hidden">
            @foreach ($servers as $server)
                <a href="{{ route('servers.show', ['serverId' => $server['id']]) }}"
                   class="flex items-center gap-3 border-b border-white/5 border-l-2 border-l-transparent px-3.5 py-3.5 transition hover:border-l-hud-green hover:bg-hud-green/7">
                    <span class="h-2 w-2 flex-none rounded-full {{ $server['online'] ? 'bg-hud-green shadow-[0_0_8px_rgba(52,227,155,.7)]' : 'bg-hud-text-faint' }}"></span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold text-hud-text">{{ $server['name'] }}</div>
                        <div class="mt-0.5 truncate font-mono text-[9px] {{ $server['online'] ? 'text-hud-cyan' : 'text-hud-text-faint' }}">{{ $server['map'] }}</div>
                    </div>
                    <div class="flex-none text-right">
                        <div class="font-mono text-[13px] font-bold text-hud-text-bright">{{ $server['best'] }}</div>
                        <div class="mt-0.5 font-mono text-[9px] text-hud-text-muted">{{ $server['players'] }}</div>
                    </div>
                </a>
            @endforeach
        </div>

    </div>
