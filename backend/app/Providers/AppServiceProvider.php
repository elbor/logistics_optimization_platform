<?php

namespace App\Providers;

use App\Geocoding\Geocoder;
use App\Geocoding\NominatimGeocoder;
use App\Models\User;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
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
        $this->app->bind(Geocoder::class, fn () => NominatimGeocoder::fromConfig(config('services.nominatim')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Поза продакшеном: помилка на ліниве завантаження в циклі (N+1),
        // мовчки відкинутий атрибут і звернення до невибраної колонки
        Model::shouldBeStrict(! $this->app->isProduction());

        // Карта в адмінці без npm на хості: готові файли з resources/, публікує `filament:assets`
        FilamentAsset::register([
            Js::make('maplibre-gl', resource_path('vendor/maplibre-gl/maplibre-gl.js'))->loadedOnRequest(),
            Css::make('maplibre-gl', resource_path('vendor/maplibre-gl/maplibre-gl.css'))->loadedOnRequest(),
            Css::make('location-picker', resource_path('css/filament/location-picker.css'))->loadedOnRequest(),
            AlpineComponent::make('location-picker', resource_path('js/filament/location-picker.js')),
        ], package: 'app');

        // Неактивному користувачу заборонено все, навіть якщо сесія чи токен ще живі
        Gate::before(fn (User $user) => $user->is_active ? null : false);
    }
}
