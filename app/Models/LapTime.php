<?php

namespace App\Models;

use Database\Factories\LapTimeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Full lap history — never pruned, never upserted. Personal bests / course records are
 * derived MIN(time) reads elsewhere, not stored on this model. See docs/database.md.
 *
 * NOTE: created_at/updated_at were widened from DATE to TIMESTAMP (see the
 * widen_lap_times_timestamps_to_datetime migration) so future lap submissions capture real
 * time-of-day — "the time we received the record" is close enough to when the lap happened.
 * Historical rows imported before this change still only have day precision (their time
 * component reads as midnight) since that detail was never captured for them; there's no way
 * to recover it retroactively. See docs/database.md's "Known constraint" section.
 */
#[Fillable(['server_id', 'map_id', 'player_id', 'time'])]
class LapTime extends Model
{
    /** @use HasFactory<LapTimeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'time' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** @return BelongsTo<Map, $this> */
    public function map(): BelongsTo
    {
        return $this->belongsTo(Map::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return HasMany<LapTimeSplit, $this> */
    public function splits(): HasMany
    {
        return $this->hasMany(LapTimeSplit::class);
    }

    /**
     * `time` is stored as raw decimal seconds (e.g. 47.27) — every UI mock so far has displayed
     * lap times as `M:SS.ss`, so this is the one shared place that conversion happens for real
     * data, rather than every consumer re-deriving it.
     */
    public static function formatSeconds(float|string $seconds): string
    {
        $seconds = (float) $seconds;
        $minutes = intdiv((int) floor($seconds), 60);
        $remainder = $seconds - ($minutes * 60);

        return sprintf('%d:%05.2f', $minutes, $remainder);
    }

    public function formattedTime(): string
    {
        return self::formatSeconds((string) $this->time);
    }
}
