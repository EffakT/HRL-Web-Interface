<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['ip', 'port', 'name', 'type', 'notify_outage', 'notify_outage_last'])]
class Server extends Model
{
    /** @use HasFactory<\Database\Factories\ServerFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'notify_outage' => 'boolean',
            'notify_outage_last' => 'datetime',
        ];
    }

    /**
     * `servers_maps` has duplicate (server_id, map_id) rows in the real data (a single server
     * can have 20x+ rows for the same map) — `distinct()` is required here, not optional. See
     * docs/database.md's "Duplicate pivot rows" section. For a plain count, use
     * `distinct('maps.id')->count('maps.id')`, not a bare `->count()` — the aggregate shortcut
     * does not honor a column-less `distinct()`.
     *
     * @return BelongsToMany<Map, $this>
     */
    public function maps(): BelongsToMany
    {
        return $this->belongsToMany(Map::class, 'servers_maps')->distinct();
    }

    /**
     * @see self::maps() — same duplicate-pivot-row caveat applies to `players_servers`.
     *
     * @return BelongsToMany<Player, $this>
     */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'players_servers')->distinct();
    }

    /** @return HasMany<LapTime, $this> */
    public function lapTimes(): HasMany
    {
        return $this->hasMany(LapTime::class);
    }

    /** @return HasMany<ServerClaim, $this> */
    public function claims(): HasMany
    {
        return $this->hasMany(ServerClaim::class);
    }
}
