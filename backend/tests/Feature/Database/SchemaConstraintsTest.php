<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Інваріанти з docs/data-model.md, які тримає сама база (CHECK, часткові індекси, тригер).
 * Порушення обриває транзакцію Postgres, тому в кожному тесті — щонайбільше одне очікуване порушення.
 */
class SchemaConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_cannot_be_in_two_routes_at_once(): void
    {
        $order = $this->order();
        $this->stopOrder($this->stop($this->route()), $order);

        $this->expectViolation('route_stop_orders_order_active_unique');
        $this->stopOrder($this->stop($this->route()), $order);
    }

    public function test_not_delivered_order_can_be_routed_again(): void
    {
        $order = $this->order();
        $this->stopOrder($this->stop($this->route()), $order, 'not_delivered');
        $this->stopOrder($this->stop($this->route()), $order);

        $this->assertSame(2, DB::table('route_stop_orders')->where('order_id', $order)->count());
    }

    public function test_draft_route_deletion_cascades_and_releases_orders(): void
    {
        $order = $this->order();
        $route = $this->route();
        $this->stopOrder($this->stop($route), $order);

        DB::table('routes')->where('id', $route)->delete();

        $this->assertSame(0, DB::table('route_stops')->count());
        $this->assertSame(0, DB::table('route_stop_orders')->count());
        $this->stopOrder($this->stop($this->route()), $order);
    }

    public function test_started_route_cannot_be_deleted(): void
    {
        $route = $this->route(['status' => 'in_progress'] + $this->started());

        $this->expectViolation('cannot be deleted');
        DB::table('routes')->where('id', $route)->delete();
    }

    public function test_driver_has_at_most_one_route_in_progress(): void
    {
        $driver = $this->driver();
        $this->route(['driver_id' => $driver, 'status' => 'in_progress'] + $this->started());

        $this->expectViolation('routes_one_in_progress_per_driver');
        $this->route(['driver_id' => $driver, 'status' => 'in_progress'] + $this->started());
    }

    public function test_route_in_progress_requires_start_times(): void
    {
        $this->expectViolation('routes_started_check');
        $this->route(['status' => 'in_progress']);
    }

    public function test_return_leg_is_set_only_with_return_to_warehouse(): void
    {
        $this->expectViolation('routes_return_leg_distance_m_check');
        $this->route(['return_to_warehouse' => true, 'return_leg_s' => 600]);
    }

    public function test_stop_sequence_is_unique_within_route(): void
    {
        $route = $this->route();
        $this->stop($route, ['sequence' => 1]);

        $this->expectViolation('route_stops_route_id_sequence_unique');
        $this->stop($route, ['sequence' => 1]);
    }

    public function test_closed_stop_requires_close_times(): void
    {
        $this->expectViolation('route_stops_closed_check');
        $this->stop($this->route(), ['status' => 'skipped']);
    }

    public function test_comment_is_allowed_only_for_not_delivered_order(): void
    {
        $this->expectViolation('route_stop_orders_comment_check');
        $this->stopOrder($this->stop($this->route()), $this->order(), 'delivered', 'Магазин зачинено');
    }

    public function test_email_must_be_lowercase(): void
    {
        $this->expectViolation('users_email_lowercase_check');
        $this->user(['email' => 'Ivan@Example.com']);
    }

    public function test_driver_requires_phone(): void
    {
        $this->expectViolation('users_driver_phone_check');
        $this->user(['role' => 'driver']);
    }

    public function test_phone_must_be_e164(): void
    {
        $this->expectViolation('users_phone_e164_check');
        $this->user(['role' => 'driver', 'phone' => '0501234567']);
    }

    public function test_plate_must_be_normalized(): void
    {
        $this->expectViolation('vehicles_plate_normalized_check');
        $this->vehicle(['plate' => 'aa 1234 bb']);
    }

    public function test_plate_must_not_contain_cyrillic_lookalikes(): void
    {
        $this->expectViolation('vehicles_plate_latin_check');
        $this->vehicle(['plate' => 'АА1234ВВ']);
    }

    public function test_blank_external_id_is_rejected(): void
    {
        $this->expectViolation('stores_external_id_1c_not_blank_check');
        $this->store(['external_id_1c' => '']);
    }

    public function test_settings_has_single_row(): void
    {
        $this->expectViolation('settings_single_row_check');
        DB::table('settings')->insert([
            'id' => 2,
            'warehouse_address' => 'Склад',
            'warehouse_lat' => 50.45,
            'warehouse_lng' => 30.52,
            'warehouse_location_source' => 'manual',
            'fuel_price' => '54.99',
        ]);
    }

    private function expectViolation(string $messageFragment): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage($messageFragment);
    }

    private function user(array $attributes = []): int
    {
        return DB::table('users')->insertGetId($attributes + [
            'name' => 'Користувач',
            'email' => 'user'.uniqid().'@example.com',
            'password' => 'hash',
            'role' => 'admin',
        ]);
    }

    private function driver(): int
    {
        return $this->user(['role' => 'driver', 'phone' => '+380501234567']);
    }

    private function vehicle(array $attributes = []): int
    {
        return DB::table('vehicles')->insertGetId($attributes + [
            'name' => 'Ford Transit',
            'plate' => 'AA'.random_int(1000, 9999).'BB',
            'fuel_consumption_l_per_100km' => '9.50',
        ]);
    }

    private function store(array $attributes = []): int
    {
        return DB::table('stores')->insertGetId($attributes + [
            'name' => 'Магазин',
            'address' => 'Київ, Хрещатик, 1',
            'lat' => 50.45,
            'lng' => 30.52,
            'location_source' => 'geocoder',
        ]);
    }

    private function order(): int
    {
        return DB::table('orders')->insertGetId([
            'store_id' => $this->store(),
            'delivery_date' => '2026-11-02',
        ]);
    }

    private function route(array $attributes = []): int
    {
        return DB::table('routes')->insertGetId($attributes + [
            'date' => '2026-11-02',
            'driver_id' => $this->driver(),
            'vehicle_id' => $this->vehicle(),
            'return_to_warehouse' => false,
            'status' => 'draft',
            'distance_m' => 42000,
            'drive_s' => 3600,
            'total_s' => 5400,
            'fuel_l' => '3.99',
            'fuel_cost' => '219.41',
            'fuel_price_snapshot' => '54.99',
            'fuel_consumption_snapshot' => '9.50',
            'stop_minutes_snapshot' => 10,
            'geometry' => json_encode(['type' => 'LineString', 'coordinates' => [[30.52, 50.45], [30.60, 50.40]]]),
        ]);
    }

    private function started(): array
    {
        return ['started_at' => now(), 'started_received_at' => now()];
    }

    private function stop(int $route, array $attributes = []): int
    {
        $sequence = DB::table('route_stops')->where('route_id', $route)->max('sequence') + 1;

        return DB::table('route_stops')->insertGetId($attributes + [
            'route_id' => $route,
            'store_id' => $this->store(),
            'sequence' => $sequence,
            'leg_distance_m' => 1500,
            'leg_s' => 300,
            'arrival_offset_s' => 300,
        ]);
    }

    private function stopOrder(int $stop, int $order, string $status = 'pending', ?string $comment = null): int
    {
        return DB::table('route_stop_orders')->insertGetId([
            'route_stop_id' => $stop,
            'order_id' => $order,
            'status' => $status,
            'comment' => $comment,
        ]);
    }
}
