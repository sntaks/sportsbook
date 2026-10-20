<?php

namespace Database\Factories;

use App\Enums\ReferralRewardStatus;
use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A reward the referrer was given (no free bet attached; add one if the test needs it).
 *
 * @extends Factory<ReferralReward>
 */
class ReferralRewardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'referrer_id' => User::factory(),
            'referred_id' => User::factory(),
            'status' => ReferralRewardStatus::Rewarded,
        ];
    }
}
