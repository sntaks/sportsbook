<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\OddsChangePolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A bet slip to place. slip_key is generated once per slip by the app, so sending the same slip twice places it once.
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
            'stake' => ['required'],
            'odds_change_policy' => ['nullable', Rule::enum(OddsChangePolicy::class)],
            'slip_key' => ['required', 'string', 'max:64'],
        ];
    }
}
