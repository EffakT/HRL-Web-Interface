<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\ProcessNewLap;

class ApiController extends Controller
{
    public function newTime(Request $request) {
      $data = [];
      $data['user_ip'] = $request->ip();
      $data['data'] = $request->all();
      if (isset($data['data']['ip']))
        $data['user_ip'] = $data['data']['ip'];
      ProcessNewLap::dispatch($data);
      /*$process = new ProcessNewLap($data);
      $process->handle();*/
      return response()->json(['success' => true]);
    }
}
