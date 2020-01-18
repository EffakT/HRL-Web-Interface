<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use JamesDordoy\LaravelVueDatatable\Traits\LaravelVueDatatableTrait;

class Server extends Model
{
    use LaravelVueDatatableTrait;

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


    public function players() {
        return $this->belongsToMany('App\Player', 'players_servers');
    }

    public function maps() {
        return $this->belongsToMany('App\Map', 'servers_maps');
    }
}
