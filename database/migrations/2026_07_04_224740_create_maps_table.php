<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matches the real (already-imported) `maps` table — see docs/database.md. This migration
 * never needs to run against the real dev/prod database (the table already exists there); it
 * exists so a fresh environment (CI, testing sqlite, a new dev machine) can build an equivalent
 * schema for factories/tests to run against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maps', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('label');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maps');
    }
};
