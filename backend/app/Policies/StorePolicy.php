<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

/**
 * Дані магазину водій отримує у складі свого маршруту, окремого доступу до довідника не має.
 */
class StorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Store $store): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Архівація — теж оновлення. */
    public function update(User $user, Store $store): bool
    {
        return $user->isAdmin();
    }

    /**
     * Магазин із замовленнями чи зупинками не видаляється (FK RESTRICT) — його архівують.
     */
    public function delete(User $user, Store $store): bool
    {
        return $user->isAdmin()
            && ! $store->orders()->exists()
            && ! $store->routeStops()->exists();
    }
}
