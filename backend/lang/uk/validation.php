<?php

return [
    'confirmed' => 'Поле «:attribute» не збігається з підтвердженням.',
    'email' => 'Поле «:attribute» має містити правильну адресу електронної пошти.',
    'gt' => [
        'numeric' => 'Поле «:attribute» має бути більшим за :value.',
    ],
    'lt' => [
        'numeric' => 'Поле «:attribute» має бути меншим за :value.',
    ],
    'max' => [
        'string' => 'Поле «:attribute» не може бути довшим за :max символів.',
    ],
    'min' => [
        'string' => 'Поле «:attribute» має містити щонайменше :min символів.',
    ],
    'numeric' => 'Поле «:attribute» має бути числом.',
    'phone' => 'Неправильний номер телефону.',
    'required' => 'Поле «:attribute» обов\'язкове.',
    'string' => 'Поле «:attribute» має бути текстом.',
    'unique' => 'Таке значення поля «:attribute» уже є.',

    // З малої літери, як назви полів у формах Filament
    'attributes' => [
        'email' => 'електронна пошта',
        'password' => 'пароль',
        'device_name' => 'назва пристрою',
    ],
];
