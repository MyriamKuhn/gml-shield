FROM php:8.5.12-apache-trixie

# Configure PHP upload limits for delivery proofs.
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Install the system libraries required by Laravel and PostgreSQL.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j$(nproc) \
        intl \
        pdo_pgsql \
        zip \
    && rm -rf /var/lib/apt/lists/*

# Serve Laravel from its public directory, never from the project root.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/apache2.conf \
    && a2enmod rewrite

WORKDIR /var/www/html

# Copy Composer from its official image.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Copy the Laravel application.
COPY . .

# Install the PHP dependencies locked by composer.lock.
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    && mkdir -p storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
