@props(['fixture', 'marketKey' => 'h2h'])

@php
    $market = $fixture->markets->firstWhere('key', $marketKey);
    $selections = $market?->selections ?? collect();
    $moreMarkets = max(($fixture->markets_count ?? 0) - ($market ? 1 : 0), 0);
    $placeholders = $marketKey === 'h2h' || $marketKey === 'double_chance' ? 3 : 2;
@endphp

<article wire:key="fixture-{{ $fixture->id }}" data-match="{{ $fixture->name() }}" class="grid gap-3 border-b border-ink-700 px-3 py-3 last:border-b-0 md:grid-cols-[minmax(0,1fr)_auto] md:items-center md:px-4">
    <a href="{{ route('matches.show', $fixture) }}" x-on:click="$openMatch($event, {{ $fixture->id }}, @js($fixture->name()))" class="group min-w-0">
        <p class="truncate text-xs text-smoke">
            <span class="tabular-nums">{{ $fixture->kickoffLabel() }}</span>
            <span aria-hidden="true">·</span>
            {{ $fixture->competition->name }}
        </p>
        <p class="mt-1 truncate font-medium text-ivory group-hover:text-gold">{{ $fixture->home_team }}</p>
        <p class="truncate font-medium text-ivory group-hover:text-gold">{{ $fixture->away_team }}</p>
    </a>

    <div class="flex items-center gap-2">
        <div data-market="{{ $market?->name() }}" @class(['grid flex-1 gap-1.5 md:w-64 md:flex-none', 'grid-cols-3' => $placeholders === 3, 'grid-cols-2' => $placeholders === 2])>
            @forelse ($selections as $selection)
                <x-sportsbook.odds-button :$selection :$fixture :open="$market->isOpen()" wire:key="selection-{{ $selection->id }}" />
            @empty
                @for ($i = 0; $i < $placeholders; $i++)
                    <span class="odds-btn opacity-45" aria-hidden="true"><span class="odds-btn-price text-smoke">—</span></span>
                @endfor
            @endforelse
        </div>

        <a href="{{ route('matches.show', $fixture) }}" x-on:click="$openMatch($event, {{ $fixture->id }}, @js($fixture->name()))" class="flex min-h-11 min-w-11 items-center justify-center rounded-lg text-xs font-semibold text-smoke hover:text-gold" aria-label="{{ __('All markets for :match', ['match' => $fixture->name()]) }}">
            +{{ $moreMarkets }}
        </a>
    </div>
</article>
