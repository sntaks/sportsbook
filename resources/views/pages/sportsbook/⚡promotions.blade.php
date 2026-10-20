<?php

use App\Models\Promotion;
use App\Promotions\ReferralCodes;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The promotions staff have published, plus the invite-friends offer while referrals are on.
 */
new #[Layout('layouts::sportsbook')] #[Title('Promotions')] class extends Component {
    /**
     * @return Collection<int, Promotion>
     */
    #[Computed]
    public function promotions(): Collection
    {
        return Promotion::query()->live()->get();
    }
};
?>

<div class="space-y-4">
    <h1 class="font-display text-xl font-bold text-ivory">{{ __('Promotions') }}</h1>

    @if ($this->promotions->isEmpty() && ! ReferralCodes::enabled())
        <x-sportsbook.empty-state icon="gift" :title="__('No promotions running right now')">
            {{ __('Offers and free bets will be listed here when they are available.') }}
        </x-sportsbook.empty-state>
    @else
        <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-test="promotions">
            @foreach ($this->promotions as $promotion)
                <li wire:key="promotion-{{ $promotion->id }}">
                    <a href="{{ route('promotions.show', $promotion) }}" wire:navigate class="glass-card group flex h-full flex-col overflow-hidden hover:border-gold/40">
                        @if ($imageUrl = $promotion->imageUrl())
                            <img src="{{ $imageUrl }}" alt="" class="aspect-[2/1] w-full object-cover" loading="lazy">
                        @endif
                        <div class="flex flex-1 flex-col gap-1 p-4">
                            <h2 class="font-display text-lg font-bold text-ivory group-hover:text-gold">{{ $promotion->title }}</h2>
                            <p class="text-sm text-smoke">{{ $promotion->summary }}</p>
                            @if ($promotion->ends_at)
                                <p class="mt-auto pt-2 text-xs text-smoke-dim">{{ __('Ends :date', ['date' => $promotion->ends_at->timezone(config('sportsbook.timezone'))->format('j M Y, H:i')]) }}</p>
                            @endif
                        </div>
                    </a>
                </li>
            @endforeach

            @if (ReferralCodes::enabled())
                <li>
                    <a href="{{ auth()->check() ? route('wallet').'#invite' : route('register') }}" @auth wire:navigate @endauth class="glass-card group flex h-full flex-col gap-1 p-4 hover:border-gold/40" data-test="invite-card">
                        <flux:icon.user-plus class="size-8 text-gold" />
                        <h2 class="font-display text-lg font-bold text-ivory group-hover:text-gold">{{ __('Invite friends, get free bets') }}</h2>
                        <p class="text-sm text-smoke">
                            {{ __('Get a KES :reward free bet for each friend who joins with your link, deposits and bets. Up to :cap a month.', [
                                'reward' => number_format((int) config('sportsbook.promotions.referrals.reward.amount')),
                                'cap' => config('sportsbook.promotions.referrals.monthly_cap'),
                            ]) }}
                        </p>
                        <p class="mt-auto pt-2 text-sm font-semibold text-gold">{{ auth()->check() ? __('Get your invite link') : __('Sign up to invite friends') }}</p>
                    </a>
                </li>
            @endif
        </ul>
    @endif
</div>
