# Dependencias PHP y Composer
FROM composer:2.8 AS vendor

WORKDIR /var/www/html

COPY composer.json composer.lock ./
COPY . .
RUN composer install \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-dev \
    --ignore-platform-req=ext-mongodb

# Compilación de los recursos frontend
FROM node:24-bookworm-slim AS assets

WORKDIR /var/www/html

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js .
RUN npm run build

# Imagen de ejecución de Laravel
FROM php:8.3-cli-bookworm

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
        libssl-dev \
        libzip-dev \
        libonig-dev \
        pkg-config \
        unzip \
    && docker-php-ext-install \
        bcmath \
        mbstring \
        pdo_sqlite \
        pdo_mysql \
        zip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=assets /var/www/html/public/build ./public/build
COPY . .
COPY --from=vendor /var/www/html/bootstrap/cache ./bootstrap/cache

RUN mkdir -p storage/framework/cache storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache /var/lib/melodic \
    && touch /var/lib/melodic/database.sqlite \
    && chown -R www-data:www-data storage bootstrap/cache /var/lib/melodic

USER www-data

EXPOSE 8000

CMD ["sh", "-c", "for attempt in $(seq 1 30); do php artisan migrate --force && break; echo \"Waiting for database (attempt $attempt/30)...\"; sleep 2; done; php artisan migrate --path=database/migrations/mongodb --force; php artisan migrate:status >/dev/null || exit 1; exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
