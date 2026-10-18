<?php

namespace Database\Factories;

use App\Enums\WalletTransactionType;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A bare ledger line that does not touch the wallet balance. Real entries go through App\Wallet\Ledger.
 *
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'type' => WalletTransactionType::Adjustment,
            'amount' => 10_000,
            'balance_after' => 10_000,
            'idempotency_key' => 'test:'.Str::uuid(),
        ];
    }
}
