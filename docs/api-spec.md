# API Specification Plan

## 1. API conventions

- base path: `/api/v1`
- auth model: session cookie for offline LAN deployment, with CSRF protection on state-changing requests
- response success shape:

```json
{
  "data": {},
  "meta": {
    "request_id": "req-..."
  }
}
```

- response error shape:

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Validation failed",
    "details": []
  },
  "meta": {
    "request_id": "req-..."
  }
}
```

### Standard error codes

- `AUTH_INVALID_CREDENTIALS`
- `AUTH_REQUIRED`
- `FORBIDDEN_ROLE`
- `FORBIDDEN_SITE_SCOPE`
- `VALIDATION_FAILED`
- `WORKFLOW_CONFLICT`
- `RESOURCE_LOCKED`
- `RATE_LIMITED`
- `UNSUPPORTED_MEDIA_TYPE`
- `FILE_TOO_LARGE`
- `NOT_FOUND`

## 2. Authentication and RBAC

### `POST /auth/login`

- request: username, password
- success: current user profile, roles, site scopes, masking profile
- failure: invalid credentials

### `POST /auth/logout`

- invalidates session

### `GET /auth/me`

- returns current identity, roles, scopes, visible feature flags/navigation permissions

### `GET /rbac/roles`
### `GET /rbac/permissions`

- admin-only reference endpoints for role and permission management

### `POST /admin/users`
### `PATCH /admin/users/{id}`
### `POST /admin/users/{id}/roles`
### `POST /admin/users/{id}/site-scopes`

- administrator-managed user, role, and site-scope changes
- every successful change writes immutable audit and permission-change records

## 3. Recipe content workflow

### `POST /recipes`

- creates a recipe shell and initial draft version

### `GET /recipes`

- filters: site, status, tag, date range, text search

### `GET /recipes/{id}`

- returns recipe summary, current version pointers, publication state, and allowed actions

### `POST /recipes/{id}/versions`

- creates a new draft revision from current recipe state

### `PUT /recipe-versions/{id}`

- allowed only for Draft versions
- validates steps, time, units, required fields, and image references

### `POST /recipe-versions/{id}/submit-review`

- transitions Draft to In Review if validation passes

### `GET /recipe-versions/{id}/diff?against_version={n}`

- returns structured diff payload for reviewer comparison

### `POST /recipe-versions/{id}/comments`

- creates inline review comments anchored to fields, steps, or content ranges

### `POST /recipe-versions/{id}/approve`
### `POST /recipe-versions/{id}/reject`

- reviewer-only workflow actions
- approval records reviewer ID and timestamp

### `POST /recipes/{id}/publish`

- requires an approved version
- updates recipe’s published-version pointer

### `GET /catalog/recipes`
### `GET /catalog/recipes/{id}`

- internal published catalog/detail surfaces exposing only approved published versions

## 4. File upload endpoints

### `POST /files/images`

- multipart image upload
- validates JPG/PNG only and file size under 5 MB
- stores file on local disk
- computes SHA-256 fingerprint
- returns file metadata, preview URL, and duplicate indicator if applicable

## 5. Analytics endpoints

### `GET /analytics/dashboard`

- filters: date range, site, community, group leader, product
- returns required KPIs:
  - sales
  - group conversion rate
  - average order value
  - repeat purchase rate
  - refund rate
  - group leader performance
  - product popularity
- includes metric-definition payloads for drawer rendering

### `POST /analytics/refresh`

- requests scoped on-demand analytics refresh
- rate limit: 5 requests per user per hour
- rate-limit response includes next allowed time

### `GET /analytics/refresh-requests/{id}`

- returns refresh request status and outcome details

## 6. Search and reporting endpoints

### `POST /search/query`

- supports multi-dimensional filters and full-text query
- domains must include positions, companies/affiliations, participants, workflow status, content, commerce, and settlement records
- backend enforces site scope before query execution or result return

### `POST /reports/definitions`
### `GET /reports/definitions/{id}`
### `PATCH /reports/definitions/{id}`

- manages saved report definitions including dimensions, filters, columns, and metric choices
- final implementation must support configurable report outputs for participation rate, retention, pass rate, and regional distribution

### `POST /reports/definitions/{id}/run`

- queues or runs report generation

### `POST /reports/definitions/{id}/schedule`

- configures daily/weekly/monthly report generation

### `GET /reports/runs`
### `GET /reports/runs/{id}`

- returns run status, metadata, artifact path metadata, expiration date, and allowed download action

### `GET /reports/runs/{id}/download`

- downloads locally stored CSV artifact
- masking policy applies before file generation
- export audit event is mandatory

### `POST /exports/csv`

- ad hoc scoped export for searchable/reportable records
- request stores export reason/scope context for auditing if required by final implementation

## 7. Settlement and finance endpoints

### `POST /finance/freight-rules`
### `GET /finance/freight-rules`
### `PATCH /finance/freight-rules/{id}`

- manage manual distance-band, weight-tier, volume-tier, surcharge, and tax rule definitions

### `POST /finance/settlements/generate`

- generates statement draft for a site and accounting period

### `GET /finance/settlements/{id}`

- returns statement summary, line items, variances, status, and audit trail summary

### `POST /finance/settlements/{id}/reconcile`

- records variance review and reconciliation notes

### `POST /finance/settlements/{id}/submit`

- Finance Clerk submits statement for final approval

### `POST /finance/settlements/{id}/approve-final`

- Administrator-only final approval and lock action

### `POST /finance/settlements/{id}/reverse`

- creates reversal entry and links correction workflow; does not mutate locked statement in place

### `GET /finance/settlements/{id}/audit-trail`

- returns settlement-specific approval, lock, reversal, and related export history

## 8. Audit endpoints

### `GET /audit/logs`

- filters: event type, site, actor, date range, target type, target ID

### `GET /audit/logs/{id}`

- immutable audit entry detail view

### `GET /audit/exports`
### `GET /audit/approvals`
### `GET /audit/permission-changes`

- specialized audit query endpoints for high-value compliance events

## 9. Contract notes for implementation

- 401, 403, 404, validation failures, workflow conflicts, rate limits, and locked-resource failures must have consistent error envelopes
- UI-visible validation should be mirrored by server-side validation
- field masking must apply to API and CSV outputs, not just rendered pages
- all export, approval, and permission-change endpoints must emit immutable audit records
