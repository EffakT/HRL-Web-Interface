<?php

namespace Database\Factories;

use App\Models\LapTime;
use App\Models\Map;
use App\Models\Player;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LapTime>
 */
class LapTimeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'map_id' => Map::factory(),
            'player_id' => Player::factory(),
            'time' => fake()->randomFloat(2, 45, 180),
        ];
    }
}
