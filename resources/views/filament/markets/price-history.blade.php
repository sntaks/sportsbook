@if ($snapshots->isEmpty())
    <p class="text-sm text-gray-500">{{ __('No price changes recorded yet.') }}</p>
@else
    <table class="w-full text-left text-sm">
        <thead>
            <tr class="text-gray-500">
                <th class="py-1 pr-4 font-medium">{{ __('When (EAT)') }}</th>
                <th class="py-1 pr-4 font-medium">{{ __('Selection') }}</th>
                <th class="py-1 pr-4 font-medium">{{ __('Price') }}</th>
                <th class="py-1 font-medium">{{ __('Source') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($snapshots as $snapshot)
                <tr class="border-t border-gray-200 dark:border-white/10">
                    <td class="py-1 pr-4 tabular-nums">{{ $snapshot->captured_at->timezone(config('sportsbook.timezone'))->format('D j M, H:i') }}</td>
                    <td class="py-1 pr-4">{{ $snapshot->selection->name }}</td>
                    <td class="py-1 pr-4 tabular-nums">{{ $snapshot->price }}</td>
                    <td class="py-1">{{ $snapshot->source === 'override' ? __('Override') : __('Feed') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
