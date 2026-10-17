<?php

use App\Models\Fixture;
use App\Sportsbook\Catalogue\FixtureListing;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::sportsbook')] #[Title('Live')] class extends Component {
    /**
     * @return Collection<int, Fixture>
     */
    #[Computed]
    public function fixtures(): Collection
    {
        return app(FixtureListing::class)->inPlay();
    }
};
?>

<div class="space-y-3">
    <div class="flex items-start gap-3 rounded-2xl border border-ink-700 bg-ink-900 p-4 text-sm text-smoke">
        <flux:icon.information-circle class="size-5 shrink-0 text-gold-dim" />
        <p>{{ __('These matches are in play. Live betting and live scores are not available yet.') }}</p>
    </div>

    <section wire:poll.120s class="overflow-hidden rounded-2xl border border-ink-700 bg-ink-900">
        @forelse ($this->fixtures as $fixture)
            <article wire:key="live-{{ $fixture->id }}" class="flex items-center justify-between gap-3 border-b border-ink-700 px-4 py-3 last:border-b-0">
                <div class="min-w-0">
                    <p class="truncate text-xs text-smoke">{{ $fixture->competition->name }}</p>
                    <p class="mt-1 truncate font-medium text-ivory">{{ $fixture->home_team }}</p>
                    <p class="truncate font-medium text-ivory">{{ $fixture->away_team }}</p>
                </div>
                <div class="shrink-0 text-right">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-live/15 px-2.5 py-1 text-xs font-semibold text-live">
                        <span class="size-1.5 rounded-full bg-live" aria-hidden="true"></span>
                        {{ __('In play') }}
                    </span>
                    <p class="mt-1 text-xs tabular-nums text-smoke">
                        {{ __('Kicked off :time', ['time' => $fixture->kicks_off_at->setTimezone(config('sportsbook.timezone'))->format('H:i')]) }}
                    </p>
                </div>
            </article>
        @empty
            <x-sportsbook.empty-state icon="signal" :title="__('No live matches right now')" class="rounded-none border-0">
                {{ __('Matches in play will be listed here.') }}
            </x-sportsbook.empty-state>
        @endforelse
    </section>
</div>
