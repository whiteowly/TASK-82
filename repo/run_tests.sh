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

set -eo pipefail

APP_URL="http://127.0.0.1:8080"
PHPUNIT_TIMEOUT=600    # 10 minutes max for PHPUnit
PLAYWRIGHT_TIMEOUT=600 # 10 minutes max for Playwright
SUITE_START=$(date +%s)

# Helper: print a step header with timestamp
step_start() {
    STEP_START=$(date +%s)
    echo ""
    echo "──── Step $1: $2 [$(date '+%H:%M:%S')] ────"
}

# Helper: print step completion with duration
step_done() {
    local elapsed=$(( $(date +%s) - STEP_START ))
    echo "  ✓ $1 (${elapsed}s)"
}

step_fail() {
    local elapsed=$(( $(date +%s) - STEP_START ))
    echo "  ✗ $1 (${elapsed}s)"
}

echo "============================================"
echo " SiteOps Community Commerce — Test Runner"
echo " Started at $(date '+%Y-%m-%d %H:%M:%S')"
echo "============================================"

FAILED=0

# Step 1: Ensure the stack is built and running
step_start 1 "Building and starting Docker stack"
docker compose up -d --build 2>&1
step_done "Docker stack ready"

# Step 2: Wait for services to be healthy
step_start 2 "Waiting for services"

echo "  Waiting for MySQL..."
RETRIES=60
while ! docker compose exec -T mysql mysqladmin ping -h 127.0.0.1 --port=3307 --silent 2>/dev/null; do
    RETRIES=$((RETRIES - 1))
    if [ $RETRIES -le 0 ]; then
        step_fail "MySQL did not become healthy"
        FAILED=1
        break
    fi
    if [ $((RETRIES % 10)) -eq 0 ]; then
        echo "    ... still waiting for MySQL ($RETRIES retries left)"
    fi
    sleep 2
done

if [ $FAILED -eq 0 ]; then
    echo "  MySQL is healthy."
fi

echo "  Waiting for web service..."
RETRIES=30
WEB_HEALTHY=0
while [ $RETRIES -gt 0 ]; do
    if curl -sf --max-time 3 "${APP_URL}/api/v1/health" > /dev/null 2>&1; then
        WEB_HEALTHY=1
        break
    fi
    RETRIES=$((RETRIES - 1))
    if [ $((RETRIES % 10)) -eq 0 ]; then
        echo "    ... still waiting for web ($RETRIES retries left)"
    fi
    sleep 2
done

if [ $WEB_HEALTHY -eq 1 ]; then
    step_done "All services healthy"
else
    echo "  WARN: Web health endpoint not responding from host yet, continuing..."
fi

# Step 3: DB initialization via centralized path
step_start 3 "Running database init"
docker compose exec -T web bash /app/docker/init-db-internal.sh 2>&1 || {
    echo "  Note: DB init returned non-zero."
}
step_done "Database initialized"

# Step 4: Health endpoint verification FROM HOST
step_start 4 "Health endpoint check (host-side)"
HEALTH_RESPONSE=$(curl -sf --max-time 5 "${APP_URL}/api/v1/health" 2>&1 || echo "FAIL")
if echo "$HEALTH_RESPONSE" | grep -q '"status"'; then
    step_done "Health endpoint returned valid response"
else
    step_fail "Health endpoint check failed"
    echo "  Response: $HEALTH_RESPONSE"
    FAILED=1
fi

# Step 5: Run PHPUnit tests inside Docker
step_start 5 "Running PHPUnit tests (timeout: ${PHPUNIT_TIMEOUT}s)"
PHPUNIT_RC=0
timeout "$PHPUNIT_TIMEOUT" \
    docker compose exec -T web php /app/vendor/bin/phpunit \
    --configuration /app/phpunit.xml \
    --colors=always \
    2>&1 || PHPUNIT_RC=$?
if [ $PHPUNIT_RC -eq 124 ]; then
    step_fail "PHPUnit timed out after ${PHPUNIT_TIMEOUT}s"
    FAILED=1
elif [ $PHPUNIT_RC -ne 0 ]; then
    step_fail "PHPUnit tests failed (exit $PHPUNIT_RC)"
    FAILED=1
else
    step_done "PHPUnit tests passed"
fi

# Step 6: Verify error envelope FROM HOST
step_start 6 "Verifying error envelope format (host-side)"
NOT_FOUND_RESPONSE=$(curl -s --max-time 5 "${APP_URL}/api/v1/nonexistent" 2>&1 || echo "FAIL")
if echo "$NOT_FOUND_RESPONSE" | grep -q '"error"'; then
    step_done "Error envelope format correct"
else
    step_fail "Error envelope not returned for 404"
    FAILED=1
fi

# Step 7: Invalid credentials must return 401 FROM HOST
step_start 7 "Verifying invalid login returns 401 (host-side)"
BAD_LOGIN=$(curl -s --max-time 5 -o /dev/null -w '%{http_code}' \
    -X POST "${APP_URL}/api/v1/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"username":"nonexistent","password":"wrong"}' 2>&1)
if [ "$BAD_LOGIN" = "401" ]; then
    step_done "Invalid login returns 401"
else
    step_fail "Invalid login returned $BAD_LOGIN, expected 401"
    FAILED=1
fi

# Step 8: Valid login returns 200 with csrf_token FROM HOST
step_start 8 "Verifying valid login returns 200 with session (host-side)"
ADMIN_PASS=$(docker compose exec -T web php -r "require '/app/vendor/autoload.php'; echo bootstrap_config('seed_admin_password', '');" 2>&1)
LOGIN_RESPONSE=$(curl -s --max-time 5 -c /tmp/siteops_test_cookies \
    -X POST "${APP_URL}/api/v1/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"username\":\"admin\",\"password\":\"${ADMIN_PASS}\"}" 2>&1)
if echo "$LOGIN_RESPONSE" | grep -q '"csrf_token"'; then
    step_done "Valid login returns user + CSRF token"
else
    step_fail "Valid login did not return expected data"
    echo "  Response: $LOGIN_RESPONSE"
    FAILED=1
fi

# Step 9: CSRF rejection FROM HOST
step_start 9 "Verifying CSRF rejection on protected route (host-side)"
CSRF_REJECT=$(curl -s --max-time 5 -o /dev/null -w '%{http_code}' \
    -X POST "${APP_URL}/api/v1/auth/logout" \
    -b /tmp/siteops_test_cookies \
    -H "Content-Type: application/json" 2>&1)
if [ "$CSRF_REJECT" = "403" ]; then
    step_done "POST without CSRF token returns 403"
else
    step_fail "POST without CSRF token returned $CSRF_REJECT, expected 403"
    FAILED=1
fi

# Step 10: Worker commands are registered
step_start 10 "Verifying worker commands are registered"
WORKER_CMDS=$(docker compose exec -T web php /app/think list 2>&1)
WORKER_OK=1
for CMD in analytics:nightly reports:scheduled retention:cleanup audit:retention seed:admin; do
    if ! echo "$WORKER_CMDS" | grep -q "$CMD"; then
        echo "  FAIL: Command '$CMD' not registered."
        WORKER_OK=0
        FAILED=1
    fi
done
if [ $WORKER_OK -eq 1 ]; then
    step_done "All worker commands registered"
fi

# Step 11: Browser E2E tests (Playwright) — REQUIRED
step_start 11 "Running Playwright browser E2E tests"

ADMIN_PASS_PW=$(docker compose exec -T web php -r "require '/app/vendor/autoload.php'; echo bootstrap_config('seed_admin_password', '');" 2>&1)
echo "$ADMIN_PASS_PW" > /tmp/siteops_test_password

PW_EXIT=1
if [ -f Dockerfile.playwright ]; then
    echo "  Building Playwright Docker image..."
    docker build -f Dockerfile.playwright -t siteops-playwright . 2>&1
    echo "  Running Playwright tests (timeout: ${PLAYWRIGHT_TIMEOUT}s)..."
    PW_EXIT=0
    timeout "$PLAYWRIGHT_TIMEOUT" \
        docker run --rm --network host \
        -v "$(pwd)/tests/Playwright/artifacts:/tests/tests/Playwright/artifacts" \
        siteops-playwright \
        sh -c "echo '$ADMIN_PASS_PW' > /tmp/siteops_test_password && npx playwright test --reporter=list" 2>&1 || PW_EXIT=$?
fi

if [ "$PW_EXIT" -eq 124 ]; then
    step_fail "Playwright timed out after ${PLAYWRIGHT_TIMEOUT}s"
    FAILED=1
elif [ "$PW_EXIT" -ne 0 ]; then
    step_fail "Playwright browser E2E tests failed or unavailable (exit $PW_EXIT)"
    FAILED=1
else
    step_done "Playwright browser E2E tests passed"
fi

# Cleanup temp cookies
rm -f /tmp/siteops_test_cookies

# Summary
SUITE_ELAPSED=$(( $(date +%s) - SUITE_START ))
SUITE_MINS=$((SUITE_ELAPSED / 60))
SUITE_SECS=$((SUITE_ELAPSED % 60))
echo ""
echo "============================================"
if [ $FAILED -eq 0 ]; then
    echo " ALL TESTS PASSED  (${SUITE_MINS}m ${SUITE_SECS}s)"
else
    echo " SOME TESTS FAILED  (${SUITE_MINS}m ${SUITE_SECS}s)"
fi
echo "============================================"

exit $FAILED
