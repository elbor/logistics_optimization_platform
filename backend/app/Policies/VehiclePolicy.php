<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->isAdmin();
    }

    /**
     * Машина з маршрутами не видаляється (FK RESTRICT) — її деактивують.
     */
    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->isAdmin() && ! $vehicle->deliveryRoutes()->exists();
    }
}
