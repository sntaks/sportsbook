<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sign-up details. RegisterPlayer checks the number, password strength, age and invite code.
 */
class StartRegistrationRequest extends FormRequest
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
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
            'password_confirmation' => ['required', 'string'],
            'adult' => ['required', 'boolean'],
            /** A friend's invite code, if they invited the player. */
            'referral_code' => ['nullable', 'string', 'max:20'],
        ];
    }
}
