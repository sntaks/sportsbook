<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The selections to save as a booking code.
 */
class StoreBookingCodeRequest extends FormRequest
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
            'selection_ids' => ['required', 'array', 'min:1'],
            'selection_ids.*' => ['required', 'integer'],
        ];
    }
}
