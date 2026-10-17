<?php

use App\Accounts\PhoneCode;
use App\Accounts\ResetPassword;
use App\Enums\PhoneCodePurpose;
use App\Support\PhoneNumber;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Forgot password')] class extends Component {
    public string $phone = '';

    public string $code = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** The normalized number the reset was requested for. */
    #[Locked]
    public ?string $pendingPhone = null;

    public function sendCode(ResetPassword $resetPassword): void
    {
        $this->pendingPhone = $resetPassword->request($this->phone, request()->ip());
    }

    public function resendCode(ResetPassword $resetPassword): void
    {
        if ($this->pendingPhone !== null) {
            $resetPassword->request($this->pendingPhone, request()->ip());
        }
    }

    /**
     * Set the new password, then send the player to log in with it. They are not logged in here, so
     * a reset can never skip their two-factor challenge.
     */
    public function resetPassword(ResetPassword $resetPassword): void
    {
        if ($this->pendingPhone === null) {
            return;
        }

        $resetPassword->reset($this->pendingPhone, [
            'code' => $this->code,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        session()->flash('status', __('Your password has been reset. Log in with your new password.'));

        $this->redirectRoute('login', navigate: true);
    }

    public function changeNumber(): void
    {
        $this->reset('pendingPhone', 'code', 'password', 'password_confirmation');
    }

    public function secondsUntilResend(): int
    {
        return $this->pendingPhone === null
            ? 0
            : app(PhoneCode::class)->secondsUntilResend($this->pendingPhone, PhoneCodePurpose::PasswordReset);
    }
}; ?>

<div class="flex flex-col gap-6">
    @if ($pendingPhone === null)
        <x-auth-header :title="__('Forgot password')" :description="__('Enter your phone number and we will text you a code to reset your password')" />

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

            <flux:button variant="primary" type="submit" class="w-full" data-test="password-reset-send-code-button">
                {{ __('Send code') }}
            </flux:button>
        </form>
    @else
        <x-auth-header
            :title="__('Reset password')"
            :description="__('If :phone has an account, we have texted it a code. Enter it with your new password.', ['phone' => PhoneNumber::mask($pendingPhone)])"
        />

        <form wire:submit="resetPassword" class="flex flex-col gap-6">
            <flux:input
                wire:model="code"
                :label="__('Code')"
                inputmode="numeric"
                autocomplete="one-time-code"
                required
                autofocus
                maxlength="{{ config('sportsbook.phone_codes.length') }}"
            />

            <flux:input
                wire:model="password"
                :label="__('New password')"
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

            <flux:button type="submit" variant="primary" class="w-full" data-test="reset-password-button">
                {{ __('Reset password') }}
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

    <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-400">
        <span>{{ __('Or, return to') }}</span>
        <flux:link :href="route('login')" wire:navigate>{{ __('log in') }}</flux:link>
    </div>
</div>
