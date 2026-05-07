#!/bin/bash
# eTeam Manager Server Setup Script
# Ejecuta esto en el servidor después de hacer git pull

set -e

echo "=========================================="
echo "eTeam Manager - Server Setup"
echo "=========================================="

# 1. Habilitar mod_rewrite en Apache
echo "[1] Habilitando mod_rewrite..."
sudo a2enmod rewrite 2>/dev/null || echo "mod_rewrite ya está habilitado"

# 2. Configurar AllowOverride All en el vhost
echo "[2] Configurando AllowOverride en vhost..."
VHOST_FILE="/etc/apache2/sites-available/000-default.conf"
if [ -f "$VHOST_FILE" ]; then
    # Backup
    sudo cp "$VHOST_FILE" "$VHOST_FILE.backup.$(date +%s)"
    
    # Update AllowOverride
    sudo sed -i '/<Directory \/var\/www\/html>/,/<\/Directory>/ {
        /<Directory \/var\/www\/html>/a\    AllowOverride All
        /AllowOverride/!b
        /AllowOverride All/!d
    }' "$VHOST_FILE" || echo "Actualizando manual..."
    
    # Alternative: agregar AllowOverride si no existe
    if ! grep -q "AllowOverride All" "$VHOST_FILE"; then
        sudo sed -i '/<Directory \/var\/www\/html>/a\    AllowOverride All' "$VHOST_FILE"
    fi
fi

# 3. Reiniciar Apache
echo "[3] Reiniciando Apache..."
sudo systemctl restart apache2
echo "✓ Apache reiniciado"

# 4. Verificar estado de mod_rewrite
echo "[4] Verificando mod_rewrite..."
sudo a2query -m rewrite >/dev/null && echo "✓ mod_rewrite está habilitado" || echo "✗ mod_rewrite no está disponible"

# 5. Ejecutar seeders SQL
echo "[5] Actualizando base de datos (seeders)..."
cd /var/www/html/eTeam-Manager || cd /var/www/eTeam-Manager || { echo "Directorio no encontrado"; exit 1; }

# Ejecutar seeder
mysql -u eteam_manager -p"eteam_secure" eteam_manager < database/03_seed_dev_valorant.sql 2>&1 | head -20
echo "✓ Seeders ejecutados"

# 6. Verificar permisos
echo "[6] Ajustando permisos..."
sudo chown -R www-data:www-data /var/www/html/eTeam-Manager 2>/dev/null || sudo chown -R www-data:www-data /var/www/eTeam-Manager 2>/dev/null
sudo chmod -R 755 /var/www/html/eTeam-Manager 2>/dev/null || sudo chmod -R 755 /var/www/eTeam-Manager 2>/dev/null
echo "✓ Permisos actualizados"

# 7. Mostrar resumen
echo ""
echo "=========================================="
echo "✓ Setup completado"
echo "=========================================="
echo "Cambios realizados:"
echo "  • mod_rewrite habilitado"
echo "  • AllowOverride All configurado"
echo "  • Seeders ejecutados"
echo "  • Permisos ajustados"
echo ""
echo "Próximos pasos:"
echo "  1. Verifica que los .htaccess funcionen visitando:"
echo "     https://eteam-manager.ieti.site/app.php?view=teams"
echo "  2. Prueba la búsqueda:"
echo "     https://eteam-manager.ieti.site/app.php?view=search"
echo "  3. Verifica el registro:"
echo "     https://eteam-manager.ieti.site/app.php?view=register"
echo ""
echo "Para ver logs en caso de error:"
echo "  sudo tail -n 100 /var/log/apache2/error.log"
echo "  sudo tail -n 100 /var/log/php*-fpm.log (si usas PHP-FPM)"
echo ""
