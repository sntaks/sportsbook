<?php

use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public array $recoveryCodes = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->loadRecoveryCodes();
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
    {
        $generateNewRecoveryCodes(auth()->user());

        $this->loadRecoveryCodes();
    }

    /**
     * Load the recovery codes for the user.
     */
    private function loadRecoveryCodes(): void
    {
        $user = auth()->user();

        if ($user->hasEnabledTwoFactorAuthentication() && $user->two_factor_recovery_codes) {
            try {
                $this->recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            } catch (Exception) {
                $this->addError('recoveryCodes', 'Failed to load recovery codes');

                $this->recoveryCodes = [];
            }
        }
    }
}; ?>

<div
    class="space-y-3 rounded-lg border border-ink-700 bg-ink-900 p-4"
    wire:cloak
    x-data="{ showRecoveryCodes: false }"
>
    <div>
        <p class="flex items-center gap-2 text-sm font-semibold text-ivory">
            <flux:icon.lock-closed variant="outline" class="size-4 text-gold" />
            {{ __('Recovery codes') }}
        </p>
        <p class="mt-1 text-sm text-smoke">
            {{ __('If you lose your phone, a recovery code lets you log in. Keep them somewhere safe.') }}
        </p>
    </div>

    <div class="flex flex-wrap gap-2">
        <button
            type="button"
            x-show="!showRecoveryCodes"
            @click="showRecoveryCodes = true"
            aria-expanded="false"
            aria-controls="recovery-codes-section"
            class="rounded-full border border-gold/50 px-4 py-2 text-sm font-semibold text-gold hover:bg-gold/10"
        >{{ __('Show codes') }}</button>

        <button
            type="button"
            x-show="showRecoveryCodes"
            x-cloak
            @click="showRecoveryCodes = false"
            aria-expanded="true"
            aria-controls="recovery-codes-section"
            class="rounded-full border border-gold/50 px-4 py-2 text-sm font-semibold text-gold hover:bg-gold/10"
        >{{ __('Hide codes') }}</button>

        @if (filled($recoveryCodes))
            <button
                type="button"
                x-show="showRecoveryCodes"
                x-cloak
                wire:click="regenerateRecoveryCodes"
                class="rounded-full px-4 py-2 text-sm font-medium text-smoke hover:text-ivory"
            >{{ __('Make new codes') }}</button>
        @endif
    </div>

    <div
        x-show="showRecoveryCodes"
        x-cloak
        x-transition
        id="recovery-codes-section"
        x-bind:aria-hidden="!showRecoveryCodes"
        class="space-y-2"
    >
        @error('recoveryCodes')
            <p class="text-xs text-live">{{ $message }}</p>
        @enderror

        @if (filled($recoveryCodes))
            <div class="grid gap-1 rounded-lg bg-ink-800 p-4 font-mono text-sm text-ivory sm:grid-cols-2" role="list" aria-label="{{ __('Recovery codes') }}">
                @foreach ($recoveryCodes as $code)
                    <div role="listitem" class="select-text" wire:loading.class="animate-pulse opacity-50">{{ $code }}</div>
                @endforeach
            </div>
            <p class="text-xs text-smoke">{{ __('Each code works once. Making new codes cancels the old ones.') }}</p>
        @endif
    </div>
</div>
