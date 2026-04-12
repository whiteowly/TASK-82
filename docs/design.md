# Community Commerce & Content Operations Management System Design

## 1. Scope and planning intent

This plan covers a fully offline, local-network ThinkPHP + Layui web system for a multi-site community commerce organization. The product combines recipe content workflow, operations analytics, multi-dimensional reporting/search, finance settlement management, RBAC, encrypted sensitive data handling, and long-retention immutable audit logging.

The repo runtime contract is planned as Docker-first:

- primary runtime command: `docker compose up --build`
- primary broad test command: `./run_tests.sh`
- only standard database initialization path: `./init_db.sh`

No checked-in `.env` files are allowed. Local-development runtime values must be generated or injected automatically by a dev-only bootstrap path invoked by Docker startup.

## 2. Stack and architecture defaults

### Backend

- PHP with ThinkPHP 6.1-style modular REST controllers, validators, middleware, filesystem handling, and scheduled job entrypoints
- MySQL as system of record
- worker/scheduler process for nightly analytics, scheduled reports, and retention cleanup

### Frontend

- Layui-based multi-role management interface
- custom rich-text editor wrapper with paste cleanup, inline image insertion, local image preview, and upload size warnings
- dashboard pages, review diff views, inline review comments, and metric-definition drawers

### Deployment topology

- `web`: Nginx + PHP-FPM + ThinkPHP app + Layui assets
- `worker`: scheduled jobs and async/background work
- `mysql`: relational data store
- persistent local volumes for uploaded files, generated CSV artifacts, and local bootstrap artifacts
- optional Redis for rate limiting and job locking; if omitted, MySQL lock tables are used

The system must not depend on internet access, cloud storage, map APIs, or external SaaS services.

## 3. Architecture overview

The recommended architecture is a modular monolith with clear service boundaries inside one ThinkPHP application. This keeps offline deployment simple while preserving strong domain separation.

### Core modules

1. **IAM & RBAC**
   - authentication, session lifecycle, password hashing
   - role-permission management
   - per-site scope grants
   - route/action/object authorization middleware

2. **Organization & Commerce Master Data**
   - sites, communities, group leaders, products
   - participants, company affiliations, positions
   - local transactional data sources for analytics and settlement generation

3. **Recipe Content Workflow**
   - recipe metadata, structured steps, quantities, tags, difficulty, time fields
   - immutable approved revisions and editable drafts
   - rich-text content and image attachment handling
   - review comments, review decisions, publish gate, internal published catalog/detail surface

4. **Analytics Engine**
   - metric definitions
   - nightly snapshot generation at 2:00 AM local time by default
   - on-demand refresh with per-user rate limiting (5/hour)
   - dashboard query services with filterable dimensions

5. **Search & Reporting**
   - multi-dimensional filters plus full-text search
   - configurable reports including participation rate, retention, pass rate, and regional distribution
   - report definitions and one-click CSV export
   - scheduled report generation for daily/weekly/monthly cadence
   - local artifact retention for 180 days

6. **Settlement Engine**
   - freight rule management with manual distance bands, weight tiers, volume tiers, surcharges, and tax fields
   - statement generation and variance reconciliation
   - approval routing, lock enforcement, reversal-only corrections

7. **Audit & Compliance**
   - immutable logs for exports, approvals, permission changes, and key workflow actions
   - field masking policies
   - encrypted tax ID handling
   - retention policy enforcement and auditor views

8. **File Integrity Service**
   - upload validation
   - secure storage paths on local disk
   - SHA-256 fingerprinting for duplicate detection and tamper checks

## 4. Multi-site operating model

Site is a first-class organizational dimension. Core operational records should carry `site_id` where applicable. Permission behavior:

- **Administrator**: cross-site read/write where role allows, including final settlement approval and permission management
- **Read-Only Auditor**: cross-site read-only access, including audit/report views and financial traceability views
- **Content Editor / Reviewer / Operations Analyst / Finance Clerk**: restricted to granted site scope unless a narrower feature rule applies

All queries, exports, dashboard requests, report generation, and object fetches must enforce site scope on the backend even if the UI already filters correctly.

## 5. Domain model

### Identity and scope

- `users`
- `roles`
- `permissions`
- `user_roles`
- `role_permissions`
- `user_site_scopes`

### Organization and business entities

- `sites`
- `communities`
- `group_leaders`
- `products`
- `participants`
- `companies`
- `positions`

### Recipe workflow entities

- `recipes`
- `recipe_versions`
- `recipe_steps`
- `recipe_tags`
- `recipe_version_tags`
- `recipe_images`
- `review_comments`
- `review_actions`

### Transaction and analytics entities

- `orders`
- `order_items`
- `refunds`
- `metric_snapshots`
- `analytics_refresh_requests`

### Reporting entities

- `report_definitions`
- `report_schedules`
- `report_runs`
- `report_artifacts`

### Settlement entities

- `freight_rules`
- `settlement_statements`
- `settlement_lines`
- `reconciliation_variances`
- `settlement_approvals`
- `settlement_reversals`

### Compliance entities

- `audit_logs`
- `export_logs`
- `permission_change_logs`

## 6. State and lifecycle contracts

### Recipe versions

`Draft -> In Review -> Approved | Rejected`

- Drafts are editable
- In Review versions are frozen pending reviewer action
- Approved versions become immutable
- Rejected versions stay as historical review outcomes and require a new editable draft revision for changes
- publishing is only allowed from an Approved version
- publishing records reviewer ID and approval timestamp on the approved version

### Review workflow

- reviewer compares candidate version against prior version
- reviewer can add inline comments anchored to structured sections or rich-text ranges
- reviewer either approves or returns for revision
- at least one reviewer approval is required before publishing

### Settlement statements

`Draft -> Submitted -> ApprovedLocked`

- Finance Clerk can generate, inspect, reconcile, and submit
- Administrator performs final approval
- approval locks the statement
- corrections after lock must create a reversal entry and replacement workflow; locked records are not edited in place

### Report runs

`Queued -> Running -> Succeeded | Failed -> Expired`

- generated CSV artifacts remain locally available for 180 days
- expired artifacts may be purged by retention jobs while metadata remains auditable

### Analytics refresh requests

`Requested -> Accepted | RateLimited -> Running -> Completed | Failed`

## 7. Frontend and backend crosswalk

### A. Authentication and role landing

- frontend: login screen, role-aware navigation, unauthorized-state messaging
- backend: session auth endpoints, role/site scope resolution, CSRF protection for state-changing requests

### B. Recipe editor flow

- frontend: draft editor with recipe fields, structured steps, tags, difficulty, prep/cook time, total time, paste-cleanup editor, image preview, file-size warnings, validation feedback
- backend: request validators, upload endpoints, file integrity service, draft save/update APIs, unit and boundary validation

### C. Reviewer flow

- frontend: review queue, side-by-side or structured diff view, inline comments, approve/reject controls, disabled states while submitting
- backend: version diff endpoints, review comment persistence, workflow transition guards, immutable approved-version enforcement

### D. Published recipe flow

- frontend: internal published catalog and detail surfaces exposing only approved published versions
- backend: publish endpoint, catalog query endpoints, published-version pointer rules

### E. Analytics dashboard flow

- frontend: KPI cards/charts with filters by date range, community, group leader, and product; consistent metric-definition drawer component on every metric surface
- backend: dashboard endpoint backed by snapshots, filter-aware aggregation, freshness metadata, metric definition payloads

### F. Search and reporting flow

- frontend: multi-dimensional filters and full-text search UI across positions, companies, participants, workflow status, and commerce/content/settlement records; report builder; export actions
- backend: scoped search service, report definition storage, CSV export pipeline, scheduled report endpoints, field masking on serialization and export

### G. Settlement flow

- frontend: freight rule forms, statement generation wizard, variance reconciliation grid, approval route visibility, lock status, reversal initiation flow
- backend: freight calculator, statement generation services, reconciliation APIs, approval/lock services, reversal ledger creation

### H. Audit and admin flow

- frontend: permission management, audit timeline filters, export history, approval history, read-only auditor navigation
- backend: immutable audit append service, permission-change logging, audit query API with strong scope control

## 8. Validation and user-feedback contracts

### Recipe validation

- step count between 1 and 50 inclusive
- total time between 1 and 720 minutes inclusive
- required fields produce immediate user-visible error messaging
- invalid units must be blocked and surfaced clearly
- upload type limited to JPG/PNG
- upload size limited to under 5 MB

### UI state contracts for prompt-critical flows

For recipe editing, review actions, analytics refresh, report runs, exports, and settlement approvals, frontend states must explicitly cover:

- loading
- empty
- submitting
- disabled while pending
- success
- error
- duplicate-action protection / re-entry prevention

## 9. Security contracts

### Authentication and session handling

- salted password hashing, with Argon2id preferred and bcrypt acceptable if runtime constraints require it
- session-based auth is preferred for offline LAN deployment
- CSRF protection required for state-changing requests
- auth failures return normalized errors without leaking sensitive detail

### Authorization

- enforce permissions at route level, action level, and object/site scope level
- navigation visibility must align with backend authorization, but backend remains authoritative
- export scope must be permission-aware and site-aware

### Sensitive data handling

- tax IDs stored using AES-256 encryption at rest
- encryption keys must not be stored in the database
- sensitive identifiers must be masked in UI, API serialization, and CSV export when role/scope rules require masking

### Upload safety

- MIME and extension whitelist validation
- file size validation before persistence
- randomized storage paths and no user-controlled file serving paths
- SHA-256 stored for every uploaded file

### Audit immutability

- append-only write model
- hash-chain fields (`prev_hash`, `entry_hash`) recommended to detect tampering
- export, approval, and permission-change events are mandatory audit events
- audit logs must be retained for at least 7 years with no purge permitted before that threshold; any post-threshold archival or purge policy must preserve prompt-required accountability expectations

## 10. Search, indexing, and query model

The search/reporting layer must preserve the prompt’s named searchable record dimensions:

- positions
- companies / affiliations
- participants
- workflow status
- recipe/content workflow records
- sales and operational records
- settlement and finance records

Recommended approach:

- indexed source tables for dimension filters
- FULLTEXT indexing on textual columns where available
- optional denormalized `search_documents` table to unify cross-domain text search with record references and site scoping

## 11. Background jobs and operational obligations

### Required jobs

1. nightly analytics refresh at 2:00 AM local time by default
2. on-demand analytics refresh execution with per-user rate limiting at 5 per hour
3. scheduled daily/weekly/monthly report generation
4. report artifact retention cleanup for artifacts older than 180 days
5. audit-log retention enforcement with no purge before 7 years and post-threshold handling that preserves accountability expectations

### Seed data

Representative local seed data is required so a reviewer can exercise:

- recipe draft, review, and publish flows
- dashboard metrics and filters
- cross-domain search
- report generation/export
- freight rule management
- statement generation, approval, and reversal traces
- audit and permission-change history

## 12. Logging and audit contracts

### Required event categories

- login success and failure
- permission changes and role assignments
- export start and completion
- recipe review decisions and publishing
- analytics refresh requests and outcomes
- settlement submit/approve/lock/reversal

### Required log fields

- actor ID
- actor role
- site context
- target type and target ID
- event type
- request ID
- timestamp
- minimal redacted payload summary

Logs must never expose raw decrypted tax IDs, unmasked sensitive identifiers beyond role entitlement, or secret material.

## 13. Runtime, bootstrap, and README implications

The repo’s README must remain self-sufficient and clearly explain:

- what the system is and the main role-based capabilities
- that the primary runtime command is `docker compose up --build`
- that the primary broad test command is `./run_tests.sh`
- that `./init_db.sh` is the standard database initialization path
- that the application runs fully offline on a local network
- that a local-development bootstrap path generates runtime values without checked-in `.env` files
- any default seed/demo data behavior
- major repo entry points, route/module boundaries, and test entry points for static review

## 14. Recommended implementation slices

1. runtime skeleton, DB bootstrap, auth, and baseline schema
2. RBAC + site-scope enforcement + audit skeleton + baseline masking and encryption services
3. recipe authoring + upload integrity + validation
4. review/diff/comments/publish workflow
5. analytics snapshots + dashboard APIs + metric-definition drawer contract
6. on-demand analytics refresh + rate limiting
7. cross-domain search + configurable report definitions (participation rate, retention, pass rate, regional distribution)
8. CSV export + scheduled reports + 180-day retention with scope enforcement, masking, and immutable export audit logging built into the first implementation pass
9. freight rules + settlement generation + reconciliation with encrypted tax-ID handling and audit coverage in the initial slice
10. admin approval lock + reversal-only correction + immutable compliance guarantees
11. seed/demo coverage + end-to-end verification

## 15. Main planning risks to watch during implementation

- accidental scope loss on multi-site enforcement
- under-planned full-text search across all required record domains
- missing immutable behavior for approved recipe revisions and approved settlement statements
- missing export masking and audit coverage
- insufficient indexing for analytics/search/reporting scale
- hidden drift from offline/no-cloud constraints
