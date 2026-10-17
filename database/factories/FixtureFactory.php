<?php

namespace Database\Factories;

use App\Enums\FixtureStatus;
use App\Models\Competition;
use App\Models\Fixture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fixture>
 */
class FixtureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory(),
            'external_id' => fake()->unique()->md5(),
            'home_team' => fake()->unique()->city().' FC',
            'away_team' => fake()->unique()->city().' United',
            'kicks_off_at' => now()->addDays(2),
            'status' => FixtureStatus::Scheduled,
            'last_seen_in_feed_at' => now(),
        ];
    }

    /**
     * Indicate that the fixture has already kicked off.
     */
    public function kickedOff(): static
    {
        return $this->state(fn (array $attributes) => [
            'kicks_off_at' => now()->subMinutes(30),
        ]);
    }
}
