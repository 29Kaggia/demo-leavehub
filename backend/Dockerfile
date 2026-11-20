FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libonig-dev unzip \
    && docker-php-ext-install intl mbstring pdo_mysql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

COPY . .

RUN sed -ri 's!/var/www/html!/var/www/app/webroot!g' /etc/apache2/sites-available/000-default.conf \
    && printf '\n<Directory /var/www/app/webroot>\n    AllowOverride All\n    Require all granted\n</Directory>\n' >> /etc/apache2/apache2.conf \
    && mkdir -p /var/www/app/tmp /var/www/app/logs /var/www/app/storage \
    && chown -R www-data:www-data /var/www/app/tmp /var/www/app/logs /var/www/app/storage

COPY docker-entrypoint.sh /usr/local/bin/leavehub-entrypoint
RUN chmod +x /usr/local/bin/leavehub-entrypoint

EXPOSE 10000
CMD ["leavehub-entrypoint"]
