<?php

use App\Enums\MpesaDepositStatus;
use App\Models\MpesaDeposit;
use App\Models\WalletTransaction;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Wallet\Deposits\StartDeposit;
use App\Wallet\Ledger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::sportsbook')] #[Title('Wallet')] class extends Component {
    use WithPagination;

    /** Seconds the page keeps checking a deposit before telling the player they can leave. */
    private const WATCH_SECONDS = 120;

    public string $amount = '';

    /** The STK deposit this page is waiting on. */
    #[Locked]
    public ?int $depositId = null;

    public function setAmount(int $amount): void
    {
        $this->amount = (string) $amount;
    }

    public function deposit(StartDeposit $startDeposit): void
    {
        $deposit = $startDeposit->handle(auth()->user(), $this->amount === '' ? null : $this->amount);

        $this->depositId = $deposit->id;
        $this->reset('amount');
        unset($this->watchedDeposit);
    }

    /**
     * Polled while the deposit is pending. Tells the header to refresh once it has settled.
     */
    public function checkDeposit(): void
    {
        unset($this->watchedDeposit);

        if ($this->watchedDeposit !== null && ! $this->watchedDeposit->status->isPending()) {
            $this->dispatch('wallet-updated');
        }
    }

    public function dismiss(): void
    {
        $this->depositId = null;
    }

    #[Computed]
    public function watchedDeposit(): ?MpesaDeposit
    {
        return $this->depositId === null
            ? null
            : MpesaDeposit::query()->whereKey($this->depositId)->where('user_id', auth()->id())->first();
    }

    public function stillWatching(): bool
    {
        $deposit = $this->watchedDeposit;

        return $deposit !== null
            && $deposit->status->isPending()
            && $deposit->created_at !== null
            && $deposit->created_at->gt(now()->subSeconds(self::WATCH_SECONDS));
    }

    #[Computed]
    public function balance(): int
    {
        return app(Ledger::class)->balanceOf(auth()->user());
    }

    /**
     * @return LengthAwarePaginator<int, WalletTransaction>
     */
    #[Computed]
    public function transactions(): LengthAwarePaginator
    {
        return WalletTransaction::query()
            ->whereHas('wallet', fn ($wallet) => $wallet->where('user_id', auth()->id()))
            ->latest('id')
            ->paginate(15);
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-4">
    <section class="glass-card p-5">
        <p class="text-sm text-smoke">{{ __('Balance') }}</p>
        <p class="mt-1 font-display text-3xl font-bold tabular-nums text-gold" data-test="wallet-balance">{{ Money::format($this->balance) }}</p>
    </section>

    <section class="glass-card p-5">
        <h1 class="font-display text-xl font-bold text-ivory">{{ __('Deposit with M-Pesa') }}</h1>
        <p class="mt-1 text-sm text-smoke">
            {{ __('We will send a payment prompt to :phone. Enter your M-Pesa PIN on your phone to finish.', ['phone' => PhoneNumber::mask(auth()->user()->phone)]) }}
        </p>

        @if ($deposit = $this->watchedDeposit)
            <div
                @if ($this->stillWatching()) wire:poll.3s="checkDeposit" @endif
                @class([
                    'mt-4 rounded-lg border p-4 text-sm',
                    'border-gold/30 bg-gold/10 text-ivory' => $deposit->status->isPending(),
                    'border-win/40 bg-win/10 text-ivory' => $deposit->status === MpesaDepositStatus::Completed,
                    'border-live/40 bg-live/10 text-ivory' => ! $deposit->status->isPending() && $deposit->status !== MpesaDepositStatus::Completed,
                ])
                data-test="deposit-status"
                role="status"
            >
                @if ($deposit->status->isPending())
                    @if ($this->stillWatching())
                        <p class="font-semibold">{{ __('Check your phone') }}</p>
                        <p class="mt-1 text-smoke">{{ __('Enter your M-Pesa PIN to deposit :amount. This page updates by itself.', ['amount' => Money::format($deposit->amount)]) }}</p>
                    @else
                        <p class="font-semibold">{{ __('Still waiting for M-Pesa') }}</p>
                        <p class="mt-1 text-smoke">{{ __('If you paid, your deposit will appear here as soon as M-Pesa confirms it. You can leave this page.') }}</p>
                    @endif
                @elseif ($deposit->status === MpesaDepositStatus::Completed)
                    <p class="font-semibold">{{ __(':amount deposited', ['amount' => Money::format($deposit->amount)]) }}</p>
                    @if ($deposit->mpesa_receipt)
                        <p class="mt-1 text-smoke">{{ __('M-Pesa receipt :receipt', ['receipt' => $deposit->mpesa_receipt]) }}</p>
                    @endif
                @elseif ($deposit->status === MpesaDepositStatus::Cancelled)
                    <p class="font-semibold">{{ __('Deposit cancelled') }}</p>
                    <p class="mt-1 text-smoke">{{ __('The payment was cancelled on your phone. No money was taken.') }}</p>
                @elseif ($deposit->status === MpesaDepositStatus::Mismatch)
                    <p class="font-semibold">{{ __('Deposit under review') }}</p>
                    <p class="mt-1 text-smoke">{{ __('M-Pesa reported a different payment than we expected. Our team will check it and credit you.') }}</p>
                @else
                    <p class="font-semibold">{{ __('Deposit not completed') }}</p>
                    <p class="mt-1 text-smoke">{{ __('M-Pesa did not complete the payment. No money was taken; you can try again.') }}</p>
                @endif

                @unless ($deposit->status->isPending() && $this->stillWatching())
                    <button type="button" wire:click="dismiss" class="mt-3 text-xs font-medium text-gold hover:text-gold-bright">{{ __('Make another deposit') }}</button>
                @endunless
            </div>
        @endif

        @unless ($deposit && $this->stillWatching())
            <form wire:submit="deposit" class="mt-4 space-y-3">
                <label class="block">
                    <span class="text-xs text-smoke">{{ __('Amount (KES)') }}</span>
                    <input
                        type="number"
                        inputmode="numeric"
                        step="1"
                        min="{{ config('sportsbook.wallet.min_deposit') }}"
                        max="{{ config('sportsbook.wallet.max_deposit') }}"
                        wire:model="amount"
                        class="mt-1 min-h-11 w-full rounded-lg border border-ink-600 bg-ink-800 px-3 tabular-nums text-ivory focus:border-gold/50"
                        placeholder="{{ config('sportsbook.wallet.min_deposit') }}"
                        data-test="deposit-amount"
                    >
                </label>
                <div class="grid grid-cols-4 gap-1.5">
                    @foreach ([100, 500, 1000, 5000] as $quickAmount)
                        <button type="button" wire:click="setAmount({{ $quickAmount }})" class="min-h-9 rounded-md bg-ink-800 text-xs font-medium tabular-nums text-ivory hover:bg-ink-700">{{ number_format($quickAmount) }}</button>
                    @endforeach
                </div>
                @error('amount')
                    <p class="text-xs text-live" data-test="deposit-error">{{ $message }}</p>
                @enderror
                <button type="submit" wire:loading.attr="disabled" class="w-full rounded-full bg-gold px-4 py-3 text-sm font-semibold text-ink-950 hover:bg-gold-bright disabled:opacity-60" data-test="deposit-button">
                    <span wire:loading.remove wire:target="deposit">{{ __('Deposit') }}</span>
                    <span wire:loading wire:target="deposit">{{ __('Sending prompt…') }}</span>
                </button>
                <p class="text-xs text-smoke">
                    {{ __('Minimum KES :min, maximum KES :max per deposit.', ['min' => number_format((int) config('sportsbook.wallet.min_deposit')), 'max' => number_format((int) config('sportsbook.wallet.max_deposit'))]) }}
                </p>
            </form>
        @endunless

        @if (filled(config('mpesa.c2b.short_code')))
            <div class="mt-5 rounded-lg border border-ink-700 bg-ink-900 p-4 text-sm" data-test="paybill-instructions">
                <p class="font-semibold text-ivory">{{ __('Or pay through the M-Pesa menu') }}</p>
                <ol class="mt-2 list-decimal space-y-1 pl-5 text-smoke">
                    <li>{{ __('Lipa na M-Pesa, then Pay Bill') }}</li>
                    <li>{{ __('Business number:') }} <span class="font-semibold tabular-nums text-ivory">{{ config('mpesa.c2b.short_code') }}</span></li>
                    <li>{{ __('Account number: your phone number') }} <span class="font-semibold tabular-nums text-ivory">{{ '0'.substr((string) auth()->user()->phone, 3) }}</span></li>
                </ol>
            </div>
        @endif
    </section>

    <section class="glass-card p-5">
        <h2 class="font-display text-lg font-bold text-ivory">{{ __('Transactions') }}</h2>

        @if ($this->transactions->isEmpty())
            <p class="mt-3 text-sm text-smoke">{{ __('No transactions yet. Your deposits will appear here.') }}</p>
        @else
            <ul class="mt-3 divide-y divide-ink-700" data-test="transactions">
                @foreach ($this->transactions as $transaction)
                    <li class="flex items-center justify-between gap-4 py-3 text-sm" wire:key="transaction-{{ $transaction->id }}">
                        <div class="min-w-0">
                            <p class="text-ivory">{{ $transaction->type->label() }}</p>
                            <p class="truncate text-xs text-smoke">
                                {{ $transaction->created_at?->timezone(config('sportsbook.timezone'))->format('j M Y, H:i') }}
                                @if ($transaction->description)
                                    · {{ $transaction->description }}
                                @endif
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p @class(['font-semibold tabular-nums', 'text-win' => $transaction->amount > 0, 'text-ivory' => $transaction->amount < 0])>
                                {{ $transaction->amount > 0 ? '+' : '' }}{{ Money::format($transaction->amount) }}
                            </p>
                            <p class="text-xs tabular-nums text-smoke">{{ __('Balance :balance', ['balance' => Money::format($transaction->balance_after)]) }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-3">{{ $this->transactions->links() }}</div>
        @endif
    </section>
</div>
