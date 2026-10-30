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
     * Номер — верхній регістр без пробілів (так само перевіряє CHECK у базі).
     */
    protected function plate(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtoupper(preg_replace('/\s+/u', '', $value)),
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
