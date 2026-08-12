FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
    --ignore-platform-req=ext-bcmath \
    --ignore-platform-req=ext-pcntl
COPY backend .
RUN composer dump-autoload --optimize

FROM php:8.3-fpm AS runtime
WORKDIR /var/www/html
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev \
    && docker-php-ext-install bcmath pcntl pdo_mysql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
COPY --from=vendor /app /var/www/html
RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 9000
CMD ["php-fpm"]
