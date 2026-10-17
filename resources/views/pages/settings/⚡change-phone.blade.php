<?php

use App\Accounts\ChangePhoneNumber;
use App\Support\PhoneNumber;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    public string $phone = '';

    public string $code = '';

    public bool $editing = false;

    /** The normalized new number a code was sent to. */
    #[Locked]
    public ?string $pendingPhone = null;

    public function sendCode(ChangePhoneNumber $changePhoneNumber): void
    {
        $this->pendingPhone = $changePhoneNumber->request(Auth::user(), $this->phone, request()->ip());
    }

    public function confirm(ChangePhoneNumber $changePhoneNumber): void
    {
        $this->validate(['code' => ['required', 'string']]);

        $changePhoneNumber->confirm(Auth::user(), $this->code);

        $this->reset();

        Flux::toast(variant: 'success', text: __('Phone number updated.'));
    }

    public function cancel(): void
    {
        $this->reset();
    }
}; ?>

<div class="space-y-3">
    <flux:field>
        <flux:label>{{ __('Phone number') }}</flux:label>
        <div class="flex items-center justify-between gap-4">
            <flux:text class="font-medium text-zinc-800 dark:text-white" data-test="current-phone">{{ PhoneNumber::mask(Auth::user()->phone) }}</flux:text>
            @unless ($editing)
                <flux:button size="sm" wire:click="$set('editing', true)">{{ __('Change') }}</flux:button>
            @endunless
        </div>
        <flux:description>{{ __('Deposits are charged to this number and withdrawals are paid to it.') }}</flux:description>
    </flux:field>

    @if ($editing && $pendingPhone === null)
        <form wire:submit="sendCode" class="space-y-3">
            <flux:input wire:model="phone" :label="__('New phone number')" type="tel" inputmode="tel" autocomplete="tel" placeholder="0712 345 678" required />
            <div class="flex gap-2">
                <flux:button type="submit" variant="primary" size="sm">{{ __('Send code') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="cancel">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @elseif ($pendingPhone !== null)
        <form wire:submit="confirm" class="space-y-3">
            <flux:input
                wire:model="code"
                :label="__('Code sent to :phone', ['phone' => PhoneNumber::mask($pendingPhone)])"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="{{ config('sportsbook.phone_codes.length') }}"
                required
            />
            <div class="flex gap-2">
                <flux:button type="submit" variant="primary" size="sm">{{ __('Confirm new number') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="cancel">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @endif
</div>
