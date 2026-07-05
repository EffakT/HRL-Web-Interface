<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Matches the real (already-imported) `servers` table — see docs/database.md. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ip');
            $table->string('port');
            $table->string('name')->default('');
            $table->string('type')->default('PC');
            $table->boolean('notify_outage')->default(false);
            $table->dateTime('notify_outage_last')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
