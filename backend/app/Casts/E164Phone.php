<?php

namespace App\Casts;

use App\Support\Phone;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * Зберігає телефон у E.164. Номер з «+» зберігає свою країну, місцевий розбирається за Phone::COUNTRIES.
 * Повертає рядок, а не PhoneNumber, щоб форми й JSON працювали без перетворень.
 * Запобіжник: неправильний номер має відсіювати валідація (Phone::rules()) ще до моделі.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class E164Phone implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $phone = new PhoneNumber(trim($value), Phone::COUNTRIES);

        if (! $phone->isValid()) {
            throw new InvalidArgumentException("Invalid phone number in [{$key}].");
        }

        return $phone->formatE164();
    }
}
