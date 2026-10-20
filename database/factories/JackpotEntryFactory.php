<?php

namespace Database\Factories;

use App\Enums\JackpotEntryStatus;
use App\Models\Jackpot;
use App\Models\JackpotEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An open KES 99 entry with no picks and no money moved. Real entries go through App\Betting\Jackpot\EnterJackpot.
 *
 * @extends Factory<JackpotEntry>
 */
class JackpotEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jackpot_id' => Jackpot::factory()->open(),
            'user_id' => User::factory(),
            'reference' => 'J'.Str::upper(Str::random(7)),
            'status' => JackpotEntryStatus::Open,
            'stake' => 9_900,
            'placed_at' => now(),
        ];
    }
}
