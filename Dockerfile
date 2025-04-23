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
    libpq-dev \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql zip gd soap imap

# Instala la extensión PHP Redis
RUN pecl install redis && docker-php-ext-enable redis

# Instala Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copia tu proyecto
COPY . /var/www/html

# Define el directorio de trabajo
WORKDIR /var/www/html

# Ejecuta composer
RUN composer install --no-interaction --prefer-dist

# Permisos adecuados
RUN chown -R www-data:www-data /var/www/html