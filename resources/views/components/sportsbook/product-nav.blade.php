@props(['variant' => 'desktop'])

@php
    $products = [
        ['route' => 'home', 'label' => __('Sports'), 'icon' => 'trophy'],
        ['route' => 'live', 'label' => __('Live'), 'icon' => 'signal'],
        ['route' => 'jackpot', 'label' => __('Jackpot'), 'icon' => 'ticket'],
        ['route' => 'virtuals', 'label' => __('Virtuals'), 'icon' => 'cpu-chip', 'soon' => true],
        ['route' => 'crash', 'label' => __('Crash'), 'icon' => 'rocket-launch', 'soon' => true],
        ['route' => 'promotions', 'label' => __('Promotions'), 'icon' => 'gift'],
    ];
@endphp

<nav aria-label="{{ __('Products') }}" {{ $attributes->class([
    'hidden items-center gap-1 lg:flex' => $variant === 'desktop',
    'scrollbar-none flex gap-2 overflow-x-auto border-t border-ink-700 px-4 py-2 lg:hidden' => $variant === 'mobile',
]) }}>
    @foreach ($products as $product)
        @php $isCurrent = request()->routeIs($product['route']); @endphp

        <a
            href="{{ route($product['route']) }}"
            wire:navigate
            @if ($isCurrent) aria-current="page" @endif
            @class([
                'flex shrink-0 items-center gap-1.5 rounded-full text-sm font-medium transition-colors',
                'px-3 py-2' => $variant === 'desktop',
                'border px-3 py-1.5' => $variant === 'mobile',
                'bg-gold/10 text-gold' => $isCurrent,
                'border-gold/40' => $isCurrent && $variant === 'mobile',
                'text-smoke hover:text-ivory' => ! $isCurrent,
                'border-ink-600' => ! $isCurrent && $variant === 'mobile',
            ])
        >
            <flux:icon :name="$product['icon']" variant="micro" class="size-4" />
            {{ $product['label'] }}
            @if ($product['soon'] ?? false)
                <span class="rounded-full bg-ink-700 px-1.5 py-0.5 text-[0.625rem] font-semibold uppercase tracking-wide text-smoke">{{ __('Soon') }}</span>
            @endif
        </a>
    @endforeach
</nav>
