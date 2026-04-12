#!/bin/bash
set -e

echo "[entrypoint] Starting web service..."

# Ensure runtime and storage directories with correct permissions
mkdir -p /app/runtime/{cache,log,session,temp}
mkdir -p /app/storage/{uploads,reports,logs}
chown -R www-data:www-data /app/runtime 2>/dev/null || true
chmod -R 777 /app/storage/uploads /app/storage/reports /app/storage/logs 2>/dev/null || true

# Wait for bootstrap config
BOOTSTRAP_CONFIG="/app/runtime/bootstrap/app_config.php"
RETRIES=30
while [ ! -f "$BOOTSTRAP_CONFIG" ] && [ $RETRIES -gt 0 ]; do
    echo "[entrypoint] Waiting for bootstrap config..."
    sleep 1
    RETRIES=$((RETRIES - 1))
done

if [ ! -f "$BOOTSTRAP_CONFIG" ]; then
    echo "[entrypoint] ERROR: Bootstrap config not found at $BOOTSTRAP_CONFIG"
    exit 1
fi

echo "[entrypoint] Bootstrap config found."

# Run centralized DB initialization
if [ -f /app/docker/init-db-internal.sh ]; then
    echo "[entrypoint] Running DB initialization..."
    bash /app/docker/init-db-internal.sh 2>&1 || echo "[entrypoint] DB init returned non-zero (may be expected on repeat startup)."
fi

# Start PHP-FPM in background
echo "[entrypoint] Starting PHP-FPM..."
php-fpm -D

# Start Nginx in foreground
echo "[entrypoint] Starting Nginx..."
exec nginx -g "daemon off;"
