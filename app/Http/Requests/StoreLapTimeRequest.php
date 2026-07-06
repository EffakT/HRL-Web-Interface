<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'player_time' => ['required', 'numeric', 'gt:0'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'race_type' => ['required', 'integer', 'between:0,2'],
            // Optional, not required: older Lua scripts that predate SEC-01's HRL query
            // verification (see docs/security.md, LapSubmissionVerifier) don't send this yet.
            // Its absence just means verification fails closed on 'token_mismatch'.
            'hrl_token' => ['nullable', 'string', 'max:64'],
            'splits' => ['nullable', 'array'],
            'splits.*.checkpoint_id' => ['required', 'integer'],
            'splits.*.duration' => ['required', 'numeric'],
            'splits.*.startTime' => ['nullable', 'numeric'],
            'splits.*.endTime' => ['nullable', 'numeric'],
        ];
    }
}
