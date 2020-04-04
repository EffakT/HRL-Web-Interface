<?php


namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Claim extends Model
{
    use SoftDeletes;

    protected $table = 'users_servers';
    protected $fillable = ['server_id', 'user_id', 'claim_code', 'claimed_at'];

    protected $dateFormat = 'Y-m-d H:i:s';

    public function server() {
        return $this->belongsTo('App\Server');
    }
    public function user() {
       return $this->belongsTo('App\User');
    }
    public function getIsClaimedAttribute() {
        return !is_null('claimed_at');
    }
}
