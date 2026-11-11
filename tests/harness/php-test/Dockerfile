# PHPUnit runner: lowest supported PHP + pdo_mysql + pcov for coverage.
ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-cli
RUN docker-php-ext-install -j"$(nproc)" pdo_mysql \
    && pecl install pcov \
    && docker-php-ext-enable pcov
WORKDIR /module
