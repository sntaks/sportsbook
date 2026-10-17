<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::sportsbook')] #[Title('Promotions')] class extends Component {
    //
};
?>

<div>
    <x-sportsbook.empty-state icon="gift" :title="__('No promotions running right now')">
        {{ __('Offers and free bets will be listed here when they are available.') }}
    </x-sportsbook.empty-state>
</div>
