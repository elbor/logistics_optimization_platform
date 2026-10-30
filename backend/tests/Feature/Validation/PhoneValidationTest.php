<?php

namespace Tests\Feature\Validation;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Правило валідації для форм і запитів API: неправильний номер — помилка валідації, а не 500 з касту.
 */
class PhoneValidationTest extends TestCase
{
    public static function acceptedNumbers(): array
    {
        return [
            'UA local' => ['050 123 45 67', '+380501234567'],
            'UA international' => ['+380 67 765 43 21', '+380677654321'],
            'GB local' => ['07400 123456', '+447400123456'],
            'GB international' => ['+44 7400 123456', '+447400123456'],
        ];
    }

    #[DataProvider('acceptedNumbers')]
    public function test_rule_accepts_number_and_cast_stores_it(string $input, string $stored): void
    {
        $validator = Validator::make(['phone' => $input], ['phone' => Phone::rules()]);

        $this->assertTrue($validator->passes());
        $this->assertSame($stored, (new User(['phone' => $input]))->phone);
    }

    public static function rejectedNumbers(): array
    {
        return [
            'too short' => ['12345'],
            'letters' => ['телефон'],
            'other country' => ['+48 512 345 678'],
        ];
    }

    #[DataProvider('rejectedNumbers')]
    public function test_rule_rejects_number_with_validation_error(string $input): void
    {
        app()->setLocale('uk');

        $validator = Validator::make(['phone' => $input], ['phone' => Phone::rules()]);

        $this->assertTrue($validator->fails());
        $this->assertSame('Неправильний номер телефону.', $validator->errors()->first('phone'));
    }

    public function test_empty_phone_passes_when_optional(): void
    {
        $validator = Validator::make(['phone' => null], ['phone' => ['nullable', ...Phone::rules()]]);

        $this->assertTrue($validator->passes());
    }
}
