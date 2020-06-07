<?php

namespace App\Jobs;

use App\PlayerClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ClearPlayerClaim implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(PlayerClaim $claim)
    {
        $this->claim = $claim;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //check if claim is not approved
        if (!$this->claim->is_claimed)
            return $this->claim->delete();

        return false;
    }
}
