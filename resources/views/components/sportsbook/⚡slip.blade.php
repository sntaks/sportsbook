<?php

use App\Betting\BetNotPlaced;
use App\Betting\BetSlipQuote;
use App\Betting\BettableSelections;
use App\Betting\BookingCodes\CreateBookingCode;
use App\Betting\BookingCodes\ResolveBookingCode;
use App\Betting\PlaceBet;
use App\Enums\OddsChangePolicy;
use App\Models\Bet;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The bet slip. Picks live in the browser (the Alpine "betslip" store); this component re-checks them against
 * current prices on the server every time they change, and never trusts a price sent by the browser.
 */
new class extends Component {
    /**
     * @var array<int, mixed> Picks as sent by the browser: [{fixture_id, selection_id, price}].
     */
    public array $picks = [];

    public string $stake = '';

    public ?string $bookingCode = null;

    public string $codeToLoad = '';

    public string $oddsPolicy = 'higher';

    /** One per slip: submitting the same slip twice finds the bet already placed instead of placing another. */
    #[Locked]
    public string $slipKey = '';

    /** The bet just placed, shown as a confirmation until the player starts a new slip. */
    #[Locked]
    public ?int $placedBetId = null;

    /** Why the last attempt was refused (a BetNotPlaced reason), so the slip can offer the right next step. */
    #[Locked]
    public ?string $refusal = null;

    /**
     * @var list<string> Selections on a loaded booking code that can no longer be bet on.
     */
    public array $unavailableFromCode = [];

    /**
     * @param  array<int, mixed>  $picks
     */
    public function mount(): void
    {
        $this->slipKey = (string) Str::uuid();
        $user = auth()->user();
        $this->oddsPolicy = $user instanceof User ? $user->odds_change_policy->value : OddsChangePolicy::Higher->value;
    }

    public function syncPicks(array $picks): void
    {
        $this->picks = array_values($picks);
        $this->bookingCode = null;
        $this->refusal = null;
        $this->resetErrorBag('bet');

        if ($this->picks !== []) {
            $this->placedBetId = null;
        }

        unset($this->quote);
    }

    /**
     * Remembered on the account, so the next slip starts with the same choice.
     */
    public function updatedOddsPolicy(string $value): void
    {
        $user = auth()->user();
        $policy = OddsChangePolicy::tryFrom($value);

        if ($user instanceof User && $policy !== null) {
            $user->forceFill(['odds_change_policy' => $policy])->save();
        }
    }

    public function placeBet(PlaceBet $placeBet): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $this->resetErrorBag('bet');
        $this->refusal = null;

        try {
            $bet = $placeBet->handle($user, $this->picks, trim($this->stake), OddsChangePolicy::tryFrom($this->oddsPolicy), $this->slipKey);
        } catch (BetNotPlaced $e) {
            $this->refusal = $e->reason;
            $this->addError('bet', $e->getMessage());
            unset($this->quote);

            return;
        }

        $this->placedBetId = $bet->id;
        $this->slipKey = (string) Str::uuid();
        $this->stake = '';
        $this->dispatch('betslip-clear');
        $this->dispatch('wallet-updated');
    }

    public function newSlip(): void
    {
        $this->placedBetId = null;
    }

    #[Computed]
    public function placedBet(): ?Bet
    {
        return $this->placedBetId === null
            ? null
            : Bet::query()->whereKey($this->placedBetId)->where('user_id', auth()->id())->first();
    }

    #[Computed]
    public function quote(): BetSlipQuote
    {
        return BetSlipQuote::for($this->picks, $this->stake, app(BettableSelections::class));
    }

    public function setStake(int $amount): void
    {
        $this->stake = (string) $amount;
    }

    public function acceptPriceChanges(): void
    {
        $this->dispatch('betslip-accept-prices', prices: $this->quote->changedPrices());
    }

    public function removeUnavailable(): void
    {
        $ids = collect($this->quote->lines)
            ->filter(fn (array $line): bool => $line['status'] === 'unavailable')
            ->map(fn (array $line): int => $line['selection']->id)
            ->values()
            ->all();

        $this->dispatch('betslip-remove', selectionIds: $ids);
    }

    public function createBookingCode(): void
    {
        $this->resetErrorBag();

        if (! $this->withinLimit('booking-code-create', 10)) {
            $this->addError('booking', __('Too many booking codes. Try again in a minute.'));

            return;
        }

        try {
            $user = auth()->user();
            $selectionIds = collect($this->quote->lines)->map(fn (array $line): int => $line['selection']->id)->all();
            $this->bookingCode = app(CreateBookingCode::class)->handle($selectionIds, $user instanceof User ? $user : null)->code;
        } catch (InvalidArgumentException $exception) {
            $this->addError('booking', $exception->getMessage());
        }
    }

    #[On('load-booking-code')]
    public function loadBookingCode(?string $code = null): void
    {
        $this->resetErrorBag();
        $code = trim($code ?? $this->codeToLoad);

        if ($code === '') {
            return;
        }

        if (! $this->withinLimit('booking-code-load', 30)) {
            $this->addError('loadCode', __('Too many attempts. Try again in a minute.'));

            return;
        }

        try {
            $resolved = app(ResolveBookingCode::class)->handle($code);
        } catch (InvalidArgumentException $exception) {
            $this->addError('loadCode', $exception->getMessage());

            return;
        }

        $this->codeToLoad = '';
        $this->unavailableFromCode = $resolved['unavailable'];
        $this->dispatch('betslip-replace', picks: $resolved['picks']);
    }

    private function withinLimit(string $action, int $perMinute): bool
    {
        $key = $action.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }
};
?>

<div
    class="flex h-full flex-col"
    x-init="if ($store.betslip.count() > 0) { $wire.syncPicks($store.betslip.picks) }"
    x-on:betslip-changed.window="$wire.syncPicks($event.detail.picks)"
    x-on:betslip-accept-prices.window="$store.betslip.acceptPrices($event.detail.prices)"
    x-on:betslip-remove.window="$event.detail.selectionIds.forEach((id) => $store.betslip.remove(id))"
    x-on:betslip-replace.window="$store.betslip.replace($event.detail.picks); betSlipOpen = true"
    x-on:betslip-clear.window="$store.betslip.clear()"
>
    @php $quote = $this->quote; @endphp

    <div class="flex items-center justify-between border-b border-ink-700 px-4 py-3">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-ivory">
            {{ __('Bet slip') }}
            <span class="rounded-full bg-ink-700 px-2 py-0.5 text-xs tabular-nums text-smoke" x-text="$store.betslip.count()">{{ count($quote->lines) }}</span>
        </h2>
        <div class="flex items-center gap-1">
            @if (count($quote->lines) > 0)
                <button type="button" x-on:click="$store.betslip.clear()" class="min-h-10 rounded-md px-2 text-xs font-medium text-smoke hover:text-live">{{ __('Clear') }}</button>
            @endif
            <button
                type="button"
                class="flex size-10 items-center justify-center rounded-md text-smoke hover:text-ivory lg:hidden"
                x-on:click="betSlipOpen = false"
                aria-label="{{ __('Close bet slip') }}"
            >
                <flux:icon.x-mark class="size-5" />
            </button>
        </div>
    </div>

    @if ($placedBet = $this->placedBet)
        <div class="px-4 py-5" role="status" data-test="bet-placed">
            <div class="flex items-center gap-2 text-win">
                <flux:icon.check-circle class="size-6" />
                <p class="font-semibold">{{ __('Bet placed') }}</p>
            </div>
            <dl class="mt-3 space-y-1.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-smoke">{{ __('Bet ID') }}</dt><dd class="font-mono font-semibold tracking-wider text-ivory" data-test="bet-reference">{{ $placedBet->reference }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-smoke">{{ $placedBet->type->label() }}</dt><dd class="tabular-nums text-ivory">{{ __('Odds') }} {{ $placedBet->total_odds }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-smoke">{{ __('Stake') }}</dt><dd class="tabular-nums text-ivory">{{ Money::format($placedBet->stake) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-smoke">{{ __('Possible win (before tax)') }}</dt><dd class="font-display font-bold tabular-nums text-gold">{{ Money::format($placedBet->potential_payout) }}</dd></div>
            </dl>
            <div class="mt-4 grid grid-cols-2 gap-2">
                <a href="{{ route('my-bets') }}" wire:navigate class="rounded-full border border-ink-600 px-3 py-2 text-center text-sm text-ivory hover:border-gold/40">{{ __('My bets') }}</a>
                <button type="button" wire:click="newSlip" class="rounded-full bg-gold px-3 py-2 text-sm font-semibold text-ink-950 hover:bg-gold-bright">{{ __('New slip') }}</button>
            </div>
        </div>
    @elseif (count($quote->lines) === 0)
        <div class="px-4 py-8 text-center">
            <flux:icon.ticket class="mx-auto mb-3 size-8 text-gold-dim" />
            <p class="font-medium text-ivory">{{ __('Your bet slip is empty') }}</p>
            <p class="mt-1 text-sm text-smoke">{{ __('Tap the odds on any match to add a selection.') }}</p>
        </div>
    @else
        <ul class="divide-y divide-ink-700">
            @foreach ($quote->lines as $line)
                @php $selection = $line['selection']; @endphp
                <li wire:key="slip-{{ $selection->id }}" @class(['px-4 py-3', 'opacity-60' => $line['status'] === 'unavailable'])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-ivory">{{ $selection->name }}</p>
                            <p class="truncate text-xs text-smoke">{{ $selection->market->name() }}</p>
                            <a href="{{ route('matches.show', $selection->market->fixture) }}" wire:navigate class="block truncate text-xs text-smoke hover:text-gold">{{ $selection->market->fixture->name() }}</a>
                        </div>
                        <div class="flex shrink-0 items-start gap-1">
                            <div class="text-right">
                                @if ($line['status'] === 'unavailable')
                                    <span class="text-sm text-smoke">—</span>
                                @else
                                    <span @class(['text-sm font-semibold tabular-nums', 'text-gold' => $line['status'] === 'price_changed', 'text-ivory' => $line['status'] === 'ok'])>{{ $selection->price() }}</span>
                                @endif
                                @if ($line['status'] === 'price_changed')
                                    <p class="text-xs tabular-nums text-smoke line-through">{{ $line['seen_price'] }}</p>
                                @endif
                            </div>
                            <button type="button" x-on:click="$store.betslip.remove({{ $selection->id }})" class="flex size-8 items-center justify-center rounded-md text-smoke hover:text-live" aria-label="{{ __('Remove :selection', ['selection' => $selection->name]) }}">
                                <flux:icon.x-mark variant="micro" class="size-4" />
                            </button>
                        </div>
                    </div>
                    @if ($line['message'])
                        <p class="mt-1 text-xs text-live">{{ __($line['message']) }}</p>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="space-y-3 border-t border-ink-700 px-4 py-3">
            @if ($quote->hasUnavailable())
                <button type="button" wire:click="removeUnavailable" class="w-full rounded-lg border border-ink-600 px-3 py-2 text-sm text-ivory hover:border-gold/40">
                    {{ __('Remove closed selections') }}
                </button>
            @endif

            @if ($quote->hasPriceChanges())
                <div class="rounded-lg border border-gold/30 bg-gold/10 p-3 text-sm text-ivory">
                    <p>{{ __('Some odds have changed since you added them.') }}</p>
                    <button type="button" wire:click="acceptPriceChanges" class="mt-2 w-full rounded-lg bg-gold px-3 py-2 font-semibold text-ink-950 hover:bg-gold-bright">
                        {{ __('Accept odds changes') }}
                    </button>
                </div>
            @endif

            <div class="flex items-center justify-between text-sm">
                <span class="text-smoke">{{ trans_choice('{1} Single|[2,*] Multi (:count)', $quote->bettableCount(), ['count' => $quote->bettableCount()]) }}</span>
                <span class="font-semibold tabular-nums text-ivory">{{ __('Odds') }} {{ number_format($quote->totalOdds, 2) }}</span>
            </div>

            <label class="block">
                <span class="text-xs text-smoke">{{ __('Stake (KES)') }}</span>
                <input
                    type="number"
                    inputmode="numeric"
                    step="1"
                    min="{{ config('sportsbook.limits.min_stake') }}"
                    max="{{ config('sportsbook.limits.max_stake') }}"
                    wire:model.live.debounce.300ms="stake"
                    class="mt-1 min-h-11 w-full rounded-lg border border-ink-600 bg-ink-800 px-3 tabular-nums text-ivory focus:border-gold/50"
                    placeholder="{{ config('sportsbook.limits.min_stake') }}"
                >
            </label>
            <div class="grid grid-cols-4 gap-1.5">
                @foreach ([50, 100, 500, 1000] as $amount)
                    <button type="button" wire:click="setStake({{ $amount }})" class="min-h-9 rounded-md bg-ink-800 text-xs font-medium tabular-nums text-ivory hover:bg-ink-700">{{ number_format($amount) }}</button>
                @endforeach
            </div>
            @foreach ($quote->stakeErrors as $error)
                <p class="text-xs text-live">{{ $error }}</p>
            @endforeach

            <div class="flex items-baseline justify-between">
                <span class="text-sm text-smoke">{{ __('Possible win (before tax)') }}</span>
                <span class="font-display text-lg font-bold tabular-nums text-gold">KES {{ number_format($quote->possibleWin, 2) }}</span>
            </div>
            @if ($quote->isPayoutCapped)
                <p class="text-xs text-smoke">{{ __('Payouts are capped at KES :amount.', ['amount' => number_format((float) config('sportsbook.limits.max_payout'))]) }}</p>
            @endif

            @auth
                @if (! auth()->user()->canBet())
                    <p class="rounded-lg border border-live/40 bg-live/10 p-3 text-xs text-ivory" data-test="cannot-bet">
                        {{ auth()->user()->isSuspended() ? __('Your account is suspended. Please contact support.') : __('Betting is paused while your break from betting runs.') }}
                    </p>
                @else
                    <label class="block">
                        <span class="sr-only">{{ __('Odds changes') }}</span>
                        <select wire:model.live="oddsPolicy" class="min-h-9 w-full rounded-lg border border-ink-600 bg-ink-800 px-2 text-xs text-smoke" data-test="odds-policy">
                            @foreach (OddsChangePolicy::cases() as $policy)
                                <option value="{{ $policy->value }}">{{ $policy->label() }}</option>
                            @endforeach
                        </select>
                    </label>

                    @error('bet')
                        <div class="rounded-lg border border-live/40 bg-live/10 p-3 text-xs text-ivory" role="alert" data-test="bet-error">
                            <p>{{ $message }}</p>
                            @if ($refusal === BetNotPlaced::INSUFFICIENT_FUNDS)
                                <a href="{{ route('wallet') }}" wire:navigate class="mt-2 inline-block font-semibold text-gold hover:text-gold-bright">{{ __('Deposit now') }}</a>
                            @endif
                        </div>
                    @enderror

                    <button
                        type="button"
                        wire:click="placeBet"
                        wire:loading.attr="disabled"
                        wire:target="placeBet"
                        @disabled($quote->bettableCount() === 0 || $quote->hasUnavailable() || $quote->stake === null || $quote->stakeErrors !== [])
                        class="w-full rounded-full bg-gold px-4 py-3 text-sm font-semibold text-ink-950 hover:bg-gold-bright disabled:cursor-not-allowed disabled:bg-ink-700 disabled:text-smoke"
                        data-test="place-bet"
                    >
                        <span wire:loading.remove wire:target="placeBet">{{ __('Place bet') }}</span>
                        <span wire:loading wire:target="placeBet">{{ __('Placing…') }}</span>
                    </button>
                @endif
            @else
                <a href="{{ route('login') }}" class="block w-full rounded-full bg-gold px-4 py-3 text-center text-sm font-semibold text-ink-950 hover:bg-gold-bright">
                    {{ __('Log in to bet') }}
                </a>
            @endauth

            <div class="border-t border-ink-700 pt-3">
                @if ($bookingCode)
                    <div x-data="{ copied: false, link: @js(url('/?betslip='.$bookingCode)), text: @js(__('My bet slip: :code', ['code' => $bookingCode])) }">
                        <p class="text-xs text-smoke">{{ __('Booking code') }}</p>
                        <p class="font-display text-2xl font-bold tracking-[0.2em] text-gold">{{ $bookingCode }}</p>
                        <div class="mt-2 grid grid-cols-4 gap-1.5 text-xs">
                            <a target="_blank" rel="noopener" :href="'https://wa.me/?text=' + encodeURIComponent(text + ' ' + link)" class="min-h-9 rounded-md bg-ink-800 py-2 text-center text-ivory hover:bg-ink-700">WhatsApp</a>
                            <a target="_blank" rel="noopener" :href="'https://t.me/share/url?url=' + encodeURIComponent(link) + '&text=' + encodeURIComponent(text)" class="min-h-9 rounded-md bg-ink-800 py-2 text-center text-ivory hover:bg-ink-700">Telegram</a>
                            <a :href="'sms:?&body=' + encodeURIComponent(text + ' ' + link)" class="min-h-9 rounded-md bg-ink-800 py-2 text-center text-ivory hover:bg-ink-700">SMS</a>
                            <button type="button" x-on:click="navigator.share ? navigator.share({ text, url: link }) : (navigator.clipboard.writeText(link), copied = true)" class="min-h-9 rounded-md bg-ink-800 py-2 text-ivory hover:bg-ink-700">
                                <span x-text="copied ? @js(__('Copied')) : @js(__('Share'))">{{ __('Share') }}</span>
                            </button>
                        </div>
                    </div>
                @else
                    <button type="button" wire:click="createBookingCode" class="w-full rounded-lg border border-ink-600 px-3 py-2 text-sm text-ivory hover:border-gold/40">
                        {{ __('Get booking code') }}
                    </button>
                @endif
                @error('booking')
                    <p class="mt-1 text-xs text-live">{{ $message }}</p>
                @enderror
            </div>
        </div>
    @endif

    <form wire:submit="loadBookingCode" class="mt-auto border-t border-ink-700 px-4 py-3">
        <label for="booking-code-input" class="text-xs text-smoke">{{ __('Have a booking code?') }}</label>
        <div class="mt-1 flex gap-2">
            <input id="booking-code-input" type="text" wire:model="codeToLoad" maxlength="12" autocapitalize="characters" autocomplete="off" class="min-h-10 min-w-0 flex-1 rounded-lg border border-ink-600 bg-ink-800 px-3 uppercase text-ivory focus:border-gold/50" placeholder="ABC123">
            <button type="submit" class="min-h-10 rounded-lg bg-ink-700 px-4 text-sm font-medium text-ivory hover:bg-ink-600">{{ __('Load') }}</button>
        </div>
        @error('loadCode')
            <p class="mt-1 text-xs text-live">{{ $message }}</p>
        @enderror
        @if ($unavailableFromCode !== [])
            <div class="mt-2 text-xs text-smoke">
                <p>{{ __('No longer available, so not added:') }}</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($unavailableFromCode as $description)
                        <li>{{ $description }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </form>

    <p class="border-t border-ink-700 px-4 py-3 text-center text-xs text-smoke">
        {{ __(':age+ only. Please bet responsibly.', ['age' => config('sportsbook.minimum_age')]) }}
    </p>
</div>
