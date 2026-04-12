#!/bin/bash
set -e

echo "[worker] Starting worker service..."

# Ensure runtime directories
mkdir -p /app/runtime/{cache,log,session,temp}
chown -R www-data:www-data /app/runtime /app/storage 2>/dev/null || true

# Wait for bootstrap config
BOOTSTRAP_CONFIG="/app/runtime/bootstrap/app_config.php"
RETRIES=30
while [ ! -f "$BOOTSTRAP_CONFIG" ] && [ $RETRIES -gt 0 ]; do
    echo "[worker] Waiting for bootstrap config..."
    sleep 1
    RETRIES=$((RETRIES - 1))
done

if [ ! -f "$BOOTSTRAP_CONFIG" ]; then
    echo "[worker] ERROR: Bootstrap config not found at $BOOTSTRAP_CONFIG"
    exit 1
fi

# Run service discovery so commands are available
php /app/think service:discover 2>/dev/null || true

# Validate that required commands exist
echo "[worker] Validating registered commands..."
for CMD in analytics:nightly reports:scheduled retention:cleanup audit:retention; do
    if ! php /app/think list 2>/dev/null | grep -q "$CMD"; then
        echo "[worker] ERROR: Required command '$CMD' not registered."
        exit 1
    fi
done
echo "[worker] All required commands validated."

echo "[worker] Bootstrap config found. Starting scheduler loop..."

# Worker loop: runs registered job commands on cadence.
# In production, use system cron. This loop is the dev/scaffold scheduler.
CYCLE=0
while true; do
    CYCLE=$((CYCLE + 1))
    NOW_HOUR=$(date +%H)
    NOW_MIN=$(date +%M)

    # Every cycle (60s): check scheduled reports
    php /app/think reports:scheduled 2>&1 | sed 's/^/[worker][reports:scheduled] /' || true

    # Daily at 02:00: nightly analytics
    if [ "$NOW_HOUR" = "02" ] && [ "$NOW_MIN" = "00" ]; then
        php /app/think analytics:nightly 2>&1 | sed 's/^/[worker][analytics:nightly] /' || true
    fi

    # Daily at 03:00: retention cleanup + audit retention
    if [ "$NOW_HOUR" = "03" ] && [ "$NOW_MIN" = "00" ]; then
        php /app/think retention:cleanup 2>&1 | sed 's/^/[worker][retention:cleanup] /' || true
        php /app/think audit:retention 2>&1 | sed 's/^/[worker][audit:retention] /' || true
    fi

    sleep 60
done
