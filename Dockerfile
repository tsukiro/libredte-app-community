FROM php:7.4-apache

# Habilita módulos de Apache
RUN a2enmod rewrite

# Instala dependencias del sistema y extensiones necesarias
RUN apt-get update && apt-get install -y \
    unzip \
    zip \
    git \
    curl \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libc-client-dev \
    libkrb5-dev \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install pdo pdo_mysql zip gd soap imap

# Instala la extensión PHP Redis
RUN pecl install redis && docker-php-ext-enable redis

# Instala Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    DB_HOST=db \
    DB_NAME=libredte \
    DB_USER=libredte \
    DB_PASSWORD=agVotUQV0 \
    REDIS_HOST=redis \
    REDIS_PORT=6379 \
    MESSENGER_REDIS_DSN=redis://localhost:6379
# Copia tu proyecto
COPY . /var/www/html

# Define el directorio de trabajo
WORKDIR /var/www/html

# Ejecuta composer
RUN composer install --no-interaction --prefer-dist

# Permisos adecuados
RUN chown -R www-data:www-data /var/www/html