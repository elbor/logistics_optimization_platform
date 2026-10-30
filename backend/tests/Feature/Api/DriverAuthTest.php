<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Http\Requests\Api\Driver\LoginRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Вхід водія в API токенами Sanctum (SPEC 7.2, 9).
 */
class DriverAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-horse-battery';

    public function test_active_driver_logs_in_and_gets_token(): void
    {
        $driver = $this->driver();

        $response = $this->login($driver->email)
            ->assertOk()
            ->assertJsonPath('data.id', $driver->id)
            ->assertJsonPath('data.email', $driver->email)
            ->assertJsonMissingPath('data.password')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'phone'], 'token', 'expires_at']);

        $token = PersonalAccessToken::findToken($response->json('token'));
        $this->assertTrue($token->tokenable->is($driver));
        $this->assertSame('Pixel 8', $token->name);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $token->expires_at->timestamp, 5);
        $this->assertSame($token->expires_at->toIso8601ZuluString(), $response->json('expires_at'));

        $this->getJson('/api/driver/me', $this->bearer($response))
            ->assertOk()
            ->assertJsonPath('data.id', $driver->id);
    }

    public function test_email_is_case_insensitive(): void
    {
        $driver = $this->driver();

        $this->login('  '.strtoupper($driver->email).' ')->assertOk();
    }

    public function test_every_rejection_looks_the_same(): void
    {
        // Тіло відповіді як у продакшені, без трасування
        config(['app.debug' => false]);
        $driver = $this->driver();
        $inactive = $this->driver(['is_active' => false]);
        $admin = User::factory()->create(['password' => self::PASSWORD]);

        $responses = [
            'wrong password' => $this->login($driver->email, 'wrong-password'),
            'unknown email' => $this->login('nobody@example.com'),
            'admin' => $this->login($admin->email),
            'inactive driver' => $this->login($inactive->email),
        ];

        foreach ($responses as $case => $response) {
            $response->assertStatus(401);
            $this->assertSame(['message' => 'Неправильний email або пароль.'], $response->json(), $case);
        }

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_validation_errors_are_in_ukrainian(): void
    {
        $this->postJson('/api/driver/login', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Поле «Електронна пошта» має містити правильну адресу електронної пошти.')
            ->assertJsonPath('errors.password.0', 'Поле «Пароль» обов\'язкове.')
            ->assertJsonPath('errors.device_name.0', 'Поле «Назва пристрою» обов\'язкове.');
    }

    public function test_logout_revokes_only_current_token(): void
    {
        $driver = $this->driver();
        $phone = $this->login($driver->email)->json('token');
        $tablet = $this->login($driver->email)->json('token');

        $this->postJson('/api/driver/logout', [], ['Authorization' => "Bearer $phone"])->assertNoContent();
        $this->forgetGuards();

        $this->getJson('/api/driver/me', ['Authorization' => "Bearer $phone"])->assertUnauthorized();
        $this->forgetGuards();
        $this->getJson('/api/driver/me', ['Authorization' => "Bearer $tablet"])->assertOk();
    }

    public function test_request_without_token_gets_401_in_ukrainian(): void
    {
        $this->getJson('/api/driver/me')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Потрібно увійти.']);
    }

    public function test_admin_session_does_not_authenticate_api(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/driver/me')
            ->assertUnauthorized();
    }

    public function test_token_expires_after_30_days(): void
    {
        $driver = $this->driver();
        $token = $this->login($driver->email)->json('token');

        $this->travel(30)->days();
        $this->travel(-1)->minutes();
        $this->getJson('/api/driver/me', ['Authorization' => "Bearer $token"])->assertOk();
        $this->forgetGuards();

        $this->travel(2)->minutes();
        $this->getJson('/api/driver/me', ['Authorization' => "Bearer $token"])->assertUnauthorized();
    }

    public function test_deactivation_revokes_all_driver_tokens(): void
    {
        $driver = $this->driver();
        $other = $this->driver();
        $token = $this->login($driver->email)->json('token');
        $this->login($driver->email);
        $this->login($other->email);

        $driver->is_active = false;
        $driver->save();

        $this->assertSame(0, $driver->tokens()->count());
        $this->assertSame(1, $other->tokens()->count());
        $this->getJson('/api/driver/me', ['Authorization' => "Bearer $token"])->assertUnauthorized();
    }

    public function test_other_updates_keep_tokens(): void
    {
        $driver = $this->driver();
        $this->login($driver->email);

        $driver->update(['name' => 'Нове імʼя']);

        $this->assertSame(1, $driver->tokens()->count());
    }

    public function test_live_token_of_user_who_is_no_longer_a_driver_gets_403(): void
    {
        $driver = $this->driver();
        $token = $this->login($driver->email)->json('token');

        $driver->role = UserRole::Admin;
        $driver->save();
        config(['app.debug' => false]);

        $this->getJson('/api/driver/me', ['Authorization' => "Bearer $token"])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Доступ лише для активного водія.']);
    }

    public function test_failed_attempts_are_throttled_per_email_and_ip(): void
    {
        $driver = $this->driver();

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS; $i++) {
            $this->login($driver->email, 'wrong-password')->assertUnauthorized();
        }

        // Навіть правильний пароль не пройде, доки діє блокування
        $this->login($driver->email)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'Забагато спроб входу.'));

        // Інша IP-адреса й інший email — окремі лічильники
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->login($driver->email)->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
        $this->login($this->driver()->email)->assertOk();

        $this->travel(LoginRequest::DECAY_SECONDS + 1)->seconds();
        $this->login($driver->email)->assertOk();
    }

    public function test_failed_attempts_from_one_ip_are_throttled_across_emails(): void
    {
        // Перебір різних email: лічильник email + IP не спрацює, лише загальний на IP.
        // Попередньо заповнюємо лічильник (кожна справжня невдала спроба — 200 мс Timebox)
        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS_PER_IP - 1; $i++) {
            RateLimiter::hit(LoginRequest::ipThrottleKey('127.0.0.1'), LoginRequest::DECAY_SECONDS);
        }

        $this->login('first-unknown@example.com', 'wrong-password')->assertUnauthorized();
        $driver = $this->driver();
        // Тепер заблоковано будь-який вхід з цієї IP, навіть правильний
        $this->login($driver->email)->assertTooManyRequests();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->login($driver->email)->assertOk();
    }

    public function test_successful_login_does_not_reset_ip_counter(): void
    {
        $driver = $this->driver();
        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS_PER_IP - 1; $i++) {
            RateLimiter::hit(LoginRequest::ipThrottleKey('127.0.0.1'), LoginRequest::DECAY_SECONDS);
        }

        $this->login($driver->email)->assertOk();
        $this->login('other@example.com', 'wrong-password')->assertUnauthorized();

        $this->login($driver->email)->assertTooManyRequests();
    }

    public function test_successful_login_resets_failed_attempts(): void
    {
        $driver = $this->driver();

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS - 1; $i++) {
            $this->login($driver->email, 'wrong-password');
        }
        $this->login($driver->email)->assertOk();

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS - 1; $i++) {
            $this->login($driver->email, 'wrong-password')->assertUnauthorized();
        }
        $this->login($driver->email)->assertOk();
    }

    private function driver(array $attributes = []): User
    {
        return User::factory()->driver()->create(['password' => self::PASSWORD, ...$attributes]);
    }

    private function login(string $email, string $password = self::PASSWORD): TestResponse
    {
        return $this->postJson('/api/driver/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'Pixel 8',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function bearer(TestResponse $loginResponse): array
    {
        return ['Authorization' => 'Bearer '.$loginResponse->json('token')];
    }

    /**
     * Гард sanctum кешує користувача між «запитами» одного тесту.
     */
    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
