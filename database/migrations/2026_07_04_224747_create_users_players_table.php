<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matches the real (already-imported) `users_players` table — the claim-code ownership system
 * (see docs/scope.md, App\Models\PlayerClaim). `user_id` is a plain unsignedInteger, not a
 * `foreignId()`, for the same real int/bigint mismatch reason as `players.user_id` — see that
 * migration's note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users_players', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('player_id');
            $table->string('claim_code');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('player_id')->references('id')->on('players');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_players');
    }
};
