<?php

use App\Enums\MarketType;
use App\Models\Fixture;
use App\Models\Market;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::sportsbook')] class extends Component {
    /**
     * Market tabs on the match page and the market types each one shows.
     */
    public const TABS = [
        'main' => ['label' => 'Main', 'types' => [MarketType::MatchResult, MarketType::DoubleChance, MarketType::DrawNoBet]],
        'goals' => ['label' => 'Goals', 'types' => [MarketType::Totals, MarketType::BothTeamsToScore]],
        'correct-score' => ['label' => 'Correct Score', 'types' => [MarketType::CorrectScore]],
        'half-time' => ['label' => 'Half-time', 'types' => [MarketType::HalfTimeFullTime]],
    ];

    public Fixture $fixture;

    #[Url(except: 'main')]
    public string $tab = 'main';

    public function mount(Fixture $fixture): void
    {
        abort_unless($fixture->competition->is_synced, 404);

        $this->fixture = $fixture;
        $this->updated();
    }

    public function updated(): void
    {
        $this->tab = array_key_exists($this->tab, self::TABS) ? $this->tab : 'main';
    }

    /**
     * @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, Market>> Markets per tab, empty tabs left out.
     */
    #[Computed]
    public function marketsByTab(): Collection
    {
        $markets = $this->fixture->markets()->with('selections')->get();

        return collect(self::TABS)
            ->map(fn (array $tab) => $markets
                ->filter(fn (Market $market): bool => in_array($market->type, $tab['types'], true) && $market->selections->isNotEmpty())
                ->sortBy(fn (Market $market): string => array_search($market->type, $tab['types'], true).'-'.str_pad((string) $market->line, 6, '0', STR_PAD_LEFT))
                ->values())
            ->filter(fn ($markets) => $markets->isNotEmpty());
    }

    public function tabLabel(string $key): string
    {
        return __(self::TABS[$key]['label'] ?? $key);
    }

    public function render(): View
    {
        return $this->view()->title($this->fixture->name());
    }
};
?>

<div class="space-y-3">
    <header class="rounded-2xl border border-ink-700 bg-ink-900 p-4">
        <a href="{{ route('competitions.show', $fixture->competition) }}" wire:navigate class="text-xs font-semibold uppercase tracking-[0.08em] text-smoke hover:text-gold">
            {{ $fixture->competition->name }}
        </a>
        <h1 class="mt-2 font-display text-xl font-bold text-ivory md:text-2xl">
            {{ $fixture->home_team }} <span class="text-smoke">v</span> {{ $fixture->away_team }}
        </h1>
        <p class="mt-1 text-sm tabular-nums text-smoke">{{ $fixture->kickoffLabel() }} {{ __('(EAT)') }}</p>

        @if ($fixture->hasKickedOff())
            <p class="mt-3 flex items-center gap-2 rounded-lg border border-live/40 bg-live/10 px-3 py-2 text-sm text-ivory">
                <span class="size-2 rounded-full bg-live" aria-hidden="true"></span>
                {{ __('This match has started. Betting on it is closed.') }}
            </p>
        @endif
    </header>

    @if ($this->marketsByTab->isEmpty())
        <x-sportsbook.empty-state icon="lock-closed" :title="__('No markets open')">
            {{ __('Prices for this match are not available right now.') }}
        </x-sportsbook.empty-state>
    @else
        <nav aria-label="{{ __('Market groups') }}" class="scrollbar-none -mx-4 flex gap-1 overflow-x-auto px-4 lg:mx-0 lg:px-0">
            @foreach ($this->marketsByTab->keys() as $key)
                <button
                    type="button"
                    wire:click="$set('tab', '{{ $key }}')"
                    @if ($tab === $key) aria-current="true" @endif
                    @class([
                        'min-h-10 shrink-0 rounded-full px-4 text-sm font-medium transition-colors',
                        'bg-gold text-ink-950' => $tab === $key,
                        'bg-ink-800 text-smoke hover:text-ivory' => $tab !== $key,
                    ])
                >{{ $this->tabLabel($key) }}</button>
            @endforeach
        </nav>

        <div class="space-y-3">
            @foreach ($this->marketsByTab->get($tab, $this->marketsByTab->first()) as $market)
                <x-sportsbook.market-block :$market :$fixture />
            @endforeach
        </div>
    @endif
</div>
