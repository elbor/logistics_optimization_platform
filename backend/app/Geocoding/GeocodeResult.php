<?php

namespace App\Geocoding;

final readonly class GeocodeResult
{
    public function __construct(
        public float $lat,
        public float $lng,
        /** Повна адреса, як її повернув геокодер, — для вибору серед кількох збігів */
        public string $label,
    ) {}

    /**
     * @return array{lat: float, lng: float, label: string}
     */
    public function toArray(): array
    {
        return ['lat' => $this->lat, 'lng' => $this->lng, 'label' => $this->label];
    }

    /**
     * @param  array{lat: float, lng: float, label: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['lat'], $data['lng'], $data['label']);
    }
}
