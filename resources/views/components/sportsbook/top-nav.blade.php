<header class="sticky top-0 z-40 border-b border-ink-700 bg-ink-950/95 backdrop-blur">
    <div class="mx-auto flex h-14 w-full max-w-[1440px] items-center gap-4 px-4">
        <button
            type="button"
            class="-ml-2 flex size-10 items-center justify-center rounded-md text-smoke hover:text-ivory lg:hidden"
            x-on:click="sportsOpen = true"
            aria-label="{{ __('Open sports menu') }}"
        >
            <flux:icon.bars-3 class="size-6" />
        </button>

        <a href="{{ route('home') }}" wire:navigate class="font-display text-lg font-bold uppercase tracking-[0.12em] text-gold">
            {{ config('app.name') }}
        </a>

        <x-sportsbook.product-nav class="ml-4" />

        <div class="ml-auto flex items-center gap-1 sm:gap-2">
            @auth
                <flux:dropdown position="bottom" align="end">
                    <button type="button" class="flex items-center gap-2 rounded-full border border-ink-600 py-1 pl-1 pr-3 text-sm text-ivory hover:border-gold/40">
                        <span class="flex size-7 items-center justify-center rounded-full bg-gold/15 text-xs font-semibold text-gold">{{ auth()->user()->initials() }}</span>
                        <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                    </button>

                    <flux:menu>
                        <flux:menu.item :href="route('my-bets')" icon="clipboard-document-list" wire:navigate>{{ __('My bets') }}</flux:menu.item>
                        <flux:menu.item :href="route('profile.edit')" icon="cog-6-tooth" wire:navigate>{{ __('Account settings') }}</flux:menu.item>
                        <flux:menu.separator />
                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">{{ __('Log out') }}</flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            @else
                <a href="{{ route('login') }}" class="whitespace-nowrap rounded-full px-3 py-2 text-sm font-medium text-gold hover:bg-gold/10 sm:px-4">{{ __('Log in') }}</a>
                <a href="{{ route('register') }}" class="whitespace-nowrap rounded-full bg-gold px-3 py-2 text-sm font-semibold text-ink-950 hover:bg-gold-bright sm:px-4">{{ __('Register') }}</a>
            @endauth
        </div>
    </div>

    <x-sportsbook.product-nav variant="mobile" />
</header>
