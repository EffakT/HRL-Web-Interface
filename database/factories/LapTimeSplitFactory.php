<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LapTime;
use App\Models\LapTimeSplit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LapTimeSplit>
 */
class LapTimeSplitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->randomFloat(2, 0, 60);
        $duration = fake()->randomFloat(2, 4, 8);

        return [
            'lap_time_id' => LapTime::factory(),
            'checkpoint_id' => fake()->numberBetween(1, 14),
            'duration' => $duration,
            'start_time' => $start,
            'end_time' => $start + $duration,
        ];
    }
}
