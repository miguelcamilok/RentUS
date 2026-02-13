#!/bin/bash
set -e

# Deshabilitar TODOS los MPM primero
echo "Disabling all MPM modules..."
a2dismod mpm_event mpm_worker mpm_prefork worker event 2>/dev/null || true

# Habilitar solo mpm_prefork
echo "Enabling mpm_prefork..."
a2enmod mpm_prefork

# Verificar qué MPM están habilitados
echo "Currently enabled MPM modules:"
ls -la /etc/apache2/mods-enabled/mpm_* 2>/dev/null || echo "No MPM symlinks found"

# Configurar el puerto desde la variable de entorno PORT de Railway
PORT=${PORT:-80}
echo "Configuring Apache to listen on port $PORT..."

# Reemplazar el puerto en ports.conf
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf

# Reemplazar el puerto en los virtualhost
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/*.conf

# Esperar a que la base de datos esté lista (máximo 30 segundos)
echo "Waiting for database connection..."
timeout=30
counter=0
until php artisan migrate:status > /dev/null 2>&1 || [ $counter -eq $timeout ]; do
  echo "Database not ready yet... waiting ($counter/$timeout)"
  sleep 2
  counter=$((counter + 2))
done

# Ejecutar migraciones si es necesario
if [ $counter -lt $timeout ]; then
  echo "Running migrations..."
  php artisan migrate --force || echo "Migrations failed or not needed"
else
  echo "Warning: Could not connect to database, skipping migrations"
fi

# Limpiar cache de configuración
echo "Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Optimizar para producción
echo "Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ejecutar el comando original de Apache
echo "Starting Apache on port $PORT..."
exec apache2-foreground
