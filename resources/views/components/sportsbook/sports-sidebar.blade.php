{{-- A sticky column on desktop; a slide-in drawer on smaller screens. $sports comes from a view composer. --}}
<div
    x-cloak
    x-show="sportsOpen"
    x-transition.opacity
    x-on:click="sportsOpen = false"
    class="fixed inset-0 z-40 bg-black/70 lg:hidden"
    aria-hidden="true"
></div>

<aside
    aria-label="{{ __('Sports and competitions') }}"
    x-on:keydown.escape.window="sportsOpen = false"
    :class="sportsOpen ? 'max-lg:translate-x-0' : 'max-lg:-translate-x-full'"
    class="fixed inset-y-0 left-0 z-50 w-72 shrink-0 -translate-x-full overflow-y-auto border-r border-ink-700 bg-ink-900 transition-transform duration-200
           lg:sticky lg:top-[4.5rem] lg:z-auto lg:mt-4 lg:h-[calc(100vh-5.5rem)] lg:w-60 lg:translate-x-0 lg:rounded-2xl lg:border lg:transition-none"
>
    <div class="flex items-center justify-between border-b border-ink-700 px-4 py-3">
        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-smoke">{{ __('Sports') }}</h2>
        <button
            type="button"
            class="flex size-10 items-center justify-center rounded-md text-smoke hover:text-ivory lg:hidden"
            x-on:click="sportsOpen = false"
            aria-label="{{ __('Close sports menu') }}"
        >
            <flux:icon.x-mark class="size-5" />
        </button>
    </div>

    @forelse ($sports as $sport)
        <nav aria-label="{{ $sport['name'] }}" class="py-2">
            <p class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-ivory">
                <flux:icon.trophy variant="micro" class="size-4 text-gold" />
                {{ $sport['name'] }}
            </p>
            <ul>
                @foreach ($sport['competitions'] as $competition)
                    @php
                        $current = request()->route('competition');
                        $isCurrent = ($current instanceof \App\Models\Competition ? $current->slug : $current) === $competition['slug'];
                    @endphp
                    <li>
                        <a
                            href="{{ route('competitions.show', $competition['slug']) }}"
                            wire:navigate.hover
                            x-on:click="sportsOpen = false"
                            @if ($isCurrent) aria-current="page" @endif
                            @class([
                                'flex min-h-11 items-center justify-between gap-2 px-4 text-sm transition-colors',
                                'bg-gold/10 text-gold' => $isCurrent,
                                'text-ivory/80 hover:bg-ink-800 hover:text-ivory' => ! $isCurrent,
                            ])
                        >
                            <span class="truncate">{{ $competition['name'] }}</span>
                            <span class="shrink-0 text-xs tabular-nums text-smoke">{{ $competition['upcoming_fixtures_count'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @empty
        <div class="px-4 py-6 text-sm text-smoke">
            <flux:icon.trophy class="mb-3 size-6 text-gold-dim" />
            <p>{{ __('Competitions will be listed here as soon as fixtures are loaded.') }}</p>
        </div>
    @endforelse
</aside>
