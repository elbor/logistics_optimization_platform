<?php

namespace App\Filament\Forms\Components;

use App\Enums\LocationSource;
use App\Geocoding\Geocoder;
use App\Geocoding\GeocodeResult;
use App\Geocoding\GeocodingException;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Support\Components\Attributes\Exposed;
use Livewire\Attributes\Renderless;

/**
 * Карта з пересувним піном для поля «адреса → координати» (магазини, склад у налаштуваннях).
 *
 * Саме поле нічого не зберігає: координати й джерело живуть у прихованих сусідніх полях,
 * назви яких задаються, бо в налаштуваннях вони з префіксом (warehouse_lat…).
 * Пошук — лише кнопкою (правила Nominatim забороняють автодоповнення).
 * Рішення «чи можна замінити ручний пін» ухвалює сервер, а не JS.
 */
class LocationPicker extends Field
{
    protected string $view = 'filament.forms.components.location-picker';

    protected string $addressField = 'address';

    protected string $latField = 'lat';

    protected string $lngField = 'lng';

    protected string $sourceField = 'location_source';

    /**
     * Адреса, карта і приховані координати разом — так їх і вставляють у форму.
     *
     * @return array<Component>
     */
    public static function fields(
        string $address = 'address',
        string $lat = 'lat',
        string $lng = 'lng',
        string $source = 'location_source',
        ?string $addressLabel = null,
    ): array {
        return [
            // Без ->live(): введення адреси не викликає геокодер
            TextInput::make($address)
                ->label($addressLabel ?? __('geocoding.address'))
                ->required()
                ->maxLength(500),
            static::make("{$address}_map")
                ->hiddenLabel()
                ->dehydrated(false)
                ->fieldNames($address, $lat, $lng, $source),
            Hidden::make($lat)
                ->required()
                ->rules(['numeric', 'between:-90,90'])
                ->validationMessages(['required' => __('geocoding.pin_required')]),
            Hidden::make($lng)
                ->required()
                ->rules(['numeric', 'between:-180,180'])
                ->validationMessages(['required' => __('geocoding.pin_required')]),
            Hidden::make($source)
                ->required()
                ->in(array_column(LocationSource::cases(), 'value'))
                // Із запису приходить enum, а Alpine працює з рядком
                ->formatStateUsing(fn ($state) => $state instanceof LocationSource ? $state->value : $state),
        ];
    }

    public function fieldNames(string $address, string $lat, string $lng, string $source): static
    {
        $this->addressField = $address;
        $this->latField = $lat;
        $this->lngField = $lng;
        $this->sourceField = $source;

        return $this;
    }

    /**
     * «Знайти на карті». Ручний пін без $replaceManual не чіпає — клієнт спершу питає підтвердження.
     *
     * @return array{status: string, message?: string, lat?: float, lng?: float, candidates?: list<array{lat: float, lng: float, label: string}>}
     */
    #[Exposed]
    #[Renderless]
    public function search(bool $replaceManual = false): array
    {
        return $this->geocode($replaceManual, function (array $results): array {
            if (count($results) === 1) {
                return $this->apply($results[0]);
            }

            return [
                'status' => 'choose',
                'candidates' => array_map(fn (GeocodeResult $result) => $result->toArray(), $results),
            ];
        });
    }

    /**
     * Вибір одного з кількох збігів. Повторний пошук бере результат з кешу, без запиту до Nominatim,
     * тож клієнт передає лише номер, а не координати.
     *
     * @return array{status: string, message?: string, lat?: float, lng?: float}
     */
    #[Exposed]
    #[Renderless]
    public function choose(int $index, bool $replaceManual = false): array
    {
        return $this->geocode($replaceManual, fn (array $results): array => isset($results[$index])
            ? $this->apply($results[$index])
            : ['status' => 'error', 'message' => __('geocoding.not_found')]);
    }

    /**
     * @param  \Closure(non-empty-list<GeocodeResult>): array  $onResults
     */
    private function geocode(bool $replaceManual, \Closure $onResults): array
    {
        $get = $this->makeGetUtility();
        $address = trim((string) $get($this->addressField));

        if ($address === '') {
            return ['status' => 'error', 'message' => __('geocoding.address_required')];
        }

        if (! $replaceManual && $this->currentSource() === LocationSource::Manual) {
            return ['status' => 'confirm_replace'];
        }

        try {
            $results = app(Geocoder::class)->search($address);
        } catch (GeocodingException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        if ($results === []) {
            return ['status' => 'not_found', 'message' => __('geocoding.not_found')];
        }

        return $onResults($results);
    }

    /**
     * @return array{status: string, lat: float, lng: float}
     */
    private function apply(GeocodeResult $result): array
    {
        $set = $this->makeSetUtility();
        $set($this->latField, $result->lat);
        $set($this->lngField, $result->lng);
        $set($this->sourceField, LocationSource::Geocoder->value);

        return ['status' => 'found', 'lat' => $result->lat, 'lng' => $result->lng];
    }

    private function currentSource(): ?LocationSource
    {
        $source = $this->makeGetUtility()($this->sourceField);

        return $source instanceof LocationSource ? $source : LocationSource::tryFrom((string) $source);
    }

    public function getLatStatePath(): string
    {
        return $this->resolveRelativeStatePath($this->latField);
    }

    public function getLngStatePath(): string
    {
        return $this->resolveRelativeStatePath($this->lngField);
    }

    public function getSourceStatePath(): string
    {
        return $this->resolveRelativeStatePath($this->sourceField);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMapConfig(): array
    {
        return [
            'styleUrl' => config('maps.style_url'),
            'center' => config('maps.default_center'),
            'zoom' => config('maps.default_zoom'),
            'pointZoom' => config('maps.point_zoom'),
            'attribution' => config('maps.attribution'),
        ];
    }
}
