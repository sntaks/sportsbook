<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A jackpot entry: one pick per match. entry_key is generated once per entry by the app, so sending the same entry
 * twice charges once. Whether every match has a valid pick is checked when the entry is made.
 */
class EnterJackpotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'picks' => ['required', 'array'],
            'picks.*.match_id' => ['required', 'integer', 'distinct'],
            'picks.*.pick' => ['required', 'string'],
            'entry_key' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * The picks keyed by jackpot match id.
     *
     * @return array<int, string>
     */
    public function picksByMatch(): array
    {
        return collect($this->array('picks'))
            ->mapWithKeys(fn (array $pick): array => [(int) $pick['match_id'] => (string) $pick['pick']])
            ->all();
    }
}
