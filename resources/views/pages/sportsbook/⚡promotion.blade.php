<?php

use App\Models\Promotion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One promotion's page. Drafts, scheduled and ended promotions are not found.
 */
new #[Layout('layouts::sportsbook')] class extends Component {
    public Promotion $promotion;

    public function mount(Promotion $promotion): void
    {
        abort_unless($promotion->isLive(), 404);

        $this->promotion = $promotion;
    }

    public function render(): View
    {
        return $this->view()->title($this->promotion->title);
    }
};
?>

<article class="mx-auto max-w-3xl space-y-4">
    <a href="{{ route('promotions') }}" wire:navigate class="text-xs font-semibold uppercase tracking-[0.08em] text-smoke hover:text-gold">{{ __('All promotions') }}</a>

    <div class="glass-card overflow-hidden">
        @if ($imageUrl = $promotion->imageUrl())
            <img src="{{ $imageUrl }}" alt="" class="aspect-[2/1] w-full object-cover">
        @endif

        <div class="space-y-4 p-5">
            <header class="space-y-1">
                <h1 class="font-display text-2xl font-bold text-ivory">{{ $promotion->title }}</h1>
                <p class="text-smoke">{{ $promotion->summary }}</p>
                @if ($promotion->ends_at)
                    <p class="text-xs text-smoke-dim">{{ __('Ends :date', ['date' => $promotion->ends_at->timezone(config('sportsbook.timezone'))->format('j M Y, H:i')]) }}</p>
                @endif
            </header>

            <div class="space-y-3 text-sm leading-relaxed text-ivory/90 [&_a]:font-semibold [&_a]:text-gold [&_a:hover]:text-gold-bright [&_h2]:pt-2 [&_h2]:font-display [&_h2]:text-lg [&_h2]:font-bold [&_h2]:text-ivory [&_h3]:font-semibold [&_h3]:text-ivory [&_ol]:list-decimal [&_ol]:space-y-1 [&_ol]:pl-5 [&_strong]:text-ivory [&_table]:w-full [&_td]:border-b [&_td]:border-ink-700 [&_td]:py-1.5 [&_th]:border-b [&_th]:border-ink-600 [&_th]:py-1.5 [&_th]:text-left [&_th]:font-semibold [&_th]:text-smoke [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-5" data-test="promotion-body">
                {{ $promotion->bodyHtml() }}
            </div>
        </div>
    </div>
</article>
