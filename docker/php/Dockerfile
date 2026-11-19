FROM php:8.4-apache

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libonig-dev \
    && docker-php-ext-install \
        intl \
        pdo_mysql \
        zip \
        opcache \
        mbstring \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

COPY docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

RUN printf '%s\n' \
    '<Directory /var/www/html/public>' \
    '    AllowOverride None' \
    '    Require all granted' \
    '    DirectoryIndex index.php' \
    '    FallbackResource /index.php' \
    '</Directory>' \
    > /etc/apache2/conf-available/symfony.conf \
    && a2enconf symfony
