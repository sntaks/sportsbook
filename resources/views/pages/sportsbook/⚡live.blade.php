<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::sportsbook')] #[Title('Live')] class extends Component {
    //
};
?>

<div>
    <x-sportsbook.empty-state icon="signal" :title="__('No live matches right now')">
        {{ __('Matches in play will be listed here.') }}
    </x-sportsbook.empty-state>
</div>
