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

Auth::routes(['verify' => true]);


Route::get('/', 'HomeController@home')->name('home');

Route::get('/my-account', 'AccountController@index')->name('my-account');
Route::post('/my-account/generate-token', 'AccountController@generateToken')->name('generate-token');


Route::get('/opt-in', 'HomeController@optIn')->name('opt-in');

Route::get('/help', 'HomeController@help')->name('help');

Route::get('/contact', 'HomeController@contact')->name('contact');

Route::get('/servers', 'LeaderboardController@servers')->name('servers');

Route::get('/servers/mine', 'ManageServerController@myServers')->name('server:mine');

Route::get('/servers/{server}', 'LeaderboardController@server')->name('server');

Route::get('/servers/{server}/manage', 'ManageServerController@index')->name('server:manage');
Route::post('/servers/{server}/manage/claim', 'ManageServerController@claimServer')->name('server:claim')->middleware("can:claim");
Route::get('/servers/{server}/manage/claim/verify', 'ManageServerController@verifyClaimServer')->name('server:claim-verify')->middleware("can:verify-claim");

Route::post('/servers/{server}/manage/reset-laps', 'ManageServerController@resetLaps')->name('server:reset-laps')->middleware("can:reset,server");
Route::post('/servers/{server}/manage/migrate-laps', 'ManageServerController@migrateLaps')->name('server:migrate-laps')->middleware("can:migrate,server");
Route::post('/servers/{server}/manage/delete', 'ManageServerController@delete')->name('server:delete')->middleware("can:delete,server");


Route::get('/maps', 'LeaderboardController@maps')->name('maps');
Route::get('/maps/{map}', 'LeaderboardController@map')->name('map');


Route::get('/players/', 'LeaderboardController@players')->name('players');
Route::get('/players/{player}', 'LeaderboardController@player')->name('player');
Route::get('/players/{player}/manage', 'ManagePlayerController@index')->name('player:manage');
Route::post('/players/{player}/manage/claim', 'ManagePlayerController@claimPlayer')->name('player:claim')->middleware("can:claim");

