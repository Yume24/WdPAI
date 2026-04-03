FROM php:8.3-fpm-alpine

ENV RUN_DEPS \
    zlib \
    libzip \
    libpng \
    libjpeg-turbo \
    postgresql-libs

ENV BUILD_DEPS \
    zlib-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    postgresql-dev

ENV PHP_EXTENSIONS \
    opcache \
    zip \
    gd \
    bcmath \
    pgsql \
    pdo_pgsql

RUN apk add --no-cache --virtual .build-deps $BUILD_DEPS \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j "$(nproc)" $PHP_EXTENSIONS \
    && apk del .build-deps

RUN apk add --no-cache --virtual .run-deps $RUN_DEPS git unzip

# Install Composer (used for PHPUnit and autoloader optimisation; not required for the app to run).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN echo 'memory_limit = -1' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini \
    && echo 'upload_max_filesize = 4M' >> /usr/local/etc/php/conf.d/docker-php-uploads.ini \
    && echo 'post_max_size = 8M' >> /usr/local/etc/php/conf.d/docker-php-uploads.ini

WORKDIR /app

COPY . /app

VOLUME ["/app"]
