<?php

namespace App\Filament\Resources\Drivers;

use App\Enums\UserRole;
use App\Filament\Actions\ActiveStateActions;
use App\Filament\Resources\Drivers\Pages\ManageDrivers;
use App\Models\User;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\Action;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;

/**
 * Водії — користувачі з роллю driver. Адміни сюди не потрапляють (і себе тут не деактивувати).
 */
class DriverResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'drivers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('drivers.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('drivers.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('drivers.navigation');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', UserRole::Driver);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('drivers.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('drivers.email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    // Унікальність — за email у нижньому регістрі, як його збереже модель
                    ->mutateStateForValidationUsing(fn (?string $state) => $state === null ? null : User::normalizeEmail($state))
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => __('drivers.email_taken')]),
                TextInput::make('phone')
                    ->label(__('drivers.phone'))
                    ->helperText(__('drivers.phone_hint'))
                    ->tel()
                    ->required()
                    ->rules(Phone::rules()),
                TextInput::make('password')
                    ->label(__('drivers.password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(Password::default())
                    ->confirmed()
                    ->visibleOn('create'),
                TextInput::make('password_confirmation')
                    ->label(__('drivers.password_confirmation'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->dehydrated(false)
                    ->visibleOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('drivers.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('drivers.email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('drivers.phone'))
                    ->searchable(),
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
                    self::changePasswordAction(),
                    ActiveStateActions::deactivate('deactivate', __('drivers.deactivate_description')),
                    ActiveStateActions::activate(),
                    // Політика ховає видалення, якщо у водія є маршрути
                    DeleteAction::make(),
                ]),
            ]);
    }

    /**
     * Новий пароль виводить водія з усіх пристроїв: зміна пароля зазвичай означає,
     * що старий відомий комусь іще (загублений телефон).
     */
    private static function changePasswordAction(): Action
    {
        return Action::make('changePassword')
            ->label(__('drivers.change_password'))
            ->icon(Heroicon::OutlinedKey)
            ->modalDescription(__('drivers.change_password_description'))
            ->authorize('update')
            ->schema([
                TextInput::make('password')
                    ->label(__('drivers.password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(Password::default())
                    ->confirmed(),
                TextInput::make('password_confirmation')
                    ->label(__('drivers.password_confirmation'))
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->successNotificationTitle(__('drivers.password_changed'))
            ->action(function (User $record, array $data, Action $action): void {
                $record->password = $data['password'];
                $record->save();
                $record->tokens()->delete();
                $action->success();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDrivers::route('/'),
        ];
    }
}
