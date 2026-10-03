FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libpq-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        ffmpeg \
        librdkafka-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        intl \
        zip \
        bcmath \
        gd \
        pcntl \
        opcache \
    && pecl install rdkafka redis \
    && docker-php-ext-enable rdkafka redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd -g "${GID}" appuser \
    && useradd -m -u "${UID}" -g "${GID}" -s /bin/bash appuser

WORKDIR /var/www/html

COPY php.ini /usr/local/etc/php/conf.d/app.ini

USER appuser
