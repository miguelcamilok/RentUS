#!/bin/bash
set -e

# Deshabilitar TODOS los MPM primero
echo "Disabling all MPM modules..."
a2dismod mpm_event mpm_worker mpm_prefork worker event 2>/dev/null || true

# Habilitar solo mpm_prefork
echo "Enabling mpm_prefork..."
a2enmod mpm_prefork

# Habilitar módulo headers de Apache
echo "Enabling Apache headers module..."
a2enmod headers

# Verificar qué MPM están habilitados
echo "Currently enabled MPM modules:"
ls -la /etc/apache2/mods-enabled/mpm_* 2>/dev/null || echo "No MPM symlinks found"

# Configurar el puerto
PORT=${PORT:-80}
echo "Configuring Apache to listen on port $PORT..."

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/*.conf

# ===== CONFIGURAR CORS EN APACHE =====
echo "Configuring CORS headers..."
cat >> /etc/apache2/apache2.conf << 'EOF'

# CORS Configuration
<IfModule mod_headers.c>
    Header unset Access-Control-Allow-Origin
    Header unset Access-Control-Allow-Methods
    Header unset Access-Control-Allow-Headers
    Header unset Access-Control-Allow-Credentials
    Header unset Access-Control-Max-Age

    Header set Access-Control-Allow-Origin "*"
    Header set Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS"
    Header set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, Accept"
    Header set Access-Control-Allow-Credentials "true"
    Header set Access-Control-Max-Age "3600"
</IfModule>
EOF

# ===== CREAR DIRECTORIOS DE LOGS Y CONFIGURAR PERMISOS =====
echo "Setting up Laravel storage directories..."
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/bootstrap/cache

# Configurar permisos ANTES de crear el enlace simbólico
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Configurar logs de Laravel para stdout
touch /var/www/html/storage/logs/laravel.log
chown www-data:www-data /var/www/html/storage/logs/laravel.log
chmod 666 /var/www/html/storage/logs/laravel.log

# Esperar base de datos
echo "Waiting for database connection..."
timeout=30
counter=0
until php artisan migrate:status > /dev/null 2>&1 || [ $counter -eq $timeout ]; do
  echo "Database not ready yet... waiting ($counter/$timeout)"
  sleep 2
  counter=$((counter + 2))
done

# Ejecutar migraciones
if [ $counter -lt $timeout ]; then
  echo "Running migrations..."
  php artisan migrate --force || echo "Migrations failed or not needed"
else
  echo "Warning: Could not connect to database, skipping migrations"
fi

# Limpiar cache
echo "Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Optimizar
echo "Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Iniciar Apache
echo "Starting Apache on port $PORT..."
exec apache2-foreground
