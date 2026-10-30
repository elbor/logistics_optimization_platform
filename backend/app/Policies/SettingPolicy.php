<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;

/**
 * Налаштування — один рядок: його створюють і змінюють, але не видаляють.
 */
class SettingPolicy
{
    public function view(User $user, Setting $setting): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Setting $setting): bool
    {
        return $user->isAdmin();
    }
}
