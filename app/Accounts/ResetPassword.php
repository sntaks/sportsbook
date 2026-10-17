<?php

namespace App\Accounts;

use App\Concerns\PasswordValidationRules;
use App\Enums\PhoneCodePurpose;
use App\Enums\PhoneCodeResult;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Forgotten password, reset with an SMS code instead of an email link.
 *
 * request() answers the same way whether or not the number has an account, so the form cannot be used
 * to find out who plays here, and it never pays for an SMS to a number with no account.
 */
class ResetPassword
{
    use PasswordValidationRules;

    public function __construct(private PhoneCode $phoneCode) {}

    /**
     * Text a reset code if the number has an account. Returns the normalized phone number.
     *
     * @throws ValidationException
     */
    public function request(mixed $phone, ?string $ip = null): string
    {
        $phone = PhoneNumber::normalize(is_string($phone) ? $phone : null);

        if ($phone === null || ! PhoneNumber::isValid($phone)) {
            throw ValidationException::withMessages(['phone' => __('Enter a valid Kenyan mobile number.')]);
        }

        if (! User::query()->where('phone', $phone)->exists()) {
            return $phone;
        }

        $result = $this->phoneCode->send($phone, PhoneCodePurpose::PasswordReset, $ip);

        if (! in_array($result, [PhoneCodeResult::Sent, PhoneCodeResult::Cooldown], true)) {
            throw ValidationException::withMessages(['phone' => __($result->message())]);
        }

        return $phone;
    }

    /**
     * Set a new password once the code matches. Every other session, remembered login and API token is
     * signed out, since whoever forgot the password may not be the only one who knows the old one.
     *
     * @param  array{code?: mixed, password?: mixed, password_confirmation?: mixed}  $input
     *
     * @throws ValidationException
     */
    public function reset(string $phone, array $input): User
    {
        $validated = Validator::make($input, [
            'code' => ['required', 'string'],
            'password' => $this->passwordRules(),
        ])->safe();

        $phone = (string) PhoneNumber::normalize($phone);
        $result = $this->phoneCode->verify($phone, PhoneCodePurpose::PasswordReset, $validated->string('code')->toString());

        if ($result !== PhoneCodeResult::Verified) {
            throw ValidationException::withMessages(['code' => __($result->message())]);
        }

        $user = User::query()->where('phone', $phone)->firstOrFail();

        $user->forceFill([
            'password' => $validated->string('password')->toString(),
            'remember_token' => Str::random(60),
        ])->save();

        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        return $user;
    }
}
