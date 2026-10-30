<?php

namespace App\Models;

use App\Casts\NullableText;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// status змінює лише сервіс маршрутів (синхронно з route_stop_orders)
#[Fillable(['store_id', 'delivery_date', 'comment', 'external_id_1c'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date:Y-m-d',
            'comment' => NullableText::class,
            'status' => OrderStatus::class,
            'external_id_1c' => NullableText::class,
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Історія: у яких маршрутах було замовлення і з яким результатом.
     *
     * @return HasMany<RouteStopOrder, $this>
     */
    public function routeStopOrders(): HasMany
    {
        return $this->hasMany(RouteStopOrder::class);
    }
}
