# MapLibre GL JS 5.6.0

Готові dist-файли з npm-пакета `maplibre-gl@5.6.0` — без збірки й без Node.js на хості.
Реєструються через `FilamentAsset` (`AppServiceProvider`), публікуються в `public/` командою `./bin/artisan filament:assets`
(її також запускає `composer install`).

Тарбол: https://registry.npmjs.org/maplibre-gl/-/maplibre-gl-5.6.0.tgz
Перевірено `dist.integrity` з реєстру npm: `sha512-7TuHMozUC4rlIp08bSsxCixFn18P24otrlZU/7UGCO5RufFTJadFzauTrvBHr9FB67MbJ6nvFXEftGd0bUl4Iw==`

Оновлення: завантажити тарбол нової версії, звірити sha512 з `dist.integrity`
(`openssl dgst -sha512 -binary pkg.tgz | openssl base64 -A`), скопіювати
`package/dist/maplibre-gl.js`, `package/dist/maplibre-gl.css`, `package/LICENSE.txt` і змінити версію тут.
