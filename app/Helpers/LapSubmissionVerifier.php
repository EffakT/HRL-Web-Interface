<?php

namespace App\Helpers;

use Log;

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

    /** Cache key marking an ip:port as having recently passed HRL query verification — shared between here, `LapSubmissionController` (which sets it), and `AppServiceProvider`'s webhook rate limiter (which reads it to pick a tier). */
    public static function verifiedMarkerKey(string $ip, int|string $port): string
    {
        return "verified-webhook-source:{$ip}:{$port}";
    }

    /**
     * @param  array{map_name: string, player_name: string, hrl_token: string|null}  $data
     * @return array{verified: bool, reason: ?string, response: array<string, string>|false|null}
     */
    public function verify(string $ip, int $port, array $data): array
    {
        // One retry — a single dropped UDP packet shouldn't fail a legitimate submission.
        $response = $this->query->query($ip, $port, config('webhook.hrl_query.timeout_seconds'))
            ?: $this->query->query($ip, $port, config('webhook.hrl_query.timeout_seconds'));

        if ($response === false) {
            // `false`, not `null` — tells ProcessNewLap "verification already tried (with its
            // own retry) and got nothing," so it doesn't waste a third UDP round-trip on the
            // same certainly-unresponsive ip:port (SEC-01 audit follow-up). `null` is reserved
            // for "verification didn't run at all," where a fresh query is still worth trying.
            return $this->fail('udp_timeout', false);
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
        //
        // Encoding mismatch caught by SITE_AUDIT.md's Lua review: the UDP query response's
        // player_N values are raw Windows-1252 bytes (Halo's own text encoding — see hrl.lua's
        // own map_1252_to_unicode table, used to build the UTF-8 name it actually sends), but
        // `$data['player_name']` arrives already UTF-8-converted (hrl.lua's string.toutf8()).
        // Comparing them without normalizing first would silently fail for any non-ASCII name —
        // real, not hypothetical, given this community's actual player list (e.g. "GåþøFêîk¬£Q",
        // "HLN«ßÕX3R»" — see docs/database.md/old API examples).
        $playerOnline = collect($response)
            ->filter(fn ($value, string $key): bool => preg_match('/^player_\d+$/', $key) === 1)
            ->map(fn (string $value): string => mb_convert_encoding($value, 'UTF-8', 'Windows-1252'))
            ->contains($data['player_name']);

        if (! $playerOnline) {
            return $this->fail('player_not_online', $response);
        }

        return ['verified' => true, 'reason' => null, 'response' => $response];
    }

    /**
     * @param  array<string, string>|false|null  $response
     * @return array{verified: bool, reason: ?string, response: array<string, string>|false|null}
     */
    private function fail(string $reason, array|false|null $response = null): array
    {
        Log::debug("Lap submission verification failed: {$reason}", ['response' => $response]);

        return ['verified' => false, 'reason' => $reason, 'response' => $response];
    }
}
