<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        // Money records must be kept, so an account that has moved money is closed by support, not deleted.
        $user = Auth::user();

        if ($user->mpesaDeposits()->exists() || $user->wallet()->whereHas('transactions')->exists()) {
            $this->addError('password', __('Accounts with deposits or transactions cannot be deleted here. Contact support to close your account.'));

            return;
        }

        tap($user, $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form method="POST" wire:submit="deleteUser" class="space-y-5">
        <div>
            <h2 class="font-display text-lg font-bold text-ivory">{{ __('Delete your account?') }}</h2>
            <p class="mt-1 text-sm text-smoke">{{ __('Your account and its data are deleted for good. Enter your password to confirm.') }}</p>
        </div>

        <label class="block">
            <span class="text-xs text-smoke">{{ __('Password') }}</span>
            <input type="password" wire:model="password" autocomplete="current-password" class="mt-1 min-h-11 w-full rounded-lg border border-ink-600 bg-ink-800 px-3 text-ivory focus:border-gold/50">
        </label>
        @error('password')
            <p class="text-xs text-live">{{ $message }}</p>
        @enderror

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <button type="button" class="rounded-full px-4 py-2 text-sm font-medium text-smoke hover:text-ivory">{{ __('Cancel') }}</button>
            </flux:modal.close>

            <button type="submit" wire:loading.attr="disabled" class="rounded-full bg-live px-5 py-2 text-sm font-semibold text-ivory hover:opacity-90 disabled:opacity-60" data-test="confirm-delete-user-button">
                {{ __('Delete account') }}
            </button>
        </div>
    </form>
</flux:modal>
