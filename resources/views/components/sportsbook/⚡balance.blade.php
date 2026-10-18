<?php

use App\Support\Money;
use App\Wallet\Ledger;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The player's balance in the header. Read fresh on every page load and whenever something dispatches
 * "wallet-updated" (no websockets: decision 8).
 */
new class extends Component {
    #[On('wallet-updated')]
    public function refresh(): void
    {
        //
    }
}; ?>

<a
    href="{{ route('wallet') }}"
    wire:navigate
    class="flex items-center gap-1.5 whitespace-nowrap rounded-full border border-gold/30 bg-gold/10 px-2.5 py-1.5 text-sm font-semibold tabular-nums text-gold hover:border-gold/60 sm:px-3"
    aria-label="{{ __('Wallet balance') }}"
    data-test="header-balance"
>
    <flux:icon.wallet variant="micro" class="hidden size-4 sm:block" />
    {{ Money::format(app(Ledger::class)->balanceOf(auth()->user())) }}
</a>
