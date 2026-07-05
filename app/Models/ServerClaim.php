<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Claim-code ownership system (`users_servers`) — table preserved as-is, no feature work
 * planned around it until explicitly revisited. See docs/database.md, docs/scope.md.
 */
#[Fillable(['user_id', 'server_id', 'claim_code', 'claimed_at'])]
class ServerClaim extends Model
{
    /** @use HasFactory<\Database\Factories\ServerClaimFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'users_servers';

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

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
