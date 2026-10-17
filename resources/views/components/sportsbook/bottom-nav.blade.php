@php
    $itemClasses = 'flex min-h-[52px] flex-1 flex-col items-center justify-center gap-0.5 text-[0.6875rem] font-medium';
@endphp

<nav aria-label="{{ __('Quick links') }}" class="fixed inset-x-0 bottom-0 z-30 border-t border-ink-700 bg-ink-950/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden">
    <div class="flex">
        <a href="{{ route('home') }}" wire:navigate @class([$itemClasses, 'text-gold' => request()->routeIs('home'), 'text-smoke' => ! request()->routeIs('home')])>
            <flux:icon.home class="size-5" />
            {{ __('Home') }}
        </a>
        <a href="{{ route('promotions') }}" wire:navigate @class([$itemClasses, 'text-gold' => request()->routeIs('promotions'), 'text-smoke' => ! request()->routeIs('promotions')])>
            <flux:icon.gift class="size-5" />
            {{ __('Promos') }}
        </a>
        <button type="button" x-on:click="betSlipOpen = true" aria-controls="bet-slip" class="{{ $itemClasses }} text-smoke">
            <span class="relative flex size-9 items-center justify-center rounded-full bg-gold text-ink-950">
                <flux:icon.ticket class="size-5" />
                <span x-cloak x-show="$store.betslip.count() > 0" x-text="$store.betslip.count()" class="absolute -right-1.5 -top-1.5 flex min-w-5 items-center justify-center rounded-full bg-live px-1 text-[0.6875rem] font-bold leading-5 text-ivory"></span>
            </span>
            {{ __('Betslip') }}
        </button>
        <a href="{{ route('my-bets') }}" wire:navigate @class([$itemClasses, 'text-gold' => request()->routeIs('my-bets'), 'text-smoke' => ! request()->routeIs('my-bets')])>
            <flux:icon.clipboard-document-list class="size-5" />
            {{ __('My Bets') }}
        </a>
        <a href="{{ auth()->check() ? route('profile.edit') : route('login') }}" wire:navigate class="{{ $itemClasses }} text-smoke">
            <flux:icon.user-circle class="size-5" />
            {{ __('Account') }}
        </a>
    </div>
</nav>
