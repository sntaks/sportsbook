{{-- A match's header and markets, shown on the match page and in the match panel. --}}
@props(['fixture', 'marketsByTab', 'tab', 'inPanel' => false])

<div class="space-y-3">
    <header class="rounded-2xl border border-ink-700 bg-ink-900 p-4">
        @if ($inPanel)
            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-smoke">{{ $fixture->competition->name }}</p>
        @else
            <a href="{{ route('competitions.show', $fixture->competition) }}" wire:navigate.hover class="text-xs font-semibold uppercase tracking-[0.08em] text-smoke hover:text-gold">
                {{ $fixture->competition->name }}
            </a>
        @endif
        <{{ $inPanel ? 'h2' : 'h1' }} class="mt-2 font-display text-xl font-bold text-ivory md:text-2xl">
            {{ $fixture->home_team }} <span class="text-smoke">v</span> {{ $fixture->away_team }}
        </{{ $inPanel ? 'h2' : 'h1' }}>
        <p class="mt-1 text-sm tabular-nums text-smoke">{{ $fixture->kickoffLabel() }} {{ __('(EAT)') }}</p>

        @if ($fixture->hasKickedOff())
            <p class="mt-3 flex items-center gap-2 rounded-lg border border-live/40 bg-live/10 px-3 py-2 text-sm text-ivory">
                <span class="size-2 rounded-full bg-live" aria-hidden="true"></span>
                {{ __('This match has started. Betting on it is closed.') }}
            </p>
        @endif
    </header>

    @if ($marketsByTab->isEmpty())
        <x-sportsbook.empty-state icon="lock-closed" :title="__('No markets open')">
            {{ __('Prices for this match are not available right now.') }}
        </x-sportsbook.empty-state>
    @else
        <nav aria-label="{{ __('Market groups') }}" class="scrollbar-none -mx-4 flex gap-1 overflow-x-auto px-4 lg:mx-0 lg:px-0">
            @foreach ($marketsByTab->keys() as $key)
                <button
                    type="button"
                    wire:click="$set('tab', '{{ $key }}')"
                    @if ($tab === $key) aria-current="true" @endif
                    @class([
                        'min-h-10 shrink-0 rounded-full px-4 text-sm font-medium transition-colors',
                        'bg-gold text-ink-950' => $tab === $key,
                        'bg-ink-800 text-smoke hover:text-ivory' => $tab !== $key,
                    ])
                >{{ \App\Sportsbook\Catalogue\MarketTabs::label($key) }}</button>
            @endforeach
        </nav>

        <div class="space-y-3">
            @foreach ($marketsByTab->get($tab, $marketsByTab->first()) as $market)
                <x-sportsbook.market-block :$market :$fixture />
            @endforeach
        </div>
    @endif
</div>
