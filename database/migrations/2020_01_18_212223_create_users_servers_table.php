<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersServersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users_servers', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->bigInteger('user_id')->unsigned();
            $table->integer('server_id')->unsigned();
            $table->string('claim_code');
            $table->timestamp('claimed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();



            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('server_id')->references('id')->on('servers');
        });

        //server user_id field not required
        Schema::table('servers', function (Blueprint $table) {
            $table->dropForeign('servers_user_id_foreign');
            $table->dropColumn('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users_servers');

        //rollback needs to add this field back in
        Schema::table('servers', function (Blueprint $table) {
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')->on('users');
        });

    }
}
