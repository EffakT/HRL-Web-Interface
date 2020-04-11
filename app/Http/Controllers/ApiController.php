<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessNewLap;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function user(Request $request)
    {
        return $request->user();
    }

    public function newTime(Request $request)
    {
        $data = [];
        $data['user_ip'] = $request->ip();
        $data['data'] = $request->all();
        /*if (isset($data['data']['ip']))
            $data['uesr_ip'] = $data['data']['ip'];*/

        if (isset($data['data']['test'])):
            $process = new ProcessNewLap($data);
            $process->handle();
        else:
            ProcessNewLap::dispatch($data);
        endif;

        return response()->json(['success' => true]);
    }
}
