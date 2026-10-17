<x-layouts::sportsbook :title="__($title)">
    <x-sportsbook.empty-state :icon="$icon" :title="__($title)" :badge="__('Coming soon')">
        {{ __($description) }}
    </x-sportsbook.empty-state>
</x-layouts::sportsbook>
