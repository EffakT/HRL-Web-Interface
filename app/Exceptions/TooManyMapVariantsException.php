<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * SEC-04 review follow-up (docs/security.md) — `ProcessNewLap::resolveMap()` forks a submission
 * whose checkpoint count doesn't match a map's established baseline into its own
 * `{map_name}-splits-{count}` `Map` row, since a genuinely different course can share a map
 * file. Thrown when a base map name has already accumulated `config('webhook.max_map_variants_per_name')`
 * such forks — a further mismatch is rejected rather than creating another one, since an
 * unbounded number of "distinct courses" sharing one file looks like abuse or a corrupted client,
 * not real level design. Caught by LapSubmissionController to return `422 checkpoint_layout_mismatch`.
 */
class TooManyMapVariantsException extends RuntimeException {}
