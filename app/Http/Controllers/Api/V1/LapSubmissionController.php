<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LapSubmissionVerifier;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLapTimeRequest;
use App\Jobs\ProcessNewLap;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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

        // Cheap replay/retry guard, independent of HRL verification below — an identical
        // payload arriving again within the window is almost certainly a duplicate, not a
        // second distinct lap (see docs/security.md). Cache::add() only succeeds the first time
        // a key is set, so this is atomic against near-simultaneous duplicate requests.
        $dedupeKey = 'lap-submission:'.hash('sha256', json_encode([
            $ip, $port, $data['player_hash'], $data['map_name'], $data['player_time'], $data['hrl_token'] ?? null,
        ]));

        if (! Cache::add($dedupeKey, true, now()->addSeconds(config('webhook.duplicate_window_seconds')))) {
            return response()->json(['success' => false, 'reason' => 'duplicate_submission'], 409);
        }

        if (config('webhook.hrl_query.enabled')) {
            $verification = $verifier->verify($ip, $port, $data);

            if (! $verification['verified']) {
                Log::warning('Lap submission failed HRL query verification', [
                    'ip' => $ip,
                    'port' => $port,
                    'reason' => $verification['reason'],
                    'enforced' => config('webhook.hrl_query.enforce'),
                ]);

                if (config('webhook.hrl_query.enforce')) {
                    return response()->json(['success' => false, 'reason' => $verification['reason']], 403);
                }
            }
        }

        $job = new ProcessNewLap(ip: $ip, port: $port, data: $data);

        $result = app()->call([$job, 'handle']);

        return response()->json($result);
    }
}
