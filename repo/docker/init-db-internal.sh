#!/bin/bash
##############################################################################
# Internal DB initialization — called from entrypoint and init_db.sh
# Runs inside the web container. Single standard DB init path.
##############################################################################

set -e

echo "[init-db] Running service discovery..."
php /app/think service:discover 2>/dev/null || true

echo "[init-db] Running migrations..."
php /app/think migrate:run 2>&1

echo "[init-db] Running seed data..."
php /app/think seed:run 2>&1 || echo "[init-db] No seeds to run."

# Seed dev admin user if bootstrap provides password
ADMIN_PASS=$(php -r "require '/app/vendor/autoload.php'; echo bootstrap_config('seed_admin_password', '');" 2>/dev/null || echo "")
if [ -n "$ADMIN_PASS" ]; then
    echo "[init-db] Seeding dev admin user..."
    php /app/think seed:admin 2>&1 || echo "[init-db] Admin seed skipped or already exists."
fi

echo "[init-db] Database initialization complete."
