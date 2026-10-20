<?php

use App\Models\Fixture;
use App\Models\Market;
use App\Sportsbook\Catalogue\MarketTabs;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A match's markets in a panel that slides over the current page, opened from match links on the lists and the bet
 * slip. The address bar shows the match page while it is open, so a shared or reloaded link opens the full page.
 */
new class extends Component {
    #[Locked]
    public ?int $fixtureId = null;

    public string $tab = 'main';

    public function show(int $fixtureId): void
    {
        $this->fixtureId = Fixture::query()
            ->whereKey($fixtureId)
            ->whereHas('competition', fn ($query) => $query->where('is_synced', true))
            ->value('id');
        $this->tab = 'main';
        unset($this->fixture, $this->marketsByTab);
    }

    public function updatedTab(): void
    {
        $this->tab = array_key_exists($this->tab, MarketTabs::TABS) ? $this->tab : 'main';
    }

    #[Computed]
    public function fixture(): ?Fixture
    {
        return $this->fixtureId === null ? null : Fixture::query()->with('competition')->find($this->fixtureId);
    }

    /**
     * @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, Market>> Markets per tab, empty tabs left out.
     */
    #[Computed]
    public function marketsByTab(): Collection
    {
        return $this->fixture === null ? collect() : MarketTabs::group($this->fixture->markets()->with('selections')->get());
    }
};
?>

<div x-data="matchPanel" x-on:open-match.window="open($event.detail)" x-on:keydown.escape.window="isOpen && ! $store.betslip.isOpen && close()" data-match-panel>
    <div
        x-cloak
        x-show="isOpen"
        x-transition.opacity.duration.150ms
        x-on:click="close()"
        class="fixed inset-0 z-[55] bg-black/70"
        aria-hidden="true"
    ></div>

    <section
        x-cloak
        x-show="isOpen"
        x-transition:enter="transition-transform duration-200 ease-out"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition-transform duration-150 ease-in"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        role="dialog"
        aria-labelledby="match-panel-heading"
        class="fixed inset-y-0 right-0 z-[56] flex w-full flex-col border-l border-ink-700 bg-ink-950 shadow-2xl sm:max-w-xl lg:right-[calc((100vw-min(100vw,1440px))/2+22rem)]"
        data-test="match-panel"
    >
        <div class="flex items-center justify-between gap-3 border-b border-ink-700 px-4 py-3">
            <h2 id="match-panel-heading" class="truncate text-sm font-semibold text-ivory" x-text="title"></h2>
            <div class="flex shrink-0 items-center gap-1">
                <a :href="url" class="min-h-10 rounded-md px-2 py-2.5 text-xs font-medium text-smoke hover:text-gold">{{ __('Full page') }}</a>
                <button type="button" x-ref="close" x-on:click="close()" class="flex size-10 items-center justify-center rounded-md text-smoke hover:text-ivory" aria-label="{{ __('Close') }}">
                    <flux:icon.x-mark class="size-5" />
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto overscroll-contain p-4">
            {{-- Shown at once while the markets load, and whenever the panel is waiting for another match. --}}
            <div x-show="loaded !== requested" class="space-y-3" aria-hidden="true" data-test="match-panel-loading">
                <div class="h-28 animate-pulse rounded-2xl bg-ink-900"></div>
                <div class="flex gap-1">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="h-10 w-20 animate-pulse rounded-full bg-ink-900"></div>
                    @endfor
                </div>
                @for ($i = 0; $i < 3; $i++)
                    <div class="h-24 animate-pulse rounded-2xl bg-ink-900"></div>
                @endfor
            </div>

            <div x-show="loaded === requested">
                @if ($fixture = $this->fixture)
                    <x-sportsbook.match-details :$fixture :markets-by-tab="$this->marketsByTab" :$tab in-panel />
                @else
                    <x-sportsbook.empty-state icon="trophy" :title="__('Match not available')">
                        {{ __('This match is no longer open for betting.') }}
                    </x-sportsbook.empty-state>
                @endif
            </div>
        </div>

        <div class="border-t border-ink-700 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] lg:hidden">
            <button type="button" x-on:click="$store.betslip.isOpen = true" class="flex w-full items-center justify-between rounded-full bg-gold px-5 py-3 text-sm font-semibold text-ink-950 hover:bg-gold-bright">
                <span>{{ __('Bet slip') }}</span>
                <span class="rounded-full bg-ink-950/15 px-2 tabular-nums" x-text="$store.betslip.count()"></span>
            </button>
        </div>
    </section>
</div>
