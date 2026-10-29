# PHP + Composer для Laravel і Filament (лише для розробки)
FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

# Розширення: pdo_pgsql — PostgreSQL; intl, zip, gd, exif — потрібні Filament
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl zip bcmath pcntl gd exif

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/backend
