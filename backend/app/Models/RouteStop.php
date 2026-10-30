<?php

namespace App\Models;

use App\Enums\RouteStopStatus;
use Database\Factories\RouteStopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Зупинка = магазин. status і час закриття змінює лише сервіс маршрутів.
 */
#[Fillable(['store_id', 'sequence', 'leg_distance_m', 'leg_s', 'arrival_offset_s'])]
class RouteStop extends Model
{
    /** @use HasFactory<RouteStopFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'leg_distance_m' => 'integer',
            'leg_s' => 'integer',
            'arrival_offset_s' => 'integer',
            'status' => RouteStopStatus::class,
            'completed_at' => 'datetime',
            'completed_received_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DeliveryRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'route_id');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<RouteStopOrder, $this>
     */
    public function stopOrders(): HasMany
    {
        return $this->hasMany(RouteStopOrder::class);
    }
}
