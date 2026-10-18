<?php

namespace Database\Factories;

use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A queued KES 500 withdrawal record. It holds no money: real withdrawals go through RequestWithdrawal, which posts
 * the hold to the ledger.
 *
 * @extends Factory<Withdrawal>
 */
class WithdrawalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => WithdrawalStatus::Queued,
            'phone' => fn (array $attributes): string => (string) User::query()->whereKey($attributes['user_id'])->value('phone'),
            'amount' => 50_000,
        ];
    }

    /**
     * Sent to M-Pesa the given number of minutes ago, with the conversation IDs Safaricom gave back.
     */
    public function processing(int $minutesAgo = 1): static
    {
        return $this->state(fn (): array => [
            'status' => WithdrawalStatus::Processing,
            'conversation_id' => 'AG_'.Str::random(16),
            'originator_conversation_id' => Str::random(12),
            'sent_at' => now()->subMinutes($minutesAgo),
        ]);
    }
}
