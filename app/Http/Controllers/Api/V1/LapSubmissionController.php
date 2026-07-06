<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLapTimeRequest;
use App\Jobs\ProcessNewLap;
use Illuminate\Http\JsonResponse;

/**
 * Rebuilt equivalent of `ApiController::newTime` (`ApiController.php-legacy`). See
 * docs/database.md's "Webhook → job flow" section for the full old-vs-new behavior writeup.
 */
class LapSubmissionController extends Controller
{
    /** POST /api/v1/laps — a Halo game server submitting a completed lap. */
    public function store(StoreLapTimeRequest $request): JsonResponse
    {
        $job = new ProcessNewLap(
            ip: $request->ip(),
            port: (int) $request->validated('port'),
            data: $request->validated(),
        );

        $result = app()->call([$job, 'handle']);

        return response()->json($result);
    }
}
