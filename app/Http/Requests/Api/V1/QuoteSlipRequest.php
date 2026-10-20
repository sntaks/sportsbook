<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bet slip picks with the prices the player saw, and an optional stake in whole shillings. free_bet prices the stake
 * as a free bet: the possible win is then only the winnings above it, with no multibet bonus.
 */
class QuoteSlipRequest extends FormRequest
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
            'picks' => ['present', 'array'],
            'picks.*.fixture_id' => ['nullable', 'integer'],
            'picks.*.selection_id' => ['required', 'integer'],
            'picks.*.price' => ['required', 'numeric'],
            'stake' => ['nullable', 'numeric'],
            'free_bet' => ['sometimes', 'boolean'],
        ];
    }
}
