<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\RouteStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Водій бачить замовлення, лише якщо воно є в його маршруті (не чернетці).
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isDriver() && $order->routeStopOrders()
            ->whereHas('stop.route', fn (Builder $route) => $route
                ->where('driver_id', $user->id)
                ->where('status', '!=', RouteStatus::Draft))
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Замовлення в маршруті чи доставлене не змінюється, інакше розійдеться з маршрутом.
     */
    public function update(User $user, Order $order): bool
    {
        return $user->isAdmin() && $order->status === OrderStatus::New;
    }

    public function cancel(User $user, Order $order): bool
    {
        return $user->isAdmin() && $order->status === OrderStatus::New;
    }

    /**
     * Замовлення з історією в маршрутах не видаляється (FK RESTRICT) — його скасовують.
     */
    public function delete(User $user, Order $order): bool
    {
        return $user->isAdmin()
            && $order->status === OrderStatus::New
            && ! $order->routeStopOrders()->exists();
    }
}
