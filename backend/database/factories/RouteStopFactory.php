<?php

namespace Database\Factories;

use App\Enums\RouteStopStatus;
use App\Models\DeliveryRoute;
use App\Models\RouteStop;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouteStop>
 */
class RouteStopFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'route_id' => DeliveryRoute::factory(),
            'store_id' => Store::factory(),
            'sequence' => 1,
            'leg_distance_m' => 1_500,
            'leg_s' => 300,
            'arrival_offset_s' => 300,
            'status' => RouteStopStatus::Pending,
        ];
    }
}
