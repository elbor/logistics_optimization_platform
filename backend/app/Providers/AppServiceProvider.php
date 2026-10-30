<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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
    }
}
