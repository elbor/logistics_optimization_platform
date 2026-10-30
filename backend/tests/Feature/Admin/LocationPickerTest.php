<?php

namespace Tests\Feature\Admin;

use App\Filament\Forms\Components\LocationPicker;
use App\Filament\Pages\LocationPickerDemo;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Поле «адреса → координати» на тестовій сторінці (SPEC 3.1, 3.6).
 */
class LocationPickerTest extends TestCase
{
    use RefreshDatabase;

    private const SEARCH_URL = 'https://nominatim.test/search*';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.nominatim.base_url' => 'https://nominatim.test',
            'services.nominatim.contact' => 'ops@example.com',
        ]);
        Sleep::fake();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    public function test_page_loads_map_assets_and_shows_attribution_config(): void
    {
        $this->get('/admin/location-picker-demo')
            ->assertOk()
            ->assertSee('Знайти на карті')
            ->assertSee('maplibre-gl.js')
            // URL стилю всередині JSON.parse('…') з екранованими «/»
            ->assertSee('tiles.openfreemap.org')
            ->assertSee('liberty')
            ->assertSee('OpenStreetMap contributors');
    }

    public function test_typing_an_address_does_not_call_geocoder(): void
    {
        Http::fake();

        Livewire::test(LocationPickerDemo::class)
            ->set('data.address', 'Київ, Хрещатик')
            ->set('data.address', 'Київ, Хрещатик 1');

        Http::assertNothingSent();
    }

    public function test_single_result_sets_pin_from_geocoder(): void
    {
        $this->fakeResults([['50.4501', '30.5234', 'Хрещатик, 1, Київ']]);

        $page = Livewire::test(LocationPickerDemo::class)->set('data.address', 'Київ, Хрещатик 1');

        $this->assertSame(['status' => 'found', 'lat' => 50.4501, 'lng' => 30.5234], $this->callPicker($page, 'search'));
        $page->assertSet('data.lat', 50.4501)
            ->assertSet('data.lng', 30.5234)
            ->assertSet('data.location_source', 'geocoder');
    }

    public function test_several_results_let_admin_choose_without_new_request(): void
    {
        $this->fakeResults([
            ['50.4501', '30.5234', 'Хрещатик, 1, Київ'],
            ['49.8397', '24.0297', 'Хрещатик, Львів'],
        ]);

        $page = Livewire::test(LocationPickerDemo::class)->set('data.address', 'Хрещатик');

        $response = $this->callPicker($page, 'search');
        $this->assertSame('choose', $response['status']);
        $this->assertSame(['Хрещатик, 1, Київ', 'Хрещатик, Львів'], array_column($response['candidates'], 'label'));
        $page->assertSet('data.lat', null);

        $this->callPicker($page, 'choose', ['index' => 1]);
        $page->assertSet('data.lat', 49.8397)
            ->assertSet('data.lng', 24.0297)
            ->assertSet('data.location_source', 'geocoder');

        // Вибір узято з кешу
        Http::assertSentCount(1);
    }

    public function test_manual_pin_is_not_replaced_without_confirmation(): void
    {
        $this->fakeResults([['50.4501', '30.5234', 'Хрещатик, 1, Київ']]);

        $page = Livewire::test(LocationPickerDemo::class)
            ->set('data.address', 'Київ, Хрещатик 1')
            ->set('data.lat', 50.1)
            ->set('data.lng', 30.1)
            ->set('data.location_source', 'manual');

        $this->assertSame(['status' => 'confirm_replace'], $this->callPicker($page, 'search'));
        $page->assertSet('data.lat', 50.1)->assertSet('data.location_source', 'manual');
        Http::assertNothingSent();

        $this->callPicker($page, 'search', ['replaceManual' => true]);
        $page->assertSet('data.lat', 50.4501)->assertSet('data.location_source', 'geocoder');
    }

    public function test_choosing_a_candidate_also_respects_manual_pin(): void
    {
        $this->fakeResults([
            ['50.4501', '30.5234', 'Хрещатик, 1, Київ'],
            ['49.8397', '24.0297', 'Хрещатик, Львів'],
        ]);

        $page = Livewire::test(LocationPickerDemo::class)
            ->set('data.address', 'Хрещатик')
            ->set('data.lat', 50.1)
            ->set('data.lng', 30.1)
            ->set('data.location_source', 'manual');

        $this->assertSame(['status' => 'confirm_replace'], $this->callPicker($page, 'choose', ['index' => 0]));
        $page->assertSet('data.lat', 50.1);
    }

    public function test_geocoder_pin_is_replaced_by_new_search_without_asking(): void
    {
        $this->fakeResults([['50.4501', '30.5234', 'Хрещатик, 1, Київ']]);

        $page = Livewire::test(LocationPickerDemo::class)
            ->set('data.address', 'Київ, Хрещатик 1')
            ->set('data.lat', 49.0)
            ->set('data.lng', 24.0)
            ->set('data.location_source', 'geocoder');

        $this->assertSame('found', $this->callPicker($page, 'search')['status']);
        $page->assertSet('data.lat', 50.4501);
    }

    public function test_empty_address_and_failures_return_ukrainian_messages(): void
    {
        Http::fake([self::SEARCH_URL => Http::sequence()->push([])->push('Server Error', 500)]);
        $page = Livewire::test(LocationPickerDemo::class);

        $this->assertSame(['status' => 'error', 'message' => 'Спершу введіть адресу.'], $this->callPicker($page, 'search'));
        Http::assertNothingSent();

        $page->set('data.address', 'Неіснуюча вулиця');
        $this->assertSame('not_found', $this->callPicker($page, 'search')['status']);

        $page->set('data.address', 'Інша адреса');
        $response = $this->callPicker($page, 'search');
        $this->assertSame('error', $response['status']);
        $this->assertSame(__('geocoding.unavailable'), $response['message']);
        $page->assertSet('data.lat', null);
    }

    public function test_form_requires_a_pin(): void
    {
        Livewire::test(LocationPickerDemo::class)
            ->set('data.address', 'Київ, Хрещатик 1')
            ->call('check')
            ->assertHasErrors(['data.lat' => 'Знайдіть адресу на карті або поставте пін.']);

        Livewire::test(LocationPickerDemo::class)
            ->set('data.address', 'Київ, Хрещатик 1')
            ->set('data.lat', 50.45)
            ->set('data.lng', 30.52)
            ->set('data.location_source', 'manual')
            ->call('check')
            ->assertHasNoErrors();
    }

    public function test_out_of_range_coordinates_and_unknown_source_are_rejected(): void
    {
        Livewire::test(LocationPickerDemo::class)
            ->set('data.address', 'Київ')
            ->set('data.lat', 91)
            ->set('data.lng', 181)
            ->set('data.location_source', 'gps')
            ->call('check')
            ->assertHasErrors(['data.lat' => 'between', 'data.lng' => 'between', 'data.location_source' => 'in']);
    }

    public function test_demo_page_is_not_available_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->assertFalse(LocationPickerDemo::canAccess());
    }

    /**
     * @param  list<array{string, string, string}>  $results  [lat, lon, display_name]
     */
    private function fakeResults(array $results): void
    {
        Http::fake([self::SEARCH_URL => Http::response(array_map(
            fn (array $r) => ['lat' => $r[0], 'lon' => $r[1], 'display_name' => $r[2]],
            $results,
        ))]);
    }

    /**
     * Як Alpine: $wire.callSchemaComponentMethod(key, method, args).
     */
    private function callPicker(Testable $page, string $method, array $arguments = []): mixed
    {
        $key = collect($page->instance()->form->getFlatComponents())
            ->first(fn ($component) => $component instanceof LocationPicker)
            ->getKey();

        $page->call('callSchemaComponentMethod', $key, $method, $arguments);

        return $page->effects['returns'][0] ?? null;
    }
}
