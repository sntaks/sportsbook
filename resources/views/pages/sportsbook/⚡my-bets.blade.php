<?php

use App\Enums\BetLegStatus;
use App\Enums\BetStatus;
use App\Enums\JackpotEntryStatus;
use App\Models\Bet;
use App\Models\JackpotEntry;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::sportsbook')] #[Title('My bets')] class extends Component {
    use WithPagination;

    /** "open", "settled" or "jackpot". */
    #[Url]
    public string $tab = 'open';

    public function show(string $tab): void
    {
        $this->tab = in_array($tab, ['settled', 'jackpot'], true) ? $tab : 'open';
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Bet>
     */
    #[Computed]
    public function bets(): LengthAwarePaginator
    {
        return Bet::query()
            ->where('user_id', auth()->id())
            ->when($this->tab === 'settled',
                fn ($query) => $query->where('status', '!=', BetStatus::Open),
                fn ($query) => $query->where('status', BetStatus::Open))
            ->with('legs')
            ->latest('placed_at')
            ->latest('id')
            ->paginate(10);
    }

    /**
     * @return LengthAwarePaginator<int, JackpotEntry>
     */
    #[Computed]
    public function jackpotEntries(): LengthAwarePaginator
    {
        return JackpotEntry::query()
            ->where('user_id', auth()->id())
            ->with(['jackpot', 'picks.jackpotFixture'])
            ->latest('placed_at')
            ->latest('id')
            ->paginate(10);
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-4">
    <div class="flex gap-2" role="tablist">
        @foreach (['open' => __('Open bets'), 'settled' => __('Settled'), 'jackpot' => __('Jackpot')] as $key => $label)
            <button
                type="button"
                role="tab"
                aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                wire:click="show('{{ $key }}')"
                @class([
                    'min-h-10 rounded-full px-4 text-sm font-medium',
                    'bg-gold text-ink-950' => $tab === $key,
                    'border border-ink-600 text-smoke hover:text-ivory' => $tab !== $key,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'jackpot')
        @forelse ($this->jackpotEntries as $entry)
            <article class="glass-card overflow-hidden" wire:key="jackpot-entry-{{ $entry->id }}" data-test="jackpot-entry-{{ $entry->reference }}">
                <header class="flex flex-wrap items-center justify-between gap-2 border-b border-ink-700 px-4 py-3">
                    <div>
                        <p class="text-sm font-semibold text-ivory">{{ $entry->jackpot->name }}</p>
                        <p class="text-xs text-smoke">
                            {{ __('Entry ID') }} <span class="font-mono tracking-wider text-ivory">{{ $entry->reference }}</span>
                            · {{ $entry->placed_at->timezone(config('sportsbook.timezone'))->format('j M Y, H:i') }}
                        </p>
                    </div>
                    <span @class([
                        'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        'bg-gold/15 text-gold' => $entry->status === JackpotEntryStatus::Open,
                        'bg-win/15 text-win' => $entry->status === JackpotEntryStatus::Won,
                        'bg-live/15 text-live' => $entry->status === JackpotEntryStatus::Lost,
                        'bg-ink-700 text-smoke' => $entry->status === JackpotEntryStatus::Refunded,
                    ])>{{ $entry->status->label() }}</span>
                </header>

                <ol class="divide-y divide-ink-700">
                    @foreach ($entry->picks->sortBy(fn ($pick) => $pick->jackpotFixture->position) as $pick)
                        @php($isRight = $pick->jackpotFixture->judge($pick->pick))
                        <li class="flex items-center justify-between gap-3 px-4 py-2" wire:key="jackpot-pick-{{ $pick->id }}">
                            <p class="min-w-0 truncate text-sm text-ivory">
                                <span class="mr-1 tabular-nums text-smoke-dim">{{ $pick->jackpotFixture->position }}.</span>{{ $pick->jackpotFixture->fixture_name }}
                            </p>
                            <p class="shrink-0 text-right text-xs">
                                <span @class([
                                    'inline-block min-w-7 rounded-md px-1.5 py-0.5 text-center font-semibold',
                                    'bg-ink-700 text-ivory' => $isRight === null,
                                    'bg-win/15 text-win' => $isRight === true,
                                    'bg-live/15 text-live' => $isRight === false,
                                ])>{{ $pick->pick }}</span>
                                @if ($pick->jackpotFixture->is_void)
                                    <span class="ml-1 text-smoke-dim">{{ __('Void') }}</span>
                                @elseif ($pick->jackpotFixture->outcome !== null)
                                    <span class="ml-1 text-smoke">{{ __('Result :outcome', ['outcome' => $pick->jackpotFixture->outcome]) }}</span>
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ol>

                <footer class="grid grid-cols-3 gap-2 border-t border-ink-700 px-4 py-3 text-sm">
                    <div>
                        <p class="text-xs text-smoke">{{ __('Entry') }}</p>
                        <p class="tabular-nums text-ivory">{{ Money::format($entry->stake) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-smoke">{{ __('Right') }}</p>
                        <p class="tabular-nums text-ivory">{{ $entry->correct_count ?? '—' }} / {{ $entry->picks->count() }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-smoke">{{ $entry->status === JackpotEntryStatus::Refunded ? __('Refunded') : __('Paid') }}</p>
                        <p @class(['tabular-nums font-semibold', 'text-win' => ($entry->prize ?? 0) > 0 && $entry->status === JackpotEntryStatus::Won, 'text-ivory' => $entry->status !== JackpotEntryStatus::Won])>{{ $entry->status->isSettled() ? Money::format($entry->prize ?? 0) : '—' }}</p>
                    </div>
                </footer>
            </article>
        @empty
            <x-sportsbook.empty-state icon="ticket" :title="__('No jackpot entries yet')">
                {{ __('Jackpot entries you make will be listed here with how each pick did.') }}
            </x-sportsbook.empty-state>
        @endforelse

        <div>{{ $this->jackpotEntries->links() }}</div>
    @elseif ($this->bets->isEmpty())
        <x-sportsbook.empty-state icon="clipboard-document-list" :title="$tab === 'open' ? __('No open bets') : __('No settled bets yet')">
            {{ $tab === 'open' ? __('Bets you place will be listed here until they are settled.') : __('Bets move here once every match on them has a result.') }}
        </x-sportsbook.empty-state>
    @else
        @foreach ($this->bets as $bet)
            <article class="glass-card overflow-hidden" wire:key="bet-{{ $bet->id }}" data-test="bet-{{ $bet->reference }}">
                <header class="flex flex-wrap items-center justify-between gap-2 border-b border-ink-700 px-4 py-3">
                    <div>
                        <p class="text-sm font-semibold text-ivory">
                            {{ $bet->type->label() }}
                            <span class="font-normal text-smoke">· {{ trans_choice('{1} :count selection|[2,*] :count selections', $bet->legs->count(), ['count' => $bet->legs->count()]) }}</span>
                            @if ($bet->isFreeBet())
                                <span class="ml-1 rounded-full bg-gold/15 px-2 py-0.5 text-xs font-medium text-gold" data-test="free-bet-badge">{{ __('Free bet') }}</span>
                            @endif
                            @if ($bet->bonus_percent > 0)
                                <span class="ml-1 rounded-full bg-ink-700 px-2 py-0.5 text-xs font-medium text-ivory">{{ __('+:percent% bonus', ['percent' => $bet->bonus_percent]) }}</span>
                            @endif
                        </p>
                        <p class="text-xs text-smoke">
                            {{ __('Bet ID') }} <span class="font-mono tracking-wider text-ivory">{{ $bet->reference }}</span>
                            · {{ $bet->placed_at->timezone(config('sportsbook.timezone'))->format('j M Y, H:i') }}
                        </p>
                    </div>
                    <span @class([
                        'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        'bg-gold/15 text-gold' => $bet->status === BetStatus::Open,
                        'bg-win/15 text-win' => $bet->status === BetStatus::Won,
                        'bg-live/15 text-live' => $bet->status === BetStatus::Lost,
                        'bg-ink-700 text-smoke' => in_array($bet->status, [BetStatus::Void, BetStatus::Cancelled], true),
                    ])>{{ $bet->status->label() }}</span>
                </header>

                <ul class="divide-y divide-ink-700">
                    @foreach ($bet->legs as $leg)
                        <li class="flex items-start justify-between gap-3 px-4 py-2.5" wire:key="leg-{{ $leg->id }}">
                            <div class="min-w-0">
                                <p class="truncate text-sm text-ivory">{{ $leg->selection_name }}</p>
                                <p class="truncate text-xs text-smoke">{{ $leg->market_name }} · {{ $leg->fixture_name }}</p>
                                <p class="text-xs text-smoke-dim">{{ $leg->kicks_off_at->timezone(config('sportsbook.timezone'))->format('D j M, H:i') }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-sm font-semibold tabular-nums text-ivory">{{ $leg->price }}</p>
                                <p @class([
                                    'text-xs',
                                    'text-smoke' => $leg->status === BetLegStatus::Open,
                                    'text-win' => $leg->status === BetLegStatus::Won,
                                    'text-live' => $leg->status === BetLegStatus::Lost,
                                    'text-smoke-dim' => $leg->status === BetLegStatus::Void,
                                ])>{{ $leg->status->label() }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <footer class="grid grid-cols-3 gap-2 border-t border-ink-700 px-4 py-3 text-sm">
                    <div>
                        <p class="text-xs text-smoke">{{ $bet->isFreeBet() ? __('Free bet stake') : __('Stake') }}</p>
                        <p class="tabular-nums text-ivory">{{ Money::format($bet->stake) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-smoke">{{ __('Odds') }}</p>
                        <p class="tabular-nums text-ivory">{{ $bet->total_odds }}</p>
                    </div>
                    <div class="text-right">
                        @if ($bet->status->isSettled())
                            <p class="text-xs text-smoke">{{ __('Paid') }}</p>
                            <p @class(['tabular-nums font-semibold', 'text-win' => ($bet->payout ?? 0) > 0, 'text-ivory' => ($bet->payout ?? 0) === 0])>{{ Money::format($bet->payout ?? 0) }}</p>
                            @if (($bet->bonus ?? 0) > 0)
                                <p class="text-xs text-smoke">{{ __('incl. :amount bonus', ['amount' => Money::format($bet->bonus)]) }}</p>
                            @endif
                        @else
                            <p class="text-xs text-smoke">{{ __('Possible win') }}</p>
                            <p class="font-semibold tabular-nums text-gold">{{ Money::format($bet->potential_payout) }}</p>
                        @endif
                    </div>
                </footer>
            </article>
        @endforeach

        <div>{{ $this->bets->links() }}</div>
    @endif
</div>
