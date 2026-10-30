<?php

namespace Database\Factories;

use App\Enums\RouteStopOrderStatus;
use App\Models\Order;
use App\Models\RouteStop;
use App\Models\RouteStopOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouteStopOrder>
 */
class RouteStopOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'route_stop_id' => RouteStop::factory(),
            // Замовлення того самого магазину, що й зупинка
            'order_id' => fn (array $attributes) => Order::factory()->state([
                'store_id' => RouteStop::find($attributes['route_stop_id'])->store_id,
            ]),
            'status' => RouteStopOrderStatus::Pending,
        ];
    }
}
