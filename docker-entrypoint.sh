#!/bin/bash
set -e

# Deshabilitar TODOS los MPM primero
echo "Disabling all MPM modules..."
a2dismod mpm_event mpm_worker mpm_prefork worker event 2>/dev/null || true

# Habilitar solo mpm_prefork
echo "Enabling mpm_prefork..."
a2enmod mpm_prefork

# Habilitar módulo headers de Apache (necesario para CORS)
echo "Enabling Apache headers module..."
a2enmod headers

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

# ===== AGREGAR HEADERS CORS DIRECTAMENTE EN APACHE =====
echo "Configuring CORS headers..."
cat >> /etc/apache2/apache2.conf << 'EOF'

# CORS Configuration
<IfModule mod_headers.c>
    # Permitir origen específico
    SetEnvIf Origin "^https://frontend-rentus-pruebas-production\.up\.railway\.app$" ORIGIN_ALLOWED=$0
    SetEnvIf Origin "^https://.*\.railway\.app$" ORIGIN_ALLOWED=$0
    SetEnvIf Origin "^http://localhost:5173$" ORIGIN_ALLOWED=$0
    SetEnvIf Origin "^http://localhost:4173$" ORIGIN_ALLOWED=$0

    Header always set Access-Control-Allow-Origin "%{ORIGIN_ALLOWED}e" env=ORIGIN_ALLOWED
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, Accept"
    Header always set Access-Control-Allow-Credentials "true"
    Header always set Access-Control-Max-Age "3600"
</IfModule>

# Responder inmediatamente a peticiones OPTIONS
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_METHOD} OPTIONS
    RewriteRule ^(.*)$ $1 [R=204,L]
</IfModule>
EOF

# Esperar a que la base de datos esté lista
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

# Configurar logs de Laravel
rm -f /var/www/html/storage/logs/laravel.log
ln -sf /dev/stdout /var/www/html/storage/logs/laravel.log
chmod -R 777 /var/www/html/storage/logs

# Ejecutar el comando original de Apache
echo "Starting Apache on port $PORT..."
exec apache2-foreground
