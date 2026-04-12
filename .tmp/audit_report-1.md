# Static Delivery Acceptance & Architecture Audit (Rerun)

## 1. Verdict
- Overall conclusion: **Partial Pass**

## 2. Scope and Static Verification Boundary
- Reviewed: `README.md`, route registration, middleware, API controllers, service layer, migration schema, key Layui views, and test suites under `tests/`.
- Not reviewed exhaustively: every seed-data branch in `app/job/SeedAdminCommand.php` and every UI style detail beyond requirement-linked checks.
- Intentionally not executed: app runtime, Docker, DB migrations, worker jobs, PHPUnit/Playwright.
- Manual verification required for: browser interaction quality, scheduler timing behavior (2:00 AM/periodic jobs), and end-to-end runtime correctness.

## 3. Repository / Requirement Mapping Summary
- Prompt core goal: offline ThinkPHP + Layui system for recipe workflow, analytics/search/reporting, and finance settlement/audit across multi-site RBAC roles.
- Core mapped areas: auth/RBAC (`app/controller/api/v1/AuthController.php`, `app/middleware/*`), recipe workflow (`app/controller/api/v1/RecipeController.php`, `app/service/recipe/*`, `view/recipe/editor.php`), analytics/reporting (`app/controller/api/v1/AnalyticsController.php`, `app/controller/api/v1/ReportController.php`), settlement (`app/controller/api/v1/SettlementController.php`, `app/service/settlement/*`), audit/security (`app/service/audit/*`, `app/service/security/*`), routes (`route/app.php`), schema (`database/migrations/20240101000000_create_schema.php`).
- Rerun status change: prior reconcile-endpoint blocker is fixed (`app/controller/api/v1/SettlementController.php:230`, `app/service/settlement/ReconciliationService.php:34`).

## 4. Section-by-section Review

### 1. Hard Gates

#### 1.1 Documentation and static verifiability
- Conclusion: **Pass**
- Rationale: startup/run/test/init commands are explicit and entry points/module structure are statically traceable.
- Evidence: `README.md:10`, `README.md:108`, `README.md:110`, `README.md:111`, `route/app.php:6`, `route/app.php:14`, `README.md:67`

#### 1.2 Material deviation from Prompt
- Conclusion: **Partial Pass**
- Rationale: major scenario is implemented, but recipe quantity/unit modeling is still incomplete at persistence level (validated in one path but not durably stored as structured data).
- Evidence: `view/recipe/editor.php:463`, `app/controller/api/v1/RecipeController.php:170`, `app/controller/api/v1/RecipeController.php:196`, `database/migrations/20240101000000_create_schema.php:182`

### 2. Delivery Completeness

#### 2.1 Coverage of core explicit requirements
- Conclusion: **Partial Pass**
- Rationale: core modules exist (recipe workflow, analytics, reports, settlement, audit), image constraints/dup detection are implemented, but sensitive-field protection and ingredient persistence have gaps.
- Evidence: `app/controller/api/v1/FileController.php:15`, `app/controller/api/v1/FileController.php:69`, `app/controller/api/v1/ReportController.php:411`, `app/service/security/FieldMaskingService.php:12`, `app/controller/api/v1/RecipeController.php:196`

#### 2.2 End-to-end 0->1 deliverable (not demo fragment)
- Conclusion: **Pass**
- Rationale: repository is a full-stack project with docs, schema, route/controller/service decomposition, and broad tests.
- Evidence: `README.md:69`, `database/migrations/20240101000000_create_schema.php:13`, `route/app.php:14`, `tests/Feature/Recipe/WorkflowTest.php:24`, `tests/Unit/Security/PasswordHashServiceTest.php:9`

### 3. Engineering and Architecture Quality

#### 3.1 Structure and module decomposition
- Conclusion: **Pass**
- Rationale: clear modular boundaries and domain separation across controller/service/model/job layers.
- Evidence: `README.md:50`, `app/controller/api/v1/RecipeController.php:17`, `app/service/settlement/StatementService.php:9`, `app/service/report/ReportService.php:10`, `config/console.php:4`

#### 3.2 Maintainability and extensibility
- Conclusion: **Partial Pass**
- Rationale: service-layer design is maintainable, but data-contract drift exists (ingredients collected/validated but not persisted; report runs list pagination metadata is minimal).
- Evidence: `view/recipe/editor.php:463`, `app/controller/api/v1/RecipeController.php:196`, `app/controller/api/v1/ReportController.php:255`, `app/controller/api/v1/ReportController.php:257`

### 4. Engineering Details and Professionalism

#### 4.1 Error handling, logging, validation, API design
- Conclusion: **Partial Pass**
- Rationale: normalized error envelope and request-id tracing exist, but sensitive export masking policy is incomplete and some object-level checks are missing.
- Evidence: `app/ExceptionHandle.php:53`, `app/middleware/RequestIdMiddleware.php:23`, `app/controller/api/v1/ReportController.php:411`, `app/service/security/FieldMaskingService.php:12`, `app/controller/api/v1/AnalyticsController.php:61`

#### 4.2 Product/service shape vs demo
- Conclusion: **Pass**
- Rationale: delivery shape is a real application with role flows, persistence, retention jobs, and security/audit services.
- Evidence: `route/app.php:30`, `route/app.php:55`, `route/app.php:79`, `app/job/NightlyAnalyticsJob.php:16`, `app/job/RetentionCleanupJob.php:20`

### 5. Prompt Understanding and Requirement Fit

#### 5.1 Understanding of business goal and constraints
- Conclusion: **Partial Pass**
- Rationale: role-separated multi-domain system is implemented and many constraints are reflected, but requirement-fit gaps remain for structured recipe quantities and comprehensive sensitive-field masking.
- Evidence: `route/app.php:17`, `route/app.php:102`, `app/controller/api/v1/FileController.php:45`, `app/validate/RecipeVersionValidate.php:16`, `app/controller/api/v1/RecipeController.php:196`, `app/service/security/FieldMaskingService.php:12`

### 6. Aesthetics (frontend)

#### 6.1 Visual/interaction quality fit
- Conclusion: **Pass**
- Rationale: functional segmentation, feedback states, and interactive cues are present for recipe editing/upload/review and dashboard cards.
- Evidence: `view/recipe/editor.php:210`, `view/recipe/editor.php:455`, `view/recipe/editor.php:507`, `view/analytics/dashboard.php:61`, `public/static/css/app.css:107`
- Manual verification note: final rendering/perceived quality requires browser validation.

## 5. Issues / Suggestions (Severity-Rated)

### High

1) **Severity: High**
- Title: Sensitive export masking policy omits declared sensitive fields
- Conclusion: **Fail**
- Evidence: `app/controller/api/v1/ReportController.php:411`, `app/service/security/FieldMaskingService.php:12`, `app/service/security/FieldMaskingService.php:36`
- Impact: CSV/API masking can leak values for `email`, `national_id`, and `bank_account` despite these being treated as sensitive in export flow.
- Minimum actionable fix: extend `FieldMaskingService::MASKING_POLICY` for all sensitive fields referenced by export/search surfaces and add tests for each field-by-role expectation.

2) **Severity: High**
- Title: Recipe ingredient quantity/unit data is validated but not durably stored as structured content
- Conclusion: **Fail**
- Evidence: `view/recipe/editor.php:463`, `app/controller/api/v1/RecipeController.php:170`, `app/controller/api/v1/RecipeController.php:196`, `database/migrations/20240101000000_create_schema.php:182`
- Impact: prompt-required structured quantity/unit recipe content is not reliably persisted; saved drafts can lose ingredient structure.
- Minimum actionable fix: persist `ingredients` into a structured store (new `recipe_ingredients` table or explicit JSON contract saved server-side), and return it in read APIs.

### Medium

3) **Severity: Medium**
- Title: Analytics refresh status lacks object-level ownership check
- Conclusion: **Partial Fail**
- Evidence: `app/controller/api/v1/AnalyticsController.php:61`, `app/service/analytics/AnalyticsService.php:216`
- Impact: authorized analyst/admin users can query refresh request IDs without verifying request ownership/site relation.
- Minimum actionable fix: enforce owner-or-privileged access in controller/service (`request.user_id == current user` unless admin).

4) **Severity: Medium**
- Title: Cross-site upload authorization test does not actually validate cross-site deny path
- Conclusion: **Insufficient Coverage**
- Evidence: `tests/Feature/Security/AuditRemediation7Test.php:37`, `tests/Feature/Security/AuditRemediation7Test.php:44`, `tests/Feature/Security/AuditRemediation7Test.php:45`
- Impact: test asserts 404 on nonexistent version, so true cross-site 403 behavior can regress undetected.
- Minimum actionable fix: create real out-of-scope version fixture and assert `403 FORBIDDEN_SITE_SCOPE`.

5) **Severity: Medium**
- Title: Security test introduces skip path in critical remediation suite
- Conclusion: **Insufficient Coverage**
- Evidence: `tests/Feature/Security/AuditRemediation7Test.php:20`
- Impact: suite can bypass a key authorization check depending on data shape, reducing confidence in remediation.
- Minimum actionable fix: replace conditional skip with deterministic fixture creation.

6) **Severity: Medium**
- Title: Report runs list pagination metadata lacks total/total_pages
- Conclusion: **Partial Fail**
- Evidence: `app/controller/api/v1/ReportController.php:255`, `app/controller/api/v1/ReportController.php:257`, `app/service/report/ReportService.php:328`
- Impact: API consumers cannot fully paginate deterministically from list response.
- Minimum actionable fix: include `total` and `total_pages` from service result in response pagination object.

## 6. Security Review Summary

- Authentication entry points: **Pass**
  - Evidence: `route/app.php:9`, `route/app.php:10`, `app/middleware/AuthMiddleware.php:19`, `app/controller/api/v1/AuthController.php:15`.
  - Reasoning: login/logout/me + session-based auth are explicitly implemented with guard middleware.

- Route-level authorization: **Partial Pass**
  - Evidence: `route/app.php:17`, `route/app.php:102`, `app/controller/api/v1/SettlementController.php:67`, `app/controller/api/v1/SearchController.php:13`.
  - Reasoning: major route guards improved, but some protections are function-level only and not uniformly policy-driven.

- Object-level authorization: **Partial Pass**
  - Evidence: upload version->recipe->site enforcement in `app/controller/api/v1/FileController.php:45`; missing ownership check in analytics refresh status `app/controller/api/v1/AnalyticsController.php:61`.
  - Reasoning: key gap fixed for uploads, but not all object IDs are ownership-checked.

- Function-level authorization: **Pass**
  - Evidence: `app/controller/api/v1/SettlementController.php:71`, `app/controller/api/v1/SettlementController.php:186`, `app/controller/api/v1/SearchController.php:17`.
  - Reasoning: explicit role checks exist on sensitive functions and deny with 403.

- Tenant / user isolation: **Partial Pass**
  - Evidence: `app/middleware/SiteScopeMiddleware.php:35`, `app/controller/BaseController.php:47`, `app/controller/api/v1/FileController.php:47`.
  - Reasoning: site-scope pattern is broad; residual ownership gaps remain for specific status endpoints.

- Admin / internal / debug protection: **Pass**
  - Evidence: `route/app.php:18`, `route/app.php:20`, `route/app.php:28`, `route/app.php:102`.
  - Reasoning: admin/auditor-only internal surfaces are now explicitly protected.

## 7. Tests and Logging Review

- Unit tests: **Pass**
  - Evidence: `phpunit.xml:8`, `tests/Unit/Security/PasswordHashServiceTest.php:9`, `tests/Unit/Security/TaxIdCryptoServiceTest.php:9`, `tests/Unit/Audit/HashChainTest.php:9`.

- API / integration tests: **Partial Pass**
  - Evidence: `phpunit.xml:12`, `tests/Feature/Recipe/WorkflowTest.php:24`, `tests/Feature/Settlement/SettlementFlowTest.php:18`, `tests/Feature/Security/AuditRemediation7Test.php:11`.
  - Reasoning: broad coverage exists, but critical scenarios still have weak assertions/skip paths.

- Logging categories / observability: **Pass**
  - Evidence: `config/log.php:4`, `app/ExceptionHandle.php:73`, `app/job/ScheduledReportJob.php:23`, `app/service/audit/AuditService.php:22`.

- Sensitive-data leakage risk in logs / responses: **Partial Pass**
  - Evidence: `tests/Feature/Security/AuditRedactionTest.php:27`, `app/ExceptionHandle.php:78`, `app/controller/api/v1/ReportController.php:411`, `app/service/security/FieldMaskingService.php:12`.
  - Reasoning: redaction intent exists, but policy-field mismatch leaves leakage risk in exports.

## 8. Test Coverage Assessment (Static Audit)

### 8.1 Test Overview
- Unit and feature tests exist under PHPUnit (`phpunit.xml`).
- Frameworks: PHPUnit + Playwright (configured, not executed here).
- Test entry points: `./run_tests.sh` and PHPUnit suites in `phpunit.xml`.
- Documentation includes broad test command and structure.
- Evidence: `phpunit.xml:8`, `phpunit.xml:12`, `run_tests.sh:85`, `README.md:170`, `playwright.config.ts:4`

### 8.2 Coverage Mapping Table

| Requirement / Risk Point | Mapped Test Case(s) | Key Assertion / Fixture / Mock | Coverage Assessment | Gap | Minimum Test Addition |
|---|---|---|---|---|---|
| Recipe happy path (create->review->approve->publish) | `tests/Feature/Recipe/WorkflowTest.php:162` | publish + catalog assertions `tests/Feature/Recipe/WorkflowTest.php:205` | sufficient | None major | Maintain regression |
| Recipe validation boundaries (steps/time) | `tests/Feature/Recipe/WorkflowTest.php:83`, `tests/Feature/Recipe/WorkflowTest.php:127` | 422 assertions on limits | basically covered | Ingredient persistence not asserted | Add read-after-save assertion for ingredients |
| Ingredient unit/quantity validation | `tests/Feature/Security/AuditRemediation7Test.php:114`, `tests/Feature/Security/AuditRemediation7Test.php:128` | invalid unit/qty => 422 | basically covered | Only update path tested | Add createVersion + submitReview validation tests |
| Auth required / invalid credentials | `tests/Feature/Auth/LoginTest.php:49`, `tests/Feature/Security/CsrfProtectionTest.php:10` | 401/403 checks | sufficient | None major | Keep |
| Route/function role authorization | `tests/Feature/Security/RoleIsolationTest.php:89`, `tests/Feature/Security/AuditRemediation7Test.php:51`, `tests/Feature/Security/AuditRemediation7Test.php:87` | deny/allow role assertions | basically covered | Analytics refresh-status ownership absent | Add owner vs non-owner refreshStatus tests |
| Object-level auth for file upload | `tests/Feature/Security/AuditRemediation7Test.php:15`, `tests/Feature/Security/AuditRemediation7Test.php:37` | disallowed role 403; cross-site case uses nonexistent ID | insufficient | No real cross-site object fixture | Add deterministic out-of-scope version test expecting 403 |
| Site-scope isolation | `tests/Feature/Rbac/SiteScopeTest.php:114`, `tests/Feature/Search/CrossDomainSearchTest.php:99` | scoped site assertions | basically covered | Not all object endpoints covered | Extend to analytics/status and report artifacts |
| 404 envelope | `tests/Feature/Api/ErrorEnvelopeTest.php:10` | NOT_FOUND + request_id | sufficient | None | Keep |
| Duplicate upload detection | `tests/Feature/File/UploadPersistenceTest.php:53` | second upload duplicate=true | sufficient | None | Keep |
| Sensitive-data masking/export | `tests/Feature/Reports/CsvMaskingTest.php:16`, `tests/Feature/Search/SearchMaskingTest.php:33` | phone masking assertions | insufficient | Missing email/national_id/bank_account cases | Add masking tests for each sensitive field |

### 8.3 Security Coverage Audit
- Authentication coverage: **Sufficient** for login success/failure and unauthenticated denial paths (`tests/Feature/Auth/LoginTest.php:10`, `tests/Feature/Auth/LoginTest.php:49`).
- Route authorization coverage: **Basically covered** for many role gates, including remediation suite checks (`tests/Feature/Security/AuditRemediation7Test.php:51`, `tests/Feature/Security/AuditRemediation7Test.php:171`).
- Object-level authorization coverage: **Insufficient**; cross-site upload test does not validate real cross-site object and refresh-status ownership has no tests (`tests/Feature/Security/AuditRemediation7Test.php:44`, `app/controller/api/v1/AnalyticsController.php:61`).
- Tenant/data isolation coverage: **Basically covered** for list/search flows (`tests/Feature/Rbac/SiteScopeTest.php:114`, `tests/Feature/Search/CrossDomainSearchTest.php:87`) but not exhaustive for all object endpoints.
- Admin/internal protection coverage: **Basically covered** with admin-only RBAC metadata tests (`tests/Feature/Security/AuditRemediation7Test.php:171`, `tests/Feature/Security/AuditRemediation7Test.php:185`).

### 8.4 Final Coverage Judgment
**Partial Pass**

- Covered well: core recipe workflow, baseline auth/CSRF, major role-denial paths, settlement lifecycle.
- Not sufficiently covered: true cross-site object authorization case, ownership checks on analytics refresh status, and full sensitive-field masking matrix.
- Boundary: current tests can still pass while significant data-exposure/access-control defects remain.

## 9. Final Notes
- This audit is static-only and does not claim runtime success.
- Conclusions are tied to traceable file/line evidence.
- Rerun shows material improvement from prior state (blocker removed), but remaining high/medium issues keep final result below Pass.
