<footer class="mt-8 border-t border-ink-700 bg-ink-900 pb-24 lg:pb-0">
    <div class="mx-auto grid w-full max-w-[1440px] gap-8 px-4 py-10 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="font-display text-base font-bold uppercase tracking-[0.12em] text-gold">{{ config('app.name') }}</p>
            <p class="mt-3 text-sm text-smoke">{{ __('Sports betting for Kenya.') }}</p>
        </div>

        <div>
            <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-smoke">{{ __('Play') }}</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="{{ route('home') }}" wire:navigate class="text-ivory/80 hover:text-gold">{{ __('Sports betting') }}</a></li>
                <li><a href="{{ route('live') }}" wire:navigate class="text-ivory/80 hover:text-gold">{{ __('Live') }}</a></li>
                <li><a href="{{ route('jackpot') }}" wire:navigate class="text-ivory/80 hover:text-gold">{{ __('Jackpot') }}</a></li>
                <li><a href="{{ route('promotions') }}" wire:navigate class="text-ivory/80 hover:text-gold">{{ __('Promotions') }}</a></li>
            </ul>
        </div>

        <div>
            <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-smoke">{{ __('Legal') }}</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="{{ route('terms') }}" wire:navigate class="text-ivory/80 hover:text-gold">{{ __('Terms & Conditions') }}</a></li>
                <li><a href="{{ route('privacy') }}" wire:navigate class="text-ivory/80 hover:text-gold">{{ __('Privacy Policy') }}</a></li>
                <li><a href="{{ route('responsible-gambling') }}" wire:navigate class="text-ivory/80 hover:text-gold">{{ __('Responsible Gambling') }}</a></li>
            </ul>
        </div>

        <div>
            <div class="flex items-center gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full border-2 border-live text-sm font-bold text-ivory">{{ config('sportsbook.minimum_age') }}+</span>
                <p class="text-sm text-smoke">{{ __('Betting is restricted to persons aged :age years and above. Please play responsibly.', ['age' => config('sportsbook.minimum_age')]) }}</p>
            </div>
        </div>
    </div>

    <div class="border-t border-ink-700">
        <div class="mx-auto flex w-full max-w-[1440px] flex-col gap-2 px-4 py-4 text-xs text-smoke-dim sm:flex-row sm:justify-between">
            <p>&copy; {{ now()->year }} {{ config('app.name') }}</p>
            @if (filled(config('sportsbook.licence_text')))
                <p>{{ config('sportsbook.licence_text') }}</p>
            @endif
        </div>
    </div>
</footer>
