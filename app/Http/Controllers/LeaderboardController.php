<?php

namespace App\Http\Controllers;

use DB;
use App\LapTime;
use App\Map;
use App\Player;
use App\Server;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class LeaderboardController extends Controller
{
    public function servers() {
      if (request()->ajax()) {
            return DataTables::of(Server::query())->make(true);
        }
      return view('leaderboard.servers');
    }

    public function server(Server $server) {
      if (request()->ajax()) {
        $lapTimes = LapTime::select('lap_times.*', 'players.name', 'maps.label')
        ->join('players', 'players.id', '=', 'lap_times.player_id')
        ->join('maps', 'maps.id', '=', 'lap_times.map_id')
        ->where('lap_times.server_id', $server->id);
        if ($map_filter = request('map_filter')) {
          $lapTimes->where('maps.label', $map_filter);
        }

        return Datatables::of($lapTimes)->make(true);
      }

      $maps = DB::table('maps')->select('maps.*')->join('servers_maps', 'servers_maps.map_id', '=', 'maps.id')->where('servers_maps.server_id', $server->id)->get();

      return view('leaderboard.server', compact('server', 'maps'));
    }

    public function maps() {
      if (request()->ajax()) {
            return DataTables::of(Map::query())->make(true);
        }
      return view('leaderboard.maps');
    }

    public function map(Map $map) {
      if (request()->ajax()) {
        $lapTimes = LapTime::select('lap_times.*', 'players.name', 'servers.name AS server_name', 'servers.ip', 'servers.port')
        ->join('players', 'players.id', '=', 'lap_times.player_id')
        ->join('servers', 'servers.id', '=', 'lap_times.server_id')
        ->where('lap_times.map_id', $map->id);

        return Datatables::of($lapTimes)->make(true);
      }
      return view('leaderboard.map', compact('map'));
    }
}
