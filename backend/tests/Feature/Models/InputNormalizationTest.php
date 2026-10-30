<?php

namespace Tests\Feature\Models;

use App\Models\Order;
use App\Models\RouteStopOrder;
use App\Models\Store;
use App\Models\User;
use App\Models\Vehicle;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Нормалізація введення в моделях: база відкидає ненормалізовані значення через CHECK,
 * тож форми й імпорт мають отримувати вже нормалізовані дані з моделі.
 */
class InputNormalizationTest extends TestCase
{
    public function test_email_is_trimmed_and_lowercased(): void
    {
        $user = new User(['email' => '  Ivan.Petrenko@Example.COM ']);

        $this->assertSame('ivan.petrenko@example.com', $user->email);
    }

    public function test_local_ukrainian_phone_becomes_e164(): void
    {
        $user = new User(['phone' => '050 123 45 67']);

        $this->assertSame('+380501234567', $user->phone);
    }

    public function test_international_phone_keeps_its_country(): void
    {
        $user = new User(['phone' => '+44 7911 123456']);

        $this->assertSame('+447911123456', $user->phone);
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new User(['phone' => '12345']);
    }

    public function test_blank_phone_becomes_null(): void
    {
        $store = new Store(['contact_phone' => '  ']);

        $this->assertNull($store->contact_phone);
    }

    public function test_store_contact_phone_is_normalized(): void
    {
        $store = new Store(['contact_phone' => '(067) 765-43-21']);

        $this->assertSame('+380677654321', $store->contact_phone);
    }

    public function test_plate_is_uppercased_without_spaces(): void
    {
        $vehicle = new Vehicle(['plate' => ' аа 1234 вв ']);

        $this->assertSame('АА1234ВВ', $vehicle->plate);
    }

    public function test_blank_optional_text_becomes_null(): void
    {
        $store = new Store(['comment' => '   ', 'contact_name' => '', 'external_id_1c' => ' ']);

        $this->assertNull($store->comment);
        $this->assertNull($store->contact_name);
        $this->assertNull($store->external_id_1c);
    }

    public function test_optional_text_is_trimmed(): void
    {
        $order = new Order(['comment' => "  вхід з двору \n"]);

        $this->assertSame('вхід з двору', $order->comment);
    }

    public function test_blank_delivery_comment_becomes_null(): void
    {
        $stopOrder = new RouteStopOrder(['comment' => ' ']);

        $this->assertNull($stopOrder->comment);
    }
}
