<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matches the real (already-imported) `players` table — see docs/database.md.
 *
 * NOTE: `user_id` is intentionally NOT a `foreignId()`/`constrained()` column. The real `users.id`
 * is `int unsigned`, but this project's stock `users` migration (Laravel's default scaffold)
 * creates it as `bigint unsigned` — a real mismatch between the imported data and the framework
 * default, not something introduced here. A plain nullable `unsignedInteger` avoids a cross-type
 * foreign key error either way. See docs/decisions.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('hash');
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
