<?php

namespace App\Models;

use App\Casts\E164Phone;
use App\Casts\NullableText;
use App\Enums\LocationSource;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'address', 'lat', 'lng', 'location_source',
    'contact_name', 'contact_phone', 'comment', 'external_id_1c',
])]
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    /** Як DEFAULT у базі, щоб щойно створена модель не мала null до refresh(). */
    protected $attributes = [
        'is_archived' => false,
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'location_source' => LocationSource::class,
            'contact_name' => NullableText::class,
            'contact_phone' => E164Phone::class,
            'comment' => NullableText::class,
            'external_id_1c' => NullableText::class,
            'is_archived' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<RouteStop, $this>
     */
    public function routeStops(): HasMany
    {
        return $this->hasMany(RouteStop::class);
    }
}
