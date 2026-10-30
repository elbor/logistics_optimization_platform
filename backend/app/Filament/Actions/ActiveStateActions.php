<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Деактивація замість видалення (машини, водії): запис з маршрутами видалити не можна,
 * тож його вимикають. is_active присвоюється явно — у User він не масово присвоюваний.
 */
final class ActiveStateActions
{
    /**
     * @param  string  $ability  здатність політики, яка дозволяє деактивацію
     */
    public static function deactivate(string $ability, string $description): Action
    {
        return Action::make('deactivate')
            ->label(__('admin.deactivate'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription($description)
            ->visible(fn (Model $record): bool => $record->is_active)
            ->authorize($ability)
            ->successNotificationTitle(__('admin.deactivated'))
            ->action(function (Model $record, Action $action): void {
                $record->is_active = false;
                $record->save();
                $action->success();
            });
    }

    public static function activate(): Action
    {
        return Action::make('activate')
            ->label(__('admin.activate'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Model $record): bool => ! $record->is_active)
            ->authorize('update')
            ->successNotificationTitle(__('admin.activated'))
            ->action(function (Model $record, Action $action): void {
                $record->is_active = true;
                $record->save();
                $action->success();
            });
    }
}
