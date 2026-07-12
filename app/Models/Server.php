<?php

namespace App\Models;

use Database\Factories\ServerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property Carbon|null $notify_outage_last
 * @property Carbon|null $queried_at
 *
 * Larastan doesn't resolve a custom `casts(): array` entry into its Carbon type for
 * non-standard (non created_at/updated_at/deleted_at) column names in this project's setup —
 * confirmed via `\PHPStan\dumpType()` returning `string|null` for both fields above despite the
 * runtime cast working correctly (verified in tinker). These `@property` annotations are the
 * standard, sanctioned way to declare an Eloquent model's magic-property types for static
 * analysis (the same thing `php artisan ide-helper:models` generates) — not a suppression.
 */
#[Fillable([
    'ip', 'port', 'name', 'type', 'notify_outage', 'notify_outage_last',
    'current_map_id', 'live_player_count', 'queried_at', 'query_successful',
])]
class Server extends Model
{
    /** @use HasFactory<ServerFactory> */
    use HasFactory, SoftDeletes;

    #[Override]
    protected function casts(): array
    {
        return [
            'notify_outage' => 'boolean',
            'notify_outage_last' => 'datetime',
            'queried_at' => 'datetime',
            'query_successful' => 'boolean',
        ];
    }

    /**
     * Live-queried current map (roadmap item 19, see docs/database.md's "QueryServer UDP
     * protocol") — null if never successfully queried, or if the query's `mapname` didn't match
     * any known `Map` row (deliberately not fabricated from a live query; a `Map` row is only
     * ever created from an actual lap submission, see ProcessNewLap).
     *
     * @return BelongsTo<Map, $this>
     */
    public function currentMap(): BelongsTo
    {
        return $this->belongsTo(Map::class, 'current_map_id');
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
