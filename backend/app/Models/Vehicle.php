<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'plate', 'fuel_consumption_l_per_100km', 'is_active'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fuel_consumption_l_per_100km' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Кириличні літери номерних знаків, що мають латинських двійників.
     */
    private const CYRILLIC_TO_LATIN = [
        'А' => 'A', 'В' => 'B', 'Е' => 'E', 'І' => 'I', 'К' => 'K', 'М' => 'M',
        'Н' => 'H', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Т' => 'T', 'Х' => 'X',
    ];

    /**
     * Номер — верхній регістр, без пробілів, латиницею: «АА1234ВВ» кирилицею і латиницею — один номер
     * (так само перевіряють CHECK-обмеження в базі).
     */
    protected function plate(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtr(mb_strtoupper(preg_replace('/\s+/u', '', $value)), self::CYRILLIC_TO_LATIN),
        );
    }

    /**
     * @return HasMany<DeliveryRoute, $this>
     */
    public function deliveryRoutes(): HasMany
    {
        return $this->hasMany(DeliveryRoute::class);
    }
}
