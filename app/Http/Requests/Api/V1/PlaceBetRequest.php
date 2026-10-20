<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\OddsChangePolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A bet slip to place. slip_key is generated once per slip by the app, so sending the same slip twice places it once.
 * The stake is either whole shillings or one of the player's free bets, never both.
 */
class PlaceBetRequest extends FormRequest
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
            'stake' => ['required_without:free_bet_id'],
            'free_bet_id' => ['nullable', 'integer', 'prohibits:stake'],
            'odds_change_policy' => ['nullable', Rule::enum(OddsChangePolicy::class)],
            'slip_key' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stake.required_without' => __('Enter a stake, or bet with a free bet.'),
            'free_bet_id.prohibits' => __('Leave out the stake when betting with a free bet: the free bet is the stake.'),
        ];
    }
}
