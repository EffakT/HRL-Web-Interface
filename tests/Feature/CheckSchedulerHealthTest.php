<?php

use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;

// 2026-07-10 incident follow-up (see docs/decisions.md) — a stuck scheduler mutex lock silently
// blocked app:refresh-live-server-info for ~23 hours with no error anywhere. This command is a
// deliberately independent watchdog (registered via real crontab, not Schedule::) that alerts
// when queried_at goes stale, regardless of whether Laravel's own scheduler is the thing stuck.
uses(LazilyRefreshDatabase::class);

it('passes quietly when no server has ever been queried yet', function () {
    Server::factory()->create(['queried_at' => null]);

    Log::shouldReceive('critical')->never();

    $this->artisan('app:check-scheduler-health')->assertSuccessful();
});

it('passes when the most recent query is within the staleness threshold', function () {
    Server::factory()->create(['queried_at' => now()->subMinutes(3)]);

    Log::shouldReceive('critical')->never();

    $this->artisan('app:check-scheduler-health')->assertSuccessful();
});

it('logs critical and fails when every server has gone stale beyond the threshold', function () {
    Server::factory()->create(['queried_at' => now()->subMinutes(45)]);
    Server::factory()->create(['queried_at' => now()->subMinutes(120)]);

    Log::shouldReceive('critical')->once()->withArgs(fn (string $message) => str_contains($message, '45 minute'));

    $this->artisan('app:check-scheduler-health')->assertFailed();
});

it('uses the most recently queried server, not the most stale one', function () {
    Server::factory()->create(['queried_at' => now()->subMinutes(45)]);
    Server::factory()->create(['queried_at' => now()->subMinutes(2)]);

    Log::shouldReceive('critical')->never();

    $this->artisan('app:check-scheduler-health')->assertSuccessful();
});
