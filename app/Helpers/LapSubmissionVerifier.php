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
     * @return array{verified: bool, reason: ?string, response: ?array<string, string>}
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
            return $this->fail('missing_hrl_marker', $response);
        }

        if (($response['hrl_protocol'] ?? null) !== config('webhook.hrl_query.supported_protocol')) {
            return $this->fail('protocol_unsupported', $response);
        }

        $submittedToken = $data['hrl_token'] ?? null;

        // Freshness/binding only, not a durable secret — UDP query responses are publicly
        // readable by anyone who queries the server, so this doesn't need (and isn't) constant
        // time comparison the way a real HMAC/session secret would. Accepts either the current
        // `hrl_token` or the immediately-previous `hrl_token_prev` (if the Lua script publishes
        // one) — a rotating-token script and an in-flight lap submission racing a rotation
        // boundary shouldn't fail a legitimate lap.
        if ($submittedToken === null || ! in_array($submittedToken, array_filter([
            $response['hrl_token'] ?? null,
            $response['hrl_token_prev'] ?? null,
        ]), true)) {
            return $this->fail('token_mismatch', $response);
        }

        if (($response['mapname'] ?? null) !== $data['map_name']) {
            return $this->fail('map_mismatch', $response);
        }

        // Exact `player_<number>` keys only — a future query extension with an unrelated
        // `player_`-prefixed key (e.g. a hypothetical `player_count`) must not be treated as an
        // online-player slot.
        $playerOnline = collect($response)
            ->filter(fn ($value, string $key): bool => preg_match('/^player_\d+$/', $key) === 1)
            ->contains($data['player_name']);

        if (! $playerOnline) {
            return $this->fail('player_not_online', $response);
        }

        return ['verified' => true, 'reason' => null, 'response' => $response];
    }

    /**
     * @param  ?array<string, string>  $response
     * @return array{verified: bool, reason: ?string, response: ?array<string, string>}
     */
    private function fail(string $reason, ?array $response = null): array
    {
        return ['verified' => false, 'reason' => $reason, 'response' => $response];
    }
}
