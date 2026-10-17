<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::sportsbook')] #[Title('Jackpot')] class extends Component {
    //
};
?>

<div>
    <x-sportsbook.empty-state icon="ticket" :title="__('No jackpot is open right now')">
        {{ __('When a jackpot opens, you will be able to predict every match here.') }}
    </x-sportsbook.empty-state>
</div>
