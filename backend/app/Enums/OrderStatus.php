<?php

namespace App\Enums;

/** Значення збігаються з CHECK-обмеженням у базі (перевіряє StatusEnumsTest). */
enum OrderStatus: string
{
    case New = 'new';
    case Planned = 'planned';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __("enums.order_status.{$this->value}");
    }
}
