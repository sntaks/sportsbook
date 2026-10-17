<?php

namespace App\Accounts;

use App\Enums\PhoneCodePurpose;
use App\Enums\PhoneCodeResult;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A player's number is where deposits are charged and payouts are sent, so a new number only replaces
 * the old one after a code sent to the new number comes back.
 */
class ChangePhoneNumber
{
    public function __construct(private PhoneCode $phoneCode) {}

    /**
     * Text a code to the new number. Returns the normalized new number.
     *
     * @throws ValidationException
     */
    public function request(User $user, mixed $newPhone, ?string $ip = null): string
    {
        $validated = Validator::make(['phone' => PhoneNumber::normalize(is_string($newPhone) ? $newPhone : null)], [
            'phone' => ['required', 'regex:'.PhoneNumber::FORMAT, Rule::notIn([$user->phone]), Rule::unique(User::class, 'phone')],
        ], [
            'phone.regex' => __('Enter a valid Kenyan mobile number.'),
            'phone.not_in' => __('That is already your number.'),
            'phone.unique' => __('This number belongs to another account.'),
        ])->safe();

        $newPhone = $validated->string('phone')->toString();
        $result = $this->phoneCode->send($newPhone, PhoneCodePurpose::PhoneChange, $ip);

        if (! in_array($result, [PhoneCodeResult::Sent, PhoneCodeResult::Cooldown], true)) {
            throw ValidationException::withMessages(['phone' => __($result->message())]);
        }

        Cache::put($this->pendingKey($user), $newPhone, now()->addMinutes((int) config('sportsbook.phone_codes.ttl_minutes')));

        return $newPhone;
    }

    /**
     * Switch to the new number once its code matches.
     *
     * @throws ValidationException
     */
    public function confirm(User $user, string $code): void
    {
        $newPhone = Cache::get($this->pendingKey($user));

        if (! is_string($newPhone)) {
            throw ValidationException::withMessages(['code' => __(PhoneCodeResult::Expired->message())]);
        }

        $result = $this->phoneCode->verify($newPhone, PhoneCodePurpose::PhoneChange, $code);

        if ($result !== PhoneCodeResult::Verified) {
            throw ValidationException::withMessages(['code' => __($result->message())]);
        }

        try {
            $user->forceFill(['phone' => $newPhone, 'phone_verified_at' => now()])->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => __('This number belongs to another account.')]);
        }

        Cache::forget($this->pendingKey($user));
    }

    private function pendingKey(User $user): string
    {
        return 'phone-change:pending:'.$user->id;
    }
}
