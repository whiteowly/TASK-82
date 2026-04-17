# Community Commerce & Content Operations Management System

Offline-first full-stack operations platform for community commerce teams, covering authentication/RBAC, recipe workflow, analytics, reporting, finance settlement, and audit logging.

## Architecture & Tech Stack

* **Frontend:** Layui 2.9.x + server-rendered ThinkPHP views
* **Backend:** PHP 8.1, ThinkPHP 6.1, Nginx + PHP-FPM
* **Database:** MySQL 8.0
* **Containerization:** Docker & Docker Compose (Required)

## Project Structure

*Below is this project's structure*

```text
.
├── app/                    # Application code (controllers, services, middleware, jobs)
├── config/                 # Framework and runtime config
├── database/migrations/    # Database schema migrations
├── docker/                 # Bootstrap, entrypoint, and infra scripts
├── public/                 # Web entry point and static assets
├── tests/                  # Unit, feature, and E2E tests
├── view/                   # UI templates
├── docker-compose.yml      # Multi-container orchestration - MANDATORY
├── run_tests.sh            # Standardized test execution script - MANDATORY
└── README.md               # Project documentation - MANDATORY
```

## Prerequisites

To ensure a consistent environment, this project is designed to run entirely within containers. You must have the following installed:
* [Docker](https://docs.docker.com/get-docker/)
* [Docker Compose](https://docs.docker.com/compose/install/)

## Running the Application

1. **Build and Start Containers:**
   Use Docker Compose to build the images and spin up the entire stack in detached mode.
   ```bash
   docker-compose up --build -d
   ```

2. **Environment Configuration:**
   This project does not use a `.env` file. Runtime secrets are generated automatically by `docker/dev-bootstrap.sh` and stored in the Docker volume `bootstrap_data`. The seeded test password is fixed and documented in the Seeded Credentials table.

3. **Access the App:**
   * Frontend: `http://127.0.0.1:8080`
   * Backend API: `http://127.0.0.1:8080/api/v1`
   * API Documentation (if applicable): Not provided in this build

4. **Stop the Application:**
   ```bash
   docker-compose down -v
   ```

## Testing

All unit, integration, and E2E tests are executed via a single, standardized shell script. This script automatically handles container orchestration for the test environment.

Make sure the script is executable, then run it:

```bash
chmod +x run_tests.sh
./run_tests.sh
```

*Note: The `run_tests.sh` script returns a standard exit code (`0` for success, non-zero for failure) for CI/CD compatibility.*

## Seeded Credentials

The database is pre-seeded with test users at startup. All seeded users use a fixed testing password so testers do not need to inspect container logs.

If you previously initialized the stack before this update, reset volumes once so credentials are recreated with the fixed password:

```bash
docker-compose down -v
docker-compose up --build -d
```

| Role | Email | Password | Notes |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@example.local` | `AdminTest123!` | Login username: `admin`; full cross-site access. |
| **Content Editor** | `editor@example.local` | `AdminTest123!` | Login username: `editor`; content creation/editing. |
| **Reviewer** | `reviewer@example.local` | `AdminTest123!` | Login username: `reviewer`; review/approval workflow. |
| **Operations Analyst** | `analyst@example.local` | `AdminTest123!` | Login username: `analyst`; analytics/reporting focus. |
| **Finance Clerk** | `finance@example.local` | `AdminTest123!` | Login username: `finance`; settlement and finance operations. |
| **Auditor (Read-Only)** | `auditor@example.local` | `AdminTest123!` | Login username: `auditor`; cross-site audit visibility. |
