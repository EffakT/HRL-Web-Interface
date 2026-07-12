<?php

namespace App\Models;

use Database\Factories\PlayerClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/**
 * Claim-code ownership system (`users_players`) — table preserved as-is, no feature work
 * planned around it until explicitly revisited. See docs/database.md, docs/scope.md.
 */
#[Fillable(['user_id', 'player_id', 'claim_code', 'claimed_at'])]
#[Table(name: 'users_players')]
class PlayerClaim extends Model
{
    /** @use HasFactory<PlayerClaimFactory> */
    use HasFactory, SoftDeletes;

    #[Override]
    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
