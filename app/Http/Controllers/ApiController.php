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

class ApiController extends Controller
{
    public function user(Request $request)
    {
        return $request->user();
    }

    public function newTime(Request $request)
    {
        $data = [];
        $data['user_ip'] = $request->ip();
        $data['data'] = $request->all();
        /*if (isset($data['data']['ip']))
            $data['uesr_ip'] = $data['data']['ip'];*/

        if (isset($data['data']['test'])):
            $process = new ProcessNewLap($data);
            $process->handle();
        else:
            ProcessNewLap::dispatch($data);
        endif;

        return response()->json(['success' => true]);
    }


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
