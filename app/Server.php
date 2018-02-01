<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Server extends Model
{

  protected $table = 'servers';
  protected $fillable = ['ip', 'port', 'name'];


  public function players() {
    return $this->belongsToMany('App\Player', 'players_servers');
  }

  public function maps() {
    return $this->belongsToMany('App\Map', 'servers_maps');
  }
}
