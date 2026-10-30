<?php

namespace App\Filament\Resources\Vehicles;

use App\Filament\Actions\ActiveStateActions;
use App\Filament\Resources\Vehicles\Pages\ManageVehicles;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('vehicles.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('vehicles.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('vehicles.navigation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('vehicles.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('plate')
                    ->label(__('vehicles.plate'))
                    ->helperText(__('vehicles.plate_hint'))
                    ->required()
                    ->maxLength(20)
                    // Унікальність — за нормалізованим номером, як його збереже модель
                    ->mutateStateForValidationUsing(fn (?string $state) => $state === null ? null : Vehicle::normalizePlate($state))
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => __('vehicles.plate_taken')]),
                TextInput::make('fuel_consumption_l_per_100km')
                    ->label(__('vehicles.fuel_consumption'))
                    ->suffix(__('vehicles.fuel_unit'))
                    ->required()
                    ->numeric()
                    // Як CHECK vehicles_fuel_consumption_check; не ->gt()/->lt() — ті порівнюють з іншим полем
                    ->rules(['gt:0', 'lt:100'])
                    ->step(0.01),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('vehicles.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plate')
                    ->label(__('vehicles.plate'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('fuel_consumption_l_per_100km')
                    ->label(__('vehicles.fuel_consumption'))
                    ->suffix(' '.__('vehicles.fuel_unit'))
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('admin.is_active'))
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.filter_active'))
                    ->trueLabel(__('admin.filter_active_true'))
                    ->falseLabel(__('admin.filter_active_false'))
                    ->placeholder(__('admin.filter_active_all'))
                    ->default(true),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    ActiveStateActions::deactivate('update', __('vehicles.deactivate_description')),
                    ActiveStateActions::activate(),
                    // Політика ховає видалення, якщо в машини є маршрути
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVehicles::route('/'),
        ];
    }
}
