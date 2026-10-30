<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Поза продакшеном: помилка на ліниве завантаження в циклі (N+1),
        // мовчки відкинутий атрибут і звернення до невибраної колонки
        Model::shouldBeStrict(! $this->app->isProduction());

        // Неактивному користувачу заборонено все, навіть якщо сесія чи токен ще живі
        Gate::before(fn (User $user) => $user->is_active ? null : false);
    }
}
