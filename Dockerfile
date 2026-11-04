FROM php:8.2-fpm

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libonig-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        pcntl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY . .

RUN composer install \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/app/entrypoint.sh /usr/local/bin/app-entrypoint
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

RUN sed -i 's/\r$//' /usr/local/bin/app-entrypoint \
    && chmod +x /usr/local/bin/app-entrypoint

EXPOSE 9000

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
