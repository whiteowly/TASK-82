#!/bin/bash
##############################################################################
# Standard database initialization path
#
# Usage: ./init_db.sh
#
# Delegates to docker/init-db-internal.sh inside the web container.
# Requires the Docker Compose stack to be running.
##############################################################################

set -e

echo "[init_db] Initializing database..."

# Check if compose services are running
if ! docker compose ps --status running 2>/dev/null | grep -q "web"; then
    echo "[init_db] Web service not running. Starting stack..."
    docker compose up -d --build
    echo "[init_db] Waiting for services to be healthy..."
    sleep 15
fi

# Wait for MySQL to be healthy
echo "[init_db] Waiting for MySQL..."
RETRIES=30
while ! docker compose exec -T mysql mysqladmin ping -h 127.0.0.1 --port=3307 --silent 2>/dev/null; do
    RETRIES=$((RETRIES - 1))
    if [ $RETRIES -le 0 ]; then
        echo "[init_db] ERROR: MySQL did not become healthy."
        exit 1
    fi
    sleep 2
done
echo "[init_db] MySQL is ready."

# Delegate to centralized init script inside the web container
docker compose exec -T web bash /app/docker/init-db-internal.sh 2>&1

echo "[init_db] Done."
