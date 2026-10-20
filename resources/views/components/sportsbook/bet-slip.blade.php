{{-- A sticky column on desktop; a bottom sheet opened from the mobile bottom bar on smaller screens. Both sit above the match panel. --}}
<div
    x-cloak
    x-show="$store.betslip.isOpen"
    x-transition.opacity
    x-on:click="$store.betslip.isOpen = false"
    class="fixed inset-0 z-[57] bg-black/70 lg:hidden"
    aria-hidden="true"
></div>

<aside
    id="bet-slip"
    aria-label="{{ __('Bet slip') }}"
    x-on:keydown.escape.window="$store.betslip.isOpen = false"
    :class="$store.betslip.isOpen ? 'max-lg:translate-y-0' : 'max-lg:translate-y-full'"
    class="fixed inset-x-0 bottom-0 z-[58] max-h-[85vh] shrink-0 translate-y-full overflow-y-auto rounded-t-2xl border-t border-ink-700 bg-ink-900 transition-transform duration-200
           lg:sticky lg:top-[4.5rem] lg:z-[58] lg:mt-4 lg:max-h-[calc(100vh-5.5rem)] lg:w-80 lg:translate-y-0 lg:self-start lg:rounded-2xl lg:border lg:transition-none"
>
    <livewire:sportsbook.slip />
</aside>
