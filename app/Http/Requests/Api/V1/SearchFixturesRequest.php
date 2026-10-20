<?php

namespace App\Http\Requests\Api\V1;

use App\Sportsbook\Catalogue\FixtureListing;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A team or competition name to search for.
 */
class SearchFixturesRequest extends FormRequest
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
            'q' => ['required', 'string', 'min:2', 'max:60'],
            'market' => ['nullable', Rule::in(array_keys(FixtureListing::LIST_MARKETS))],
        ];
    }
}
