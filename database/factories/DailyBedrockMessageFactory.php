<?php

namespace Database\Factories;

use App\Models\DailyBedrockMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyBedrockMessage>
 */
class DailyBedrockMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'response' => fake()->sentence(),
        ];
    }

    public function forToday(): static
    {
        return $this->state(['date' => today()->toDateString()]);
    }
}
