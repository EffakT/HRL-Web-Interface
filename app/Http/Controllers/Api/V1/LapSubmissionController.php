<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\LapSubmissionConflictException;
use App\Helpers\LapSubmissionHash;
use App\Helpers\LapSubmissionVerifier;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLapTimeRequest;
use App\Jobs\ProcessNewLap;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rebuilt equivalent of `ApiController::newTime` (`ApiController.php-legacy`). See
 * docs/database.md's "Webhook → job flow" section for the full old-vs-new behavior writeup.
 */
class LapSubmissionController extends Controller
{
    /** POST /api/v1/laps — a Halo game server submitting a completed lap. */
    public function store(StoreLapTimeRequest $request, LapSubmissionVerifier $verifier): JsonResponse
    {
        $ip = $request->ip();
        $port = (int) $request->validated('port');
        $data = $request->validated();

        // Canonical content hash (App\Helpers\LapSubmissionHash) — identifies "the fields that
        // make two submissions the same lap," independent of whether a `submission_id` was
        // sent. Used two ways below: as the idempotency key itself when no `submission_id` is
        // present, and (SEC-01 audit follow-up) as a stored fingerprint to catch a reused
        // `submission_id` whose actual lap content has changed, which would otherwise silently
        // replay a stale response instead of recording (or flagging) a genuinely different lap.
        // ProcessNewLap computes and persists the same fingerprint for the durable,
        // cache-independent version of this same check.
        $contentHash = LapSubmissionHash::compute($data);

        // Idempotency key — always namespaced by the submitting ip:port, even when the client
        // sends its own `submission_id`. Without that namespace, two different game servers
        // generating similar counters/IDs (a real possibility, not hypothetical — many scripts
        // just count from 1) could collide and one server would receive the other's cached
        // response, or believe its own lap was rejected as a duplicate.
        $submissionKey = $data['submission_id'] ?? $contentHash;
        $idempotencyKey = "lap-submission:{$ip}:{$port}:{$submissionKey}";

        // Reservation and result-replay use deliberately different lifetimes (SEC-01 audit
        // follow-up) — see config/webhook.php for why one shared 10s window was wrong for both.
        // Cache::add() only succeeds the first time a key is set, so this reservation is atomic
        // against near-simultaneous retries.
        $reserved = Cache::add(
            $idempotencyKey,
            ['status' => 'processing', 'contentHash' => $contentHash],
            now()->addSeconds(config('webhook.processing_reservation_seconds')),
        );

        if (! $reserved) {
            $existing = Cache::get($idempotencyKey);

            // A `submission_id` reused with genuinely different lap content (SEC-01 audit
            // follow-up) — silently replaying the OLD response would hide that the new attempt
            // was never actually recorded; a plain duplicate_submission would look identical to
            // a real in-flight race. Neither is correct, so this gets its own distinct reason.
            if (($existing['contentHash'] ?? null) !== $contentHash) {
                return response()->json(['success' => false, 'reason' => 'idempotency_conflict'], 409);
            }

            // A terminal outcome (success or a rejected verification) already exists for this
            // exact key — replay it verbatim rather than either double-recording the lap or
            // bouncing a legitimate retry with a bare error (an earlier version of this guard
            // did the latter, which meant a client that legitimately didn't see its own
            // successful response had no way to safely retry). Only a request that's still
            // mid-flight (or one whose in-flight reservation was never cleaned up — see the
            // catch block below) reports as a genuine duplicate.
            if (($existing['status'] ?? null) === 'done') {
                return response()->json($existing['body'], $existing['statusCode']);
            }

            return response()->json(['success' => false, 'reason' => 'duplicate_submission'], 409);
        }

        try {
            [$body, $statusCode] = $this->process($ip, $port, $data, $verifier);
        } catch (Throwable $e) {
            // Release the reservation on any pre-commit failure (verification's own network
            // call throwing, an unexpected exception inside ProcessNewLap, etc.) — leaving it
            // held for the rest of the window would make a legitimate retry fail with
            // "duplicate_submission" for something that was never actually recorded. Note this
            // cache guard is a convenience layer, not the durable source of truth for a
            // `submission_id` specifically — see ProcessNewLap's (server_id, submission_id)
            // unique-constraint handling for what actually prevents a duplicate lap row if this
            // cache entry is ever lost (restart, eviction, a very late retry).
            Cache::forget($idempotencyKey);

            throw $e;
        }

        Cache::put(
            $idempotencyKey,
            ['status' => 'done', 'body' => $body, 'statusCode' => $statusCode, 'contentHash' => $contentHash],
            now()->addSeconds(config('webhook.result_retention_seconds')),
        );

        return response()->json($body, $statusCode);
    }

    /** @return array{0: array<string, mixed>, 1: int} */
    private function process(string $ip, int $port, array $data, LapSubmissionVerifier $verifier): array
    {
        $liveQueryResponse = null;

        if (config('webhook.hrl_query.enabled')) {
            $verification = $verifier->verify($ip, $port, $data);
            $liveQueryResponse = $verification['response'];
            $verifiedMarkerKey = LapSubmissionVerifier::verifiedMarkerKey($ip, $port);

            if ($verification['verified']) {
                // Marks this ip:port as "recently verified" for the webhook rate limiter's
                // tiered allowance (SEC-01 audit follow-up, AppServiceProvider) — verification
                // still runs on every request regardless of tier; this only ever raises how much
                // traffic a source is allowed, never skips the check itself.
                Cache::put($verifiedMarkerKey, true, now()->addSeconds(config('webhook.rate_limit.verified_marker_ttl_seconds')));
            } else {
                // Revoke immediately on ANY failure (SEC-01 audit follow-up), not just let the
                // marker expire naturally — otherwise a source could verify once, then stop
                // answering UDP entirely, and keep the generous "verified" rate-limit tier for
                // up to the marker's full TTL while forcing timeout work on every request.
                Cache::forget($verifiedMarkerKey);

                Log::warning('Lap submission failed HRL query verification', [
                    'ip' => $ip,
                    'port' => $port,
                    'reason' => $verification['reason'],
                    'enforced' => config('webhook.hrl_query.enforce'),
                ]);

                if (config('webhook.hrl_query.enforce')) {
                    return [['success' => false, 'reason' => $verification['reason']], 403];
                }
            }
        }

        // Reuses the query response verification already fetched above, when there is one,
        // instead of ProcessNewLap querying the same ip:port a second time for the hostname
        // (SEC-01 audit follow-up) — see ProcessNewLap's constructor docblock.
        $job = new ProcessNewLap(ip: $ip, port: $port, data: $data, liveQueryResponse: $liveQueryResponse);

        try {
            $body = app()->call([$job, 'handle']);
        } catch (LapSubmissionConflictException) {
            // The durable, database-backed counterpart to this method's own cache-based
            // idempotency-conflict check above (SEC-01 audit follow-up) — reached when a reused
            // `submission_id` with different content is detected only after the cache entry for
            // the original submission has expired, been evicted, or the app restarted.
            return [['success' => false, 'reason' => 'idempotency_conflict'], 409];
        }

        return [$body, 200];
    }
}
