<?php

namespace Tests\Feature\Authorization;

use App\Models\DeliveryRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeliveryRoutePolicyTest extends TestCase
{
    use RefreshDatabase;

    public static function editableStates(): array
    {
        return [
            'draft' => [null, true],
            'assigned' => ['assigned', true],
            'in progress' => ['inProgress', false],
            'completed' => ['completed', false],
        ];
    }

    #[DataProvider('editableStates')]
    public function test_admin_edits_and_deletes_route_only_before_start(?string $state, bool $allowed): void
    {
        $admin = User::factory()->create();
        $route = $this->routeInState($state);

        $this->assertSame($allowed, $admin->can('update', $route));
        $this->assertSame($allowed, $admin->can('delete', $route));
    }

    public static function states(): array
    {
        return array_map(fn (array $set) => [$set[0]], self::editableStates());
    }

    #[DataProvider('states')]
    public function test_admin_views_route_in_any_state(?string $state): void
    {
        $admin = User::factory()->create();

        $this->assertTrue($admin->can('view', $this->routeInState($state)));
        $this->assertTrue($admin->can('viewAny', DeliveryRoute::class));
        $this->assertTrue($admin->can('create', DeliveryRoute::class));
    }

    public function test_driver_views_and_drives_own_assigned_route(): void
    {
        $route = DeliveryRoute::factory()->assigned()->create();

        $this->assertTrue($route->driver->can('view', $route));
        $this->assertTrue($route->driver->can('start', $route));
        $this->assertTrue($route->driver->can('finish', $route));
    }

    public function test_driver_does_not_see_other_drivers_route(): void
    {
        $route = DeliveryRoute::factory()->inProgress()->create();
        $otherDriver = User::factory()->driver()->create();

        $this->assertFalse($otherDriver->can('view', $route));
        $this->assertFalse($otherDriver->can('start', $route));
        $this->assertFalse($otherDriver->can('finish', $route));
    }

    public function test_driver_does_not_see_own_draft_route(): void
    {
        $route = DeliveryRoute::factory()->create();

        $this->assertFalse($route->driver->can('view', $route));
        $this->assertFalse($route->driver->can('start', $route));
    }

    public function test_driver_cannot_create_edit_or_delete_routes(): void
    {
        $route = DeliveryRoute::factory()->assigned()->create();

        $this->assertFalse($route->driver->can('create', DeliveryRoute::class));
        $this->assertFalse($route->driver->can('update', $route));
        $this->assertFalse($route->driver->can('delete', $route));
    }

    public function test_admin_does_not_drive_routes(): void
    {
        $admin = User::factory()->create();
        $route = DeliveryRoute::factory()->assigned()->create();

        $this->assertFalse($admin->can('start', $route));
        $this->assertFalse($admin->can('finish', $route));
    }

    public function test_driver_route_list_contains_only_own_non_draft_routes(): void
    {
        $driver = User::factory()->driver()->create();
        $own = DeliveryRoute::factory()->for($driver, 'driver')->assigned()->create();
        DeliveryRoute::factory()->for($driver, 'driver')->create();
        DeliveryRoute::factory()->assigned()->create();

        $this->assertSame([$own->id], DeliveryRoute::visibleToDriver($driver)->pluck('id')->all());
    }

    private function routeInState(?string $state): DeliveryRoute
    {
        $factory = DeliveryRoute::factory();

        return ($state ? $factory->{$state}() : $factory)->create();
    }
}
