@props(['market', 'fixture'])

@php
    $isOpen = $market->isOpen() && ! $fixture->hasKickedOff();
    $columns = match (true) {
        $market->type === \App\Enums\MarketType::CorrectScore => 'grid-cols-3 sm:grid-cols-5',
        $market->selections->count() === 2 => 'grid-cols-2',
        default => 'grid-cols-3',
    };
@endphp

<section wire:key="market-{{ $market->id }}" data-match="{{ $fixture->name() }}" data-market="{{ $market->name() }}" class="rounded-2xl border border-ink-700 bg-ink-900 p-3 md:p-4">
    <header class="mb-3 flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-ivory">{{ $market->name() }}</h3>
        @unless ($isOpen)
            <span class="flex items-center gap-1 text-xs text-smoke">
                <flux:icon.lock-closed variant="micro" class="size-3.5" />
                {{ __('Suspended') }}
            </span>
        @endunless
    </header>

    <div class="grid {{ $columns }} gap-1.5">
        @foreach ($market->selections as $selection)
            <x-sportsbook.odds-button :$selection :$fixture :open="$isOpen" :label="$selection->name" wire:key="selection-{{ $selection->id }}" />
        @endforeach
    </div>
</section>
