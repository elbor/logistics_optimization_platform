<?php

namespace App\Enums;

/** Значення збігаються з CHECK-обмеженням у базі (перевіряє StatusEnumsTest). */
enum RouteStopOrderStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case NotDelivered = 'not_delivered';

    public function label(): string
    {
        return __("enums.route_stop_order_status.{$this->value}");
    }
}
