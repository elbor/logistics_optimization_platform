<?php

namespace App\Geocoding;

use RuntimeException;

/**
 * Повідомлення — для адміна в інтерфейсі; технічна причина — у логах і в previous.
 */
class GeocodingException extends RuntimeException
{
    public static function unavailable(?\Throwable $previous = null): self
    {
        return new self(__('geocoding.unavailable'), previous: $previous);
    }

    public static function busy(): self
    {
        return new self(__('geocoding.busy'));
    }

    public static function notConfigured(): self
    {
        return new self(__('geocoding.not_configured'));
    }
}
