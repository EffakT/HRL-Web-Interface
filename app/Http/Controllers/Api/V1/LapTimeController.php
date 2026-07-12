<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\LapTimeResource;
use App\Models\LapTime;
use App\Models\Server;

class LapTimeController extends Controller
{
    /**
     * GET /api/v1/laps/{lapTime} — a single real lap's detail, including per-checkpoint splits
     * where recorded. Uses the real `lap_times.id` directly (see docs/api.md's resolved "does
     * this need a different name now that 'record' isn't a stored row" open question — it does
     * not, this was never about a course record, just one specific submitted lap). Not scoped
     * to active servers — a lap's historical existence doesn't depend on whether its server was
     * later archived, matching this app's "full history, never pruned" philosophy.
     *
     * `LapTime::server()` is a plain (non-`withTrashed`) relation by design — every leaderboard
     * read in this app deliberately treats an archived server's laps as nonexistent (see
     * docs/decisions.md). That's the right default for rankings, but wrong here: this endpoint
     * addresses one specific lap directly, which should still show which server it was really
     * set on even if that server was later archived. Loaded explicitly with `withTrashed()`
     * rather than changing the shared relation, which every other real consumer depends on.
     */
    public function show(LapTime $lapTime): LapTimeResource
    {
        $lapTime->load(['player', 'map', 'splits']);
        $lapTime->setRelation('server', Server::withTrashed()->find($lapTime->server_id));

        return new LapTimeResource($lapTime);
    }
}
