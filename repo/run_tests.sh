#!/bin/bash
##############################################################################
# Broad test runner
#
# Runs all tests through Docker. Requires only Docker and curl on the host.
# Does NOT depend on host PHP, Composer, Node, npm, or other toolchains.
#
# With host networking, 127.0.0.1:8080 is reachable from both
# the host and inside the container identically.
#
# Usage: ./run_tests.sh
##############################################################################

set -e

APP_URL="http://127.0.0.1:8080"

echo "============================================"
echo " SiteOps Community Commerce — Test Runner"
echo "============================================"

FAILED=0

# Step 1: Ensure the stack is built and running
echo ""
echo "[test] Step 1: Building and starting Docker stack..."
docker compose up -d --build 2>&1

# Step 2: Wait for services to be healthy
echo ""
echo "[test] Step 2: Waiting for services..."

RETRIES=60
while ! docker compose exec -T mysql mysqladmin ping -h 127.0.0.1 --port=3307 --silent 2>/dev/null; do
    RETRIES=$((RETRIES - 1))
    if [ $RETRIES -le 0 ]; then
        echo "[test] FAIL: MySQL did not become healthy."
        FAILED=1
        break
    fi
    sleep 2
done

if [ $FAILED -eq 0 ]; then
    echo "[test] MySQL is healthy."
fi

echo "[test] Waiting for web service..."
RETRIES=30
WEB_HEALTHY=0
while [ $RETRIES -gt 0 ]; do
    if curl -sf --max-time 3 "${APP_URL}/api/v1/health" > /dev/null 2>&1; then
        WEB_HEALTHY=1
        break
    fi
    RETRIES=$((RETRIES - 1))
    sleep 2
done

if [ $WEB_HEALTHY -eq 1 ]; then
    echo "[test] Web service is healthy (host-side verified)."
else
    echo "[test] WARN: Web health endpoint not responding from host yet, continuing..."
fi

# Step 3: DB initialization via centralized path
echo ""
echo "[test] Step 3: Running database init (centralized)..."
docker compose exec -T web bash /app/docker/init-db-internal.sh 2>&1 || {
    echo "[test] Note: DB init returned non-zero."
}

# Step 4: Health endpoint verification FROM HOST
echo ""
echo "[test] Step 4: Health endpoint check (host-side)..."
HEALTH_RESPONSE=$(curl -sf --max-time 5 "${APP_URL}/api/v1/health" 2>&1 || echo "FAIL")
if echo "$HEALTH_RESPONSE" | grep -q '"status"'; then
    echo "[test] PASS: Health endpoint returned valid response from host."
else
    echo "[test] FAIL: Health endpoint check failed from host."
    echo "  Response: $HEALTH_RESPONSE"
    FAILED=1
fi

# Step 5: Run PHPUnit tests inside Docker
echo ""
echo "[test] Step 5: Running PHPUnit tests..."
docker compose exec -T web php /app/vendor/bin/phpunit \
    --configuration /app/phpunit.xml \
    --colors=always \
    2>&1 || {
    echo "[test] FAIL: PHPUnit tests failed."
    FAILED=1
}

# Step 6: Verify error envelope FROM HOST
echo ""
echo "[test] Step 6: Verifying error envelope format (host-side)..."
NOT_FOUND_RESPONSE=$(curl -s --max-time 5 "${APP_URL}/api/v1/nonexistent" 2>&1 || echo "FAIL")
if echo "$NOT_FOUND_RESPONSE" | grep -q '"error"'; then
    echo "[test] PASS: Error envelope format is correct."
else
    echo "[test] FAIL: Error envelope not returned for 404."
    FAILED=1
fi

# Step 7: Invalid credentials must return 401 FROM HOST
echo ""
echo "[test] Step 7: Verifying invalid login returns 401 (host-side)..."
BAD_LOGIN=$(curl -s --max-time 5 -o /dev/null -w '%{http_code}' \
    -X POST "${APP_URL}/api/v1/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"username":"nonexistent","password":"wrong"}' 2>&1)
if [ "$BAD_LOGIN" = "401" ]; then
    echo "[test] PASS: Invalid login returns 401."
else
    echo "[test] FAIL: Invalid login returned $BAD_LOGIN, expected 401."
    FAILED=1
fi

# Step 8: Valid login returns 200 with csrf_token FROM HOST
echo ""
echo "[test] Step 8: Verifying valid login returns 200 with session (host-side)..."
ADMIN_PASS=$(docker compose exec -T web php -r "require '/app/vendor/autoload.php'; echo bootstrap_config('seed_admin_password', '');" 2>&1)
LOGIN_RESPONSE=$(curl -s --max-time 5 -c /tmp/siteops_test_cookies \
    -X POST "${APP_URL}/api/v1/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"username\":\"admin\",\"password\":\"${ADMIN_PASS}\"}" 2>&1)
if echo "$LOGIN_RESPONSE" | grep -q '"csrf_token"'; then
    echo "[test] PASS: Valid login returns user + CSRF token (host-side)."
else
    echo "[test] FAIL: Valid login did not return expected data."
    echo "  Response: $LOGIN_RESPONSE"
    FAILED=1
fi

# Step 9: CSRF rejection FROM HOST
echo ""
echo "[test] Step 9: Verifying CSRF rejection on protected route (host-side)..."
CSRF_REJECT=$(curl -s --max-time 5 -o /dev/null -w '%{http_code}' \
    -X POST "${APP_URL}/api/v1/auth/logout" \
    -b /tmp/siteops_test_cookies \
    -H "Content-Type: application/json" 2>&1)
if [ "$CSRF_REJECT" = "403" ]; then
    echo "[test] PASS: POST without CSRF token returns 403."
else
    echo "[test] FAIL: POST without CSRF token returned $CSRF_REJECT, expected 403."
    FAILED=1
fi

# Step 10: Worker commands are registered
echo ""
echo "[test] Step 10: Verifying worker commands are registered..."
WORKER_CMDS=$(docker compose exec -T web php /app/think list 2>&1)
WORKER_OK=1
for CMD in analytics:nightly reports:scheduled retention:cleanup audit:retention seed:admin; do
    if ! echo "$WORKER_CMDS" | grep -q "$CMD"; then
        echo "[test] FAIL: Command '$CMD' not registered."
        WORKER_OK=0
        FAILED=1
    fi
done
if [ $WORKER_OK -eq 1 ]; then
    echo "[test] PASS: All worker commands registered."
fi

# Step 11: Browser E2E tests (Playwright) — REQUIRED
echo ""
echo "[test] Step 11: Running Playwright browser E2E tests..."

ADMIN_PASS_PW=$(docker compose exec -T web php -r "require '/app/vendor/autoload.php'; echo bootstrap_config('seed_admin_password', '');" 2>&1)
echo "$ADMIN_PASS_PW" > /tmp/siteops_test_password

PW_EXIT=1
if command -v npx >/dev/null 2>&1 && [ -d node_modules/@playwright ]; then
    npx playwright test --reporter=list 2>&1
    PW_EXIT=$?
elif [ -f Dockerfile.playwright ]; then
    echo "[test] Building Playwright Docker image..."
    docker build -f Dockerfile.playwright -t siteops-playwright . 2>&1 | tail -3
    docker run --rm --network host \
        -v "$(pwd)/tests/Playwright/artifacts:/tests/tests/Playwright/artifacts" \
        siteops-playwright \
        sh -c "echo '$ADMIN_PASS_PW' > /tmp/siteops_test_password && npx playwright test --reporter=list" 2>&1
    PW_EXIT=$?
fi

if [ $PW_EXIT -ne 0 ]; then
    echo "[test] FAIL: Playwright browser E2E tests failed or unavailable."
    FAILED=1
else
    echo "[test] PASS: Playwright browser E2E tests passed."
fi

# Cleanup temp cookies
rm -f /tmp/siteops_test_cookies

# Summary
echo ""
echo "============================================"
if [ $FAILED -eq 0 ]; then
    echo " ALL TESTS PASSED"
else
    echo " SOME TESTS FAILED"
fi
echo "============================================"

exit $FAILED
