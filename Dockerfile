FROM php:7.4-apache

# Habilita módulos de Apache
RUN a2enmod rewrite

# Instala dependencias del sistema y extensiones de PHP
RUN apt-get update && apt-get install -y \
    unzip \
    zip \
    git \
    curl \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql zip gd

# Instala Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copia el contenido del proyecto
COPY . /var/www/html

# Define el directorio de trabajo
WORKDIR /var/www/html

# Corre composer install automáticamente
RUN composer install --no-interaction --prefer-dist

# Establece permisos básicos (opcional)
RUN chown -R www-data:www-data /var/www/html