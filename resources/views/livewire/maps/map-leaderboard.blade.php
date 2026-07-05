<div>
    <div class="mx-auto max-w-[1080px] px-6 py-14">

        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <div class="mb-3 font-mono text-[11px] font-semibold tracking-[0.34em] text-hud-cyan">// ALL SERVERS · GLOBAL</div>
                <h1 class="text-4xl font-bold uppercase leading-[0.98] text-hud-text-bright tp:text-5xl">{{ $map }}</h1>
                <div class="mt-4 flex gap-2.5">
                    <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">TIME TRIAL</span>
                    <span class="border border-hud-green/16 px-2.5 py-1.5 font-mono text-[10px] font-semibold tracking-[0.14em] text-hud-text-muted">{{ count($players) }} LAPS</span>
                </div>
            </div>
        </div>

        @include('livewire.partials.leaderboard-podium-and-table')

    </div>

    @include('livewire.partials.lap-detail-modal')
</div>
