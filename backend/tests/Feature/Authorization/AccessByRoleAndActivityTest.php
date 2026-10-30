<?php

namespace Tests\Feature\Authorization;

use App\Models\DeliveryRoute;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Хто може увійти в панель і в API водія; неактивний користувач не може нічого.
 */
class AccessByRoleAndActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_admin_accesses_panel(): void
    {
        $this->assertTrue(User::factory()->create()->canAccessPanel());
        $this->assertFalse(User::factory()->inactive()->create()->canAccessPanel());
        $this->assertFalse(User::factory()->driver()->create()->canAccessPanel());
    }

    public function test_only_active_driver_uses_driver_api(): void
    {
        $this->assertTrue(User::factory()->driver()->create()->canUseDriverApi());
        $this->assertFalse(User::factory()->driver()->inactive()->create()->canUseDriverApi());
        $this->assertFalse(User::factory()->create()->canUseDriverApi());
    }

    public function test_inactive_admin_is_denied_every_ability(): void
    {
        $admin = User::factory()->inactive()->create();

        $this->assertFalse($admin->can('viewAny', Store::class));
        $this->assertFalse($admin->can('create', Store::class));
        $this->assertFalse($admin->can('view', DeliveryRoute::factory()->create()));
    }

    public function test_inactive_driver_cannot_see_own_route(): void
    {
        $route = DeliveryRoute::factory()->assigned()->create();
        $route->driver->is_active = false;

        $this->assertFalse($route->driver->can('view', $route));
        $this->assertFalse($route->driver->can('start', $route));
    }
}
