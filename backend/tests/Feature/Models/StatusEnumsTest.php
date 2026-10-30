<?php

namespace Tests\Feature\Models;

use App\Enums\LocationSource;
use App\Enums\OrderStatus;
use App\Enums\RouteStatus;
use App\Enums\RouteStopOrderStatus;
use App\Enums\RouteStopStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StatusEnumsTest extends TestCase
{
    use RefreshDatabase;

    public static function enumsWithConstraints(): array
    {
        return [
            'user role' => [UserRole::class, 'users_role_check'],
            'store location source' => [LocationSource::class, 'stores_location_source_check'],
            'warehouse location source' => [LocationSource::class, 'settings_warehouse_location_source_check'],
            'order status' => [OrderStatus::class, 'orders_status_check'],
            'route status' => [RouteStatus::class, 'routes_status_check'],
            'route stop status' => [RouteStopStatus::class, 'route_stops_status_check'],
            'route stop order status' => [RouteStopOrderStatus::class, 'route_stop_orders_status_check'],
        ];
    }

    /**
     * PHP enum і CHECK у базі — два списки одних значень; тест не дає їм розійтися.
     *
     * @param  class-string<\BackedEnum>  $enum
     */
    #[DataProvider('enumsWithConstraints')]
    public function test_enum_matches_database_check(string $enum, string $constraint): void
    {
        $definition = DB::scalar('SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conname = ?', [$constraint]);
        preg_match_all("/'([a-z_]+)'::text/", (string) $definition, $matches);

        $databaseValues = $matches[1];
        $enumValues = array_column($enum::cases(), 'value');
        sort($databaseValues);
        sort($enumValues);

        $this->assertSame($databaseValues, $enumValues);
    }

    public static function enums(): array
    {
        return array_map(fn (array $set) => [$set[0]], self::enumsWithConstraints());
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     */
    #[DataProvider('enums')]
    public function test_every_case_has_ukrainian_label(string $enum): void
    {
        app()->setLocale('uk');

        foreach ($enum::cases() as $case) {
            $this->assertStringNotContainsString('enums.', $case->label(), "{$enum}::{$case->name} has no translation");
        }
    }
}
