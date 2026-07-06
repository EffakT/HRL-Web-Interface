<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A `submission_id` was reused with lap content that no longer matches the originally recorded
 * lap's stored `submission_hash` (SEC-01 audit follow-up), discovered at the durable
 * database-constraint layer rather than the cache-based idempotency guard — e.g. after the
 * cache entry has expired, been evicted, or the app restarted. Caught by
 * LapSubmissionController to return `409 idempotency_conflict` instead of silently replaying
 * the stale recorded lap as a success.
 */
class LapSubmissionConflictException extends RuntimeException {}
