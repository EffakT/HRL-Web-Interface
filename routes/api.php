<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Route::middleware('auth:api')->get('/user', 'ApiController@user');

//New Time Endpoint
Route::post('/newtime', 'ApiController@newTime');


Route::middleware('auth:api')->prefix('servers')->group(function () {
    Route::get('/', 'ApiController@servers');
});
