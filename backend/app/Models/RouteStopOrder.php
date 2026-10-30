<?php

namespace App\Models;

use App\Casts\NullableText;
use App\Enums\RouteStopOrderStatus;
use Database\Factories\RouteStopOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Замовлення в зупинці та його результат. status змінює лише сервіс маршрутів.
 */
#[Fillable(['order_id', 'comment'])]
class RouteStopOrder extends Model
{
    /** @use HasFactory<RouteStopOrderFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => RouteStopOrderStatus::class,
            'comment' => NullableText::class,
        ];
    }

    /**
     * @return BelongsTo<RouteStop, $this>
     */
    public function stop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class, 'route_stop_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
