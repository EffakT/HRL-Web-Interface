<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matches the real (already-imported) `users_servers` table — the claim-code ownership system
 * (see docs/scope.md, App\Models\ServerClaim). Real `id` is `bigint unsigned` here (unlike its
 * `users_players` counterpart, which is `int unsigned`) — replicated as-is even though the
 * inconsistency looks like an old-app quirk rather than a deliberate choice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users_servers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('server_id');
            $table->string('claim_code');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('server_id')->references('id')->on('servers');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_servers');
    }
};
