<?php

namespace App\Helpers;

/**
 * Cross-checks a lap-submission HTTP request against a live UDP `\query` response from the
 * same ip:port (SEC-01, docs/security.md) — binds the HTTP submission to a game server that is
 * actually running the HRL Lua script, currently on the submitted map, with the submitting
 * player online, without needing TLS/HMAC support on the Lua side. Not full cryptographic
 * authentication: a server operator controls their own query responses and could fabricate
 * activity on their own server. See docs/security.md for what this does and doesn't protect
 * against.
 */
class LapSubmissionVerifier
{
    public function __construct(private readonly GameServerQuery $query) {}

    /**
     * @param  array{map_name: string, player_name: string, hrl_token: string|null}  $data
     * @return array{verified: bool, reason: ?string}
     */
    public function verify(string $ip, int $port, array $data): array
    {
        // One retry — a single dropped UDP packet shouldn't fail a legitimate submission.
        $response = $this->query->query($ip, $port, config('webhook.hrl_query.timeout_seconds'))
            ?: $this->query->query($ip, $port, config('webhook.hrl_query.timeout_seconds'));

        if ($response === false) {
            return $this->fail('udp_timeout');
        }

        if (($response['hrl_enabled'] ?? null) !== '1') {
            return $this->fail('missing_hrl_marker');
        }

        if (($response['hrl_protocol'] ?? null) !== config('webhook.hrl_query.supported_protocol')) {
            return $this->fail('protocol_unsupported');
        }

        // Freshness/binding only, not a durable secret — UDP query responses are publicly
        // readable by anyone who queries the server, so this doesn't need (and isn't) constant
        // time comparison the way a real HMAC/session secret would.
        if (($response['hrl_token'] ?? null) === null || $response['hrl_token'] !== ($data['hrl_token'] ?? null)) {
            return $this->fail('token_mismatch');
        }

        if (($response['mapname'] ?? null) !== $data['map_name']) {
            return $this->fail('map_mismatch');
        }

        $playerOnline = collect($response)
            ->filter(fn ($value, string $key): bool => str_starts_with($key, 'player_'))
            ->contains($data['player_name']);

        if (! $playerOnline) {
            return $this->fail('player_not_online');
        }

        return ['verified' => true, 'reason' => null];
    }

    /** @return array{verified: bool, reason: ?string} */
    private function fail(string $reason): array
    {
        return ['verified' => false, 'reason' => $reason];
    }
}
