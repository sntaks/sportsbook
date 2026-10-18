<?php

namespace Database\Factories;

use App\Models\Fixture;
use App\Models\FixtureResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A 2-1 home win from the feed, with no half-time score.
 *
 * @extends Factory<FixtureResult>
 */
class FixtureResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fixture_id' => Fixture::factory(),
            'home_score' => 2,
            'away_score' => 1,
            'is_void' => false,
            'source' => FixtureResult::SOURCE_FEED,
        ];
    }
}
