<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClaimPlayerRequest;
use App\Http\Requests\DeletePlayerRequest;
use App\Jobs\ClearPlayerClaim;
use App\Player;
use App\PlayerClaim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use JamesDordoy\LaravelVueDatatable\Http\Resources\DataTableCollectionResource;

class ManagePlayerController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request, Player $player)
    {
        $user = Auth::user();
        $players = $user->players;
        return view('manage-player/index', compact('player', 'user', 'players'));
    }

    public function claimPlayer(ClaimPlayerRequest $request, Player $player)
    {
        $user = Auth::user();

        $hasBeenClaimed = ($player->claims->count() > 0);
        if ($hasBeenClaimed):
            flash('This player has already been claimed')->error();
            return redirect(route('player:manage', $player));
        endif;

        //if current user already has a pending claim
        $userHasPendingClaim = ($player->pendingClaims->where('user_id', $user->id)->count() > 0);
        if ($userHasPendingClaim):
            flash('You already have a pending claim on this player')->error();
            return redirect(route('player:manage', $player));
        endif;


        $claim = new PlayerClaim([
            'user_id' => $user->id,
            'player_id' => $player->id,
            'claim_code' => uniqid()
        ]);
        $claim->save();
        //on create, schedule a job to clear the claim
        ClearPlayerClaim::dispatch($claim)->delay(now()->addHours(24));


        flash('Player claim has been successfully initiated')->success();
        return redirect(route('player:manage', $player));

    }


    public function myPlayers(Request $request)
    {
        $user = Auth::user();

        $length = $request->input('length') ?? "10";
        $sortBy = $request->input('column') ?? "name";
        $orderBy = $request->input('dir') ?? "asc";
        $searchValue = $request->input('search') ?? "";

        $query = Player::eloquentQuery($sortBy, $orderBy, $searchValue);
        $query->join('users_players', 'player_id', '=', 'players.id')
            ->select('players.*')
            ->where('users_players.user_id', '=', $user->id)
            ->whereNotNull('users_players.claimed_at')
            ->whereNull('players.deleted_at');

        $data = $query->paginate($length);

        return new DataTableCollectionResource($data);

    }
}
