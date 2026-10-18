<?php

namespace App\Wallet;

use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only way money moves. Every change to a balance is one WalletTransaction, written in the same database
 * transaction as the new balance, with the wallet row locked so two requests can never spend the same money.
 *
 * Each entry carries an idempotency key naming what caused it (such as "mpesa-deposit:42"). Posting the same
 * key again returns the entry already written instead of moving money twice, so callers can retry freely.
 */
class Ledger
{
    /**
     * Post one entry. A positive amount (cents) is money in, a negative one is money out.
     *
     * @throws InsufficientFunds when money out would take the balance below zero.
     * @throws IdempotencyConflict when the key was already used for a different entry.
     */
    public function post(
        User $user,
        WalletTransactionType $type,
        int $amount,
        string $idempotencyKey,
        ?Model $source = null,
        ?string $description = null,
        ?Admin $admin = null,
    ): WalletTransaction {
        if ($amount === 0) {
            throw new InvalidArgumentException('A wallet entry must move some money.');
        }

        return DB::transaction(function () use ($user, $type, $amount, $idempotencyKey, $source, $description, $admin): WalletTransaction {
            $wallet = $this->lockWallet($user);

            // Checked under the wallet lock, so a duplicate posted at the same moment waits and then finds this one.
            $existing = WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                if ($existing->wallet_id !== $wallet->id || $existing->type !== $type || $existing->amount !== $amount) {
                    throw new IdempotencyConflict($idempotencyKey);
                }

                return $existing;
            }

            $balanceAfter = $wallet->balance + $amount;

            if ($balanceAfter < 0) {
                throw new InsufficientFunds($wallet->balance, $amount);
            }

            $transaction = new WalletTransaction;
            $transaction->forceFill([
                'wallet_id' => $wallet->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'idempotency_key' => $idempotencyKey,
                'description' => $description,
                'admin_id' => $admin?->id,
            ])->save();

            $wallet->forceFill(['balance' => $balanceAfter])->save();

            return $transaction;
        });
    }

    /**
     * The player's balance in cents. Zero for a player who has never had money in.
     */
    public function balanceOf(User $user): int
    {
        return (int) Wallet::query()->where('user_id', $user->id)->value('balance');
    }

    /**
     * Lock the player's wallet row for the rest of the transaction, creating it on first use.
     */
    private function lockWallet(User $user): Wallet
    {
        $wallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->first();

        if ($wallet !== null) {
            return $wallet;
        }

        try {
            DB::transaction(function () use ($user): void {
                $wallet = new Wallet;
                $wallet->forceFill(['user_id' => $user->id, 'currency' => config('sportsbook.currency.code')])->save();
            });
        } catch (UniqueConstraintViolationException) {
            // Another request created it first; lock that one.
        }

        return Wallet::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
    }
}
