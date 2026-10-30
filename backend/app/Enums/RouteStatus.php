<?php

namespace App\Enums;

/** Значення збігаються з CHECK-обмеженням у базі (перевіряє StatusEnumsTest). */
enum RouteStatus: string
{
    case Draft = 'draft';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return __("enums.route_status.{$this->value}");
    }
}
