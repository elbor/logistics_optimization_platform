<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Vehicles\Pages\ManageVehicles;
use App\Models\DeliveryRoute;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ресурс Filament «Машини» (SPEC 3.5).
 */
class VehicleResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    public function test_page_is_in_ukrainian(): void
    {
        $this->get('/admin/vehicles')
            ->assertOk()
            ->assertSee('Машини')
            ->assertSee('Витрата пального');
    }

    public function test_list_hides_inactive_by_default_and_is_searchable_and_sortable(): void
    {
        $transit = Vehicle::factory()->create(['name' => 'Ford Transit', 'plate' => 'AA1111AA']);
        $sprinter = Vehicle::factory()->create(['name' => 'Mercedes Sprinter', 'plate' => 'BB2222BB']);
        $inactive = Vehicle::factory()->create(['name' => 'Старий бус', 'is_active' => false]);

        Livewire::test(ManageVehicles::class)
            ->assertCanSeeTableRecords([$transit, $sprinter], inOrder: true)
            ->assertCanNotSeeTableRecords([$inactive])
            ->sortTable('name', 'desc')
            ->assertCanSeeTableRecords([$sprinter, $transit], inOrder: true)
            ->searchTable('BB2222')
            ->assertCanSeeTableRecords([$sprinter])
            ->assertCanNotSeeTableRecords([$transit]);

        Livewire::test(ManageVehicles::class)
            ->filterTable('is_active', false)
            ->assertCanSeeTableRecords([$inactive])
            ->assertCanNotSeeTableRecords([$transit, $sprinter]);
    }

    public function test_create_normalizes_plate(): void
    {
        Livewire::test(ManageVehicles::class)
            ->callAction('create', data: [
                'name' => 'Ford Transit',
                'plate' => 'аа 1234 вв',
                'fuel_consumption_l_per_100km' => '9.5',
            ])
            ->assertHasNoFormErrors();

        $vehicle = Vehicle::sole();
        $this->assertSame('AA1234BB', $vehicle->plate);
        $this->assertSame('9.50', $vehicle->fuel_consumption_l_per_100km);
        $this->assertTrue($vehicle->is_active);
    }

    public function test_duplicate_plate_in_another_spelling_is_a_form_error(): void
    {
        Vehicle::factory()->create(['plate' => 'AA1234BB']);

        // Кирилиця, пробіли, нижній регістр — той самий номер
        Livewire::test(ManageVehicles::class)
            ->callAction('create', data: [
                'name' => 'Інша машина',
                'plate' => 'аа 1234 вв',
                'fuel_consumption_l_per_100km' => '9.5',
            ])
            ->assertHasFormErrors(['plate' => 'unique']);

        $this->assertSame(1, Vehicle::count());
    }

    public function test_editing_keeps_own_plate_valid(): void
    {
        $vehicle = Vehicle::factory()->create(['plate' => 'AA1234BB']);

        Livewire::test(ManageVehicles::class)
            ->callAction(TestAction::make('edit')->table($vehicle), data: [
                'name' => 'Нова назва',
                'plate' => 'aa 1234 bb',
                'fuel_consumption_l_per_100km' => '11',
            ])
            ->assertHasNoFormErrors();

        $vehicle->refresh();
        $this->assertSame('Нова назва', $vehicle->name);
        $this->assertSame('11.00', $vehicle->fuel_consumption_l_per_100km);
    }

    public function test_validation_matches_database_checks(): void
    {
        foreach (['0', '100', 'abc'] as $consumption) {
            Livewire::test(ManageVehicles::class)
                ->callAction('create', data: [
                    'name' => 'Ford Transit',
                    'plate' => 'AA1234BB',
                    'fuel_consumption_l_per_100km' => $consumption,
                ])
                ->assertHasFormErrors(['fuel_consumption_l_per_100km']);
        }

        Livewire::test(ManageVehicles::class)
            ->callAction('create', data: ['name' => '', 'plate' => '', 'fuel_consumption_l_per_100km' => ''])
            ->assertHasFormErrors(['name' => 'required', 'plate' => "Поле «номер» обов'язкове.", 'fuel_consumption_l_per_100km' => 'required']);

        $this->assertSame(0, Vehicle::count());
    }

    public function test_vehicle_with_routes_is_deactivated_not_deleted(): void
    {
        $vehicle = DeliveryRoute::factory()->create()->vehicle;

        Livewire::test(ManageVehicles::class)
            ->assertActionHidden(TestAction::make('delete')->table($vehicle))
            ->callAction(TestAction::make('deactivate')->table($vehicle));

        $this->assertFalse($vehicle->fresh()->is_active);
    }

    public function test_vehicle_without_routes_can_be_deleted(): void
    {
        $vehicle = Vehicle::factory()->create();

        Livewire::test(ManageVehicles::class)
            ->callAction(TestAction::make('delete')->table($vehicle));

        $this->assertModelMissing($vehicle);
    }

    public function test_inactive_vehicle_can_be_activated(): void
    {
        $vehicle = Vehicle::factory()->create(['is_active' => false]);

        Livewire::test(ManageVehicles::class)
            ->filterTable('is_active', false)
            ->assertActionHidden(TestAction::make('deactivate')->table($vehicle))
            ->callAction(TestAction::make('activate')->table($vehicle));

        $this->assertTrue($vehicle->fresh()->is_active);
    }

    public function test_driver_has_no_access(): void
    {
        $this->actingAs(User::factory()->driver()->create())
            ->get('/admin/vehicles')
            ->assertForbidden();
    }
}
