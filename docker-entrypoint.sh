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

# Ejecutar el comando original de Apache
echo "Starting Apache..."
exec apache2-foreground
