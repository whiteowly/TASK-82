You are working in this repo: /home/nico/Work/week-2/TASK-82/repo

Goal: fix BOTH of these:
1) `run_tests.sh` appears to "not stop running" / feels hung
2) current failing PHPUnit tests

Current status from latest run:
- Playwright now passes (32 passed), so browser image mismatch is resolved.
- `run_tests.sh` completes, but user experience is poor during long steps.
- 5 PHPUnit failures remain:

1. tests/Feature/Reports/ScheduledReportScopeTest.php:188
   testScopelessNonPrivilegedUserIsBlockedByMiddleware
   expected 200 role assignment, got 422 VALIDATION_FAILED "Username is required"

2. tests/Feature/Reports/ScheduledReportScopeTest.php:288
   testScheduledRunFailsForNonPrivilegedOwnerWithEmptyScopesAndNoArtifact
   "Insert definition failed", exit code 255 from php -r DB insert command

3. tests/Feature/Security/AuditRemediation8Test.php:104
   testRefreshStatusOwnerCanRead
   refresh request got 429 RATE_LIMITED instead of 200/202

4. tests/Feature/Security/AuditRemediation8Test.php:120
   testRefreshStatusNonOwnerDenied
   refresh request setup got 429

5. tests/Feature/Security/AuditRemediation8Test.php:141
   testRefreshStatusAdminCanReadAny
   refresh request setup got 429

Important repo clues:
- Routes are in route/app.php
- Analytics refresh status route is:
  GET /api/v1/analytics/refresh-requests/:id
  (not /analytics/refresh-status/:id)
- Admin routes include:
  POST /api/v1/admin/users/:id/roles
  POST /api/v1/admin/users/:id/site-scopes

What to do:
A) Improve run_tests UX (without changing core contract)
- Keep `./run_tests.sh` as the broad command.
- Make progress visible for long phases so it does not look stuck.
- Remove/avoid output buffering patterns that hide ongoing progress.
- Add explicit timestamps and clear "start/end + duration" for major steps.
- Keep script robust and readable.

B) Fix the 5 failing PHPUnit tests
- Update tests to match current API routes and behaviors.
- Fix test setup so rate limit on analytics refresh does not cause flakiness.
  (Use isolated user contexts or deterministic setup/reset strategy.)
- Fix ScheduledReportScopeTest role/scope assignment flow so it actually hits the intended endpoint and passes reliably.
- Replace brittle shell `exec('php -r ...')` patterns in tests if needed with safer DB setup approach already used in repo conventions.

C) Verify
- Run targeted PHPUnit files first:
  - tests/Feature/Reports/ScheduledReportScopeTest.php
  - tests/Feature/Security/AuditRemediation8Test.php
- Then run full `./run_tests.sh`
- Ensure Playwright still passes and no regressions introduced.

Output format I want from you:
1) What you changed (files + why)
2) Exact commands you ran
3) Final test results summary
4) Any remaining known issues (if any)

Constraints:
- Do not edit AGENTS.md
- Do not use destructive git commands
- Keep changes minimal, deterministic, and maintainable
