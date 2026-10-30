<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Водій бачить лише власний профіль (GET /api/driver/me). */
    public function view(User $user, User $model): bool
    {
        return $user->isAdmin() || $user->is($model);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Не можна деактивувати себе — інакше останній адмін заблокує сам себе.
     */
    public function deactivate(User $user, User $model): bool
    {
        return $user->isAdmin() && ! $user->is($model);
    }

    /**
     * Не можна видалити себе; водій з маршрутами не видаляється (FK RESTRICT) — його деактивують.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin()
            && ! $user->is($model)
            && ! $model->driverRoutes()->exists();
    }
}
