<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ip' => fake()->ipv4(),
            'port' => (string) fake()->numberBetween(10000, 65000),
            'name' => fake()->words(3, true),
            'type' => 'PC',
            'notify_outage' => false,
            'notify_outage_last' => null,
        ];
    }
}
