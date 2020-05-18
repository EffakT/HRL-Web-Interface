<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use JamesDordoy\LaravelVueDatatable\Traits\LaravelVueDatatableTrait;

class Player extends Model
{
    use LaravelVueDatatableTrait;

    protected $table = 'players';
    protected $fillable = ['name', 'hash'];

    protected $dataTableColumns = [
        'id' => [
            'searchable' => true,
        ],
        'name' => [
            'searchable' => true,
        ]
    ];

    public function servers() {
        return $this->belongsToMany('App\Server', 'players_servers');
    }

    public function alias() {
        return Player::where('hash', $this->hash)->get();
    }


    public function allClaims()
    {
        return $this->hasMany('App\PlayerClaim');
    }

    public function pendingClaims()
    {
        return $this->hasMany('App\PlayerClaim')->whereNull('claimed_at');
    }

    public function claims()
    {
        return $this->hasMany('App\PlayerClaim')->whereNotNull('claimed_at');
    }

    public function isClaimed()
    {
        $claims = $this->claims;
        return ($claims->count() > 0) ? $claims->first() : false;
    }

    public function isClaimedBy(User $user)
    {
        $claims = $this->claims->where('user_id', $user->id);
        return ($claims->count() > 0) ? $claims->first() : false;
    }

    public function isPendingClaimBy(User $user)
    {
        $claims = $this->pendingClaims->where('user_id', $user->id);
        return ($claims->count() > 0) ? $claims->first() : false;
    }

    public function isPendingClaim()
    {
        $claims = $this->pendingClaims;
        return ($claims->count() > 0) ? $claims->first() : false;
    }
}
