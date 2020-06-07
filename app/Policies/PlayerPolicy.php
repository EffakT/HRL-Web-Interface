<?php

namespace App\Policies;

use App\Player;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class PlayerPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can reset the player's laps.
     *
     * @param User $user
     * @param Player $player
     * @return mixed
     */
    public function reset(User $user, Player $player)
    {
        return $player->isClaimedBy($user)
            ? Response::allow()
            : Response::deny('You have not claimed this player.');
    }

    /**
     * Determine whether the user can delete the player.
     *
     * @param User $user
     * @param Player $player
     * @return mixed
     */
    public function delete(User $user, Player $player)
    {
        return $player->isClaimedBy($user)
            ? Response::allow()
            : Response::deny('You have not claimed this player.');
    }

    /**
     * Determine whether the user can claim the player.
     *
     * @param User $user
     * @param Player $player
     * @return mixed
     */
    public function claim(User $user, Player $player)
    {
        return !$player->isClaimedBy($user)
            ? Response::allow()
            : Response::deny('This player is already claimed.');
    }
}
