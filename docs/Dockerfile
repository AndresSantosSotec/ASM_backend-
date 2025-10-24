##################################
# Etapa 1: Construcción de dependencias (usando composer)
##################################
FROM composer:2 AS build

WORKDIR /app

# Copiar archivos de composer y bloquear dependencias
COPY composer.json composer.lock ./

# Instalar dependencias de producción
RUN composer install --no-dev --prefer-dist --no-scripts --no-progress --optimize-autoloader

##################################
# Etapa 2: Imagen final PHP-FPM
##################################
FROM php:8.1-fpm-alpine

# Instalar dependencias del sistema y extensiones de PHP necesarias
RUN apk update && apk add --no-cache \
    bash \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    icu-dev \
    zip \
    unzip && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo pdo_mysql gd intl opcache

# Copiar la carpeta vendor instalada en la etapa build
COPY --from=build /app/vendor /var/www/vendor

# Copiar el resto del código fuente de la aplicación
COPY . /var/www

# Establecemos el directorio de trabajo
WORKDIR /var/www

# Ajustar permisos para que el usuario de php-fpm pueda escribir en storage y cache (ajustar si es necesario)
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Exponer el puerto en el que PHP-FPM escucha
EXPOSE 9000

# Comando por defecto para iniciar PHP-FPM
CMD ["php-fpm"]