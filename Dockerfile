FROM php:8.2-apache

# Instalar dependencias
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    curl \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# **SOLUCIÓN MPM: Deshabilitar TODAS las variantes posibles de MPM**
RUN a2dismod mpm_event mpm_worker mpm_prefork worker event || true \
    && a2enmod mpm_prefork

# Copiar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos del proyecto
COPY . /var/www/html

# Instalar dependencias de Composer
RUN composer install --no-dev --optimize-autoloader

# Configurar DocumentRoot para Laravel
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Exponer puerto
EXPOSE 80

# Comando de inicio
CMD ["apache2-foreground"]
