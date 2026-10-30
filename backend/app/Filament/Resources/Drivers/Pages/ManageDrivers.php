<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Drivers\DriverResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDrivers extends ManageRecords
{
    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                // role не масово присвоюваний: ресурс створює лише водіїв
                ->using(function (array $data): User {
                    $driver = new User($data);
                    $driver->role = UserRole::Driver;
                    $driver->save();

                    return $driver;
                }),
        ];
    }
}
