<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Drivers\Pages\ManageDrivers;
use App\Models\DeliveryRoute;
use App\Models\User;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ресурс Filament «Водії» (SPEC 3.5).
 */
class DriverResourceTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-horse-battery';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->admin = User::factory()->create(['name' => 'Адмін']);
        $this->actingAs($this->admin);
    }

    public function test_page_is_in_ukrainian(): void
    {
        $this->get('/admin/drivers')
            ->assertOk()
            ->assertSee('Водії')
            ->assertSee('Телефон');
    }

    public function test_list_shows_only_drivers_and_is_searchable_and_sortable(): void
    {
        $ivan = User::factory()->driver()->create(['name' => 'Іван', 'phone' => '+380501112233']);
        $petro = User::factory()->driver()->create(['name' => 'Петро']);
        $otherAdmin = User::factory()->create();

        Livewire::test(ManageDrivers::class)
            ->assertCanSeeTableRecords([$ivan, $petro], inOrder: true)
            ->assertCanNotSeeTableRecords([$this->admin, $otherAdmin])
            ->sortTable('name', 'desc')
            ->assertCanSeeTableRecords([$petro, $ivan], inOrder: true)
            ->searchTable('501112233')
            ->assertCanSeeTableRecords([$ivan])
            ->assertCanNotSeeTableRecords([$petro]);
    }

    public function test_create_makes_driver_with_normalized_email_and_phone(): void
    {
        Livewire::test(ManageDrivers::class)
            ->callAction('create', data: [
                'name' => 'Іван Петренко',
                'email' => ' Ivan@Example.COM ',
                'phone' => '050 123 45 67',
                'password' => self::PASSWORD,
                'password_confirmation' => self::PASSWORD,
            ])
            ->assertHasNoFormErrors();

        $driver = User::where('role', UserRole::Driver)->sole();
        $this->assertSame('ivan@example.com', $driver->email);
        $this->assertSame('+380501234567', $driver->phone);
        $this->assertTrue(Hash::check(self::PASSWORD, $driver->password));
        $this->assertTrue($driver->canUseDriverApi());
    }

    public function test_create_validation(): void
    {
        Livewire::test(ManageDrivers::class)
            ->callAction('create', data: ['name' => '', 'email' => '', 'phone' => '', 'password' => '', 'password_confirmation' => ''])
            ->assertHasFormErrors(['name' => 'required', 'email' => 'required', 'phone' => 'required', 'password' => 'required']);

        Livewire::test(ManageDrivers::class)
            ->callAction('create', data: [
                'name' => 'Іван',
                'email' => 'not-an-email',
                'phone' => '123',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertHasFormErrors([
                'email' => 'email',
                'phone' => 'Неправильний номер телефону.',
                'password' => 'Поле «пароль» має містити щонайменше 8 символів.',
            ]);

        Livewire::test(ManageDrivers::class)
            ->callAction('create', data: [
                'name' => 'Іван',
                'email' => 'ivan@example.com',
                'phone' => '0501234567',
                'password' => self::PASSWORD,
                'password_confirmation' => 'typo',
            ])
            ->assertHasFormErrors(['password' => 'confirmed']);

        $this->assertSame(0, User::where('role', UserRole::Driver)->count());
    }

    public function test_email_taken_by_anyone_in_another_case_is_a_form_error(): void
    {
        Livewire::test(ManageDrivers::class)
            ->callAction('create', data: [
                'name' => 'Іван',
                'email' => strtoupper($this->admin->email),
                'phone' => '0501234567',
                'password' => self::PASSWORD,
                'password_confirmation' => self::PASSWORD,
            ])
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_edit_has_no_password_and_keeps_it(): void
    {
        $driver = $this->driver();

        Livewire::test(ManageDrivers::class)
            ->mountAction(TestAction::make('edit')->table($driver))
            ->assertFormFieldHidden('password')
            ->callMountedAction();

        Livewire::test(ManageDrivers::class)
            ->callAction(TestAction::make('edit')->table($driver), data: [
                'name' => 'Нове імʼя',
                'email' => $driver->email,
                'phone' => '07400 123456',
            ])
            ->assertHasNoFormErrors();

        $driver->refresh();
        $this->assertSame('Нове імʼя', $driver->name);
        $this->assertSame('+447400123456', $driver->phone);
        $this->assertTrue(Hash::check(self::PASSWORD, $driver->password));
    }

    public function test_change_password_logs_driver_out_of_devices(): void
    {
        $driver = $this->driver();
        $driver->createToken('Pixel 8');

        Livewire::test(ManageDrivers::class)
            ->callAction(TestAction::make('changePassword')->table($driver), data: [
                'password' => 'new-secret-password',
                'password_confirmation' => 'new-secret-password',
            ])
            ->assertHasNoActionErrors();

        $driver->refresh();
        $this->assertTrue(Hash::check('new-secret-password', $driver->password));
        $this->assertSame(0, $driver->tokens()->count());
    }

    public function test_change_password_requires_confirmation(): void
    {
        $driver = $this->driver();
        $driver->createToken('Pixel 8');

        Livewire::test(ManageDrivers::class)
            ->callAction(TestAction::make('changePassword')->table($driver), data: [
                'password' => 'new-secret-password',
                'password_confirmation' => 'typo',
            ])
            ->assertHasActionErrors(['password' => 'confirmed']);

        $driver->refresh();
        $this->assertTrue(Hash::check(self::PASSWORD, $driver->password));
        $this->assertSame(1, $driver->tokens()->count());
    }

    public function test_driver_with_routes_is_deactivated_not_deleted(): void
    {
        $driver = DeliveryRoute::factory()->create()->driver;
        $driver->createToken('Pixel 8');

        Livewire::test(ManageDrivers::class)
            ->assertActionHidden(TestAction::make('delete')->table($driver))
            ->callAction(TestAction::make('deactivate')->table($driver));

        $driver->refresh();
        $this->assertFalse($driver->is_active);
        $this->assertSame(0, $driver->tokens()->count());
    }

    public function test_driver_without_routes_can_be_deleted(): void
    {
        $driver = $this->driver();

        Livewire::test(ManageDrivers::class)
            ->callAction(TestAction::make('delete')->table($driver));

        $this->assertModelMissing($driver);
    }

    public function test_inactive_driver_can_be_activated(): void
    {
        $driver = $this->driver(['is_active' => false]);

        Livewire::test(ManageDrivers::class)
            ->filterTable('is_active', false)
            ->assertActionHidden(TestAction::make('deactivate')->table($driver))
            ->callAction(TestAction::make('activate')->table($driver));

        $this->assertTrue($driver->fresh()->is_active);
    }

    public function test_admin_cannot_reach_own_record_through_drivers(): void
    {
        $this->get('/admin/drivers')->assertOk()->assertDontSee($this->admin->email);

        // Запис адміна поза запитом ресурсу: Filament не знаходить його для дії
        try {
            Livewire::test(ManageDrivers::class)
                ->callAction(TestAction::make('deactivate')->table($this->admin));
            $this->fail('Action on admin record must not resolve');
        } catch (ActionNotResolvableException) {
        }

        $this->assertTrue($this->admin->fresh()->is_active);
        $this->assertFalse($this->admin->can('deactivate', $this->admin));
    }

    private function driver(array $attributes = []): User
    {
        return User::factory()->driver()->create(['password' => self::PASSWORD, ...$attributes]);
    }
}
