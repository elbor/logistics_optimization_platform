@php
    use Filament\Support\Facades\FilamentAsset;
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    {{-- wire:ignore: Livewire не перемальовує карту; стан синхронізує $entangle --}}
    <div
        wire:ignore
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('location-picker', 'app') }}"
        x-load-css="[@js(FilamentAsset::getStyleHref('maplibre-gl', 'app')), @js(FilamentAsset::getStyleHref('location-picker', 'app'))]"
        x-data="locationPicker({
            key: @js($getKey()),
            lat: $wire.$entangle(@js($getLatStatePath())),
            lng: $wire.$entangle(@js($getLngStatePath())),
            source: $wire.$entangle(@js($getSourceStatePath())),
            maplibreSrc: @js(FilamentAsset::getScriptSrc('maplibre-gl', 'app')),
            map: @js($getMapConfig()),
            labels: @js([
                'confirmReplace' => __('geocoding.confirm_replace'),
            ]),
        })"
        class="app-location-picker"
    >
        <div class="app-location-picker__toolbar">
            <x-filament::button
                type="button"
                icon="heroicon-o-map-pin"
                x-on:click="search()"
                x-bind:disabled="busy"
            >
                {{ __('geocoding.find_on_map') }}
            </x-filament::button>

            <x-filament::loading-indicator x-show="busy" x-cloak class="app-location-picker__spinner" />

            <span x-show="source === 'geocoder'" x-cloak class="app-location-picker__muted">
                {{ __('geocoding.source_geocoder') }}
            </span>
            <span x-show="source === 'manual'" x-cloak class="app-location-picker__muted">
                {{ __('geocoding.source_manual') }}
            </span>
        </div>

        <p x-show="message" x-text="message" x-cloak class="app-location-picker__error"></p>

        <div x-show="candidates.length" x-cloak class="app-location-picker__candidates">
            <p class="app-location-picker__candidates-title">{{ __('geocoding.choose_result') }}</p>
            <template x-for="(candidate, index) in candidates" :key="index">
                <button
                    type="button"
                    x-on:click="choose(index)"
                    x-text="candidate.label"
                    class="app-location-picker__candidate"
                ></button>
            </template>
        </div>

        <div x-ref="map" class="app-location-picker__map"></div>

        <p class="app-location-picker__muted">{{ __('geocoding.drag_hint') }}</p>
    </div>

    {{-- Поза wire:ignore, щоб помилка валідації прихованих координат з'являлась після збереження --}}
    @error($getLatStatePath())
        <p class="fi-fo-field-wrp-error-message">{{ $message }}</p>
    @enderror
</x-dynamic-component>
