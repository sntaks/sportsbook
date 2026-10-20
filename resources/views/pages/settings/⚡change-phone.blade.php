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

<div class="mt-1 space-y-3">
    <p class="text-sm text-smoke">{{ __('Deposits are charged to this number and withdrawals are paid to it.') }}</p>
    <div class="flex items-center justify-between gap-4 rounded-lg border border-ink-700 bg-ink-900 px-4 py-3">
        <span class="font-semibold tabular-nums text-ivory" data-test="current-phone">{{ PhoneNumber::mask(Auth::user()->phone) }}</span>
        @unless ($editing)
            <button type="button" wire:click="$set('editing', true)" class="min-h-9 rounded-lg border border-gold/50 px-4 text-sm font-semibold text-gold hover:bg-gold/10">{{ __('Change') }}</button>
        @endunless
    </div>

    @if ($editing && $pendingPhone === null)
        <form wire:submit="sendCode" class="space-y-3">
            <label class="block">
                <span class="text-xs text-smoke">{{ __('New phone number') }}</span>
                <input type="tel" inputmode="tel" autocomplete="tel" wire:model="phone" placeholder="0712 345 678" required class="mt-1 min-h-11 w-full rounded-lg border border-ink-600 bg-ink-800 px-3 tabular-nums text-ivory focus:border-gold/50">
            </label>
            @error('phone')
                <p class="text-xs text-live">{{ $message }}</p>
            @enderror
            <div class="flex gap-2">
                <button type="submit" wire:loading.attr="disabled" class="rounded-full bg-gold px-5 py-2 text-sm font-semibold text-ink-950 hover:bg-gold-bright disabled:opacity-60">{{ __('Send code') }}</button>
                <button type="button" wire:click="cancel" class="rounded-full px-4 py-2 text-sm font-medium text-smoke hover:text-ivory">{{ __('Cancel') }}</button>
            </div>
        </form>
    @elseif ($pendingPhone !== null)
        <form wire:submit="confirm" class="space-y-3">
            <label class="block">
                <span class="text-xs text-smoke">{{ __('Code sent to :phone', ['phone' => PhoneNumber::mask($pendingPhone)]) }}</span>
                <input inputmode="numeric" autocomplete="one-time-code" maxlength="{{ config('sportsbook.phone_codes.length') }}" wire:model="code" required class="mt-1 min-h-11 w-full rounded-lg border border-ink-600 bg-ink-800 px-3 tabular-nums tracking-widest text-ivory focus:border-gold/50">
            </label>
            @error('code')
                <p class="text-xs text-live">{{ $message }}</p>
            @enderror
            <div class="flex gap-2">
                <button type="submit" wire:loading.attr="disabled" class="rounded-full bg-gold px-5 py-2 text-sm font-semibold text-ink-950 hover:bg-gold-bright disabled:opacity-60">{{ __('Confirm new number') }}</button>
                <button type="button" wire:click="cancel" class="rounded-full px-4 py-2 text-sm font-medium text-smoke hover:text-ivory">{{ __('Cancel') }}</button>
            </div>
        </form>
    @endif
</div>
