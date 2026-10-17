<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::sportsbook')] #[Title('My bets')] class extends Component {
    //
};
?>

<div>
    <x-sportsbook.empty-state icon="clipboard-document-list" :title="__('You have not placed any bets yet')">
        {{ __('Bets you place will be listed here with their status and payout.') }}
    </x-sportsbook.empty-state>
</div>
