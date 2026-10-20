<?php

use App\Concerns\PasswordValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
/* @chisel-passkeys */
use Laravel\Passkeys\Actions\DeletePasskey;
use Livewire\Attributes\Locked;
/* @end-chisel-passkeys */
/* @chisel-2fa */
use Livewire\Attributes\On;
/* @end-chisel-2fa */

new #[Layout('layouts::sportsbook')] #[Title('Security settings')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /* @chisel-2fa */
    public bool $canManageTwoFactor;

    public bool $twoFactorEnabled;

    public bool $requiresConfirmation;
    /* @end-chisel-2fa */

    /* @chisel-passkeys */
    #[Locked]
    public bool $canManagePasskeys;

    #[Locked]
    public array $passkeys = [];

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingPasskeyId = null;

    #[Locked]
    public string $deletingPasskeyName = '';
    /* @end-chisel-passkeys */

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        /* @chisel-2fa */
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication(auth()->user());
            }

            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }
        /* @end-chisel-2fa */

        /* @chisel-passkeys */
        $this->canManagePasskeys = Features::canManagePasskeys();

        if ($this->canManagePasskeys) {
            $this->loadPasskeys();
        }
        /* @end-chisel-passkeys */
    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password updated.'));
    }

    /* @chisel-passkeys */
    /**
     * Load the user's passkeys.
     */
    public function loadPasskeys(): void
    {
        $this->passkeys = auth()->user()->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get()
            ->map(fn ($passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'created_at_diff' => $passkey->created_at->diffForHumans(),
                'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
            ])
            ->toArray();
    }

    /**
     * Show the delete confirmation modal.
     */
    public function confirmDelete(int $passkeyId): void
    {
        $passkey = auth()->user()->passkeys()->findOrFail($passkeyId);

        $this->deletingPasskeyId = $passkey->id;
        $this->deletingPasskeyName = $passkey->name;
        $this->showDeleteModal = true;
    }

    /**
     * Delete the passkey.
     */
    public function deletePasskey(DeletePasskey $deletePasskey): void
    {
        if (! $this->deletingPasskeyId) {
            return;
        }

        $passkey = auth()->user()->passkeys()->findOrFail($this->deletingPasskeyId);

        $deletePasskey(auth()->user(), $passkey);

        $this->closeDeleteModal();
        $this->loadPasskeys();
    }

    /**
     * Close the delete confirmation modal.
     */
    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingPasskeyId = null;
        $this->deletingPasskeyName = '';
    }
    /* @end-chisel-passkeys */

    /* @chisel-2fa */
    /**
     * Handle the two-factor authentication enabled event.
     */
    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }
    /* @end-chisel-2fa */
}; ?>

<x-pages::settings.layout current="security.edit">
    <section class="glass-card p-5">
        <h2 class="font-display text-lg font-bold text-ivory">{{ __('Change password') }}</h2>
        <p class="mt-1 text-sm text-smoke">{{ __('Use a long password that you do not use anywhere else.') }}</p>

        <form method="POST" wire:submit="updatePassword" class="mt-4 space-y-3">
            <label class="block">
                <span class="text-xs text-smoke">{{ __('Current password') }}</span>
                <input type="password" wire:model="current_password" required autocomplete="current-password" class="mt-1 min-h-11 w-full rounded-lg border border-ink-600 bg-ink-800 px-3 text-ivory focus:border-gold/50">
            </label>
            @error('current_password')
                <p class="text-xs text-live">{{ $message }}</p>
            @enderror

            <label class="block">
                <span class="text-xs text-smoke">{{ __('New password') }}</span>
                <input type="password" wire:model="password" required autocomplete="new-password" passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" class="mt-1 min-h-11 w-full rounded-lg border border-ink-600 bg-ink-800 px-3 text-ivory focus:border-gold/50">
            </label>
            @error('password')
                <p class="text-xs text-live">{{ $message }}</p>
            @enderror

            <label class="block">
                <span class="text-xs text-smoke">{{ __('Confirm new password') }}</span>
                <input type="password" wire:model="password_confirmation" required autocomplete="new-password" passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" class="mt-1 min-h-11 w-full rounded-lg border border-ink-600 bg-ink-800 px-3 text-ivory focus:border-gold/50">
            </label>

            <button type="submit" wire:loading.attr="disabled" class="rounded-full bg-gold px-6 py-2.5 text-sm font-semibold text-ink-950 hover:bg-gold-bright disabled:opacity-60" data-test="update-password-button">
                {{ __('Save') }}
            </button>
        </form>
    </section>

    {{-- @chisel-2fa --}}
    @if ($canManageTwoFactor)
        <section class="glass-card p-5" wire:cloak>
            <div class="flex items-start justify-between gap-4">
                <h2 class="font-display text-lg font-bold text-ivory">{{ __('Two-factor authentication') }}</h2>
                <span @class([
                    'shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                    'bg-win/15 text-win' => $twoFactorEnabled,
                    'bg-ink-700 text-smoke' => ! $twoFactorEnabled,
                ])>{{ $twoFactorEnabled ? __('On') : __('Off') }}</span>
            </div>

            @if ($twoFactorEnabled)
                <p class="mt-1 text-sm text-smoke">{{ __('When you log in we ask for a 6-digit code from the authenticator app on your phone.') }}</p>

                <button type="button" wire:click="disable" wire:confirm="{{ __('Turn off two-factor authentication?') }}" class="mt-4 rounded-full border border-live/60 px-5 py-2 text-sm font-semibold text-live hover:bg-live/10">
                    {{ __('Turn off') }}
                </button>

                <div class="mt-5">
                    <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                </div>
            @else
                <p class="mt-1 text-sm text-smoke">{{ __('Add a second step to logging in: a 6-digit code from an authenticator app such as Google Authenticator.') }}</p>

                <flux:modal.trigger name="two-factor-setup-modal">
                    <button type="button" wire:click="$dispatch('start-two-factor-setup')" class="mt-4 rounded-full bg-gold px-5 py-2 text-sm font-semibold text-ink-950 hover:bg-gold-bright">
                        {{ __('Turn on') }}
                    </button>
                </flux:modal.trigger>

                <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
            @endif
        </section>
    @endif
    {{-- @end-chisel-2fa --}}

    {{-- @chisel-passkeys --}}
    @if ($canManagePasskeys)
        <section class="glass-card p-5" wire:cloak>
            <h2 class="font-display text-lg font-bold text-ivory">{{ __('Passkeys') }}</h2>
            <p class="mt-1 text-sm text-smoke">{{ __('Log in with your fingerprint, face or screen lock instead of a password.') }}</p>

            <ul class="mt-4 divide-y divide-ink-700 rounded-lg border border-ink-700 bg-ink-900">
                @forelse ($passkeys as $passkey)
                    <li class="flex items-center justify-between gap-4 px-4 py-3" wire:key="passkey-{{ $passkey['id'] }}">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gold/10">
                                <flux:icon.key class="size-5 text-gold" />
                            </span>
                            <div class="min-w-0">
                                <p class="flex items-center gap-2 truncate text-sm font-semibold text-ivory">
                                    {{ $passkey['name'] }}
                                    @if ($passkey['authenticator'])
                                        <span class="rounded-full bg-ink-700 px-2 py-0.5 text-xs font-medium text-smoke">{{ $passkey['authenticator'] }}</span>
                                    @endif
                                </p>
                                <p class="text-xs text-smoke">
                                    {{ __('Added :time', ['time' => $passkey['created_at_diff']]) }}
                                    @if ($passkey['last_used_at_diff'])
                                        · {{ __('Last used :time', ['time' => $passkey['last_used_at_diff']]) }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        <button type="button" wire:click="confirmDelete({{ $passkey['id'] }})" class="flex size-9 shrink-0 items-center justify-center rounded-lg text-smoke hover:bg-live/10 hover:text-live" aria-label="{{ __('Remove :name', ['name' => $passkey['name']]) }}">
                            <flux:icon.trash class="size-5" variant="outline" />
                        </button>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-sm text-smoke">{{ __('No passkeys yet.') }}</li>
                @endforelse
            </ul>

            <div class="mt-4">
                <x-passkey-registration />
            </div>
        </section>

        <flux:modal
            name="delete-passkey-modal"
            class="max-w-md md:min-w-md"
            @close="closeDeleteModal"
            wire:model="showDeleteModal"
        >
            <div class="space-y-5">
                <div>
                    <h2 class="font-display text-lg font-bold text-ivory">{{ __('Remove passkey') }}</h2>
                    <p class="mt-1 text-sm text-smoke">
                        {{ __('Remove the passkey ":name"? You will no longer be able to log in with it.', ['name' => $deletingPasskeyName]) }}
                    </p>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeDeleteModal" class="rounded-full px-4 py-2 text-sm font-medium text-smoke hover:text-ivory">{{ __('Cancel') }}</button>
                    <button type="button" wire:click="deletePasskey" class="rounded-full bg-live px-5 py-2 text-sm font-semibold text-ivory hover:opacity-90">{{ __('Remove passkey') }}</button>
                </div>
            </div>
        </flux:modal>
    @endif
    {{-- @end-chisel-passkeys --}}
</x-pages::settings.layout>
