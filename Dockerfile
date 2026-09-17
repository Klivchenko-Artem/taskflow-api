# Образ для запуска: без dev-зависимостей
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
        git \
        unzip \
        libzip-dev \
        icu-dev \
        postgresql-dev \
        oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        zip \
        intl \
        bcmath \
        pcntl \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Зависимости отдельным слоем, пересобираются только при смене composer.lock
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# --- Стенд и CI ---
#
# Тут же phpunit и остальной инструмент для тестов
FROM base AS dev

RUN composer install --no-interaction --prefer-dist

# Последней стадией снова идёт образ для запуска: `docker build .` без --target
# должен собирать его, а не стенд с dev-зависимостями
FROM base AS prod
