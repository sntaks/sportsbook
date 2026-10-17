<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::sportsbook')] #[Title('Sports betting')] class extends Component {
    //
};
?>

<div>
    <x-sportsbook.empty-state icon="trophy" :title="__('Fixtures are on their way')">
        {{ __('Matches and odds will appear here as soon as they are loaded. Check back shortly.') }}
    </x-sportsbook.empty-state>
</div>
