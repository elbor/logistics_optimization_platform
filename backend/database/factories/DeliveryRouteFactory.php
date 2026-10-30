<?php

namespace Database\Factories;

use App\Enums\RouteStatus;
use App\Models\DeliveryRoute;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryRoute>
 */
class DeliveryRouteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now()->addDay()->toDateString(),
            'driver_id' => User::factory()->driver(),
            'vehicle_id' => Vehicle::factory(),
            'return_to_warehouse' => false,
            'status' => RouteStatus::Draft,
            'distance_m' => 42_000,
            'drive_s' => 3_600,
            'total_s' => 5_400,
            'fuel_l' => '3.99',
            'fuel_cost' => '219.41',
            'fuel_price_snapshot' => '54.99',
            'fuel_consumption_snapshot' => '9.50',
            'stop_minutes_snapshot' => 10,
            'geometry' => ['type' => 'LineString', 'coordinates' => [[30.52, 50.45], [30.60, 50.40]]],
        ];
    }

    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RouteStatus::Assigned,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RouteStatus::InProgress,
            'started_at' => now(),
            'started_received_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RouteStatus::Completed,
            'started_at' => now()->subHours(3),
            'started_received_at' => now()->subHours(3),
            'completed_at' => now(),
        ]);
    }
}
