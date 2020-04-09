<?php

namespace App\Http\Controllers;

use App\Claim;
use App\Http\Requests\ClaimServerRequest;
use App\Http\Requests\DeleteServerRequest;
use App\Http\Requests\MigrateLapsRequest;
use App\Http\Requests\ResetLapsRequest;
use App\Jobs\ClearClaim;
use App\LapTime;
use App\Server;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use JamesDordoy\LaravelVueDatatable\Http\Resources\DataTableCollectionResource;

class ManageServerController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request, Server $server)
    {
        $user = Auth::user();
        $servers = $user->servers;
        return view('manage-server/index', compact('server', 'user', 'servers'));
    }

    public function claimServer(ClaimServerRequest $request, Server $server)
    {
        $user = Auth::user();

        $hasBeenClaimed = ($server->claims->count() > 0);
        if ($hasBeenClaimed):
            flash('This server has already been claimed')->error();
            return redirect(route('server:manage', $server));
        endif;

        //if current user already has a pending claim
        $userHasPendingClaim = ($server->pendingClaims->where('user_id', $user->id)->count() > 0);
        if ($userHasPendingClaim):
            flash('You already have a pending claim on this server')->error();
            return redirect(route('server:manage', $server));
        endif;


        $claim = new Claim([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'claim_code' => uniqid()
        ]);
        $claim->save();
        //on create, schedule a job to clear the claim
        ClearClaim::dispatch($claim)->delay(now()->addHours(24));


        flash('Server claim has been successfully initiated')->success();
        return redirect(route('server:manage', $server));

    }

    public function resetLaps(ResetLapsRequest $request, Server $server)
    {
        $server->laps()->delete();

        flash('Server lap times have been successfully reset')->success();
        return redirect(route('server:manage', $server));
    }

    public function migrateLaps(MigrateLapsRequest $request, Server $server)
    {
        $user = Auth::user();
        $toServer = Server::where('id', $request->post('to-server'))->get();

        //if server found
        if (!$toServer->count()):
            flash('The selected server could not be found')->error();
            return redirect(route('server:manage', $server));
        endif;
        $toServer = $toServer->first();

        //if server claimed by user
        if (!$toServer->isClaimedBy($user)):
            flash('The selected server is not claimed by you')->error();
            return redirect(route('server:manage', $server));
        endif;

        //copy server maps
        $maps = $server->maps;
        $toServer->maps()->syncWithoutDetaching($maps);

        //copy players
        $players = $server->players;
        $toServer->players()->syncWithoutDetaching($players);

        //copy laps - There may be a better way to do this?
        $laps = $server->laps;
        $laps->each(function ($lap) use ($toServer) {
            $newLap = $lap->replicate();
            //we don't want to update the timestamps, since we are just replicating.
            $newLap->timestamps = false;

            $newLap->fill([
                'created_at' => $lap->created_at,
                'updated_at' => $lap->updated_at,
                'server_id' => $toServer->id,
            ]);
            //check if exists
            $existingLap = LapTime::where('server_id', $newLap->server_id)
                ->where('map_id', $newLap->map_id)
                ->where('player_id', $newLap->player_id)->get();

            //if existing lap does not exist, create it
            if (!$existingLap->count()):
                $newLap->save();
            else:
                //if existing lap exists
                $existingLap = $existingLap->first();
                //check if existing time is greater then the new time
                if ($existingLap->time > $newLap->time):

                    //we don't want to update the timestamps, since we are overriding.
                    $existingLap->timestamps = false;

                    //update the time, and dates and save.
                    $existingLap->fill([
                        'time' => $newLap->time,
                        'created_at' => $newLap->created_at,
                        'updated_at' => $newLap->updated_at,
                    ]);

                    $existingLap->save();
                endif;
            endif;
        });


        flash('Server lap times have been successfully migrated')->success();
        return redirect(route('server:manage', $server));
    }


    public function delete(DeleteServerRequest $request, Server $server)
    {
        $server->delete();

        flash('Server has been successfully deleted')->success();
        return redirect(route('my-account', $server));
    }

    public function verifyClaimServer(Request $request, Server $server)
    {
        $user = Auth::user();
        $claim = $server->isPendingClaimBy($user);

        $response = $server->queryServer();
        if (!$response['success']):
            flash('Unable to access the server. Is the server running?')->error();
            return redirect(route('server:manage', $server));
        endif;
        $response = $response['response'];

        if (strpos($response['hostname'], $claim->claim_code) > -1):
            $claim->claimed_at = new Carbon();
            $claim->save();
            flash('Server Successfully Claimed')->success();
            return redirect(route('server:manage', $server));
        else:
            flash('Unable to verify server. Please make sure the claim code is in the server name')->error();
            return redirect(route('server:manage', $server));
        endif;

    }

    public function myServers(Request $request)
    {
        $user = Auth::user();

        $length = $request->input('length') ?? "10";
        $sortBy = $request->input('column') ?? "name";
        $orderBy = $request->input('dir') ?? "asc";
        $searchValue = $request->input('search') ?? "";

        $query = Server::queryBuilderQuery($sortBy, $orderBy, $searchValue);
        $query->join('users_servers', 'server_id', '=', 'servers.id')
            ->select('servers.*')
            ->where('users_servers.user_id', '=', $user->id)
            ->whereNotNull('users_servers.claimed_at')
            ->whereNull('servers.deleted_at');

        $data = $query->paginate($length);

        return new DataTableCollectionResource($data);

    }
}
