<?php

namespace App\Geocoding;

/**
 * Адреса → координати. Реалізацію можна замінити (власний Nominatim, комерційний сервіс)
 * без змін у формах: вони залежать лише від цього інтерфейсу.
 */
interface Geocoder
{
    /**
     * @return list<GeocodeResult> найкращі збіги, від найімовірнішого; порожній — нічого не знайдено
     *
     * @throws GeocodingException сервіс недоступний, зайнятий або не налаштований
     */
    public function search(string $query): array;
}
