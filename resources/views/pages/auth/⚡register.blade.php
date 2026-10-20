<?php

use App\Accounts\PhoneCode;
use App\Accounts\RegisterPlayer;
use App\Enums\PhoneCodePurpose;
use App\Promotions\ReferralCodes;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Register')] class extends Component {
    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $adult = false;

    /** A friend's invite code, filled in from their link (?ref=). */
    public string $referral_code = '';

    public string $code = '';

    /** The normalized number a code was sent to; set once the details step passes. */
    #[Locked]
    public ?string $pendingPhone = null;

    public function mount(): void
    {
        $this->referral_code = (string) ReferralCodes::normalize(request()->query('ref'));
    }

    /**
     * Check the details and text a code. The password is not kept on the component afterwards.
     */
    public function sendCode(RegisterPlayer $registerPlayer): void
    {
        $this->pendingPhone = $registerPlayer->start([
            'phone' => $this->phone,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
            'adult' => $this->adult,
            'referral_code' => $this->referral_code,
        ], request()->ip());

        $this->reset('password', 'password_confirmation', 'code');
    }

    public function resendCode(RegisterPlayer $registerPlayer): void
    {
        if ($this->pendingPhone !== null) {
            $registerPlayer->resend($this->pendingPhone, request()->ip());
        }
    }

    public function createAccount(RegisterPlayer $registerPlayer): void
    {
        if ($this->pendingPhone === null) {
            return;
        }

        $this->validate(['code' => ['required', 'string']]);

        $user = $registerPlayer->complete($this->pendingPhone, $this->code);

        Auth::login($user);
        session()->regenerate();

        $this->redirectIntended(route('home', absolute: false), navigate: true);
    }

    public function changeNumber(): void
    {
        $this->reset('pendingPhone', 'code');
    }

    public function secondsUntilResend(): int
    {
        return $this->pendingPhone === null
            ? 0
            : app(PhoneCode::class)->secondsUntilResend($this->pendingPhone, PhoneCodePurpose::Registration);
    }
}; ?>

<div class="flex flex-col gap-6">
    @if ($pendingPhone === null)
        <x-auth-header :title="__('Create an account')" :description="__('Sign up with your phone number. We will text you a code to confirm it.')" />

        <form wire:submit="sendCode" class="flex flex-col gap-6">
            <flux:input
                wire:model="phone"
                :label="__('Phone number')"
                type="tel"
                inputmode="tel"
                required
                autofocus
                autocomplete="tel"
                placeholder="0712 345 678"
            />

            <flux:input
                wire:model="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                viewable
            />

            <flux:input
                wire:model="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                viewable
            />

            @if (ReferralCodes::enabled())
                <flux:input
                    wire:model="referral_code"
                    :label="__('Invite code')"
                    :badge="__('Optional')"
                    autocomplete="off"
                    autocapitalize="characters"
                    maxlength="20"
                    :placeholder="__('From a friend\'s invite link')"
                />
            @endif

            <flux:field variant="inline">
                <flux:checkbox wire:model="adult" />
                <flux:label>
                    {{ __('I am :age or older and accept the', ['age' => config('sportsbook.minimum_age')]) }}
                    <flux:link :href="route('terms')" target="_blank">{{ __('Terms & Conditions') }}</flux:link>
                </flux:label>
                <flux:error name="adult" />
            </flux:field>

            <flux:button type="submit" variant="primary" class="w-full" data-test="register-send-code-button">
                {{ __('Send code') }}
            </flux:button>
        </form>
    @else
        <x-auth-header
            :title="__('Enter your code')"
            :description="__('We sent a :length-digit code to :phone.', ['length' => config('sportsbook.phone_codes.length'), 'phone' => PhoneNumber::mask($pendingPhone)])"
        />

        <form wire:submit="createAccount" class="flex flex-col gap-6">
            <flux:input
                wire:model="code"
                :label="__('Code')"
                inputmode="numeric"
                autocomplete="one-time-code"
                required
                autofocus
                maxlength="{{ config('sportsbook.phone_codes.length') }}"
            />

            <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                {{ __('Create account') }}
            </flux:button>
        </form>

        <div
            wire:key="resend-{{ $pendingPhone }}"
            x-data="{ left: {{ $this->secondsUntilResend() }} }"
            x-init="setInterval(() => { if (left > 0) left-- }, 1000)"
            class="flex items-center justify-between text-sm"
        >
            <flux:link class="cursor-pointer" wire:click="changeNumber">{{ __('Change number') }}</flux:link>

            <span x-show="left > 0" class="text-zinc-400">{{ __('Resend in') }} <span x-text="left"></span>s</span>
            <flux:link x-show="left === 0" class="cursor-pointer" wire:click="resendCode" x-on:click="left = {{ (int) config('sportsbook.phone_codes.resend_seconds') }}">
                {{ __('Resend code') }}
            </flux:link>
        </div>

        <flux:error name="phone" />
    @endif

    <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
        <span>{{ __('Already have an account?') }}</span>
        <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
    </div>
</div>
