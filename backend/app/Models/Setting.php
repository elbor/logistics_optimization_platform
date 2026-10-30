<?php

namespace App\Models;

use App\Enums\LocationSource;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Налаштування компанії — рівно один рядок з id = 1 (CHECK у базі).
 */
#[Table(incrementing: false)]
#[Fillable([
    'warehouse_address', 'warehouse_lat', 'warehouse_lng', 'warehouse_location_source',
    'fuel_price', 'default_stop_minutes',
])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    protected $attributes = [
        'id' => 1,
    ];

    protected function casts(): array
    {
        return [
            'warehouse_lat' => 'float',
            'warehouse_lng' => 'float',
            'warehouse_location_source' => LocationSource::class,
            'fuel_price' => 'decimal:2',
            'default_stop_minutes' => 'integer',
        ];
    }

    /**
     * null — налаштування ще не збережені.
     */
    public static function current(): ?self
    {
        return static::find(1);
    }
}
