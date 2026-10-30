<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Ford Transit',
            'plate' => fake()->unique()->bothify('AA####??'),
            'fuel_consumption_l_per_100km' => '9.50',
            'is_active' => true,
        ];
    }
}
