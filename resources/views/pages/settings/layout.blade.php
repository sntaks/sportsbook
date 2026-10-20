@props([
    /* The route of the page being shown. Passed in because Livewire re-renders this on its own update requests. */
    'current',
])

<div class="mx-auto max-w-3xl space-y-4">
    <section class="glass-card p-5">
        <h1 class="font-display text-xl font-bold text-ivory">{{ __('Account settings') }}</h1>
        <nav class="scrollbar-none -mx-5 mt-4 flex gap-2 overflow-x-auto px-5" aria-label="{{ __('Settings') }}" data-test="settings-nav">
            @foreach ([
                'profile.edit' => __('Profile'),
                'security.edit' => __('Security'),
                'responsible-gambling.settings' => __('Limits & breaks'),
            ] as $route => $label)
                <a
                    href="{{ route($route) }}"
                    wire:navigate
                    @if ($current === $route) aria-current="page" @endif
                    @class([
                        'flex min-h-10 shrink-0 items-center whitespace-nowrap rounded-full px-4 text-sm font-medium',
                        'bg-gold text-ink-950' => $current === $route,
                        'border border-ink-600 text-smoke hover:text-ivory' => $current !== $route,
                    ])
                >{{ $label }}</a>
            @endforeach
        </nav>
    </section>

    {{ $slot }}
</div>
