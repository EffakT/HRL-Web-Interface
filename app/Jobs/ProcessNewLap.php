<?php

namespace App\Jobs;

use Log;
use App\LapTime;
use App\Map;
use App\Player;
use App\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessNewLap implements ShouldQueue
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
        //Query the Halo Server for all details
        $buffer = " ";

        $query = new \App\Helpers\QueryServer($buffer, trim($this->request['user_ip']), (int)$this->request['data']['port']);
        if (($response = $query->runQuery()) === false):
            \Log::error($query->getError());
            throw new \Exception($query->getError().": ".$this->request['user_ip'].":".$this->request['data']['port']);
            return;
        endif;

        //remove broken characters from hostname
        $response['hostname'] = str_replace("", "", trim($response['hostname']));

        $map_aliases = [
            'beavercreek' => 'Battle Creek',
            'bloodgulch' => 'Bloodgulch',
            'boardingaction' => 'Boarding Action',
            'chillout' => 'Chillout',
            'putput' => 'Chiron TL-34',
            'damnation' => 'Damnation',
            'dangercanyon' => 'Danger Canyon',
            'deathisland' => 'Death Island',
            'carousel' => 'Derelict',
            'gephyrophobia' => 'Gephyrophobia',
            'hangemhigh' => 'Hang \'Em High',
            'icefields' => 'Ice Fields',
            'infinity' => 'Infinity',
            'longest' => 'Longest',
            'prisoner' => 'Prisoner',
            'ratrace' => 'Rat Race',
            'sidewinder' => 'Sidewinder',
            'timberland' => 'Timberland',
            'wizard' => 'Wizard',
        ];
        $map_label = "";
        if (isset($map_aliases[$this->request['data']['map_name']])):
            $map_label = $map_aliases[$this->request['data']['map_name']];
        else:
            $map_label = $this->request['data']['map_name'];
        endif;

        $race_types = [
            '',
            'Any Order',
            'Rally'
        ];
        if ($race_types[$this->request['data']['race_type']] != ""):
            $map_label .= " - " . $race_types[$this->request['data']['race_type']];
        endif;

        //Create server if it doesnt exist
        $server = Server::firstOrCreate(['ip' => $this->request['user_ip'], 'port' => $this->request['data']['port']]);

        //if server name is different, update it
        if ($server->name != $response['hostname']) {
            $server->name = $response['hostname'];
            $server->save();
        }

        //Create Player if not already exists
        $player = Player::firstOrCreate(['hash' => hash('sha256', $this->request['data']['player_hash']), 'name' => $this->request['data']['player_name']]);
        if (!$player->servers->contains($server->id))
            $player->servers()->attach($server->id);

        //Create Map if not already exists, and assign it to the server
        $map = Map::firstOrCreate(['name' => $this->request['data']['map_name'], 'label' => $map_label]);
        if (!$server->maps->contains($map->id))
            $server->maps()->attach($map->id);


        //Find this players laps on this map
        $lapTime = LapTime::where(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $player->id])->get();
        //If player already has a lap time, update with new time.
        if (!$lapTime->isEmpty()):
            $lapTime = $lapTime->first();
            if ($lapTime->time > $this->request['data']['player_time']):
                $lapTime->time = $this->request['data']['player_time'];
                $lapTime->save();
            endif;
        else:
            //Create LapTime
            LapTime::firstOrCreate(['server_id' => $server->id, 'map_id' => $map->id, 'player_id' => $player->id, 'time' => $this->request['data']['player_time']]);
        endif;
    }


    public function retryUntil()
    {
        return now()->addSeconds(5);
    }
}
