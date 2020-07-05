<?php


namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlayerClaim extends Model
{
    use SoftDeletes;

    protected $table = 'users_players';
    protected $fillable = ['player_id', 'user_id', 'claim_code', 'claimed_at'];

    protected $dateFormat = 'Y-m-d H:i:s';

    public function player() {
        return $this->belongsTo('App\Player');
    }
    public function user() {
        return $this->belongsTo('App\User');
    }
    public function getIsClaimedAttribute() {
        return !is_null('claimed_at');
    }
}
