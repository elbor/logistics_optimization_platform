<?php

namespace App\Policies;

use App\Models\DeliveryRoute;
use App\Models\User;

/**
 * Політика вирішує, ХТО може діяти. Чи дозволяє дію поточний стан маршруту (старт лише з assigned,
 * завершення без незакритих зупинок) — перевіряє сервіс маршрутів, щоб повтор після офлайну був ідемпотентним.
 * Виняток — редагування й видалення адміном: вони залежать від стану й керують кнопками Filament.
 */
class DeliveryRoutePolicy
{
    /** Список водія обмежує скоуп DeliveryRoute::visibleToDriver(). */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isDriver();
    }

    public function view(User $user, DeliveryRoute $route): bool
    {
        return $user->isAdmin() || $route->isVisibleToDriver($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, DeliveryRoute $route): bool
    {
        return $user->isAdmin() && $route->isEditable();
    }

    public function delete(User $user, DeliveryRoute $route): bool
    {
        return $user->isAdmin() && $route->isEditable();
    }

    public function start(User $user, DeliveryRoute $route): bool
    {
        return $user->isDriver() && $route->isVisibleToDriver($user);
    }

    public function finish(User $user, DeliveryRoute $route): bool
    {
        return $user->isDriver() && $route->isVisibleToDriver($user);
    }
}
