<?php

use App\Models\Fixture;
use App\Sportsbook\Catalogue\FixtureListing;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::sportsbook')] #[Title('Sports betting')] class extends Component {
    #[Url(except: 'highlights')]
    public string $tab = 'highlights';

    #[Url(as: 'market', except: 'h2h')]
    public string $marketKey = 'h2h';

    /**
     * A shared slip opened from a link like /?betslip=ABC123.
     */
    #[Url(as: 'betslip', except: '')]
    public string $bookingCode = '';

    public function mount(): void
    {
        $this->clampFilters();

        if ($this->bookingCode !== '') {
            $this->dispatch('load-booking-code', code: $this->bookingCode);
            $this->bookingCode = '';
        }
    }

    public function updated(): void
    {
        $this->clampFilters();
    }

    /**
     * @return Collection<int, Fixture>
     */
    #[Computed]
    public function fixtures(): Collection
    {
        return app(FixtureListing::class)->forTab($this->tab, $this->marketKey);
    }

    private function clampFilters(): void
    {
        $this->tab = array_key_exists($this->tab, FixtureListing::TABS) ? $this->tab : 'highlights';
        $this->marketKey = array_key_exists($this->marketKey, FixtureListing::LIST_MARKETS) ? $this->marketKey : 'h2h';
    }
};
?>

<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav aria-label="{{ __('Fixture lists') }}" class="scrollbar-none -mx-4 flex gap-1 overflow-x-auto px-4 lg:mx-0 lg:px-0">
            @foreach (FixtureListing::TABS as $key => $label)
                <button
                    type="button"
                    wire:click="$set('tab', '{{ $key }}')"
                    @if ($tab === $key) aria-current="true" @endif
                    @class([
                        'min-h-10 shrink-0 rounded-full px-4 text-sm font-medium transition-colors',
                        'bg-gold text-ink-950' => $tab === $key,
                        'bg-ink-800 text-smoke hover:text-ivory' => $tab !== $key,
                    ])
                >{{ __($label) }}</button>
            @endforeach
        </nav>

        <label class="flex items-center gap-2 text-sm text-smoke">
            <span>{{ __('Market') }}</span>
            <select wire:model.live="marketKey" class="min-h-10 rounded-lg border border-ink-600 bg-ink-800 px-3 text-sm text-ivory">
                @foreach (FixtureListing::LIST_MARKETS as $key => $label)
                    <option value="{{ $key }}">{{ __($label) }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <section wire:poll.300s class="overflow-hidden rounded-2xl border border-ink-700 bg-ink-900" aria-live="polite">
        @forelse ($this->fixtures as $fixture)
            <x-sportsbook.fixture-row :$fixture :market-key="$marketKey" />
        @empty
            <x-sportsbook.empty-state icon="trophy" :title="__('No matches to show')" class="rounded-none border-0">
                {{ __('There are no matches open for betting in this list right now. Try another tab.') }}
            </x-sportsbook.empty-state>
        @endforelse
    </section>
</div>
