<?php

namespace App\Http\Requests\Api\V1;

use App\Sportsbook\Catalogue\FixtureListing;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for a fixture list: the home page tab, the market shown per fixture and an optional competition slug.
 */
class ListFixturesRequest extends FormRequest
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
            'tab' => ['nullable', Rule::in(array_keys(FixtureListing::TABS))],
            'market' => ['nullable', Rule::in(array_keys(FixtureListing::LIST_MARKETS))],
            'competition' => ['nullable', 'string', 'max:255'],
        ];
    }
}
