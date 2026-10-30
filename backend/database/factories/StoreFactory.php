<?php

namespace Database\Factories;

use App\Enums\LocationSource;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'address' => fake()->address(),
            'lat' => fake()->latitude(50.35, 50.55),
            'lng' => fake()->longitude(30.35, 30.75),
            'location_source' => LocationSource::Geocoder,
        ];
    }
}
