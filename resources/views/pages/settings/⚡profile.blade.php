<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::sportsbook')] #[Title('Profile settings')] class extends Component {
    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = (string) Auth::user()->name;
        $this->email = (string) Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user. Both fields are optional;
     * clearing one stores null.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill([
            'name' => filled($validated['name']) ? $validated['name'] : null,
            'email' => filled($validated['email']) ? $validated['email'] : null,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }
}; ?>

<x-pages::settings.layout current="profile.edit">
    <section class="glass-card p-5">
        <h2 class="font-display text-lg font-bold text-ivory">{{ __('Phone number') }}</h2>
        <livewire:pages::settings.change-phone />
    </section>

    <section class="glass-card p-5">
        <h2 class="font-display text-lg font-bold text-ivory">{{ __('Name and email') }}</h2>
        <p class="mt-1 text-sm text-smoke">{{ __('Both are optional. Your name is shown on your account menu.') }}</p>

        <form wire:submit="updateProfileInformation" class="mt-4 space-y-3">
            <label class="block">
                <span class="text-xs text-smoke">{{ __('Name') }}</span>
                <input type="text" wire:model="name" autocomplete="name" class="mt-1 min-h-11 w-full rounded-lg border border-ink-600 bg-ink-800 px-3 text-ivory focus:border-gold/50">
            </label>
            @error('name')
                <p class="text-xs text-live">{{ $message }}</p>
            @enderror

            <label class="block">
                <span class="text-xs text-smoke">{{ __('Email') }}</span>
                <input type="email" wire:model="email" autocomplete="email" class="mt-1 min-h-11 w-full rounded-lg border border-ink-600 bg-ink-800 px-3 text-ivory focus:border-gold/50">
            </label>
            @error('email')
                <p class="text-xs text-live">{{ $message }}</p>
            @enderror

            <button type="submit" wire:loading.attr="disabled" class="rounded-full bg-gold px-6 py-2.5 text-sm font-semibold text-ink-950 hover:bg-gold-bright disabled:opacity-60" data-test="update-profile-button">
                {{ __('Save') }}
            </button>
        </form>
    </section>

    <livewire:pages::settings.delete-user-form />
</x-pages::settings.layout>
