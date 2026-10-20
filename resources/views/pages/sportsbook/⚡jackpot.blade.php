<?php

use App\Betting\Jackpot\EnterJackpot;
use App\Betting\Jackpot\JackpotEntryRefused;
use App\Models\Jackpot;
use App\Models\JackpotEntry;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The current jackpot. Picks live in the browser until the player enters; the server checks every pick, the
 * deadline and the balance again when the entry is made.
 */
new #[Layout('layouts::sportsbook')] #[Title('Jackpot')] class extends Component {
    /** One per entry: submitting the same picks twice finds the entry already made instead of paying again. */
    #[Locked]
    public string $entryKey = '';

    /** The entry just made, shown as a confirmation until the player starts another. */
    #[Locked]
    public ?int $enteredEntryId = null;

    /** Why the last attempt was refused (a JackpotEntryRefused reason), so the page can offer the right next step. */
    #[Locked]
    public ?string $refusal = null;

    public function mount(): void
    {
        $this->entryKey = (string) Str::uuid();
    }

    /**
     * @param  array<int|string, mixed>  $picks  Jackpot match id => "1", "X" or "2".
     */
    public function enter(array $picks, EnterJackpot $enterJackpot): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $jackpot = $this->jackpot;

        if ($jackpot === null) {
            return;
        }

        $this->resetErrorBag('entry');
        $this->refusal = null;

        try {
            $entry = $enterJackpot->handle($user, $jackpot, $picks, $this->entryKey);
        } catch (JackpotEntryRefused $e) {
            $this->refusal = $e->reason;
            $this->addError('entry', $e->getMessage());

            return;
        }

        $this->enteredEntryId = $entry->id;
        $this->entryKey = (string) Str::uuid();
        unset($this->myEntryCount);
        $this->dispatch('jackpot-entered');
        $this->dispatch('wallet-updated');
    }

    public function enterAgain(): void
    {
        $this->enteredEntryId = null;
    }

    #[Computed]
    public function jackpot(): ?Jackpot
    {
        return Jackpot::query()->current()->with('jackpotFixtures')->first();
    }

    #[Computed]
    public function closesAt(): ?CarbonImmutable
    {
        return $this->jackpot?->closesAt();
    }

    #[Computed]
    public function isTakingEntries(): bool
    {
        return $this->closesAt?->isFuture() ?? false;
    }

    #[Computed]
    public function enteredEntry(): ?JackpotEntry
    {
        return $this->enteredEntryId === null
            ? null
            : JackpotEntry::query()->whereKey($this->enteredEntryId)->where('user_id', auth()->id())->first();
    }

    #[Computed]
    public function myEntryCount(): int
    {
        return $this->jackpot === null || ! auth()->check()
            ? 0
            : $this->jackpot->entries()->where('user_id', auth()->id())->count();
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-4">
    @if ($this->jackpot === null)
        <x-sportsbook.empty-state icon="ticket" :title="__('No jackpot is open right now')">
            {{ __('When a jackpot opens, you will be able to predict every match here.') }}
        </x-sportsbook.empty-state>
    @else
        @php
            $jackpot = $this->jackpot;
            $matches = $jackpot->jackpotFixtures;
        @endphp

        <section class="glass-card space-y-4 p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gold">{{ __('Jackpot') }}</p>
                    <h1 class="font-display text-2xl font-bold text-ivory">{{ $jackpot->name }}</h1>
                    <p class="text-sm text-smoke">
                        {{ __('Predict all :count matches for :price.', ['count' => $matches->count(), 'price' => Money::format($jackpot->entry_price)]) }}
                    </p>
                </div>

                @if ($this->isTakingEntries)
                    <div
                        class="rounded-xl border border-ink-600 px-3 py-2 text-right"
                        x-data="{
                            closesAt: {{ $this->closesAt->getTimestampMs() }},
                            left: '',
                            tick() {
                                const seconds = Math.max(0, Math.floor((this.closesAt - Date.now()) / 1000));
                                const days = Math.floor(seconds / 86400);
                                const clock = [Math.floor(seconds / 3600) % 24, Math.floor(seconds / 60) % 60, seconds % 60]
                                    .map((part) => String(part).padStart(2, '0')).join(':');
                                this.left = (days > 0 ? days + 'd ' : '') + clock;
                            },
                        }"
                        x-init="tick(); setInterval(() => tick(), 1000)"
                        data-test="jackpot-countdown"
                    >
                        <p class="text-xs text-smoke">{{ __('Entries close in') }}</p>
                        <p class="font-display text-lg font-bold tabular-nums text-gold" x-text="left">&nbsp;</p>
                        <p class="text-xs text-smoke-dim">{{ $this->closesAt->timezone(config('sportsbook.timezone'))->format('D j M, H:i') }}</p>
                    </div>
                @else
                    <span class="rounded-full bg-ink-700 px-3 py-1 text-xs font-semibold text-smoke">{{ __('Entries closed') }}</span>
                @endif
            </div>

            <ul class="grid gap-2 sm:grid-cols-3" data-test="prize-tiers">
                @foreach ($jackpot->prize_tiers as $tier)
                    <li class="rounded-xl border border-ink-700 bg-ink-900/40 px-3 py-2">
                        <p class="text-xs text-smoke">{{ __(':correct of :count right', ['correct' => $tier['correct'], 'count' => $matches->count()]) }}</p>
                        <p @class(['font-semibold tabular-nums', 'text-gold text-lg' => $loop->first, 'text-ivory' => ! $loop->first])>{{ Money::format($tier['pool']) }}</p>
                    </li>
                @endforeach
            </ul>
            <p class="text-xs text-smoke-dim">{{ __('Each prize is shared equally by every entry with that many right. A void match counts as right for everyone.') }}</p>
        </section>

        @if ($this->enteredEntry)
            <section class="glass-card space-y-3 p-4 text-center" role="status" data-test="jackpot-entered">
                <flux:icon.check-circle class="mx-auto size-10 text-win" />
                <p class="font-semibold text-ivory">{{ __('You are in!') }}</p>
                <p class="text-sm text-smoke">
                    {{ __('Entry ID') }} <span class="font-mono tracking-wider text-ivory">{{ $this->enteredEntry->reference }}</span>
                    · {{ Money::format($this->enteredEntry->stake) }}
                </p>
                <div class="flex flex-wrap justify-center gap-2">
                    <a href="{{ route('my-bets', ['tab' => 'jackpot']) }}" wire:navigate class="min-h-10 rounded-full border border-ink-600 px-4 py-2 text-sm font-medium text-ivory hover:border-gold">{{ __('See my entries') }}</a>
                    @if ($this->isTakingEntries)
                        <button type="button" wire:click="enterAgain" class="min-h-10 rounded-full bg-gold px-4 py-2 text-sm font-semibold text-ink-950 hover:bg-gold-bright">{{ __('Enter again') }}</button>
                    @endif
                </div>
            </section>
        @else
            <section
                class="glass-card overflow-hidden"
                x-data="{
                    picks: {},
                    ids: @js($matches->pluck('id')),
                    pick(id, outcome) { this.picks[id] = this.picks[id] === outcome ? undefined : outcome; },
                    autoPick() { this.ids.forEach((id) => this.picks[id] = ['1', 'X', '2'][Math.floor(Math.random() * 3)]); },
                    clear() { this.picks = {}; },
                    get count() { return this.ids.filter((id) => this.picks[id]).length; },
                }"
                x-on:jackpot-entered.window="clear()"
            >
                <header class="flex items-center justify-between gap-2 border-b border-ink-700 px-4 py-3">
                    <p class="text-sm text-smoke">
                        <span class="font-semibold tabular-nums text-ivory" x-text="count">0</span> / {{ $matches->count() }} {{ __('picked') }}
                    </p>
                    @if ($this->isTakingEntries)
                        <div class="flex gap-2">
                            <button type="button" x-on:click="autoPick()" class="min-h-9 rounded-full border border-ink-600 px-3 text-xs font-medium text-ivory hover:border-gold" data-test="auto-pick">{{ __('Auto-pick') }}</button>
                            <button type="button" x-on:click="clear()" class="min-h-9 rounded-full px-3 text-xs font-medium text-smoke hover:text-ivory">{{ __('Clear') }}</button>
                        </div>
                    @endif
                </header>

                <ol class="divide-y divide-ink-700">
                    @foreach ($matches as $match)
                        <li class="grid grid-cols-[1fr_auto] items-center gap-3 px-4 py-2.5" wire:key="jackpot-match-{{ $match->id }}">
                            <div class="min-w-0">
                                <p class="truncate text-sm text-ivory"><span class="mr-1 tabular-nums text-smoke-dim">{{ $match->position }}.</span>{{ $match->fixture_name }}</p>
                                <p class="text-xs text-smoke-dim">
                                    {{ $match->kicks_off_at->timezone(config('sportsbook.timezone'))->format('D j M, H:i') }}
                                    @if ($match->is_void)
                                        · <span class="text-smoke">{{ __('Void') }}</span>
                                    @elseif ($match->outcome !== null)
                                        · <span class="text-win">{{ __('Result: :outcome', ['outcome' => $match->outcome]) }}</span>
                                    @endif
                                </p>
                            </div>
                            <div class="grid w-36 grid-cols-3 gap-1.5">
                                @foreach (['1', 'X', '2'] as $outcome)
                                    <button
                                        type="button"
                                        class="odds-btn"
                                        aria-label="{{ $match->fixture_name }}: {{ $outcome }}"
                                        @if ($this->isTakingEntries)
                                            aria-pressed="false"
                                            x-bind:aria-pressed="picks[{{ $match->id }}] === '{{ $outcome }}' ? 'true' : 'false'"
                                            x-on:click="pick({{ $match->id }}, '{{ $outcome }}')"
                                        @else
                                            disabled
                                        @endif
                                    ><span class="odds-btn-price">{{ $outcome }}</span></button>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ol>

                @if ($this->isTakingEntries)
                    <footer class="space-y-3 border-t border-ink-700 px-4 py-3">
                        @error('entry')
                            <div class="rounded-lg border border-live/40 bg-live/10 p-3 text-xs text-ivory" role="alert" data-test="entry-error">
                                <p>{{ $message }}</p>
                                @if ($refusal === JackpotEntryRefused::INSUFFICIENT_FUNDS)
                                    <a href="{{ route('wallet') }}" wire:navigate class="mt-2 inline-block font-semibold text-gold hover:text-gold-bright">{{ __('Deposit now') }}</a>
                                @endif
                            </div>
                        @enderror

                        @auth
                            @if ($this->myEntryCount > 0)
                                <p class="text-xs text-smoke">{{ trans_choice('{1} You have 1 entry in this jackpot.|[2,*] You have :count entries in this jackpot.', $this->myEntryCount, ['count' => $this->myEntryCount]) }}</p>
                            @endif
                            <button
                                type="button"
                                x-on:click="$wire.enter(picks)"
                                x-bind:disabled="count < ids.length"
                                wire:loading.attr="disabled"
                                wire:target="enter"
                                class="w-full rounded-full bg-gold px-4 py-3 text-sm font-semibold text-ink-950 hover:bg-gold-bright disabled:cursor-not-allowed disabled:bg-ink-700 disabled:text-smoke"
                                data-test="enter-jackpot"
                            >
                                <span wire:loading.remove wire:target="enter">{{ __('Enter for :price', ['price' => Money::format($jackpot->entry_price)]) }}</span>
                                <span wire:loading wire:target="enter">{{ __('Entering…') }}</span>
                            </button>
                        @else
                            <a href="{{ route('login') }}" class="block w-full rounded-full bg-gold px-4 py-3 text-center text-sm font-semibold text-ink-950 hover:bg-gold-bright">
                                {{ __('Log in to enter') }}
                            </a>
                        @endauth
                    </footer>
                @endif
            </section>
        @endif
    @endif
</div>
