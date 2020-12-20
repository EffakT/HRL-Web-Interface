<?php

namespace App\Jobs;

use App\Mail\ServerOutage;
use App\Mail\ServerOutageAvailable;
use App\Server;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckServerOutages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $now = Carbon::now();
        $sub24hr = $now->copy()->subDays(1);
        //get servers with notify enabled, and not sent in the last day
        $servers = Server::where('notify_outage', 1)->where(function ($query) use ($sub24hr) {
            $query->where('notify_outage_last', '<', $sub24hr)
                ->orWhereNull('notify_outage_last');
        })->get();
        $servers->each(function ($server) use ($now) {
            if (!$this->queryServer($server)):
                //send email to claimed user
                $server->notify_outage_last = $now;
                $server->save();
                \Mail::to($server->isClaimed()->user->email)->send(new ServerOutage($server));
            else:
                if (!is_null($server->notify_outage_last)):
                    \Mail::to($server->isClaimed()->user->email)->send(new ServerOutageAvailable($server));
                endif;
            endif;
        });
    }

    private function queryServer($server)
    {
        //Query the Halo Server for all details
        $buffer = " ";
        $query = new \App\Helpers\QueryServer($buffer, trim($server->ip), (int)$server->port);
        if (($response = $query->runQuery()) === false):
            return false;
        endif;
        return true;
    }
}
