<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use JamesDordoy\LaravelVueDatatable\Traits\LaravelVueDatatableTrait;

class LapTime extends Model
{

    use LaravelVueDatatableTrait;

    protected $table = 'lap_times';
    protected $fillable = ['server_id', 'map_id', 'player_id', 'time'];

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $dataTableColumns = [
        'time' => [
            'searchable' => false,
        ]
    ];

    public function server() {
        $this->hasOne('App\Server');
    }
    public function map() {
        $this->hasOne('App\Map');
    }
    public function player() {
        $this->hasOne('App\Player');
    }
}
