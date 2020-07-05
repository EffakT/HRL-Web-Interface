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
            $query->select('servers.*', DB::raw("(SELECT `lap_times`.`updated_at` FROM `lap_times` WHERE `lap_times`.`server_id` = `servers`.`id` ORDER BY `lap_times`.`updated_at` DESC LIMIT 1 ) as latest_lap"));
            $query->whereNull('deleted_at');

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

            $query = LapTime::has('server')->eloquentQuery($sortBy, $orderBy, $searchValue,
                [
                    "map",
                    "server",
                    "player"
                ]);

            $query->where('lap_times.server_id', $server->id);
            $query->select('lap_times.*');

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

            $query = LapTime::has('server')->eloquentQuery($sortBy, $orderBy, $searchValue,
                [
                    "map",
                    "server",
                    "player"
                ]);

            $query->where('lap_times.map_id', $map->id);
            $query->select('lap_times.*');

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
            $query = LapTime::has('server')->eloquentQuery($sortBy, $orderBy, $searchValue,
                [
                    "map",
                    "server",
                    "player"
                ]);

            $query->where('player_id', $player->id);
            $query->select('lap_times.*');

            $data = $query->paginate($length);


            return new DataTableCollectionResource($data);

        }
        return view('leaderboard.player', compact('player'));
    }


}
