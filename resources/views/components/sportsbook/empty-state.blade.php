@props(['icon', 'title', 'badge' => null])

<section {{ $attributes->class('glass-card flex flex-col items-center px-6 py-14 text-center') }}>
    <span class="flex size-14 items-center justify-center rounded-full bg-gold/10 text-gold">
        <flux:icon :name="$icon" class="size-7" />
    </span>

    @if ($badge)
        <span class="mt-5 rounded-full border border-gold/25 bg-gold/10 px-3 py-0.5 text-xs font-semibold uppercase tracking-[0.08em] text-gold">{{ $badge }}</span>
    @endif

    <h1 class="mt-4 font-display text-2xl font-bold text-ivory">{{ $title }}</h1>

    <div class="mt-2 max-w-md text-sm text-smoke">
        {{ $slot }}
    </div>
</section>
