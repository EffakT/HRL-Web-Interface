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

    /**
     * Determine whether the user can be notified of outages of the server.
     *
     * @param User $user
     * @param Server $server
     * @return mixed
     */
    public function notifyOutage(User $user, Server $server)
    {
        return $server->isClaimedBy($user);
    }

    /**
     * Determine whether the user can delete the server.
     *
     * @param User $user
     * @param Server $server
     * @return mixed
     */
    public function delete(User $user, Server $server)
    {
        return $server->isClaimedBy($user);
    }

    /**
     * Determine whether the user can claim the server.
     *
     * @param User $user
     * @param Server $server
     * @return mixed
     */
    public function claim(User $user, Server $server)
    {
        return !$server->isClaimed();
    }

    /**
     * Determine whether the user can verify the claim on the server.
     *
     * @param User $user
     * @param Server $server
     * @return mixed
     */
    public function verifyclaim(User $user, Server $server)
    {
        return $server->isPendingClaimBy($user);
    }
}
