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
Route::post('/newtime', 'ApiController@newTime')->name('new-time');


Route::middleware('auth:api')->group(function () {
    Route::get('servers/', 'ApiController@servers');
    Route::get('servers/{server}', 'ApiController@server');

    Route::get('players', 'ApiController@players');
    Route::get('players/{player}', 'ApiController@player');

    Route::get('maps', 'ApiController@maps');
    Route::get('maps/{map}', 'ApiController@map');
});
