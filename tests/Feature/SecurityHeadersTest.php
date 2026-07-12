<?php

use App\Models\Server;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

// SEC-05 audit follow-up (docs/security.md) — App\Http\Middleware\AddSecurityHeaders.

uses(LazilyRefreshDatabase::class);

it('adds hardening headers to a web response', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy');

    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()');
    expect($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();
});

it('adds the same hardening headers to an api response', function () {
    Server::factory()->create(['id' => 1]);

    $this->get('/api/v1/servers')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
});

it('suppresses the X-Powered-By PHP version disclosure', function () {
    $this->get('/')->assertHeaderMissing('X-Powered-By');
});

it('scopes the CSP connect-src to this app\'s own domain, including the wss upgrade', function () {
    $response = $this->get('/');
    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("connect-src 'self' ".config('app.url').' '.str_replace('http', 'ws', config('app.url')));
});
