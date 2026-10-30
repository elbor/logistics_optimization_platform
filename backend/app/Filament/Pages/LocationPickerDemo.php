<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\LocationPicker;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Тимчасова сторінка для перевірки поля «адреса → координати» в браузері,
 * поки немає ресурсів магазинів і налаштувань. Нічого не зберігає; поза продакшеном.
 * Видалити разом із тестом, коли поле з'явиться у формі магазину.
 */
class LocationPickerDemo extends Page
{
    protected string $view = 'filament.pages.location-picker-demo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'location-picker-demo';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return ! app()->isProduction() && parent::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return 'Демо: карта';
    }

    public function getTitle(): string
    {
        return 'Демо: адреса → координати';
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(LocationPicker::fields())
            ->statePath('data');
    }

    public function check(): void
    {
        $data = $this->form->getState();

        Notification::make()
            ->success()
            ->title('Форма валідна (нічого не збережено)')
            ->body("{$data['address']}: {$data['lat']}, {$data['lng']} ({$data['location_source']})")
            ->send();
    }
}
