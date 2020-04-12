<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use JamesDordoy\LaravelVueDatatable\Traits\LaravelVueDatatableTrait;

class LapTime extends Model
{

    use LaravelVueDatatableTrait;

    protected $table = 'lap_times';
    protected $fillable = ['server_id', 'map_id', 'player_id', 'time', 'created_at', 'updated_at'];

    protected $dateFormat = 'Y-m-d';

    protected $dataTableColumns = [
        'time' => [
            'searchable' => false,
        ]
    ];

    public function server() {
        return $this->belongsTo('App\Server');
    }
    public function map() {
        return $this->belongsTo('App\Map');
    }
    public function player() {
        return $this->belongsTo('App\Player');
    }
}
