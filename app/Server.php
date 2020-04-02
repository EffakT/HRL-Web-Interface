<?php

namespace App;

use App\Helpers\QueryServer;
use App\Traits\HasClaims;
use Illuminate\Database\Eloquent\Model;
use JamesDordoy\LaravelVueDatatable\Traits\LaravelVueDatatableTrait;

class Server extends Model
{
    use LaravelVueDatatableTrait;
    use HasClaims;

    protected $table = 'servers';
    protected $fillable = ['ip', 'port', 'name'];
    protected $dataTableColumns = [
        'ip' => [
            'searchable' => false,
        ],
        'port' => [
            'searchable' => false,
        ],
        'name' => [
            'searchable' => true,
        ]
    ];


    public function players()
    {
        return $this->belongsToMany('App\Player', 'players_servers');
    }

    public function maps()
    {
        return $this->belongsToMany('App\Map', 'servers_maps');
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


    public function queryServer() {
        $buffer = " ";
        $query = new QueryServer($buffer, $this->ip, $this->port);
        if (($response = $query->runQuery()) === false):
            \Log::error($query->getError());
            //throw new \Exception($query->getError().": ".$this->request['user_ip'].":".$this->request['data']['port']);
            return ['success' => false, 'query' => $query];
        endif;
        return ['success' => true, 'response' => $response, 'query' => $query];
    }


}