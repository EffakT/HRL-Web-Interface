<?php

namespace App\Policies;

use App\Server;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ServerPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can reset the server's laps.
     *
     * @param User $user
     * @param Server $server
     * @return mixed
     */
    public function reset(User $user, Server $server)
    {
        return $server->isClaimedBy($user);
    }

    /**
     * Determine whether the user can migrate the server's laps.
     *
     * @param User $user
     * @param Server $server
     * @return mixed
     */
    public function migrate(User $user, Server $server)
    {
        return $server->isClaimedBy($user);
    }
}