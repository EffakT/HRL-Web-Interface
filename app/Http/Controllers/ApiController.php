<?php

namespace App\Http\Controllers;

use App\Http\Resources\MapLapResource;
use App\Http\Resources\MapResource;
use App\Http\Resources\PlayerLapResource;
use App\Http\Resources\PlayerResource;
use App\Http\Resources\ServerLapResource;
use App\Http\Resources\ServerResource;
use App\Jobs\ProcessNewLap;
use App\LapTime;
use App\Map;
use App\Player;
use App\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class ApiController
 * @package App\Http\Controllers
 */
class ApiController extends Controller
{

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function newTime(Request $request)
    {
        $data = [];
        $data['user_ip'] = $request->ip();
        $data['data'] = $request->all();

        if (isset($data['data']['test'])):
            $process = new ProcessNewLap($data);
            $process->handle();
        else:
            ProcessNewLap::dispatch($data);
        endif;

        return response()->json(['success' => true]);
    }


    /**
     * List Servers
     * Get a paginated list of all servers
     *
     *
     * @group Servers
     * @authenticated
     *
     * @response {"data":[{"id":4,"ip":"163.47.230.216","port":"2302","name":"Halo Race Leaderboard - Demo Server 04.04.20","created_at":"2020-04-02T23:14:44.000000Z","latest_lap":{"id":1096,"time":"100.00","date":"2020-04-11T00:00:00.000000Z","map":{"id":1,"name":"bloodgulch","label":"Bloodgulch"},"player":{"id":143,"name ":"EffakT"}}}],"links":{"first":"https://haloraceleaderboard.effakt.info/api/servers?page=1","last":"https://haloraceleaderboard.effakt.info/api/servers?page=1","prev":null,"next":null},"meta":{"current_page":1,"from":1,"last_page":1,"path":"https://haloraceleaderboard.effakt.info/api/servers","per_page":"10","to":1,"total":1}}
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function servers(Request $request)
    {
        $length = $request->input('length') ?? "10";
        $sortBy = $request->input('column') ?? "name";
        $orderBy = $request->input('dir') ?? "asc";
        $searchValue = $request->input('search') ?? "";

        $query = Server::eloquentQuery($sortBy, $orderBy, $searchValue);
        $query->select('servers.*', DB::raw("(SELECT `lap_times`.`updated_at` FROM `lap_times` WHERE `lap_times`.`server_id` = `servers`.`id` ORDER BY `lap_times`.`updated_at` DESC LIMIT 1 ) as latest_lap"));
        $query->whereNull('deleted_at');

        $data = $query->paginate($length);

        return ServerResource::collection($data);
    }

    /**
     * Get Server
     * Get a specific server and a list of its lap times
     *
     * @group Servers
     * @authenticated
     *
     * @response {"data":{"id":4,"ip":"163.47.230.216","port":"2302","name":"Halo Race Leaderboard - Demo Server 04.04.20","created_at":"2020-04-02T23:14:44.000000Z","latest_lap":{"id":1096,"time":"100.00","date":"2020-04-11T00:00:00.000000Z","map":{"id":1,"name":"bloodgulch","label":"Bloodgulch"},"player":{"id":143,"name ":"EffakT"}}},"laps":[{"id":9,"time":"38.87","date":"2018-07-05T00:00:00.000000Z","map":{"id":9,"name":"chillout","label":"Chillout"},"player":{"id":6,"name ":"HLN«ßÕX3R»"}},{"id":9,"time":"42.90","date":"2018-04-21T00:00:00.000000Z","map":{"id":9,"name":"chillout","label":"Chillout"},"player":{"id":5,"name ":"©opyrite"}}]}
     *
     * @param Server $server
     * @param Request $request
     * @return ServerResource
     */
    public function server(Server $server, Request $request)
    {

        $res = new ServerResource($server);

        $length = $request->input('length') ?? "10";
        $sortBy = $request->input('column') ?? "time";
        $orderBy = $request->input('dir') ?? "asc";
        $searchValue = $request->input('search') ?? "";

        $query = LapTime::eloquentQuery($sortBy, $orderBy, $searchValue);
        $query->join('players', 'players.id', '=', 'lap_times.player_id')
            ->join('maps', 'maps.id', '=', 'lap_times.map_id')
            ->where('lap_times.server_id', $server->id)
            ->select('lap_times.*', 'players.id', 'maps.id');

        $data = $query->paginate($length);


        return $res->additional(['laps' => ServerLapResource::collection($data)]);

    }

    /**
     * List Players
     * Get a paginated list of all players
     * @group Players
     * @authenticated
     *
     *
     * @response {"data":[{"id":5,"name ":"©opyrite"},{"id":6,"name ":"HLN«ßÕX3R»"},{"id":7,"name ":"WarNeverDie"},{"id":8,"name ":"CryForce"},{"id":9,"name ":"destroyer"},{"id":10,"name ":"GåþøFêîk¬£Q"},{"id":11,"name ":"Pretty Girl"},{"id":12,"name ":"Fooch"},{"id":13,"name ":"Mr Hankey"},{"id":14,"name ":"Malleus"}],"links":{"first":"https://haloraceleaderboard.effakt.info/api/players?page=1","last":"https://haloraceleaderboard.effakt.info/api/players?page=14","prev":null,"next":"https://haloraceleaderboard.effakt.info/api/players?page=2"},"meta":{"current_page":1,"from":1,"last_page":14,"path":"https://haloraceleaderboard.effakt.info/api/players","per_page":"10","to":10,"total":140}}
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function players(Request $request)
    {
        $length = $request->input('length') ?? "10";
        $sortBy = $request->input('column') ?? "id";
        $orderBy = $request->input('dir') ?? "asc";
        $searchValue = $request->input('search') ?? "";

        $query = Player::eloquentQuery($sortBy, $orderBy, $searchValue);
        $query->select('id', 'name');
        $data = $query->paginate($length);

        return PlayerResource::collection($data);
    }

    /**
     * Get Player
     * Get a specific player and a list of their lap times
     * @group Players
     * @authenticated
     *
     *
     * @response {"data":{"id":5,"name ":"©opyrite"},"laps":[{"id":912,"time":"42.90","date":"2018-04-21T00:00:00.000000Z","map":{"id":9,"name":"chillout","label":"Chillout"},"server":{"id":4,"ip":"163.47.230.216","port":"2302","name":"Halo Race Leaderboard - Demo Server 04.04.20"}},{"id":875,"time":"47.27","date":"2020-04-03T00:00:00.000000Z","map":{"id":3,"name":"timberland","label":"Timberland"},"server":{"id":4,"ip":"163.47.230.216","port":"2302","name":"Halo Race Leaderboard - Demo Server 04.04.20"}}]}
     *
     * @param Player $player
     * @param Request $request
     * @return PlayerResource
     */
    function player(Player $player, Request $request)
    {

        $res = new PlayerResource($player);

        $length = $request->input('length') ?? "10";
        $sortBy = $request->input('column') ?? "time";
        $orderBy = $request->input('dir') ?? "asc";
        $searchValue = $request->input('search') ?? "";

        $query = LapTime::eloquentQuery($sortBy, $orderBy, '');
        //$query = LapTime::queryBuilderQuery($sortBy, $orderBy, '');

        $query ->join('servers', 'servers.id', '=', 'lap_times.server_id')
            ->where('lap_times.player_id', $player->id)
            ->whereNull('servers.deleted_at')
            ->select('lap_times.*');

        $data = $query->paginate($length);

        return $res->additional(['laps' => PlayerLapResource::collection($data)]);
    }

    /**
     * List Maps
     * Get a paginated list of all maps
     *
     * @group Maps
     * @authenticated
     *
     *
     * @response {"data":[{"id":1,"name":"bloodgulch","label":"Bloodgulch"},{"id":2,"name":"dangercanyon","label":"Danger Canyon"},{"id":3,"name":"timberland","label":"Timberland"},{"id":4,"name":"deathisland","label":"Death Island"},{"id":5,"name":"gephyrophobia","label":"Gephyrophobia"},{"id":6,"name":"icefields","label":"Ice Fields"},{"id":7,"name":"infinity","label":"Infinity"},{"id":8,"name":"sidewinder","label":"Sidewinder"},{"id":9,"name":"chillout","label":"Chillout"},{"id":10,"name":"bloodgulch","label":"Bloodgulch - Any Order"}],"links":{"first":"https://haloraceleaderboard.effakt.info/api/maps?page=1","last":"https://haloraceleaderboard.effakt.info/api/maps?page=1","prev":null,"next":null},"meta":{"current_page":1,"from":1,"last_page":1,"path":"https://haloraceleaderboard.effakt.info/api/maps","per_page":"10","to":10,"total":10}}
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function maps(Request $request) {
        $length = $request->input('length') ?? "10";
        $sortBy = $request->input('column') ?? "id";
        $orderBy = $request->input('dir') ?? "asc";
        $searchValue = $request->input('search') ?? "";

        $query = Map::eloquentQuery($sortBy, $orderBy, $searchValue);
        $query->select('*');
        $data = $query->paginate($length);

        return MapResource::collection($data);
    }

    /**
     * Get Map
     * Get a map and a list of its lap times
     *
     * @group Maps
     * @authenticated
     *
     *
     * @response {"data":{"id":1,"name":"bloodgulch","label":"Bloodgulch"},"laps":[{"id":886,"time":"62.10","date":"2018-07-05T00:00:00.000000Z","player":{"id":6,"name ":"HLN«ßÕX3R»"},"server":{"id":4,"ip":"163.47.230.216","port":"2302","name":"Halo Race Leaderboard - Demo Server 04.04.20"}},{"id":876,"time":"65.37","date":"2018-03-01T00:00:00.000000Z","player":{"id":5,"name ":"©opyrite"},"server":{"id":4,"ip":"163.47.230.216","port":"2302","name":"Halo Race Leaderboard - Demo Server 04.04.20"}}]}
     *
     * @param Map $map
     * @param Request $request
     * @return MapResource
     */
    public function map(Map $map, Request $request) {
        $res = new MapResource($map);

        $length = $request->input('length') ?? "10";
        $sortBy = $request->input('column') ?? "time";
        $orderBy = $request->input('dir') ?? "asc";
        $searchValue = $request->input('search') ?? "";

        $query = LapTime::eloquentQuery($sortBy, $orderBy, $searchValue);

        $query->join('servers', 'servers.id', '=', 'lap_times.server_id')
            ->where('lap_times.map_id', $map->id)
            ->whereNull('servers.deleted_at')
            ->select('lap_times.*');

        $data = $query->paginate($length);

        return $res->additional(['laps' => MapLapResource::collection($data)]);
    }
}
