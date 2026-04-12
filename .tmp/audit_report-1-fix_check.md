# Fix Check Report: Remaining 6 Issues

Date: 2026-04-12
Mode: Static-only verification (no runtime/test execution)

## Overall Result

All 6 targeted issues are **resolved in code** based on static evidence.

## Detailed Status

### 1) Sensitive export masking policy mismatch
- Status: **Fixed**
- What changed:
  - Masking policy now includes `email`, `national_id`, and `bank_account` in addition to existing sensitive fields.
  - Export path still applies masking through `FieldMaskingService` for sensitive columns.
- Evidence:
  - `app/service/security/FieldMaskingService.php:17`
  - `app/service/security/FieldMaskingService.php:18`
  - `app/service/security/FieldMaskingService.php:19`
  - `app/controller/api/v1/ReportController.php:418`
- Test evidence added:
  - `tests/Feature/Security/FieldMaskingTest.php:71`
  - `tests/Feature/Security/FieldMaskingTest.php:118`

### 2) Ingredient quantity/unit validated but not durably persisted
- Status: **Fixed**
- What changed:
  - `updateVersion` now persists `ingredients` into structured `content_json`.
  - `createVersion` service also stores `ingredients` into `content_json`.
  - Recipe read API rehydrates `ingredients` from version `content_json` to stable response field.
- Evidence:
  - `app/controller/api/v1/RecipeController.php:205`
  - `app/controller/api/v1/RecipeController.php:223`
  - `app/controller/api/v1/RecipeController.php:114`
  - `app/service/recipe/RecipeService.php:238`
  - `app/service/recipe/RecipeService.php:248`
- Test evidence added:
  - `tests/Feature/Security/AuditRemediation8Test.php:16`
  - `tests/Feature/Security/AuditRemediation8Test.php:53`

### 3) Analytics refresh status missing ownership check
- Status: **Fixed**
- What changed:
  - `refreshStatus` now denies non-admin access unless `status.user_id == current user`.
- Evidence:
  - `app/controller/api/v1/AnalyticsController.php:74`
  - `app/controller/api/v1/AnalyticsController.php:77`
- Supporting tests added:
  - `tests/Feature/Security/AuditRemediation8Test.php:97`
  - `tests/Feature/Security/AuditRemediation8Test.php:134`
  - Note: non-owner denial test currently uses editor-role denial path (`tests/Feature/Security/AuditRemediation8Test.php:129`), which is weaker than a same-role different-user ownership test.

### 4) Cross-site upload test not truly cross-site
- Status: **Fixed**
- What changed:
  - Test now creates a real out-of-scope recipe/version in site 3 using admin.
  - Editor (scoped to sites 1/2) upload to that version now asserts 403.
- Evidence:
  - `tests/Feature/Security/AuditRemediation7Test.php:40`
  - `tests/Feature/Security/AuditRemediation7Test.php:43`
  - `tests/Feature/Security/AuditRemediation7Test.php:53`

### 5) Critical remediation suite had skip path
- Status: **Fixed**
- What changed:
  - Deterministic fixture creation replaced prior skip behavior in upload-denial test.
- Evidence:
  - `tests/Feature/Security/AuditRemediation7Test.php:18`
  - `tests/Feature/Security/AuditRemediation7Test.php:20`
  - `tests/Feature/Security/AuditRemediation7Test.php:23`
- Static confirmation:
  - No `markTestSkipped` call remains in this remediation suite.

### 6) Report runs pagination metadata incomplete
- Status: **Fixed**
- What changed:
  - `listRuns` response now includes `total` and `total_pages` with `page` and `per_page`.
- Evidence:
  - `app/controller/api/v1/ReportController.php:256`
  - `app/controller/api/v1/ReportController.php:265`
  - `app/service/report/ReportService.php:330`
- Test evidence added:
  - `tests/Feature/Security/AuditRemediation8Test.php:153`
  - `tests/Feature/Security/AuditRemediation8Test.php:162`

## Conclusion

From static inspection, the requested 6 issues are fixed.

## Manual Verification Recommended

- Run targeted feature tests for `AuditRemediation7Test` and `AuditRemediation8Test` to confirm runtime behavior.
- Add one stronger ownership test for analytics refresh status using two users with the same authorized role, if feasible in seed fixtures.
