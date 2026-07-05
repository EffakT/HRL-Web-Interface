<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'hash', 'user_id'])]
class Player extends Model
{
    /** @use HasFactory<\Database\Factories\PlayerFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @see \App\Models\Server::players() — `players_servers` has duplicate pivot rows, see docs/database.md.
     *
     * @return BelongsToMany<Server, $this>
     */
    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'players_servers')->distinct();
    }

    /** @return HasMany<LapTime, $this> */
    public function lapTimes(): HasMany
    {
        return $this->hasMany(LapTime::class);
    }

    /** @return HasMany<PlayerClaim, $this> */
    public function claims(): HasMany
    {
        return $this->hasMany(PlayerClaim::class);
    }
}
