#!/bin/bash
# =====================================================================
# Actualiza el sitio desde GitHub. Se corre en la Terminal de cPanel.
#
#   cd ~/cotizacion.enlix.pe && bash tools/desplegar.sh
#
# Trae los ultimos cambios y reinstala dependencias. No toca los
# archivos locales que no estan en el repositorio (config/empresa.php,
# config/credenciales.php y la firma).
# =====================================================================

set -e

echo "==> Carpeta: $(pwd)"

if [ ! -d .git ]; then
    echo "ERROR: aqui no hay un repositorio git. Revisa la carpeta." >&2
    exit 1
fi

# ---------------------------------------------------------------------
# 1. Traer cambios
# ---------------------------------------------------------------------
echo "==> Descargando cambios de GitHub"
git fetch origin main
git reset --hard origin/main

# ---------------------------------------------------------------------
# 2. Dependencias
# ---------------------------------------------------------------------
echo "==> Instalando dependencias"
if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader --no-interaction
elif [ -f composer.phar ]; then
    php composer.phar install --no-dev --optimize-autoloader --no-interaction
else
    echo "AVISO: no hay composer. Descargalo una vez con:" >&2
    echo "  curl -sS https://getcomposer.org/installer | php" >&2
    exit 1
fi

# ---------------------------------------------------------------------
# 3. Comprobar lo que el repositorio no trae
# ---------------------------------------------------------------------
echo "==> Comprobando archivos locales"
faltan=0
for archivo in config/empresa.php config/credenciales.php; do
    if [ ! -f "$archivo" ]; then
        echo "  FALTA $archivo"
        faltan=1
    else
        echo "  ok    $archivo"
    fi
done

if [ ! -f pdf/firma-transparente.png ]; then
    echo "  falta pdf/firma-transparente.png (el PDF saldra sin firma)"
else
    echo "  ok    pdf/firma-transparente.png"
fi

# El directorio de sesiones y los PDFs generados
mkdir -p storage
chmod 755 storage

echo
if [ "$faltan" = "1" ]; then
    echo "Despliegue incompleto: falta configuracion. Ver arriba."
    exit 1
fi

echo "Listo. Abre https://cotizacion.enlix.pe/"
