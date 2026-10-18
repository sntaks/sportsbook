<?php

namespace Database\Factories;

use App\Enums\DepositLimitPeriod;
use App\Models\DepositLimit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A daily limit of KES 1,000 with no change waiting.
 *
 * @extends Factory<DepositLimit>
 */
class DepositLimitFactory extends Factory
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
            'period' => DepositLimitPeriod::Daily,
            'amount' => 100_000,
            'pending_amount' => null,
            'has_pending_change' => false,
            'pending_from' => null,
        ];
    }
}
