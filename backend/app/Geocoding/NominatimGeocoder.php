<?php

namespace App\Geocoding;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Геокодування через Nominatim за правилами публічного сервера
 * (https://operations.osmfoundation.org/policies/nominatim/):
 * не частіше 1 запиту/с на весь застосунок, власний User-Agent з контактом, кеш результатів.
 * Автодоповнення немає: пошук викликається лише явною дією адміна.
 */
class NominatimGeocoder implements Geocoder
{
    /** Мінімальний проміжок між запитами — спільний для всіх процесів (через кеш). */
    public const INTERVAL_MS = 1000;

    /** Якщо черга довша, не чекаємо, а просимо спробувати пізніше. */
    public const MAX_WAIT_MS = 5000;

    public const CACHE_DAYS = 30;

    /** «Нічого не знайдено» теж кешуємо, але ненадовго: OSM доповнюється. */
    public const CACHE_EMPTY_DAYS = 1;

    private const LAST_SLOT_KEY = 'nominatim:last-request-at-ms';

    private const SLOT_LOCK = 'nominatim:slot';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $appName,
        private readonly ?string $contact,
        private readonly int $timeoutSeconds = 10,
    ) {}

    /**
     * @param  array{base_url: string, app_name: string, contact: ?string, timeout: int}  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self($config['base_url'], $config['app_name'], $config['contact'], $config['timeout']);
    }

    public function search(string $query): array
    {
        $query = self::normalizeQuery($query);
        $cacheKey = 'geocode:nominatim:'.sha1($query);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return array_map(GeocodeResult::fromArray(...), $cached);
        }

        if (blank($this->contact)) {
            Log::error('Nominatim: NOMINATIM_CONTACT is not set, request not sent');

            throw GeocodingException::notConfigured();
        }

        $this->waitForSlot();
        $results = $this->request($query);

        Cache::put(
            $cacheKey,
            array_map(fn (GeocodeResult $result) => $result->toArray(), $results),
            now()->addDays($results === [] ? self::CACHE_EMPTY_DAYS : self::CACHE_DAYS),
        );

        return $results;
    }

    public function userAgent(): string
    {
        return "{$this->appName} ({$this->contact})";
    }

    /**
     * Однаковий запит з іншим регістром чи пробілами — той самий запис кешу.
     */
    public static function normalizeQuery(string $query): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $query)));
    }

    /**
     * Резервує наступний вільний момент (останній + 1 с) під коротким замком і чекає до нього.
     * Замок не тримається під час HTTP-запиту, тож повільна відповідь не блокує інших.
     */
    private function waitForSlot(): void
    {
        try {
            $slotMs = Cache::lock(self::SLOT_LOCK, 5)->block(5, function (): ?int {
                $nowMs = (int) now()->getPreciseTimestamp(3);
                $slotMs = max($nowMs, (int) Cache::get(self::LAST_SLOT_KEY, 0) + self::INTERVAL_MS);

                if ($slotMs - $nowMs > self::MAX_WAIT_MS) {
                    return null;
                }

                Cache::put(self::LAST_SLOT_KEY, $slotMs, 60);

                return $slotMs;
            });
        } catch (LockTimeoutException) {
            $slotMs = null;
        }

        if ($slotMs === null) {
            throw GeocodingException::busy();
        }

        $waitMs = $slotMs - (int) now()->getPreciseTimestamp(3);
        if ($waitMs > 0) {
            Sleep::usleep($waitMs * 1000);
        }
    }

    /**
     * @return list<GeocodeResult>
     */
    private function request(string $query): array
    {
        try {
            $items = Http::baseUrl($this->baseUrl)
                ->withUserAgent($this->userAgent())
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->get('/search', [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 5,
                    'countrycodes' => 'ua,gb',
                    'accept-language' => 'uk',
                ])
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $e) {
            Log::warning('Nominatim request failed', ['error' => $e->getMessage()]);

            throw GeocodingException::unavailable($e);
        }

        if (! is_array($items)) {
            Log::warning('Nominatim returned non-list JSON');

            throw GeocodingException::unavailable();
        }

        $results = [];
        foreach ($items as $item) {
            if (is_numeric($item['lat'] ?? null) && is_numeric($item['lon'] ?? null)) {
                $results[] = new GeocodeResult((float) $item['lat'], (float) $item['lon'], (string) ($item['display_name'] ?? ''));
            }
        }

        return $results;
    }
}
