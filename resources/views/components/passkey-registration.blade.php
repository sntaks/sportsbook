@assets
@vite('resources/js/passkeys.js')
@endassets

<div
    x-data="{
        supported: false,
        showForm: false,
        name: '',
        loading: false,
        error: null,
        updateSupport() {
            this.supported = Boolean(window.Passkeys?.isSupported());
        },
        getDefaultPasskeyName() {
            const ua = navigator.userAgent;

            const browser = [
                { pattern: /Edg|Edge/, name: 'Edge' },
                { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
                { pattern: /Firefox|FxiOS/, name: 'Firefox' },
                { pattern: /Chrome|CriOS/, name: 'Chrome' },
                { pattern: /Safari/, name: 'Safari' },
            ].find(({ pattern }) => pattern.test(ua))?.name;

            const os = [
                { pattern: /iPhone/, name: 'iPhone' },
                { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
                { pattern: /Android/, name: 'Android' },
                { pattern: /Mac/, name: 'Mac' },
                { pattern: /Windows/, name: 'Windows' },
            ].find(({ pattern }) => pattern.test(ua))?.name;

            return [browser, os].filter(Boolean).join(' on ') || '';
        },
        init() {
            this.name = this.getDefaultPasskeyName();
            this.updateSupport();

            window.addEventListener('passkeys:ready', () => this.updateSupport(), { once: true });
        },
        async register() {
            if (!this.name.trim()) return;

            this.loading = true;
            this.error = null;

            try {
                await window.Passkeys.register({ name: this.name });
                this.name = '';
                this.showForm = false;
                await $wire.loadPasskeys();
            } catch (e) {
                if (e.constructor?.name !== 'UserCancelledError') {
                    this.error = e.message;
                }
            } finally {
                this.loading = false;
            }
        },
        cancel() {
            this.showForm = false;
            this.name = '';
            this.error = null;
        },
    }"
>
    <template x-if="!supported">
        <p class="text-sm text-smoke">{{ __('Passkeys are not supported in this browser.') }}</p>
    </template>

    <template x-if="supported && !showForm">
        <button type="button" x-on:click="showForm = true" class="flex items-center gap-1.5 rounded-full bg-gold px-5 py-2 text-sm font-semibold text-ink-950 hover:bg-gold-bright">
            <flux:icon.plus class="size-4" />
            {{ __('Add passkey') }}
        </button>
    </template>

    <template x-if="supported && showForm">
        <div class="space-y-3 rounded-lg border border-ink-700 bg-ink-900 p-4">
            <label class="block">
                <span class="text-xs text-smoke">{{ __('Passkey name') }}</span>
                <input
                    type="text"
                    x-model="name"
                    placeholder="{{ __('e.g. My phone') }}"
                    x-on:keydown.enter.prevent="register()"
                    x-ref="passkeyNameInput"
                    x-init="$nextTick(() => $refs.passkeyNameInput?.focus())"
                    class="mt-1 min-h-11 w-full rounded-lg border border-ink-600 bg-ink-800 px-3 text-ivory focus:border-gold/50"
                >
            </label>
            <p class="text-xs text-smoke">{{ __('A name helps you tell your passkeys apart.') }}</p>

            <p x-show="error" x-text="error" x-cloak class="text-xs text-live"></p>

            <div class="flex gap-2">
                <button type="button" x-on:click="register()" x-bind:disabled="loading || !name.trim()" class="rounded-full bg-gold px-5 py-2 text-sm font-semibold text-ink-950 hover:bg-gold-bright disabled:opacity-60">
                    <span x-show="!loading">{{ __('Save passkey') }}</span>
                    <span x-show="loading" x-cloak>{{ __('Saving…') }}</span>
                </button>
                <button type="button" x-on:click="cancel()" class="rounded-full px-4 py-2 text-sm font-medium text-smoke hover:text-ivory">{{ __('Cancel') }}</button>
            </div>
        </div>
    </template>
</div>
