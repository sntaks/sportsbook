<?php

use App\Enums\BetLegStatus;
use App\Enums\BetStatus;
use App\Models\Bet;
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

    /** "open" or "settled". */
    #[Url]
    public string $tab = 'open';

    public function show(string $tab): void
    {
        $this->tab = $tab === 'settled' ? 'settled' : 'open';
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
}; ?>

<div class="mx-auto max-w-3xl space-y-4">
    <div class="flex gap-2" role="tablist">
        @foreach (['open' => __('Open bets'), 'settled' => __('Settled')] as $key => $label)
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

    @if ($this->bets->isEmpty())
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
                        <p class="text-xs text-smoke">{{ __('Stake') }}</p>
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
