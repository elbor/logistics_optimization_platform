<?php

namespace Tests\Feature\Authorization;

use App\Enums\OrderStatus;
use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\RouteStop;
use App\Models\RouteStopOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StopAndOrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_views_and_closes_stops_of_own_route(): void
    {
        $stopOrder = $this->stopOrderOnRoute(DeliveryRoute::factory()->inProgress()->create());
        $driver = $stopOrder->stop->route->driver;

        $this->assertTrue($driver->can('view', $stopOrder->stop));
        $this->assertTrue($driver->can('complete', $stopOrder->stop));
        $this->assertTrue($driver->can('skip', $stopOrder->stop));
        $this->assertTrue($driver->can('view', $stopOrder));
        $this->assertTrue($driver->can('view', $stopOrder->order));
    }

    public function test_driver_does_not_see_stops_and_orders_of_other_drivers_route(): void
    {
        $stopOrder = $this->stopOrderOnRoute(DeliveryRoute::factory()->inProgress()->create());
        $otherDriver = User::factory()->driver()->create();

        $this->assertFalse($otherDriver->can('view', $stopOrder->stop));
        $this->assertFalse($otherDriver->can('complete', $stopOrder->stop));
        $this->assertFalse($otherDriver->can('skip', $stopOrder->stop));
        $this->assertFalse($otherDriver->can('view', $stopOrder));
        $this->assertFalse($otherDriver->can('view', $stopOrder->order));
    }

    public function test_driver_does_not_see_stops_and_orders_of_own_draft_route(): void
    {
        $stopOrder = $this->stopOrderOnRoute(DeliveryRoute::factory()->create());
        $driver = $stopOrder->stop->route->driver;

        $this->assertFalse($driver->can('view', $stopOrder->stop));
        $this->assertFalse($driver->can('complete', $stopOrder->stop));
        $this->assertFalse($driver->can('view', $stopOrder->order));
    }

    public function test_driver_does_not_see_orders_outside_routes_or_order_lists(): void
    {
        $driver = User::factory()->driver()->create();

        $this->assertFalse($driver->can('view', Order::factory()->create()));
        $this->assertFalse($driver->can('viewAny', Order::class));
        $this->assertFalse($driver->can('create', Order::class));
    }

    public function test_admin_views_stops_but_does_not_close_them(): void
    {
        $admin = User::factory()->create();
        $stopOrder = $this->stopOrderOnRoute(DeliveryRoute::factory()->inProgress()->create());

        $this->assertTrue($admin->can('view', $stopOrder->stop));
        $this->assertTrue($admin->can('view', $stopOrder));
        $this->assertFalse($admin->can('complete', $stopOrder->stop));
        $this->assertFalse($admin->can('skip', $stopOrder->stop));
    }

    public function test_admin_changes_and_cancels_only_new_orders(): void
    {
        $admin = User::factory()->create();
        $new = Order::factory()->create();
        $planned = Order::factory()->create(['status' => OrderStatus::Planned]);

        $this->assertTrue($admin->can('viewAny', Order::class));
        $this->assertTrue($admin->can('create', Order::class));
        $this->assertTrue($admin->can('update', $new));
        $this->assertTrue($admin->can('cancel', $new));
        $this->assertFalse($admin->can('update', $planned));
        $this->assertFalse($admin->can('cancel', $planned));
    }

    public function test_admin_deletes_only_new_order_without_route_history(): void
    {
        $admin = User::factory()->create();
        $fresh = Order::factory()->create();
        $returned = RouteStopOrder::factory()->create(['status' => 'not_delivered'])->order;

        $this->assertTrue($admin->can('delete', $fresh));
        $this->assertSame(OrderStatus::New, $returned->status);
        $this->assertFalse($admin->can('delete', $returned));
    }

    private function stopOrderOnRoute(DeliveryRoute $route): RouteStopOrder
    {
        return RouteStopOrder::factory()
            ->for(RouteStop::factory()->for($route, 'route'), 'stop')
            ->create();
    }
}
