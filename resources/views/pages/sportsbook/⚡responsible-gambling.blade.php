<?php

use App\Enums\DepositLimitPeriod;
use App\Enums\ExclusionType;
use App\Models\DepositLimit;
use App\Support\Money;
use App\Wallet\ResponsibleGambling\DepositAllowance;
use App\Wallet\ResponsibleGambling\ExcludePlayer;
use App\Wallet\ResponsibleGambling\SetDepositLimit;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::sportsbook')] #[Title('Responsible gambling')] class extends Component {
    /** @var array<string, string> New limit per period, in shillings; blank removes it. */
    public array $limits = [];

    public string $exclusionType = 'cool-off';

    public string $duration = '';

    public string $password = '';

    public ?string $saved = null;

    public function mount(): void
    {
        foreach (DepositLimitPeriod::cases() as $period) {
            $amount = $this->currentLimits->get($period->value)?->effectiveAmount();
            $this->limits[$period->value] = $amount === null ? '' : (string) Money::toShillings($amount);
        }
    }

    public function saveLimit(string $period, SetDepositLimit $setDepositLimit): void
    {
        $periodCase = DepositLimitPeriod::from($period);

        try {
            $limit = $setDepositLimit->handle(auth()->user(), $periodCase, $this->limits[$period] ?? null);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(["limits.{$period}" => collect($e->errors())->flatten()->all()]);
        }

        unset($this->currentLimits, $this->allowance);

        $this->saved = $limit->hasWaitingChange()
            ? __('Your :period limit will change on :date. Raising or removing a limit takes :hours hours.', [
                'period' => mb_strtolower($periodCase->label()),
                'date' => $limit->pending_from?->timezone(config('sportsbook.timezone'))->format('j M, H:i'),
                'hours' => config('sportsbook.wallet.limit_raise_delay_hours'),
            ])
            : __('Your :period limit is saved and applies now.', ['period' => mb_strtolower($periodCase->label())]);
    }

    public function takeBreak(ExcludePlayer $excludePlayer): void
    {
        $this->validate([
            'exclusionType' => ['required', 'in:'.implode(',', array_map(fn (ExclusionType $type): string => $type->value, ExclusionType::cases()))],
            'duration' => ['required', 'string'],
            'password' => ['required', 'current_password'],
        ], ['password.current_password' => __('That password is not correct.')]);

        $excludePlayer->handle(auth()->user(), ExclusionType::from($this->exclusionType), $this->duration);

        $this->reset('password', 'duration');
        $this->dispatch('wallet-updated');
    }

    /**
     * @return Collection<string, DepositLimit>
     */
    #[Computed]
    public function currentLimits(): Collection
    {
        return auth()->user()->depositLimits()->get()->keyBy(fn (DepositLimit $limit): string => $limit->period->value);
    }

    #[Computed]
    public function allowance(): ?int
    {
        return app(DepositAllowance::class)->remaining(auth()->user());
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-4">
    <section class="glass-card p-5">
        <h1 class="font-display text-xl font-bold text-ivory">{{ __('Responsible gambling') }}</h1>
        <p class="mt-1 text-sm text-smoke">
            {{ __('Betting should stay fun. Set limits on what you deposit, or take a break. If gambling is causing you harm, please talk to someone you trust or a professional.') }}
        </p>
    </section>

    @if ($saved)
        <p class="rounded-lg border border-win/40 bg-win/10 p-4 text-sm text-ivory" role="status" data-test="limit-saved">{{ $saved }}</p>
    @endif

    <section class="glass-card p-5">
        <h2 class="font-display text-lg font-bold text-ivory">{{ __('Deposit limits') }}</h2>
        <p class="mt-1 text-sm text-smoke">
            {{ __('Each limit counts what you deposited over the period up to now. Lowering a limit applies at once; raising or removing one takes :hours hours.', ['hours' => config('sportsbook.wallet.limit_raise_delay_hours')]) }}
        </p>
        @if ($this->allowance !== null)
            <p class="mt-2 text-sm text-ivory" data-test="allowance">{{ __('You can deposit up to :amount right now.', ['amount' => Money::format($this->allowance)]) }}</p>
        @endif

        <div class="mt-4 space-y-4">
            @foreach (DepositLimitPeriod::cases() as $period)
                @php($limit = $this->currentLimits->get($period->value))
                <form wire:submit="saveLimit('{{ $period->value }}')" class="space-y-1" wire:key="limit-{{ $period->value }}">
                    <label class="block">
                        <span class="text-xs text-smoke">{{ $period->label() }} (KES)</span>
                        <div class="mt-1 flex gap-2">
                            <input
                                type="number"
                                inputmode="numeric"
                                step="1"
                                min="{{ config('sportsbook.wallet.min_deposit') }}"
                                wire:model="limits.{{ $period->value }}"
                                class="min-h-11 w-full rounded-lg border border-ink-600 bg-ink-800 px-3 tabular-nums text-ivory focus:border-gold/50"
                                placeholder="{{ __('No limit') }}"
                                data-test="limit-{{ $period->value }}"
                            >
                            <button type="submit" class="shrink-0 rounded-lg border border-gold/50 px-4 text-sm font-semibold text-gold hover:bg-gold/10">{{ __('Save') }}</button>
                        </div>
                    </label>
                    @error("limits.{$period->value}")
                        <p class="text-xs text-live">{{ $message }}</p>
                    @enderror
                    @if ($limit?->hasWaitingChange())
                        <p class="text-xs text-gold">
                            {{ __('Changing to :amount on :date.', [
                                'amount' => $limit->pending_amount === null ? __('no limit') : Money::format($limit->pending_amount),
                                'date' => $limit->pending_from?->timezone(config('sportsbook.timezone'))->format('j M, H:i'),
                            ]) }}
                        </p>
                    @endif
                </form>
            @endforeach
        </div>
    </section>

    <section class="glass-card p-5">
        <h2 class="font-display text-lg font-bold text-ivory">{{ __('Take a break') }}</h2>

        @if (auth()->user()->isExcluded())
            <p class="mt-2 rounded-lg border border-gold/30 bg-gold/10 p-3 text-sm text-ivory" data-test="current-break">
                {{ __('You are on a :type until :date. It can be made longer, but not shorter.', [
                    'type' => mb_strtolower((string) auth()->user()->exclusion_type?->label()),
                    'date' => auth()->user()->excluded_until?->timezone(config('sportsbook.timezone'))->format('j M Y, H:i'),
                ]) }}
            </p>
        @endif

        <p class="mt-2 text-sm text-smoke">
            {{ __('During a break you cannot deposit or bet, and it cannot be cancelled early, by you or by us. You can still log in and withdraw your balance.') }}
        </p>

        <form wire:submit="takeBreak" class="mt-4 space-y-3">
            <div class="grid grid-cols-2 gap-2">
                @foreach (ExclusionType::cases() as $type)
                    <label @class(['flex min-h-11 cursor-pointer items-center justify-center rounded-lg border px-3 text-sm', 'border-gold bg-gold/10 text-gold' => $exclusionType === $type->value, 'border-ink-600 text-ivory' => $exclusionType !== $type->value])>
                        <input type="radio" wire:model.live="exclusionType" value="{{ $type->value }}" class="sr-only">
                        {{ $type->label() }}
                    </label>
                @endforeach
            </div>

            <label class="block">
                <span class="text-xs text-smoke">{{ __('How long') }}</span>
                <select wire:model="duration" class="mt-1 min-h-11 w-full rounded-lg border border-ink-600 bg-ink-800 px-3 text-ivory" data-test="break-duration">
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach (ExclusionType::from($exclusionType)->durations() as $key => [$label])
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            @error('duration')
                <p class="text-xs text-live">{{ $message }}</p>
            @enderror

            <label class="block">
                <span class="text-xs text-smoke">{{ __('Your password, to confirm') }}</span>
                <input type="password" wire:model="password" autocomplete="current-password" class="mt-1 min-h-11 w-full rounded-lg border border-ink-600 bg-ink-800 px-3 text-ivory" data-test="break-password">
            </label>
            @error('password')
                <p class="text-xs text-live">{{ $message }}</p>
            @enderror

            <button type="submit" wire:confirm="{{ __('Start this break? It cannot be cancelled early.') }}" class="w-full rounded-full bg-live px-4 py-3 text-sm font-semibold text-ivory hover:opacity-90" data-test="break-button">
                {{ __('Start break') }}
            </button>
        </form>
    </section>
</div>
