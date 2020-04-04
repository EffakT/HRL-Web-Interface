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
}
