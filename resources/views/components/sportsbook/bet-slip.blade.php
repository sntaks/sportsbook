{{-- A sticky column on desktop; a bottom sheet opened from the mobile bottom bar on smaller screens. --}}
<div
    x-cloak
    x-show="betSlipOpen"
    x-transition.opacity
    x-on:click="betSlipOpen = false"
    class="fixed inset-0 z-40 bg-black/70 lg:hidden"
    aria-hidden="true"
></div>

<aside
    id="bet-slip"
    aria-label="{{ __('Bet slip') }}"
    x-on:keydown.escape.window="betSlipOpen = false"
    :class="betSlipOpen ? 'max-lg:translate-y-0' : 'max-lg:translate-y-full'"
    class="fixed inset-x-0 bottom-0 z-50 max-h-[85vh] shrink-0 translate-y-full overflow-y-auto rounded-t-2xl border-t border-ink-700 bg-ink-900 transition-transform duration-200
           lg:sticky lg:top-[4.5rem] lg:z-auto lg:mt-4 lg:max-h-[calc(100vh-5.5rem)] lg:w-80 lg:translate-y-0 lg:self-start lg:rounded-2xl lg:border lg:transition-none"
>
    <div class="flex items-center justify-between border-b border-ink-700 px-4 py-3">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-ivory">
            {{ __('Bet slip') }}
            <span class="rounded-full bg-ink-700 px-2 py-0.5 text-xs tabular-nums text-smoke">0</span>
        </h2>
        <button
            type="button"
            class="flex size-10 items-center justify-center rounded-md text-smoke hover:text-ivory lg:hidden"
            x-on:click="betSlipOpen = false"
            aria-label="{{ __('Close bet slip') }}"
        >
            <flux:icon.x-mark class="size-5" />
        </button>
    </div>

    <div class="px-4 py-8 text-center">
        <flux:icon.ticket class="mx-auto mb-3 size-8 text-gold-dim" />
        <p class="font-medium text-ivory">{{ __('Your bet slip is empty') }}</p>
        <p class="mt-1 text-sm text-smoke">{{ __('Tap the odds on any match to add a selection.') }}</p>
    </div>

    <p class="border-t border-ink-700 px-4 py-3 text-center text-xs text-smoke">
        {{ __(':age+ only. Please bet responsibly.', ['age' => config('sportsbook.minimum_age')]) }}
    </p>
</aside>
