<?php

namespace App\Http\Controllers\Api\V1;

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

        // Idempotency key (SEC-01 audit follow-up, docs/security.md) — prefers the client's own
        // `submission_id` when sent; falls back to a content hash of the fields that make two
        // submissions "the same lap" otherwise. Either way, Cache::add() only succeeds the
        // first time a key is set, so reservation is atomic against near-simultaneous retries.
        $idempotencyKey = 'lap-submission:'.($data['submission_id'] ?? hash('sha256', json_encode([
            $ip, $port, $data['player_hash'], $data['map_name'], $data['player_time'], $data['hrl_token'] ?? null,
        ])));

        $reserved = Cache::add($idempotencyKey, ['status' => 'processing'], now()->addSeconds(config('webhook.duplicate_window_seconds')));

        if (! $reserved) {
            $existing = Cache::get($idempotencyKey);

            // A terminal outcome (success or a rejected verification) already exists for this
            // exact key — replay it verbatim rather than either double-recording the lap or
            // bouncing a legitimate retry with a bare error (the previous version of this
            // guard did the latter, which meant a client that legitimately didn't see its own
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
            // "duplicate_submission" for something that was never actually recorded.
            Cache::forget($idempotencyKey);

            throw $e;
        }

        Cache::put($idempotencyKey, ['status' => 'done', 'body' => $body, 'statusCode' => $statusCode], now()->addSeconds(config('webhook.duplicate_window_seconds')));

        return response()->json($body, $statusCode);
    }

    /** @return array{0: array<string, mixed>, 1: int} */
    private function process(string $ip, int $port, array $data, LapSubmissionVerifier $verifier): array
    {
        $liveQueryResponse = null;

        if (config('webhook.hrl_query.enabled')) {
            $verification = $verifier->verify($ip, $port, $data);
            $liveQueryResponse = $verification['response'];

            if (! $verification['verified']) {
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

        return [app()->call([$job, 'handle']), 200];
    }
}
