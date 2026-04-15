# Community Commerce & Content Operations Management System

**Project Type: fullstack**

A fully offline, local-network multi-site management system built with ThinkPHP 6 + Layui. Designed for community commerce organizations that need recipe content workflow, operations analytics, multi-dimensional reporting, finance settlement management, RBAC, encrypted sensitive data handling, and immutable audit logging.

**This system does not depend on internet access, cloud storage, or external SaaS services.**

## Quick Start

```bash
# Required startup command (backend/fullstack gate)
docker-compose up

# Recommended command for first run (build images + start all services)
docker compose up --build

# Access the application
open http://127.0.0.1:8080

# Initialize database schema (also runs automatically on first startup)
./init_db.sh

# Run the full test suite
./run_tests.sh
```

No `.env` files are used. A dev-bootstrap script automatically generates local-development runtime values (DB credentials, app keys, encryption keys) on first startup. These are stored in a Docker named volume and persist across restarts. See [Local Development Bootstrap](#local-development-bootstrap) below.

## Architecture

### Stack

| Component | Technology |
|-----------|-----------|
| Backend | PHP 8.1 + ThinkPHP 6.1 |
| Frontend | Layui 2.9.x (local assets, no CDN) |
| Database | MySQL 8.0 |
| Worker | PHP CLI scheduler for background jobs |
| Runtime | Docker Compose (Nginx + PHP-FPM) |

### Services

| Service | Purpose | Host Port |
|---------|---------|-----------|
| `web` | Nginx + PHP-FPM application server | 127.0.0.1:8080 |
| `worker` | Background job scheduler | none |
| `mysql` | MySQL 8.0 database | 127.0.0.1:3307 (loopback only) |
| `bootstrap` | One-shot dev secret generation | none |

All services use Docker host networking (`network_mode: host`) so the app is directly reachable at `http://127.0.0.1:8080`. MySQL binds to `127.0.0.1` only (`--bind-address=127.0.0.1`) and is not exposed beyond the loopback interface.

Session auth is cookie-based and server-side. For non-local deployments, either run a single `web` instance or use sticky sessions/shared session storage across replicas; otherwise authenticated requests can intermittently return `401 AUTH_REQUIRED`.

## Module Boundaries

The application is a modular monolith with these domain modules:

| Module | Directory | Scope |
|--------|-----------|-------|
| **Auth & RBAC** | `app/service/auth/`, `app/service/rbac/`, `app/middleware/` | Session auth, password hashing (Argon2id), role-permission management, site-scope grants |
| **Recipe Workflow** | `app/service/recipe/` | Draft/review/approve/publish lifecycle, version diffs, inline comments |
| **Analytics** | `app/service/analytics/` | Nightly snapshots, on-demand refresh (5/hr rate limit), dashboard KPIs |
| **Search & Reporting** | `app/service/search/`, `app/service/report/` | Cross-domain search, configurable reports, CSV export, scheduled generation |
| **Settlement & Finance** | `app/service/settlement/` | Freight rules, statement generation, variance reconciliation, approval/lock/reversal |
| **Audit & Compliance** | `app/service/audit/` | Immutable append-only logs with hash chain, field masking, AES-256 tax ID encryption |
| **File Integrity** | `app/service/file/` | Upload validation (JPG/PNG, <5MB), SHA-256 fingerprinting, secure local storage |
| **Security** | `app/service/security/` | Tax ID encryption (AES-256-CBC), field masking service |

### Multi-Site Scope

Site is a first-class organizational dimension. All operational queries enforce site scope on the backend. Administrators and Auditors have cross-site access; other roles are restricted to assigned sites.

## Repo Structure

```
├── app/
│   ├── controller/         # Route handlers
│   │   └── api/v1/         # REST API controllers (Health, Auth, Recipe, etc.)
│   ├── middleware/          # Auth, RBAC, SiteScope, CSRF, RequestId
│   ├── model/              # Eloquent-style ORM models (39 entities)
│   ├── service/            # Domain service layer (auth, rbac, recipe, analytics, etc.)
│   ├── validate/           # Request validators
│   ├── job/                # Background job commands
│   ├── common.php          # Shared helpers (bootstrap_config, generate_request_id)
│   └── ExceptionHandle.php # Normalized error envelope handler
├── config/                 # ThinkPHP configuration
├── database/migrations/    # Phinx-based schema migrations
├── docker/                 # Docker infrastructure
│   ├── dev-bootstrap.sh    # Local dev secret generator
│   ├── entrypoint.sh       # Web service entrypoint
│   ├── worker-entrypoint.sh
│   ├── nginx/              # Nginx config
│   ├── php/                # PHP config
│   └── mysql/init/         # MySQL initialization
├── public/
│   ├── index.php           # Application entry point
│   └── static/             # Frontend assets (Layui, JS, CSS)
├── route/app.php           # All API and page route definitions
├── storage/                # Upload and report artifact storage
├── tests/                  # PHPUnit test suites
├── view/                   # Layui HTML templates
├── docker-compose.yml
├── Dockerfile              # Web service (multi-stage: Layui + Composer + PHP-FPM)
├── Dockerfile.worker       # Worker service
├── init_db.sh              # Database initialization script
├── run_tests.sh            # Broad test runner (Docker-based)
└── think                   # ThinkPHP CLI entry
```

## Commands

| Command | Purpose |
|---------|---------|
| `docker compose up --build` | Primary runtime command — builds and starts all services |
| `docker-compose up` | Required compatibility startup command (same stack startup intent) |
| `docker compose down` | Stop and remove containers |
| `./run_tests.sh` | Primary broad test command — runs health checks, PHPUnit, error envelope verification through Docker |
| `./init_db.sh` | Standard database initialization — runs migrations and seeds through Docker |

## Local Development Bootstrap

On first `docker compose up --build`, the `bootstrap` service:

1. Generates random values for: MySQL root password, app database credentials (random DB name and username), application key, tax ID encryption key, CSRF secret, dev admin password
2. Writes these to a named Docker volume (`bootstrap_data`)
3. Generates `app_config.php` that the PHP application reads at runtime
4. Seeds a dev admin user (username: `admin`, password printed to bootstrap logs)

**This is local development bootstrap only. Production deployments must use a proper secret management system.**

To regenerate secrets: `docker compose down -v` (destroys volumes) then `docker compose up --build`.

No `.env` files are created, checked in, or required. No hardcoded database credentials exist in source files.

## API

All API endpoints use the `/api/v1` prefix. Response format:

```json
// Success
{"data": {...}, "meta": {"request_id": "req-..."}}

// Error
{"error": {"code": "VALIDATION_FAILED", "message": "...", "details": []}, "meta": {"request_id": "req-..."}}
```

Standard error codes: `AUTH_INVALID_CREDENTIALS`, `AUTH_REQUIRED`, `FORBIDDEN_ROLE`, `FORBIDDEN_SITE_SCOPE`, `VALIDATION_FAILED`, `WORKFLOW_CONFLICT`, `RESOURCE_LOCKED`, `RATE_LIMITED`, `NOT_FOUND`.

Key endpoints: `/api/v1/health`, `/api/v1/auth/login`, `/api/v1/auth/me`, `/api/v1/recipes`, `/api/v1/analytics/dashboard`, `/api/v1/reports/definitions`, `/api/v1/finance/settlements`, `/api/v1/audit/logs`.

## Security Foundations

- **Password hashing**: Argon2id via `PasswordHashService`
- **CSRF protection**: Token-based validation on all state-changing requests via `CsrfMiddleware`
- **RBAC**: Route-level and action-level enforcement via `RbacMiddleware`
- **Site scope**: Backend enforcement on all data access via `SiteScopeMiddleware`
- **Tax ID encryption**: AES-256-CBC with random IV via `TaxIdEncryptionService`
- **Field masking**: Role-aware masking in API responses and CSV exports via `FieldMaskingService`
- **Audit immutability**: Append-only with SHA-256 hash chain via `AuditService`, enforced at DB level by `BEFORE UPDATE` and `BEFORE DELETE` triggers on `audit_logs` that signal an error to prevent mutation
- **Upload safety**: MIME/extension whitelist, size limits, randomized storage paths via `UploadValidationService`
- **File integrity**: SHA-256 fingerprinting for all uploads via `FingerprintService`
- **Error normalization**: All errors use standard envelope; internals never leaked

## Background Jobs

| Job | Command | Schedule |
|-----|---------|----------|
| Nightly analytics | `php think analytics:nightly` | 2:00 AM daily |
| Scheduled reports | `php think reports:scheduled` | Every minute (checks due reports) |
| Report retention cleanup | `php think retention:cleanup` | Daily (180-day artifact retention) |
| Audit retention check | `php think audit:retention` | Daily (7-year minimum, no purge) |

Jobs run via the `worker` service's scheduler loop.

## Testing

### Broad test command (PHPUnit + Playwright)

```bash
./run_tests.sh
```

The broad test command runs PHPUnit backend tests inside Docker, plus Playwright browser E2E tests via a dedicated Playwright Docker container. No host PHP, Node, or Playwright installation required — only Docker. Run `./run_tests.sh` for authoritative counts.

### Running Playwright separately (for development)

```bash
# Docker-only Playwright run (no host runtime install)
docker build -f Dockerfile.playwright -t siteops-playwright .
docker run --rm --network host \
  -v "$(pwd)/tests/Playwright/artifacts:/tests/tests/Playwright/artifacts" \
  siteops-playwright \
  sh -c "npx playwright test --reporter=list"
```

Playwright tests covering the major role-based UI flows with screenshot evidence saved to `tests/Playwright/artifacts/`.

### Test structure

**PHPUnit (see `./run_tests.sh` for current count):**
- `tests/Unit/` — password hashing, encryption, fingerprinting, hash chain, freight calculator
- `tests/Feature/Auth/` — login success/failure, CSRF rejection
- `tests/Feature/Recipe/` — full workflow (create → validate → submit → approve → publish → immutability)
- `tests/Feature/Analytics/` — dashboard metrics, refresh, rate limiting (429)
- `tests/Feature/Settlement/` — freight rules, statement lifecycle, approval/lock/reversal
- `tests/Feature/Rbac/` — site scope isolation, role-based access
- `tests/Feature/Audit/` — audit log queries, hash chain integrity
- `tests/Feature/Security/` — CSRF protection, field masking policy, audit redaction
- `tests/Feature/Search/` — cross-domain search, site-scope isolation
- `tests/Feature/Reports/` — report retention cleanup, CSV masking for restricted roles
- `tests/Feature/File/` — upload path safety, randomized filenames

**Playwright:**
- `tests/Playwright/01-auth.spec.ts` — login, logout, unauthenticated redirect, CSRF rejection
- `tests/Playwright/02-recipe-workflow.spec.ts` — editor→reviewer→publish→catalog→immutability
- `tests/Playwright/03-analytics.spec.ts` — dashboard, refresh, rate limit (429), report run, CSV export
- `tests/Playwright/04-settlement.spec.ts` — generate→submit→approve-lock→resubmit-rejected→reversal
- `tests/Playwright/05-audit-admin.spec.ts` — audit logs, admin panel, cross-site auditor access
- `tests/Playwright/06-rbac-negative.spec.ts` — RBAC denial, site scope isolation, validation errors, 401/403/404

## Seed Data

By default, `./init_db.sh` seeds representative demo data so the system is immediately reviewable:

| Data | Details |
|------|---------|
| Users | 6 users (admin, editor, reviewer, analyst, finance, auditor) — one per role |
| Sites | 3 sites (HQ, East District, West District) |
| Recipes | 4 recipes in different workflow states (published, approved, in_review, draft) |
| Orders | 20 orders with items, refunds, across sites |
| Settlements | 3 statements (approved_locked, submitted, draft) with lines |
| Freight Rules | 2 rules with distance/weight tiers |
| Audit Logs | 10 entries covering login, permissions, recipe workflow, settlements |
| Metrics | 30 daily snapshots across 5 metric types |

## Access Method

- Web UI: `http://127.0.0.1:8080`
- Health endpoint: `http://127.0.0.1:8080/api/v1/health`
- MySQL (loopback only): `127.0.0.1:3307`

## Verification Method

After startup, verify the system with both API and UI checks:

```bash
# 1) API health check
curl -s http://127.0.0.1:8080/api/v1/health

# 2) API auth check (expect 401 for invalid credentials)
curl -s -o /dev/null -w "%{http_code}\n" \
  -X POST http://127.0.0.1:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"invalid","password":"invalid"}'

# 3) Full automated verification
./run_tests.sh
```

UI verification flow:
1. Open `http://127.0.0.1:8080/login`
2. Log in with one of the demo users in the table below
3. Confirm redirect to `/dashboard`
4. Open `/recipes/editor` and `/analytics` to confirm authenticated pages render

## Demo Credentials (Authentication Required)

Authentication is required. Seeded users are role-based and all use the same password value from `seed_admin_password`.

Get demo password:

```bash
docker compose exec -T web php -r "require '/app/vendor/autoload.php'; echo bootstrap_config('seed_admin_password', '');"
```

Use that returned value as the password for every demo user below.

| Role | Username | Email | Password |
|------|----------|-------|----------|
| administrator | `admin` | `admin@example.local` | value of `seed_admin_password` |
| content_editor | `editor` | `editor@example.local` | value of `seed_admin_password` |
| reviewer | `reviewer` | `reviewer@example.local` | value of `seed_admin_password` |
| operations_analyst | `analyst` | `analyst@example.local` | value of `seed_admin_password` |
| finance_clerk | `finance` | `finance@example.local` | value of `seed_admin_password` |
| auditor | `auditor` | `auditor@example.local` | value of `seed_admin_password` |

## Current Status

Development phase is complete. All core product workflows (auth/RBAC, recipe content, analytics, reporting, settlement, audit) are implemented with real backend logic, database-backed services, and Layui-based UI surfaces. The system is ready for integrated verification and hardening.
