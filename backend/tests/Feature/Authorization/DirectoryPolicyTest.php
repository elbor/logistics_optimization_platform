<?php

namespace Tests\Feature\Authorization;

use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Models\RouteStop;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Довідники (магазини, машини, користувачі, налаштування) — лише для адміна.
 */
class DirectoryPolicyTest extends TestCase
{
    use RefreshDatabase;

    public static function directoryModels(): array
    {
        return [
            'store' => [Store::class],
            'vehicle' => [Vehicle::class],
            'user' => [User::class],
        ];
    }

    #[DataProvider('directoryModels')]
    public function test_admin_manages_directory(string $model): void
    {
        $admin = User::factory()->create();
        $record = $model::factory()->create();

        $this->assertTrue($admin->can('viewAny', $model));
        $this->assertTrue($admin->can('create', $model));
        $this->assertTrue($admin->can('view', $record));
        $this->assertTrue($admin->can('update', $record));
        $this->assertTrue($admin->can('delete', $record));
    }

    #[DataProvider('directoryModels')]
    public function test_driver_has_no_access_to_directory(string $model): void
    {
        $driver = User::factory()->driver()->create();
        $record = $model::factory()->create();

        $this->assertFalse($driver->can('viewAny', $model));
        $this->assertFalse($driver->can('create', $model));
        $this->assertFalse($driver->can('view', $record));
        $this->assertFalse($driver->can('update', $record));
        $this->assertFalse($driver->can('delete', $record));
    }

    public function test_store_with_orders_or_stops_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();

        $this->assertFalse($admin->can('delete', Order::factory()->create()->store));
        $this->assertFalse($admin->can('delete', RouteStop::factory()->create()->store));
    }

    public function test_vehicle_with_routes_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();

        $this->assertFalse($admin->can('delete', DeliveryRoute::factory()->create()->vehicle));
    }

    public function test_driver_with_routes_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();

        $this->assertFalse($admin->can('delete', DeliveryRoute::factory()->create()->driver));
    }

    public function test_admin_cannot_delete_or_deactivate_self(): void
    {
        $admin = User::factory()->create();
        $otherAdmin = User::factory()->create();

        $this->assertFalse($admin->can('delete', $admin));
        $this->assertFalse($admin->can('deactivate', $admin));
        $this->assertTrue($admin->can('deactivate', $otherAdmin));
    }

    public function test_driver_views_own_profile_only(): void
    {
        $driver = User::factory()->driver()->create();

        $this->assertTrue($driver->can('view', $driver));
        $this->assertFalse($driver->can('update', $driver));
        $this->assertFalse($driver->can('view', User::factory()->driver()->create()));
    }

    public function test_only_admin_manages_settings(): void
    {
        $admin = User::factory()->create();
        $driver = User::factory()->driver()->create();
        $settings = Setting::factory()->create();

        $this->assertTrue($admin->can('view', $settings));
        $this->assertTrue($admin->can('update', $settings));
        $this->assertFalse($admin->can('delete', $settings));
        $this->assertFalse($driver->can('view', $settings));
        $this->assertFalse($driver->can('update', $settings));
    }
}
