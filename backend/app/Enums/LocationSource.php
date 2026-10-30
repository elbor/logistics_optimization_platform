<?php

namespace App\Enums;

/** Значення збігаються з CHECK-обмеженням у базі (перевіряє StatusEnumsTest). */
enum LocationSource: string
{
    case Geocoder = 'geocoder';
    case Manual = 'manual';

    public function label(): string
    {
        return __("enums.location_source.{$this->value}");
    }
}
