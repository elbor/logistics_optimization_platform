<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Вхід в адмін-панель Filament і вихід з усіх сесій при деактивації (SPEC 7.1).
 */
class PanelLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-horse-battery';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_login_page_is_in_ukrainian(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Увійдіть у свій акаунт')
            ->assertSee('Електронна пошта');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_active_admin_logs_in_by_email(): void
    {
        $admin = User::factory()->create(['password' => self::PASSWORD]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk();
    }

    public function test_driver_cannot_log_in(): void
    {
        $driver = User::factory()->driver()->create(['password' => self::PASSWORD]);

        $this->assertLoginRejected($driver);
    }

    public function test_inactive_admin_cannot_log_in(): void
    {
        $admin = User::factory()->inactive()->create(['password' => self::PASSWORD]);

        $this->assertLoginRejected($admin);
    }

    public function test_authenticated_driver_or_inactive_admin_gets_403(): void
    {
        $this->actingAs(User::factory()->driver()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->inactive()->create())->get('/admin')->assertForbidden();
    }

    public function test_deactivation_kills_existing_session(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();
        $sessionId = $this->startSessionFor($admin);
        $this->startSessionFor($other);

        // Контроль: сесія справді автентифікує
        $this->withCookie(config('session.cookie'), $sessionId)->get('/admin')->assertOk();
        $this->forgetRequestState();

        $admin->is_active = false;
        $admin->save();

        $this->assertDatabaseMissing('sessions', ['user_id' => $admin->id]);
        $this->assertDatabaseHas('sessions', ['user_id' => $other->id]);
        // Гість, а не 403: сесії вже немає, а не лише доступ заборонено
        $this->withCookie(config('session.cookie'), $sessionId)->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_deactivation_rotates_remember_token(): void
    {
        $admin = User::factory()->create(['remember_token' => 'old-token']);

        $admin->is_active = false;
        $admin->save();

        $this->assertNotSame('old-token', $admin->fresh()->remember_token);
    }

    public function test_other_updates_keep_sessions(): void
    {
        $admin = User::factory()->create();
        $this->startSessionFor($admin);

        $admin->update(['name' => 'Нове імʼя']);

        $this->assertDatabaseHas('sessions', ['user_id' => $admin->id]);
    }

    private function assertLoginRejected(User $user): void
    {
        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    /**
     * Сесія в базі, як після справжнього входу: рядок з user_id і ключем входу гарда web.
     */
    private function startSessionFor(User $user): string
    {
        config(['session.driver' => 'database']);

        // DatabaseSessionHandler бере user_id з поточного гарда
        Auth::setUser($user);
        $session = $this->app['session']->driver();
        $session->setId(null);
        $session->start();
        $session->put(Auth::guard('web')->getName(), $user->getAuthIdentifier());
        $session->save();

        $this->forgetRequestState();

        $this->assertSame(1, DB::table('sessions')->where('id', $session->getId())->where('user_id', $user->id)->count());

        return $session->getId();
    }

    /**
     * Тести ділять один застосунок між «запитами»: скидаємо закешованих гардів і сесію,
     * щоб наступний запит автентифікувався лише через cookie і рядок у базі.
     */
    private function forgetRequestState(): void
    {
        Auth::forgetGuards();
        // Guard::class — синглтон auth.driver, він тримає гард навіть після forgetGuards()
        $this->app->forgetInstance('auth.driver');
        // Гард читає сесію через синглтон session.store, а не через менеджер
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }
}
