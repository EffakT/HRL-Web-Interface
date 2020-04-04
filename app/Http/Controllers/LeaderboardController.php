<?php

namespace App\Http\Controllers;

use App\LapTime;
use App\Map;
use App\Player;
use App\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JamesDordoy\LaravelVueDatatable\Http\Resources\DataTableCollectionResource;
use Yajra\DataTables\Facades\DataTables;

class LeaderboardController extends Controller
{
    public function servers(Request $request)
    {
        if (request()->wantsJson()):
            $length = $request->input('length') ?? "10";
            $sortBy = $request->input('column') ?? "name";
            $orderBy = $request->input('dir') ?? "asc";
            $searchValue = $request->input('search') ?? "";

            $query = Server::eloquentQuery($sortBy, $orderBy, $searchValue);
            $data = $query->paginate($length);

            return new DataTableCollectionResource($data);
        endif;
        return view('leaderboard.servers');

        /*if (request()->ajax())
            return DataTables::of(Server::query())->make(true);

        return view('leaderboard.servers');*/
    }

    public function server(Server $server, Request $request)
    {
        if (request()->wantsJson()):
            $length = $request->input('length') ?? "10";
            $sortBy = $request->input('column') ?? "time";
            $orderBy = $request->input('dir') ?? "asc";
            $searchValue = $request->input('search') ?? "";
            $map = $request->input('map');


            //$query = Server::eloquentQuery($sortBy, $orderBy, $searchValue);

            $query = LapTime::queryBuilderQuery($sortBy, $orderBy, '');
            $query->join('players', 'players.id', '=', 'lap_times.player_id')
                ->join('maps', 'maps.id', '=', 'lap_times.map_id')
                ->where('lap_times.server_id', $server->id)
                ->where(function ($query) use ($searchValue) {
                    $query->where('players.name', "LIKE", "%$searchValue%")
                        ->orWhere('maps.label', "LIKE", "%$searchValue%");
                })
                ->select('lap_times.*', 'players.name', 'maps.label');

            if (isset($map) && !empty($map))
                $query->where('lap_times.map_id', $map);

            $data = $query->paginate($length);

            return new DataTableCollectionResource($data);
        endif;


        $maps = DB::table('maps')->select('maps.*')->join('servers_maps', 'servers_maps.map_id', '=', 'maps.id')->where('servers_maps.server_id', $server->id)->get();

        return view('leaderboard.server', compact('server', 'maps'));
    }


    public function maps(Request $request)
    {
        if (request()->wantsJson()) {
            $length = $request->input('length') ?? "10";
            $sortBy = $request->input('column') ?? "name";
            $orderBy = $request->input('dir') ?? "asc";
            $searchValue = $request->input('search') ?? "";

            $query = Map::eloquentQuery($sortBy, $orderBy, $searchValue);
            $data = $query->paginate($length);

            return new DataTableCollectionResource($data);
        }
        return view('leaderboard.maps');
    }

    public function map(Map $map, Request $request)
    {
        if (request()->wantsJson()) {

            $length = $request->input('length') ?? "10";
            $sortBy = $request->input('column') ?? "time";
            $orderBy = $request->input('dir') ?? "asc";
            $searchValue = $request->input('search') ?? "";

            $query = LapTime::queryBuilderQuery($sortBy, $orderBy, '');

            $query->join('players', 'players.id', '=', 'lap_times.player_id')
                ->join('servers', 'servers.id', '=', 'lap_times.server_id')
                ->join('maps', 'maps.id', '=', 'lap_times.map_id')
                ->where('lap_times.map_id', $map->id)
                ->select('lap_times.*', 'players.name', 'servers.name AS server_name', 'servers.ip', 'servers.port');

            $query->where(function ($query) use ($searchValue) {
                $query->where('players.name', "LIKE", "%$searchValue%")
                    ->orWhere('maps.label', "LIKE", "%$searchValue%")
                    ->orWhere('servers.name', "LIKE", "%$searchValue%")
                    ->orWhere('servers.ip', "LIKE", "%$searchValue%")
                    ->orWhere('servers.port', "LIKE", "%$searchValue%");
            });

            $data = $query->paginate($length);

            return new DataTableCollectionResource($data);

        }
        return view('leaderboard.map', compact('map'));
    }

    public function players(Player $player, Request $request)
    {
        if (request()->wantsJson()) {
            $length = $request->input('length') ?? "10";
            $sortBy = $request->input('column') ?? "name";
            $orderBy = $request->input('dir') ?? "asc";
            $searchValue = $request->input('search') ?? "";

            $query = Player::eloquentQuery($sortBy, $orderBy, $searchValue);
            $data = $query->paginate($length);

            return new DataTableCollectionResource($data);
        }
        return view('leaderboard.players');
    }

    public function player(Player $player, Request $request)
    {
        if (request()->wantsJson()) {

            $length = $request->input('length') ?? "10";
            $sortBy = $request->input('column') ?? "time";
            $orderBy = $request->input('dir') ?? "asc";
            $searchValue = $request->input('search') ?? "";

            $query = LapTime::queryBuilderQuery($sortBy, $orderBy, '');

            $query->join('players', 'players.id', '=', 'lap_times.player_id')
                ->join('servers', 'servers.id', '=', 'lap_times.server_id')
                ->join('maps', 'maps.id', '=', 'lap_times.map_id')
                ->where('lap_times.player_id', $player->id)
                ->select('lap_times.*', 'maps.label AS map_name', 'servers.name AS server_name', 'servers.ip', 'servers.port');

            $query->where(function ($query) use ($searchValue) {
                $query->where('players.name', "LIKE", "%$searchValue%")
                    ->orWhere('maps.label', "LIKE", "%$searchValue%")
                    ->orWhere('servers.name', "LIKE", "%$searchValue%")
                    ->orWhere('servers.ip', "LIKE", "%$searchValue%")
                    ->orWhere('servers.port', "LIKE", "%$searchValue%");
            });

            $data = $query->paginate($length);

            return new DataTableCollectionResource($data);

        }
        return view('leaderboard.player', compact('player'));
    }


}
