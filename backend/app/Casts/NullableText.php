<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Необов'язковий текст: обрізає пробіли, порожній рядок зберігає як NULL
 * (база відкидає '' через CHECK, а для UNIQUE-колонок два '' — це конфлікт).
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class NullableText implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }
}
