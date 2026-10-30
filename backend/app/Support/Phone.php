<?php

namespace App\Support;

/**
 * Країни телефонних номерів — спільні для валідації і касту E164Phone,
 * щоб номер, який пройшов валідацію, завжди можна було зберегти.
 */
final class Phone
{
    /** Місцевий формат спершу розбирається як український, потім як британський (тести в UK). */
    public const COUNTRIES = ['UA', 'GB'];

    /**
     * Правило валідації для форм і запитів API.
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['phone:'.implode(',', self::COUNTRIES)];
    }
}
