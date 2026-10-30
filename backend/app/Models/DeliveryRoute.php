<?php

namespace App\Models;

use App\Enums\RouteStatus;
use Database\Factories\DeliveryRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Маршрут доставки. Не `Route`, щоб не плутати з фасадом маршрутизації Laravel.
 * status і часові мітки старту/завершення змінює лише сервіс маршрутів.
 */
#[Table(name: 'routes')]
#[Fillable([
    'date', 'driver_id', 'vehicle_id', 'return_to_warehouse',
    'distance_m', 'drive_s', 'total_s', 'return_leg_distance_m', 'return_leg_s',
    'fuel_l', 'fuel_cost', 'fuel_price_snapshot', 'fuel_consumption_snapshot', 'stop_minutes_snapshot',
    'geometry',
])]
class DeliveryRoute extends Model
{
    /** @use HasFactory<DeliveryRouteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'status' => RouteStatus::class,
            'return_to_warehouse' => 'boolean',
            'distance_m' => 'integer',
            'drive_s' => 'integer',
            'total_s' => 'integer',
            'return_leg_distance_m' => 'integer',
            'return_leg_s' => 'integer',
            'fuel_l' => 'decimal:2',
            'fuel_cost' => 'decimal:2',
            'fuel_price_snapshot' => 'decimal:2',
            'fuel_consumption_snapshot' => 'decimal:2',
            'stop_minutes_snapshot' => 'integer',
            'geometry' => 'array',
            'started_at' => 'datetime',
            'started_received_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Адмін може змінювати й видаляти маршрут, поки водій його не почав (видалення так само блокує тригер у базі).
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [RouteStatus::Draft, RouteStatus::Assigned], true);
    }

    /**
     * Водій бачить свої маршрути, крім чернеток — чернетка ще робоча копія адміна.
     */
    public function isVisibleToDriver(User $driver): bool
    {
        return $this->driver_id === $driver->id && $this->status !== RouteStatus::Draft;
    }

    /**
     * Те саме правило, що й isVisibleToDriver(), для списків.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleToDriver(Builder $query, User $driver): void
    {
        $query->where('driver_id', $driver->id)->where('status', '!=', RouteStatus::Draft);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return HasMany<RouteStop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class, 'route_id')->orderBy('sequence');
    }
}
