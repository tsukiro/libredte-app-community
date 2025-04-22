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

# Instala Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copia tu proyecto dentro del contenedor
COPY . /var/www/html

# Establece el directorio de trabajo
WORKDIR /var/www/html

# Ejecuta composer install (con extensiones ya disponibles)
RUN composer install --no-interaction --prefer-dist

# Permisos adecuados para Apache
RUN chown -R www-data:www-data /var/www/html