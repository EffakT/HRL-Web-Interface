<?php

namespace App\Jobs;

use App\Player;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPlayerClaim implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $request;
    public $timeout = 30;
    public $tries = 5;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($request)
    {
        $this->request = $request;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $hash = hash('sha256', $this->request['data']['player_hash']);
        $hash = $this->request['data']['player_hash'];

        //find the player, or ignore
        $player = Player::where('hash', $hash)->get()->first();
        if (is_null($player)):
            return false;
        endif;

        //if not pending claim
        if (!$player->isPendingClaim()):
            return false;
        endif;

        $code = $this->request['data']['code'];

        //find claim with code, if no claim, ignore
        $claim = $player->pendingClaims->firstWhere('claim_code', $code);
        if (!$claim):
            return false;
        endif;

        //claim the player successfully
        $claim->claimed_at = new Carbon();
        $claim->save();
        return true;
    }
}
