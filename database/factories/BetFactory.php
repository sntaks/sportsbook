<?php

namespace Database\Factories;

use App\Enums\BetStatus;
use App\Enums\BetType;
use App\Enums\OddsChangePolicy;
use App\Models\Bet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An open KES 100 single at 2.00 with no legs and no money moved. Real bets go through App\Betting\PlaceBet.
 *
 * @extends Factory<Bet>
 */
class BetFactory extends Factory
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
            'reference' => Str::upper(Str::random(8)),
            'type' => BetType::Single,
            'status' => BetStatus::Open,
            'stake' => 10_000,
            'total_odds' => '2.00',
            'potential_payout' => 20_000,
            'odds_change_policy' => OddsChangePolicy::Higher,
            'placed_at' => now(),
        ];
    }
}
