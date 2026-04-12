#!/bin/bash
##############################################################################
# MySQL init script — runs only on first database initialization
# Reads all values from the bootstrap volume. Fails if bootstrap incomplete.
##############################################################################

set -e

APP_PASSWORD=$(cat /bootstrap/db_app_password 2>/dev/null || echo "")
DB_NAME=$(cat /bootstrap/db_name 2>/dev/null || echo "")
DB_USER=$(cat /bootstrap/db_user 2>/dev/null || echo "")

if [ -z "$APP_PASSWORD" ] || [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
    echo "[mysql-init] ERROR: Bootstrap values missing. Ensure bootstrap service completed."
    echo "[mysql-init] Required: /bootstrap/db_app_password, /bootstrap/db_name, /bootstrap/db_user"
    exit 1
fi

echo "[mysql-init] Creating database '$DB_NAME' and user '$DB_USER'..."

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_unicode_ci;

    CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${APP_PASSWORD}';

    GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
    FLUSH PRIVILEGES;
EOSQL

echo "[mysql-init] Database and user created."
