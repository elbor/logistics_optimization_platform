<?php

namespace Tests\Feature\Models;

use App\Enums\OrderStatus;
use App\Enums\RouteStatus;
use App\Enums\RouteStopOrderStatus;
use App\Enums\RouteStopStatus;
use App\Enums\UserRole;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\RouteStop;
use App\Models\RouteStopOrder;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_graph_is_created_by_factories_and_loads_through_relations(): void
    {
        $route = DeliveryRoute::factory()->create();
        $stop = RouteStop::factory()->for($route, 'route')->create();
        $order = Order::factory()->for($stop->store)->create();
        $stopOrder = RouteStopOrder::factory()->for($stop, 'stop')->for($order)->create();

        $route = DeliveryRoute::with('driver', 'vehicle', 'stops.store', 'stops.stopOrders.order')->find($route->id);

        $this->assertSame(UserRole::Driver, $route->driver->role);
        $this->assertSame(RouteStatus::Draft, $route->status);
        $this->assertSame($stop->id, $route->stops->first()->id);
        $this->assertSame(RouteStopStatus::Pending, $route->stops->first()->status);
        $this->assertSame($stopOrder->id, $route->stops->first()->stopOrders->first()->id);
        $this->assertSame(RouteStopOrderStatus::Pending, $route->stops->first()->stopOrders->first()->status);
        $this->assertSame($order->id, $route->stops->first()->stopOrders->first()->order->id);
        $this->assertTrue($order->store->is($stop->store));
        $this->assertSame(OrderStatus::New, $order->fresh()->status);
    }

    public function test_route_stops_are_ordered_by_sequence(): void
    {
        $route = DeliveryRoute::factory()->create();
        RouteStop::factory()->for($route, 'route')->create(['sequence' => 2]);
        RouteStop::factory()->for($route, 'route')->create(['sequence' => 1]);

        $this->assertSame([1, 2], $route->stops->pluck('sequence')->all());
    }

    public function test_order_history_lists_its_route_stop_entries(): void
    {
        $stopOrder = RouteStopOrder::factory()->create();

        $this->assertTrue($stopOrder->order->routeStopOrders->first()->is($stopOrder));
        $this->assertTrue($stopOrder->stop->route->driver->driverRoutes->first()->is($stopOrder->stop->route));
    }

    public function test_settings_current_returns_the_single_row(): void
    {
        $this->assertNull(Setting::current());

        Setting::factory()->create();

        $this->assertSame(1, Setting::current()?->id);
    }
}
