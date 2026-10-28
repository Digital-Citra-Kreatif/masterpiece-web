<?php

namespace Database\Factories;

use App\Models\UnitPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitPoint>
 */
class UnitPointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => fake()->numberBetween(1, 1000),
            'unit_id' => fake()->unique()->numberBetween(1, 100000),
            'x' => fake()->randomFloat(2, 0, 100),
            'y' => fake()->randomFloat(2, 0, 100),
        ];
    }
}
