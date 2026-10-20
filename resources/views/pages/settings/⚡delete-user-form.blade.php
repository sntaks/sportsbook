<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="glass-card p-5">
    <h2 class="font-display text-lg font-bold text-ivory">{{ __('Delete account') }}</h2>
    <p class="mt-1 text-sm text-smoke">{{ __('Accounts that have never deposited or bet can be deleted here. Otherwise contact support to close your account.') }}</p>

    <flux:modal.trigger name="confirm-user-deletion">
        <button type="button" class="mt-4 rounded-full border border-live/60 px-5 py-2 text-sm font-semibold text-live hover:bg-live/10" data-test="delete-user-button">
            {{ __('Delete account') }}
        </button>
    </flux:modal.trigger>

    <livewire:pages::settings.delete-user-modal />
</section>
