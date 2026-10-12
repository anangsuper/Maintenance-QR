#!/bin/bash
# ==============================================================================
# Script Instalasi Otomatis Server Maintenance & Immich Ready di Ubuntu Server
# ==============================================================================

set -e

echo "=== 1. Menginstal Paket Apache, PHP, dan MariaDB ==="
apt update
apt install -y apache2 mariadb-server php libapache2-mod-php php-mysql php-gd php-curl php-mbstring php-xml php-zip git curl unzip

echo "=== 2. Mengaktifkan Modul Apache ==="
a2enmod rewrite headers

echo "=== 3. Menyiapkan Database MySQL ==="
mariadb -e "CREATE DATABASE IF NOT EXISTS db_maintenance_qr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mariadb -e "CREATE USER IF NOT EXISTS 'qr_user'@'localhost' IDENTIFIED BY 'QrPass123';"
mariadb -e "GRANT ALL PRIVILEGES ON db_maintenance_qr.* TO 'qr_user'@'localhost'; FLUSH PRIVILEGES;"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -f "$SCRIPT_DIR/sql/00_master_schema.sql" ]; then
    echo "=== Mengimpor Skema Master Database ==="
    mariadb db_maintenance_qr < "$SCRIPT_DIR/sql/00_master_schema.sql"
fi

echo "=== 4. Menyiapkan Folder Upload Foto & Izin File ==="
mkdir -p "$SCRIPT_DIR/api/uploads"
chown -R www-data:www-data "$SCRIPT_DIR"
chmod -R 775 "$SCRIPT_DIR"
chmod -R 777 "$SCRIPT_DIR/api/uploads"

echo "=== 5. Menyiapkan config.local.php ==="
if [ ! -f "$SCRIPT_DIR/config.local.php" ]; then
    cp "$SCRIPT_DIR/config.local.example.php" "$SCRIPT_DIR/config.local.php"
    chown www-data:www-data "$SCRIPT_DIR/config.local.php"
fi
cp "$SCRIPT_DIR/config.local.php" "$SCRIPT_DIR/api/config.local.php" || true

echo "=== 6. Mengatur Apache VirtualHost ke api/ ==="
cat << EOF > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot $SCRIPT_DIR/api

    <Directory $SCRIPT_DIR/api>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Perbesar batas upload foto di PHP
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 25M/' /etc/php/*/apache2/php.ini 2>/dev/null || true
sed -i 's/post_max_size = .*/post_max_size = 30M/' /etc/php/*/apache2/php.ini 2>/dev/null || true

systemctl restart apache2
echo "=== SELESAI! Server Maintenance siap digunakan di IP server Anda! ==="
