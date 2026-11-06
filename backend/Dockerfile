# Backend image: multi-stage — зависимости собираются в отдельном stage.

# ---- deps: composer install ----
FROM composer:2.8 AS deps
WORKDIR /app

COPY composer.json composer.lock ./
# ext-amqp ставится в runtime-stage, поэтому здесь platform-check отключаем
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --ignore-platform-req=ext-amqp \
    --ignore-platform-req=ext-gd

# ---- runtime: php-fpm ----
FROM php:8.3-fpm-alpine AS runtime

RUN apk add --no-cache \
        postgresql-dev \
        icu-dev \
        libzip-dev \
        libpng-dev \
        rabbitmq-c-dev \
    && apk add --no-cache --virtual .build-deps \
        autoconf \
        gcc \
        g++ \
        make \
        linux-headers \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        gd \
        opcache \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && apk del .build-deps \
    && rm -rf /tmp/pear /var/cache/apk/*

WORKDIR /var/www

COPY --from=deps /app/vendor ./vendor
COPY . .

COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint \
    && mkdir -p storage/logs storage/images storage/imports \
    && chown -R www-data:www-data storage

EXPOSE 9000

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
