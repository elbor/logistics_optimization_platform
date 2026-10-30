<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_active_admin(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Email', ' Owner@Example.com ')
            ->expectsQuestion('Name', 'Олександр')
            ->expectsQuestion('Password', 'correct-horse-battery')
            ->expectsQuestion('Repeat password', 'correct-horse-battery')
            ->assertSuccessful();

        $admin = User::sole();
        $this->assertSame('owner@example.com', $admin->email);
        $this->assertSame('Олександр', $admin->name);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('correct-horse-battery', $admin->password));
    }

    public function test_refuses_existing_email_in_any_case(): void
    {
        User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('app:create-admin')
            ->expectsQuestion('Email', 'OWNER@example.com')
            ->expectsQuestion('Name', 'Дубль')
            ->expectsQuestion('Password', 'correct-horse-battery')
            ->expectsQuestion('Repeat password', 'correct-horse-battery')
            ->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_refuses_short_or_mismatched_password(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Email', 'owner@example.com')
            ->expectsQuestion('Name', 'Олександр')
            ->expectsQuestion('Password', 'short')
            ->expectsQuestion('Repeat password', 'other')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
