<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A live promotion with no end date and no image.
 *
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(3, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'summary' => fake()->sentence(),
            'body' => "## How it works\n\n".fake()->paragraph(),
            'starts_at' => now()->subDay(),
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => ['starts_at' => now()->addDay()]);
    }

    public function ended(): static
    {
        return $this->state(fn (): array => ['starts_at' => now()->subWeek(), 'ends_at' => now()->subMinute()]);
    }
}
