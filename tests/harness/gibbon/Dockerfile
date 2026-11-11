# Gibbon core for integration/E2E testing of the BigBlueButton module.
ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libicu-dev mariadb-client unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip intl gettext \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

COPY php.ini /usr/local/etc/php/conf.d/gibbon.ini

ARG GIBBON_VERSION=v30.0.02
ARG GIBBON_SHA256=2dffb380ff9d7f9b815fbfc3ee8b8308bef2728f09a24e5084fd4901df77eeca
RUN curl -fsSL -o /tmp/gibbon.tgz "https://github.com/GibbonEdu/core/releases/download/${GIBBON_VERSION}/GibbonEduCore-InstallBundle.tar.gz" \
    && echo "${GIBBON_SHA256}  /tmp/gibbon.tgz" | sha256sum -c - \
    && tar -xzf /tmp/gibbon.tgz -C /var/www/html \
    && rm /tmp/gibbon.tgz \
    && touch /var/www/html/config.php \
    && chown -R www-data:www-data /var/www/html
