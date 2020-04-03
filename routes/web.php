<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

Auth::routes(['verify' => true]);

Route::get('/my-account', 'AccountController@index')->name('my-account');


Route::get('/opt-in', function () {
    return view('opt-in');
})->name('opt-in');

Route::get('/help', function () {
    return view('help');
})->name('help');

Route::get('/contact', function () {
    return view('contact');
})->name('contact');

Route::get('/servers', 'LeaderboardController@servers')->name('servers');

Route::get('/servers/mine', 'ManageServerController@myServers')->name('server:mine');

Route::get('/servers/{server}', 'LeaderboardController@server')->name('server');

Route::get('/servers/{server}/manage', 'ManageServerController@index')->name('server:manage');
Route::post('/servers/{server}/manage/claim', 'ManageServerController@claimServer')->name('server:claim');
Route::get('/servers/{server}/manage/claim/verify', 'ManageServerController@verifyClaimServer')->name('server:claim-verify');

Route::post('/servers/{server}/manage/reset-laps', 'ManageServerController@resetLaps')->name('server:reset-laps');


Route::get('/maps', 'LeaderboardController@maps')->name('maps');
Route::get('/maps/{map}', 'LeaderboardController@map')->name('map');


Route::get('/players/{player}', 'LeaderboardController@player')->name('player');
