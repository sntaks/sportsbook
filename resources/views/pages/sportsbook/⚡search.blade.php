<?php

use App\Models\Fixture;
use App\Sportsbook\Catalogue\FixtureListing;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::sportsbook')] #[Title('Search')] class extends Component {
    #[Url(as: 'q', except: '')]
    public string $query = '';

    /**
     * @return Collection<int, Fixture>
     */
    #[Computed]
    public function fixtures(): Collection
    {
        return app(FixtureListing::class)->search($this->query);
    }
};
?>

<div class="space-y-3">
    <label class="block">
        <span class="sr-only">{{ __('Search teams and competitions') }}</span>
        <span class="relative block">
            <flux:icon.magnifying-glass class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-smoke" />
            <input
                type="search"
                wire:model.live.debounce.400ms="query"
                autofocus
                maxlength="60"
                placeholder="{{ __('Search teams and competitions') }}"
                class="min-h-12 w-full rounded-xl border border-ink-600 bg-ink-900 pl-11 pr-4 text-ivory placeholder:text-smoke-dim focus:border-gold/50"
            >
        </span>
    </label>

    @if (mb_strlen(trim($query)) >= 2)
        <section class="overflow-hidden rounded-2xl border border-ink-700 bg-ink-900">
            @forelse ($this->fixtures as $fixture)
                <x-sportsbook.fixture-row :$fixture />
            @empty
                <x-sportsbook.empty-state icon="magnifying-glass" :title="__('No matches found')" class="rounded-none border-0">
                    {{ __('No upcoming matches match “:query”.', ['query' => $query]) }}
                </x-sportsbook.empty-state>
            @endforelse
        </section>
    @else
        <p class="px-1 text-sm text-smoke">{{ __('Type at least two letters of a team or competition.') }}</p>
    @endif
</div>
