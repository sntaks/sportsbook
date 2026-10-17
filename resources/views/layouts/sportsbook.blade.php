<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-ink-950 font-sans text-ivory antialiased" x-data="{ sportsOpen: false, betSlipOpen: false }">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-md focus:bg-gold focus:px-4 focus:py-2 focus:text-ink-950">
            {{ __('Skip to content') }}
        </a>

        <x-sportsbook.top-nav />

        <div class="mx-auto flex w-full max-w-[1440px] gap-0 lg:gap-4 lg:px-4">
            <x-sportsbook.sports-sidebar />

            <main id="main" class="min-w-0 flex-1 px-4 py-4 pb-24 lg:px-0 lg:pb-8">
                {{ $slot }}
            </main>

            <x-sportsbook.bet-slip />
        </div>

        <x-sportsbook.site-footer />

        <x-sportsbook.bottom-nav />

        @fluxScripts
    </body>
</html>
