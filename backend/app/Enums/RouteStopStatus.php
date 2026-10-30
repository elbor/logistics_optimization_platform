<?php

namespace App\Enums;

/** Значення збігаються з CHECK-обмеженням у базі (перевіряє StatusEnumsTest). */
enum RouteStopStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return __("enums.route_stop_status.{$this->value}");
    }
}
