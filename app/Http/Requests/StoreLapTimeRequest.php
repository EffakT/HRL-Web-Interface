<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * Payload shape confirmed from `ApiController.php-legacy`'s logged example — the game server
 * posts this flat body directly (no `data` wrapper; that nesting was the old controller's own
 * logging structure, not part of the wire format). `map_label` is accepted but ignored, same
 * as the old app — the display label is always computed server-side from `map_name`.
 */
class StoreLapTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // No login/token-based auth on this endpoint, matching the old app — see
        // docs/security.md. Request-level auth still isn't gated here; the SEC-01 HRL query
        // cross-check (LapSubmissionVerifier) happens in the controller instead, since it's an
        // async network call with structured rejection reasons, not a validation rule.
        return true;
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'race_type' => $this->input('race_type', 0),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'map_name' => ['required', 'string', 'max:255'],
            'player_hash' => ['required', 'string', 'max:255'],
            'player_name' => ['required', 'string', 'max:255'],
            // Upper bound (SEC-04 audit follow-up, docs/security.md) — real laps range
            // 15.3s-1170.9s (~19.5 min); config('webhook.max_lap_time_seconds')'s default 3600s
            // (1hr) leaves 3x headroom. See its own docblock for why splits.*.duration is bound
            // relative to this field instead of getting its own flat ceiling.
            'player_time' => ['required', 'numeric', 'gt:0', 'lte:'.config('webhook.max_lap_time_seconds')],
            'port' => ['required', 'integer', 'between:1,65535'],
            'race_type' => ['required', 'integer', 'between:0,2'],
            // Optional, not required: older Lua scripts that predate SEC-01's HRL query
            // verification (see docs/security.md, LapSubmissionVerifier) don't send this yet.
            // Its absence just means verification fails closed on 'token_mismatch'.
            'hrl_token' => ['nullable', 'string', 'max:64'],
            // Idempotency key (SEC-01 audit follow-up, docs/security.md) — a client that
            // generates one and resubmits the exact same value+content on a retry gets back the
            // original response instead of either a duplicate lap or a bare rejection. Without
            // one, the controller falls back to a content hash, which only dedupes exact-value
            // resubmissions, not e.g. two genuinely distinct laps that happen to tie on time.
            // `min:8` is a minimum-entropy floor, not a format requirement — it's always
            // namespaced by the submitting ip:port (LapSubmissionController), so this just
            // guards against a trivially-short value like "1" that a naive incrementing counter
            // might send. Required once HRL enforcement is on — at that point every submission
            // is already expected to come from an updated, HRL-aware Lua script, so requiring
            // its idempotency key too is no extra rollout burden; before then, an un-updated
            // script's submissions rely only on the weaker content-hash fallback, same as today.
            'submission_id' => [config('webhook.hrl_query.enforce') ? 'required' : 'nullable', 'string', 'min:8', 'max:64'],
            // `max` (SEC-04 audit follow-up, docs/security.md) is a hard protocol-wide ceiling,
            // not per-map — real maps top out at 14 checkpoints; see config('webhook.max_checkpoints')'s
            // own docblock. App\Jobs\ProcessNewLap separately learns and enforces each specific
            // map's own real checkpoint count, below this ceiling.
            'splits' => ['nullable', 'array', 'max:'.config('webhook.max_checkpoints')],
            // `distinct` (SEC-01 audit follow-up) rejects a payload with two splits claiming
            // the same checkpoint — without it, equal-key split ordering in
            // App\Helpers\LapSubmissionHash would stay payload-order-dependent, and a submitted
            // lap's checkpoint progression would be ambiguous.
            'splits.*.checkpoint_id' => ['required', 'integer', 'distinct'],
            // `lte:player_time` (SEC-04 audit follow-up) — a single checkpoint segment can't
            // legitimately take longer than the whole lap; confirmed against real data first
            // that this holds for every real split ever recorded (zero exceptions), so it's a
            // correctness constraint, not just an arbitrary cap. `gt:0` (SEC-04 review
            // follow-up) — a checkpoint segment can't take zero or negative time; real data has
            // no such rows, and this rule previously had no lower bound at all.
            'splits.*.duration' => ['required', 'numeric', 'gt:0', 'lte:player_time'],
            // Deliberately loose (SEC-04 audit follow-up) — real data shows these aren't
            // reliably lap-relative across different Lua script versions in the wild (some rows
            // use small relative-looking values, many use large absolute server-clock-like
            // values up to a literal 999999.99 sentinel), and neither field feeds any real
            // leaderboard/comparison logic today (only `duration` does). A tight bound would
            // reject real, already-accepted submissions for no functional benefit — this is
            // purely an overflow guard, not a sanity check.
            'splits.*.startTime' => ['nullable', 'numeric', 'gte:0', 'lte:99999999'],
            'splits.*.endTime' => ['nullable', 'numeric', 'gte:0', 'lte:99999999'],
        ];
    }

    /**
     * A map's checkpoint IDs form the real physical sequence `1..N` (SEC-04 review follow-up,
     * confirmed against real data: every map's recorded checkpoint IDs are a stable, contiguous
     * `1..N` set), not just N *distinct* values — `splits.*.checkpoint_id`'s `distinct` rule
     * alone still let `[-7, 40, 999]` or any other 3-distinct-value set through as a "valid"
     * 3-checkpoint submission. Checked here rather than as a per-field rule since it needs the
     * whole `splits` array at once.
     *
     * Applies to every `race_type`, including Any Order/Rally — "any order" means the *player*
     * doesn't have to complete checkpoints in course sequence, not that the physical checkpoint
     * IDs themselves change; `sort()` before comparing is exactly what already lets a submission
     * report them in any order. (TEST-01 audit follow-up, 2026-07-09: a real Any Order submission
     * with checkpoint IDs `[1, 4, 5]` against `bloodgulch`'s real, established 5-checkpoint
     * baseline was rejected here — investigated and confirmed correct: the map has checkpoints
     * 1-5, so a genuine Any Order lap should still report all 5, just potentially out of
     * sequence. The missing 2/3 point to the game server's Lua script under-reporting splits in
     * Any Order mode, not a backend validation bug — see docs/decisions.md.)
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $splits = $this->input('splits');

            if (! is_array($splits) || $splits === []) {
                return;
            }

            $checkpointIds = array_column($splits, 'checkpoint_id');

            if (count($checkpointIds) !== count(array_filter($checkpointIds, is_numeric(...)))) {
                // Non-numeric values are already rejected by `splits.*.checkpoint_id`'s own
                // `integer` rule — skip the sequence check so this doesn't pile a redundant,
                // confusing second error onto the same field.
                return;
            }

            // Cast before sorting/comparing — validated request input can carry a numeric field
            // as either a native int or a numeric string (e.g. form-encoded submissions), same
            // as App\Helpers\LapSubmissionHash::compute() already accounts for.
            $checkpointIds = array_map(intval(...), $checkpointIds);
            sort($checkpointIds);

            if ($checkpointIds !== range(1, count($checkpointIds))) {
                $validator->errors()->add('splits', 'The splits checkpoint IDs must form a contiguous sequence starting at 1.');
            }
        });
    }
}
