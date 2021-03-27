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



    protected $dataTableRelationships = [
        "belongsTo" => [
            "server" => [
                "model" => Server::class,
                "foreign_key" => "server_id",
                "columns" => [
                    "name" => [
                        "searchable" => true,
                        "orderable" => true,
                    ],
                ],
            ],
            "map" => [
                "model" => Map::class,
                "foreign_key" => "map_id",
                "columns" => [
                    "name" => [
                        "searchable" => true,
                        "orderable" => true,
                    ],
                ],
            ],
            "player" => [
                "model" => Player::class,
                "foreign_key" => "player_id",
                "columns" => [
                    "name" => [
                        "searchable" => true,
                        "orderable" => true,
                    ],
                ],
            ],
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
