<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Matches the real (already-imported) `lap_time_splits` table — one row per checkpoint per lap. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lap_time_splits', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('lap_time_id');
            $table->integer('checkpoint_id');
            $table->double('duration', 8, 2);
            $table->double('start_time', 8, 2);
            $table->double('end_time', 8, 2);
            $table->timestamps();

            $table->foreign('lap_time_id')->references('id')->on('lap_times');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lap_time_splits');
    }
};
