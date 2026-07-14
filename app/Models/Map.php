<?php

namespace App\Models;

use Database\Factories\MapFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

#[Fillable(['name', 'label', 'checkpoint_count'])]
class Map extends Model
{
    /** @use HasFactory<MapFactory> */
    use HasFactory;

    #[Override]
    protected function casts(): array
    {
        return [
            'checkpoint_count' => 'integer',
        ];
    }

    /**
     * @see Server::maps() — `servers_maps` has duplicate pivot rows, see docs/database.md.
     *
     * @return BelongsToMany<Server, $this>
     */
    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'servers_maps')->distinct();
    }

    /** @return HasMany<LapTime, $this> */
    public function lapTimes(): HasMany
    {
        return $this->hasMany(LapTime::class);
    }

    /**
     * Lets a route like `/maps/{map}/leaderboard` accept either the numeric id or the map's real
     * `name` (e.g. `bloodgulch`) — a caller that already knows the name (the common case, since
     * that's what a game server or a human recognizes) doesn't need a prior GET /api/v1/maps
     * round trip just to look up the id first. `ctype_digit` rather than `is_numeric` deliberately
     * excludes `"1.5"`/`"-1"`/scientific notation — a real id is always a plain positive integer
     * string, and `maps.name` has its own real-world values that could otherwise collide with a
     * looser numeric check (unlikely today, but this is the correct check regardless).
     */
    #[Override]
    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= ctype_digit((string) $value) ? $this->getRouteKeyName() : 'name';

        return $this->resolveRouteBindingQuery($this, $value, $field)->first();
    }
}
