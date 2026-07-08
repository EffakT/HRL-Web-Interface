<?php

namespace App\Models;

use Database\Factories\MapFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'label', 'checkpoint_count'])]
class Map extends Model
{
    /** @use HasFactory<MapFactory> */
    use HasFactory;

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
}
