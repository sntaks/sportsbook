@props(['selection', 'fixture', 'open' => true, 'label' => null])

@php
    $price = $selection->price();
    $isBettable = $open && $price !== null;
    $movement = $isBettable ? $selection->recentMovement() : null;
    $label ??= $selection->shortLabel();
@endphp

<button
    type="button"
    {{ $attributes->class('odds-btn w-full') }}
    @if ($isBettable)
        aria-pressed="false"
        x-bind:aria-pressed="$store.betslip.has({{ $selection->id }}) ? 'true' : 'false'"
        x-on:click="$store.betslip.toggle({{ $fixture->id }}, {{ $selection->id }}, '{{ $price }}', $pickLabels)"
        data-pick-name="{{ $selection->name }}"
        aria-label="{{ $selection->name }} {{ $price }}"
        @if ($movement) data-movement="{{ $movement }}" @endif
    @else
        disabled
        aria-label="{{ $selection->name }}: {{ __('betting closed') }}"
    @endif
>
    <span class="odds-btn-label max-w-full truncate">{{ $label }}</span>
    @if ($isBettable)
        <span class="odds-btn-price">{{ $price }}</span>
    @else
        <flux:icon.lock-closed variant="micro" class="size-4 text-smoke" />
    @endif
</button>
