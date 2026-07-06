<?php

use App\Helpers\GameServerQuery;
use App\Helpers\LapSubmissionVerifier;

function fakeQuery(array|false $response): GameServerQuery
{
    return new class($response) implements GameServerQuery
    {
        public function __construct(private readonly array|false $response) {}

        public function query(string $ip, int $port, int $timeoutSeconds = 2): array|false
        {
            return $this->response;
        }

        public function getError(): ?string
        {
            return $this->response === false ? 'stubbed failure' : null;
        }
    };
}

function validHrlResponse(array $overrides = []): array
{
    return array_merge([
        'hrl_enabled' => '1',
        'hrl_protocol' => '1',
        'hrl_token' => 'secret-token',
        'mapname' => 'bloodgulch',
        'player_0' => 'Effakt',
    ], $overrides);
}

function submissionData(array $overrides = []): array
{
    return array_merge([
        'map_name' => 'bloodgulch',
        'player_name' => 'Effakt',
        'hrl_token' => 'secret-token',
    ], $overrides);
}

it('verifies a submission that matches a live, HRL-enabled query response', function () {
    $verifier = new LapSubmissionVerifier(fakeQuery(validHrlResponse()));

    expect($verifier->verify('1.2.3.4', 2302, submissionData()))
        ->toBe(['verified' => true, 'reason' => null, 'response' => validHrlResponse()]);
});

it('fails closed when the UDP query itself fails', function () {
    $verifier = new LapSubmissionVerifier(fakeQuery(false));

    expect($verifier->verify('1.2.3.4', 2302, submissionData()))
        ->toBe(['verified' => false, 'reason' => 'udp_timeout', 'response' => null]);
});

it('accepts the previous token during a rotation grace window', function () {
    $verifier = new LapSubmissionVerifier(fakeQuery(validHrlResponse([
        'hrl_token' => 'new-token',
        'hrl_token_prev' => 'secret-token',
    ])));

    expect($verifier->verify('1.2.3.4', 2302, submissionData())['verified'])->toBeTrue();
});

it('does not treat an unrelated player_-prefixed key as an online-player slot', function () {
    $verifier = new LapSubmissionVerifier(fakeQuery(validHrlResponse([
        'player_0' => 'SomeoneElse',
        'player_count' => 'Effakt',
    ])));

    expect($verifier->verify('1.2.3.4', 2302, submissionData())['reason'])->toBe('player_not_online');
});

it('rejects a server that does not publish the HRL marker (script not updated yet)', function () {
    $verifier = new LapSubmissionVerifier(fakeQuery(['hostname' => 'A Server', 'numplayers' => '1']));

    expect($verifier->verify('1.2.3.4', 2302, submissionData())['reason'])->toBe('missing_hrl_marker');
});

it('rejects an unsupported protocol version', function () {
    $verifier = new LapSubmissionVerifier(fakeQuery(validHrlResponse(['hrl_protocol' => '99'])));

    expect($verifier->verify('1.2.3.4', 2302, submissionData())['reason'])->toBe('protocol_unsupported');
});

it('rejects a mismatched token', function () {
    $verifier = new LapSubmissionVerifier(fakeQuery(validHrlResponse(['hrl_token' => 'other-token'])));

    expect($verifier->verify('1.2.3.4', 2302, submissionData())['reason'])->toBe('token_mismatch');
});

it('rejects when no token was submitted at all', function () {
    $verifier = new LapSubmissionVerifier(fakeQuery(validHrlResponse()));

    expect($verifier->verify('1.2.3.4', 2302, submissionData(['hrl_token' => null]))['reason'])->toBe('token_mismatch');
});

it('rejects a map mismatch between the submission and the live query', function () {
    $verifier = new LapSubmissionVerifier(fakeQuery(validHrlResponse(['mapname' => 'sidewinder'])));

    expect($verifier->verify('1.2.3.4', 2302, submissionData())['reason'])->toBe('map_mismatch');
});

it('rejects a player who is not among the live query player_N values', function () {
    $verifier = new LapSubmissionVerifier(fakeQuery(validHrlResponse(['player_0' => 'SomeoneElse'])));

    expect($verifier->verify('1.2.3.4', 2302, submissionData())['reason'])->toBe('player_not_online');
});

it('retries once before failing on a dropped UDP packet', function () {
    $query = new class implements GameServerQuery
    {
        public int $calls = 0;

        public function query(string $ip, int $port, int $timeoutSeconds = 2): array|false
        {
            $this->calls++;

            return false;
        }

        public function getError(): ?string
        {
            return 'stubbed failure';
        }
    };

    $verifier = new LapSubmissionVerifier($query);
    $verifier->verify('1.2.3.4', 2302, submissionData());

    expect($query->calls)->toBe(2);
});
