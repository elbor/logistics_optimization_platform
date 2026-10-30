<?php

namespace App\Policies;

use App\Models\RouteStop;
use App\Models\User;

/**
 * Зупинки створює й видаляє лише сервіс маршрутів (перебудова), тож create/update/delete не визначені — заборонено.
 */
class RouteStopPolicy
{
    public function view(User $user, RouteStop $stop): bool
    {
        return $user->isAdmin() || $stop->route->isVisibleToDriver($user);
    }

    public function complete(User $user, RouteStop $stop): bool
    {
        return $user->isDriver() && $stop->route->isVisibleToDriver($user);
    }

    public function skip(User $user, RouteStop $stop): bool
    {
        return $user->isDriver() && $stop->route->isVisibleToDriver($user);
    }
}
