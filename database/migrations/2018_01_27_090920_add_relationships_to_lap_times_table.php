<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRelationshipsToLapTimesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lap_times', function (Blueprint $table) {
          $table->foreign('server_id')->references('id')->on('servers');
          $table->foreign('map_id')->references('id')->on('maps');
          $table->foreign('player_id')->references('id')->on('players');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lap_times', function (Blueprint $table) {
            $table->dropForeign(['server_id']);
            $table->dropForeign(['map_id']);
            $table->dropForeign(['player_id']);
        });
    }
}
