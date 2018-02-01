<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LapTime extends Model
{
     protected $table = 'lap_times';
     protected $fillable = ['server_id', 'map_id', 'player_id', 'time'];

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
