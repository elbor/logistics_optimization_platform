<?php

namespace App\Policies;

use App\Models\RouteStopOrder;
use App\Models\User;

/**
 * Результат замовлення змінює сервіс через «Виконано» / «Пропустити» зупинки (RouteStopPolicy).
 */
class RouteStopOrderPolicy
{
    public function view(User $user, RouteStopOrder $stopOrder): bool
    {
        return $user->isAdmin() || $stopOrder->stop->route->isVisibleToDriver($user);
    }
}
