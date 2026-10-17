<?php

use App\Models\Competition;
use App\Models\Fixture;
use App\Sportsbook\Catalogue\FixtureListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::sportsbook')] class extends Component {
    public Competition $competition;

    #[Url(as: 'market', except: 'h2h')]
    public string $marketKey = 'h2h';

    public function mount(Competition $competition): void
    {
        abort_unless($competition->is_synced, 404);

        $this->competition = $competition;
        $this->updated();
    }

    public function updated(): void
    {
        $this->marketKey = array_key_exists($this->marketKey, FixtureListing::LIST_MARKETS) ? $this->marketKey : 'h2h';
    }

    /**
     * @return Collection<int, Fixture>
     */
    #[Computed]
    public function fixtures(): Collection
    {
        return app(FixtureListing::class)->forTab('upcoming', $this->marketKey, $this->competition);
    }

    public function render(): View
    {
        return $this->view()->title($this->competition->name);
    }
};
?>

<div class="space-y-3">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-smoke">{{ $competition->sport->name }}</p>
            <h1 class="font-display text-2xl font-bold text-ivory">{{ $competition->name }}</h1>
        </div>

        <label class="flex items-center gap-2 text-sm text-smoke">
            <span>{{ __('Market') }}</span>
            <select wire:model.live="marketKey" class="min-h-10 rounded-lg border border-ink-600 bg-ink-800 px-3 text-sm text-ivory">
                @foreach (FixtureListing::LIST_MARKETS as $key => $label)
                    <option value="{{ $key }}">{{ __($label) }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <section wire:poll.300s class="overflow-hidden rounded-2xl border border-ink-700 bg-ink-900">
        @forelse ($this->fixtures as $fixture)
            <x-sportsbook.fixture-row :$fixture :market-key="$marketKey" />
        @empty
            <x-sportsbook.empty-state icon="trophy" :title="__('No matches to show')" class="rounded-none border-0">
                {{ __('There are no :competition matches open for betting right now.', ['competition' => $competition->name]) }}
            </x-sportsbook.empty-state>
        @endforelse
    </section>
</div>
