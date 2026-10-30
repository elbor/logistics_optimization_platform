<?php

namespace App\Enums;

/** Значення збігаються з CHECK-обмеженням у базі (перевіряє StatusEnumsTest). */
enum UserRole: string
{
    case Admin = 'admin';
    case Driver = 'driver';

    public function label(): string
    {
        return __("enums.user_role.{$this->value}");
    }
}
