<?php

namespace Database\Factories;

use App\Models\OddsSnapshot;
use App\Models\Selection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OddsSnapshot>
 */
class OddsSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'selection_id' => Selection::factory(),
            'price' => '2.09',
            'fair_probability' => '0.450000',
            'source' => 'feed',
            'captured_at' => now(),
        ];
    }
}
