<?php

namespace Database\Factories;

use App\Enums\LocationSource;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 1,
            'warehouse_address' => 'Київ, вул. Складська, 1',
            'warehouse_lat' => 50.45,
            'warehouse_lng' => 30.52,
            'warehouse_location_source' => LocationSource::Manual,
            'fuel_price' => '54.99',
            'default_stop_minutes' => 10,
        ];
    }
}
