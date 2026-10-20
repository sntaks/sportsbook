<?php

namespace App\Accounts;

use App\Concerns\PasswordValidationRules;
use App\Enums\PhoneCodePurpose;
use App\Enums\PhoneCodeResult;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Two-step sign-up. start() checks the details and texts a code; complete() creates the account once
 * the code matches. Until then the details wait in the cache with the password already hashed, so
 * nobody can claim a number they don't hold.
 */
class RegisterPlayer
{
    use PasswordValidationRules;

    public function __construct(private PhoneCode $phoneCode) {}

    /**
     * Validate the sign-up details and send the code. Returns the normalized phone number.
     *
     * @param  array{phone?: mixed, password?: mixed, password_confirmation?: mixed, adult?: mixed}  $input
     *
     * @throws ValidationException
     */
    public function start(array $input, ?string $ip = null): string
    {
        $input['phone'] = PhoneNumber::normalize(is_string($input['phone'] ?? null) ? $input['phone'] : null);

        $validated = Validator::make($input, [
            'phone' => ['required', 'regex:'.PhoneNumber::FORMAT, Rule::unique(User::class, 'phone')],
            'password' => $this->passwordRules(),
            'adult' => ['accepted'],
        ], [
            'phone.regex' => __('Enter a valid Kenyan mobile number.'),
            'phone.unique' => __('This number already has an account. Log in instead.'),
            'adult.accepted' => __('You must be :age or older to sign up.', ['age' => config('sportsbook.minimum_age')]),
        ])->safe();

        $phone = $validated->string('phone')->toString();

        Cache::put($this->pendingKey($phone), [
            'password' => Hash::make($validated->string('password')->toString()),
            'adult_confirmed_at' => now()->toIso8601String(),
        ], now()->addMinutes((int) config('sportsbook.phone_codes.ttl_minutes')));

        // Going back and resubmitting within the resend wait keeps the code already sent.
        $this->sendCode($phone, $ip, allowCooldown: true);

        return $phone;
    }

    /**
     * Send another code to a number that has started signing up. A number with no sign-up waiting gets no SMS,
     * so this cannot be used to text strangers.
     *
     * @throws ValidationException
     */
    public function resend(string $phone, ?string $ip = null): void
    {
        $phone = (string) PhoneNumber::normalize($phone);

        if (! Cache::has($this->pendingKey($phone))) {
            throw ValidationException::withMessages(['phone' => __('Your sign-up has expired. Please start again.')]);
        }

        $this->sendCode($phone, $ip);
    }

    /**
     * Create the account once the code matches.
     *
     * @throws ValidationException
     */
    public function complete(string $phone, string $code): User
    {
        $phone = (string) PhoneNumber::normalize($phone);
        $pending = Cache::get($this->pendingKey($phone));

        // Checked before the code, so a correct code is never used up on a sign-up that has expired.
        if (! is_array($pending)) {
            throw ValidationException::withMessages(['code' => __('Your sign-up has expired. Please start again.')]);
        }

        $result = $this->phoneCode->verify($phone, PhoneCodePurpose::Registration, $code);

        if ($result !== PhoneCodeResult::Verified) {
            throw ValidationException::withMessages(['code' => __($result->message())]);
        }

        try {
            $user = User::create([
                'phone' => $phone,
                'password' => $pending['password'],
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => __('This number already has an account. Log in instead.')]);
        }

        $user->forceFill([
            'phone_verified_at' => now(),
            'adult_confirmed_at' => $pending['adult_confirmed_at'],
        ])->save();

        Cache::forget($this->pendingKey($phone));

        event(new Registered($user));

        return $user;
    }

    /**
     * @throws ValidationException
     */
    private function sendCode(string $phone, ?string $ip, bool $allowCooldown = false): void
    {
        $result = $this->phoneCode->send($phone, PhoneCodePurpose::Registration, $ip);

        if ($result !== PhoneCodeResult::Sent && ! ($allowCooldown && $result === PhoneCodeResult::Cooldown)) {
            throw ValidationException::withMessages(['phone' => __($result->message())]);
        }
    }

    private function pendingKey(string $phone): string
    {
        return 'registration:pending:'.$phone;
    }
}
