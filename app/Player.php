<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{

       protected $table = 'players';
       protected $fillable = ['name', 'hash'];


       public function servers() {
         return $this->belongsToMany('App\Server', 'players_servers');
       }
}
