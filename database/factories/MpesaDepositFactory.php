<?php

namespace Database\Factories;

use App\Enums\MpesaDepositChannel;
use App\Enums\MpesaDepositStatus;
use App\Models\MpesaDeposit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A pending STK deposit of KES 500 to the player's number.
 *
 * @extends Factory<MpesaDeposit>
 */
class MpesaDepositFactory extends Factory
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
            'channel' => MpesaDepositChannel::Stk,
            'status' => MpesaDepositStatus::Pending,
            'phone' => fn (array $attributes): string => (string) User::query()->whereKey($attributes['user_id'])->value('phone'),
            'amount' => 50_000,
            'merchant_request_id' => 'MR-'.Str::random(10),
            'checkout_request_id' => 'ws_CO_'.Str::random(16),
        ];
    }

    /**
     * Started a given number of minutes ago.
     */
    public function startedMinutesAgo(int $minutes): static
    {
        return $this->state(fn (): array => ['created_at' => now()->subMinutes($minutes)]);
    }
}
