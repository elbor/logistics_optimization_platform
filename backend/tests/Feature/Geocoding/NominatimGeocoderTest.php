<?php

namespace Tests\Feature\Geocoding;

use App\Geocoding\Geocoder;
use App\Geocoding\GeocodeResult;
use App\Geocoding\GeocodingException;
use App\Geocoding\NominatimGeocoder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Nominatim за правилами публічного сервера: 1 запит/с на весь застосунок, User-Agent з контактом, кеш.
 */
class NominatimGeocoderTest extends TestCase
{
    private const SEARCH_URL = 'https://nominatim.test/search*';

    /** @var list<int> */
    private array $requestTimesMs = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.nominatim' => [
            'base_url' => 'https://nominatim.test',
            'app_name' => 'TeaCoffeeLogistics',
            'contact' => 'ops@example.com',
            'timeout' => 10,
        ]]);

        Sleep::fake(syncWithCarbon: true);
        Carbon::setTestNow('2026-10-30 12:00:00.000');
    }

    public function test_sends_policy_compliant_request_and_parses_results(): void
    {
        Http::fake([self::SEARCH_URL => Http::response([
            ['lat' => '50.4501', 'lon' => '30.5234', 'display_name' => 'Хрещатик, 1, Київ'],
            ['lat' => '49.8397', 'lon' => '24.0297', 'display_name' => 'Хрещатик, Львів'],
        ])]);

        $results = $this->geocoder()->search('  Хрещатик   1 ');

        $this->assertEquals([
            new GeocodeResult(50.4501, 30.5234, 'Хрещатик, 1, Київ'),
            new GeocodeResult(49.8397, 24.0297, 'Хрещатик, Львів'),
        ], $results);

        Http::assertSent(function (Request $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://nominatim.test/search?')
                && $request->hasHeader('User-Agent', 'TeaCoffeeLogistics (ops@example.com)')
                && $query === [
                    'q' => 'хрещатик 1',
                    'format' => 'jsonv2',
                    'limit' => '5',
                    'countrycodes' => 'ua,gb',
                    'accept-language' => 'uk',
                ];
        });
    }

    public function test_container_resolves_nominatim_from_config(): void
    {
        $geocoder = app(Geocoder::class);

        $this->assertInstanceOf(NominatimGeocoder::class, $geocoder);
        $this->assertSame('TeaCoffeeLogistics (ops@example.com)', $geocoder->userAgent());
    }

    public function test_results_are_cached_by_normalized_query(): void
    {
        Http::fake([self::SEARCH_URL => Http::response([
            ['lat' => '50.45', 'lon' => '30.52', 'display_name' => 'Київ'],
        ])]);

        $first = $this->geocoder()->search('Київ, Хрещатик 1');
        $second = $this->geocoder()->search('  київ,   ХРЕЩАТИК 1');

        $this->assertEquals($first, $second);
        Http::assertSentCount(1);
    }

    public function test_empty_result_is_cached_for_a_day(): void
    {
        Http::fake([self::SEARCH_URL => Http::response([])]);

        $this->assertSame([], $this->geocoder()->search('Неіснуюча вулиця'));
        $this->assertSame([], $this->geocoder()->search('Неіснуюча вулиця'));
        Http::assertSentCount(1);

        $this->travel(NominatimGeocoder::CACHE_EMPTY_DAYS)->days();
        $this->travel(1)->minutes();
        $this->geocoder()->search('Неіснуюча вулиця');
        Http::assertSentCount(2);
    }

    public function test_requests_are_spaced_at_least_one_second_apart(): void
    {
        $this->fakeRecordingRequestTimes();
        $geocoder = $this->geocoder();

        $geocoder->search('Перша адреса');
        $this->travel(300)->milliseconds();
        $geocoder->search('Друга адреса');

        // Друга чекала решту секунди від першої
        Sleep::assertSequence([Sleep::usleep(700_000)]);
        $this->assertGapsBetweenRequestsAtLeast(1000);
    }

    public function test_limit_is_shared_by_all_geocoder_instances(): void
    {
        $this->fakeRecordingRequestTimes();

        // Як два різні запити адмінів: окремі об'єкти, спільний кеш
        $this->geocoder()->search('Перша адреса');
        $this->geocoder()->search('Друга адреса');
        $this->geocoder()->search('Третя адреса');

        Sleep::assertSequence([Sleep::usleep(1_000_000), Sleep::usleep(1_000_000)]);
        $this->assertGapsBetweenRequestsAtLeast(1000);
    }

    public function test_no_wait_after_a_quiet_second(): void
    {
        Http::fake([self::SEARCH_URL => Http::response([])]);

        $this->geocoder()->search('Перша адреса');
        $this->travel(1)->seconds();
        $this->geocoder()->search('Друга адреса');

        Sleep::assertNeverSlept();
    }

    public function test_cached_search_does_not_wait_for_a_slot(): void
    {
        Http::fake([self::SEARCH_URL => Http::response([])]);

        $this->geocoder()->search('Перша адреса');
        $this->geocoder()->search('Перша адреса');

        Sleep::assertNeverSlept();
    }

    public function test_long_queue_fails_fast_instead_of_waiting(): void
    {
        Http::fake();
        // Хтось уже зарезервував слот на 6 с уперед
        Cache::put('nominatim:last-request-at-ms', (int) now()->getPreciseTimestamp(3) + 6000, 60);

        $this->expectExceptionObject(GeocodingException::busy());

        try {
            $this->geocoder()->search('Київ');
        } finally {
            Http::assertNothingSent();
            Sleep::assertNeverSlept();
        }
    }

    public function test_server_errors_become_ukrainian_message_and_are_not_cached(): void
    {
        Http::fakeSequence(self::SEARCH_URL)
            ->push('Too Many Requests', 429)
            ->push([['lat' => '50.45', 'lon' => '30.52', 'display_name' => 'Київ']]);

        try {
            $this->geocoder()->search('Київ');
            $this->fail('Expected GeocodingException');
        } catch (GeocodingException $e) {
            $this->assertSame(__('geocoding.unavailable'), $e->getMessage());
            $this->assertStringStartsWith('Сервіс пошуку адрес', $e->getMessage());
        }

        $this->travel(1)->seconds();
        $this->assertCount(1, $this->geocoder()->search('Київ'));
    }

    public function test_connection_failure_becomes_geocoding_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectExceptionObject(GeocodingException::unavailable());

        $this->geocoder()->search('Київ');
    }

    public function test_without_contact_nothing_is_sent(): void
    {
        Http::fake();
        config(['services.nominatim.contact' => null]);

        try {
            $this->geocoder()->search('Київ');
            $this->fail('Expected GeocodingException');
        } catch (GeocodingException $e) {
            $this->assertSame(__('geocoding.not_configured'), $e->getMessage());
        }

        Http::assertNothingSent();
    }

    private function geocoder(): NominatimGeocoder
    {
        return NominatimGeocoder::fromConfig(config('services.nominatim'));
    }

    /**
     * Відповідає порожнім списком і запам'ятовує момент кожного запиту за підробленим годинником
     * (Sleep синхронізований з Carbon, тож очікування зсуває «now»).
     */
    private function fakeRecordingRequestTimes(): void
    {
        $this->requestTimesMs = [];
        Http::fake(function () {
            $this->requestTimesMs[] = (int) now()->getPreciseTimestamp(3);

            return Http::response([]);
        });
    }

    private function assertGapsBetweenRequestsAtLeast(int $milliseconds): void
    {
        $this->assertGreaterThanOrEqual(2, count($this->requestTimesMs));

        for ($i = 1; $i < count($this->requestTimesMs); $i++) {
            $this->assertGreaterThanOrEqual($milliseconds, $this->requestTimesMs[$i] - $this->requestTimesMs[$i - 1]);
        }
    }
}
