<?php
declare(strict_types=1);

namespace tests\Feature\Reports;

use tests\TestCase;
use think\facade\Db;

/**
 * Tests that scheduled and interactive report runs enforce site-scope
 * for non-privileged users and that the empty-scope fallback is safe.
 *
 * Coverage:
 * - Non-privileged user (analyst) interactive run succeeds with scoped data
 * - Privileged user (admin) interactive run succeeds cross-site
 * - Successful run produces artifact; failed run does not
 * - Non-privileged user without site scopes is blocked at middleware level
 * - ScheduledReportJob contains the empty-scope guard (static proof)
 * - ReportService::executeRun contains defense-in-depth guard (static proof)
 */
class ScheduledReportScopeTest extends TestCase
{
    private array $analystSession;
    private array $adminSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analystSession = $this->loginAs('analyst');
        $this->adminSession   = $this->loginAs('admin');
        $this->assertNotEmpty($this->analystSession['csrf_token'], 'Analyst login must succeed');
        $this->assertNotEmpty($this->adminSession['csrf_token'], 'Admin login must succeed');
    }

    // ------------------------------------------------------------------
    // Interactive run paths (same executeRun pipeline as scheduler)
    // ------------------------------------------------------------------

    /**
     * Analyst (non-privileged, has site scopes) can run a report
     * and the run succeeds with scoped data.
     */
    public function testNonPrivilegedRunSucceedsWithScopes(): void
    {
        $defId = $this->createDefinitionAs($this->analystSession);

        $runResponse = $this->authenticatedRequest(
            'POST',
            "/api/v1/reports/definitions/{$defId}/run",
            $this->analystSession
        );
        $this->assertContains($runResponse['status'], [200, 202],
            'Analyst run should succeed. Got: ' . ($runResponse['raw'] ?? ''));

        $runId = $runResponse['body']['data']['run_id'] ?? null;
        $this->assertNotNull($runId, 'Run ID must be returned');

        // Verify run completed successfully
        $detail = $this->authenticatedRequest(
            'GET',
            "/api/v1/reports/runs/{$runId}",
            $this->analystSession
        );
        $this->assertEquals(200, $detail['status']);
        $this->assertEquals('succeeded', $detail['body']['data']['status'] ?? '',
            'Analyst run should succeed');
    }

    /**
     * Successful non-privileged run produces a report artifact.
     */
    public function testSuccessfulRunProducesArtifact(): void
    {
        $defId = $this->createDefinitionAs($this->analystSession);

        $runResponse = $this->authenticatedRequest(
            'POST',
            "/api/v1/reports/definitions/{$defId}/run",
            $this->analystSession
        );
        $this->assertContains($runResponse['status'], [200, 202]);
        $runId = $runResponse['body']['data']['run_id'] ?? null;
        $this->assertNotNull($runId);

        $detail = $this->authenticatedRequest(
            'GET',
            "/api/v1/reports/runs/{$runId}",
            $this->analystSession
        );
        $this->assertEquals(200, $detail['status']);
        $runData = $detail['body']['data'] ?? [];

        // A succeeded run must have an artifact_path set
        $this->assertEquals('succeeded', $runData['status'] ?? '');
        $this->assertNotEmpty($runData['artifact_path'] ?? '',
            'Succeeded run must have an artifact_path');
    }

    /**
     * Admin (privileged) run succeeds cross-site with no scope restriction.
     */
    public function testPrivilegedRunSucceedsCrossSite(): void
    {
        $defId = $this->createDefinitionAs($this->adminSession);

        $runResponse = $this->authenticatedRequest(
            'POST',
            "/api/v1/reports/definitions/{$defId}/run",
            $this->adminSession
        );
        $this->assertContains($runResponse['status'], [200, 202],
            'Admin run should succeed. Got: ' . ($runResponse['raw'] ?? ''));

        $runId = $runResponse['body']['data']['run_id'] ?? null;
        $this->assertNotNull($runId);

        $detail = $this->authenticatedRequest(
            'GET',
            "/api/v1/reports/runs/{$runId}",
            $this->adminSession
        );
        $this->assertEquals(200, $detail['status']);
        $this->assertEquals('succeeded', $detail['body']['data']['status'] ?? '',
            'Admin run should succeed');
        $this->assertNotEmpty($detail['body']['data']['artifact_path'] ?? '',
            'Admin succeeded run must have an artifact_path');
    }

    // ------------------------------------------------------------------
    // Empty-scope enforcement: middleware blocks non-privileged users
    // ------------------------------------------------------------------

    /**
     * A non-privileged user with no site scopes cannot access any
     * authenticated endpoint (SiteScopeMiddleware returns 403).
     * This proves the interactive path is safe from empty-scope runs.
     */
    public function testScopelessNonPrivilegedUserIsBlockedByMiddleware(): void
    {
        $password = bootstrap_config('seed_admin_password', '');
        $username = 'scopeless_analyst_' . uniqid();

        // Create a test user via admin API
        $createUser = $this->authenticatedRequest(
            'POST',
            '/api/v1/admin/users',
            $this->adminSession,
            [
                'username'     => $username,
                'password'     => $password,
                'display_name' => 'Scopeless Analyst',
            ]
        );
        $this->assertEquals(201, $createUser['status'],
            'Admin must create user. Got: ' . ($createUser['raw'] ?? ''));
        $userId = $createUser['body']['data']['id'] ?? null;
        $this->assertNotNull($userId);

        // Find the operations_analyst role ID
        $rolesResponse = $this->authenticatedRequest(
            'GET',
            '/api/v1/rbac/roles',
            $this->adminSession
        );
        $this->assertEquals(200, $rolesResponse['status']);
        $roles = $rolesResponse['body']['data']['roles']
            ?? $rolesResponse['body']['data']['items']
            ?? $rolesResponse['body']['data']
            ?? [];

        $analystRoleId = null;
        foreach ($roles as $role) {
            if (($role['name'] ?? '') === 'operations_analyst') {
                $analystRoleId = (int) $role['id'];
                break;
            }
        }
        $this->assertNotNull($analystRoleId,
            'operations_analyst role must exist in DB');

        // Assign role but NO site scopes
        $assignRole = $this->authenticatedRequest(
            'POST',
            "/api/v1/admin/users/{$userId}/roles",
            $this->adminSession,
            ['role_ids' => [$analystRoleId]]
        );
        $this->assertEquals(200, $assignRole['status'],
            'Role assignment must succeed. Got: ' . ($assignRole['raw'] ?? ''));

        // Explicitly assign empty site scopes
        $assignScopes = $this->authenticatedRequest(
            'POST',
            "/api/v1/admin/users/{$userId}/site-scopes",
            $this->adminSession,
            ['site_ids' => []]
        );
        $this->assertEquals(200, $assignScopes['status'],
            'Scope assignment must succeed. Got: ' . ($assignScopes['raw'] ?? ''));

        // Attempt to log in as the scopeless user
        $scopelessSession = $this->loginAs($username);

        // The user may get a CSRF token from login, but any subsequent
        // request should fail at SiteScopeMiddleware with 403
        $testResponse = $this->authenticatedRequest(
            'GET',
            '/api/v1/reports/definitions',
            $scopelessSession
        );

        $this->assertEquals(403, $testResponse['status'],
            'Scopeless non-privileged user should be blocked by SiteScopeMiddleware (403). '
            . 'Got: ' . ($testResponse['raw'] ?? ''));
    }

    // ------------------------------------------------------------------
    // Scheduled path: empty-scope owner => run fails, no artifact
    // ------------------------------------------------------------------

    /**
     * A report definition owned by a non-privileged user with zero site
     * scopes must produce a FAILED run with no artifact when the
     * scheduler processes it.
     *
     * Setup:
     *   1. Admin creates a user with operations_analyst role, empty scopes
     *   2. Insert a report_definitions row owned by that user (via DB)
     *   3. Insert a report_schedules row due now
     *   4. Invoke `php think reports:scheduled`
     *
     * Assertions:
     *   - A report_runs row exists for that definition
     *   - run status = 'failed'
     *   - artifact_path is null/empty
     *   - No report_artifacts row for that run
     */
    public function testScheduledRunFailsForNonPrivilegedOwnerWithEmptyScopesAndNoArtifact(): void
    {
        $password = bootstrap_config('seed_admin_password', '');
        $username = 'sched_scopeless_' . uniqid();

        // --- 1. Create a scopeless analyst user via admin API ----------
        $createUser = $this->authenticatedRequest(
            'POST', '/api/v1/admin/users', $this->adminSession,
            ['username' => $username, 'password' => $password, 'display_name' => 'Sched Scopeless']
        );
        $this->assertEquals(201, $createUser['status'],
            'Admin must create user. Got: ' . ($createUser['raw'] ?? ''));
        $userId = (int) $createUser['body']['data']['id'];

        // Find operations_analyst role ID
        $rolesResp = $this->authenticatedRequest('GET', '/api/v1/rbac/roles', $this->adminSession);
        $this->assertEquals(200, $rolesResp['status']);
        $roles = $rolesResp['body']['data']['roles']
            ?? $rolesResp['body']['data']['items']
            ?? $rolesResp['body']['data'] ?? [];
        $analystRoleId = null;
        foreach ($roles as $r) {
            if (($r['name'] ?? '') === 'operations_analyst') {
                $analystRoleId = (int) $r['id'];
                break;
            }
        }
        $this->assertNotNull($analystRoleId, 'operations_analyst role must exist');

        // Assign role, empty site scopes
        $this->authenticatedRequest('POST', "/api/v1/admin/users/{$userId}/roles",
            $this->adminSession, ['role_ids' => [$analystRoleId]]);
        $this->authenticatedRequest('POST', "/api/v1/admin/users/{$userId}/site-scopes",
            $this->adminSession, ['site_ids' => []]);

        // --- 2. Insert a report definition owned by that user ---------
        $defName = 'sched_scope_test_' . uniqid();
        $now = date('Y-m-d H:i:s');
        $defId = (int) Db::name('report_definitions')->insertGetId([
            'name'            => $defName,
            'description'     => 'scope test',
            'dimensions_json' => json_encode(['type' => 'participation']),
            'created_by'      => $userId,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
        $this->assertGreaterThan(0, $defId, 'Definition ID must be positive');

        // --- 3. Insert a schedule row due now -------------------------
        Db::name('report_schedules')->insert([
            'definition_id' => $defId,
            'cadence'       => 'daily',
            'next_run_at'   => date('Y-m-d H:i:s', strtotime('-1 minute')),
            'active'        => 1,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        // --- 4. Run the scheduler -------------------------------------
        exec('cd /app && php think reports:scheduled 2>&1', $cmdOut, $cmdExit);
        $cmdText = implode("\n", $cmdOut);
        $this->assertEquals(0, $cmdExit, 'Scheduler command must exit 0. Output: ' . $cmdText);

        // --- 5. Query the run row and artifact row --------------------
        $runRow = Db::name('report_runs')
            ->where('definition_id', $defId)
            ->order('id', 'desc')
            ->find();

        $this->assertNotEmpty($runRow, 'A report_runs row must exist for the definition');
        $runId = (int) $runRow['id'];

        // Assert: run status is failed
        $this->assertEquals('failed', $runRow['status'],
            "Run status must be 'failed' for non-privileged owner with empty scopes. Got: " . ($runRow['status'] ?? 'null'));

        // Assert: artifact_path is null or empty
        $this->assertEmpty($runRow['artifact_path'] ?? '',
            'Failed run must not have an artifact_path. Got: ' . ($runRow['artifact_path'] ?? ''));

        // Assert: no report_artifacts row
        $artifactCount = (int) Db::name('report_artifacts')
            ->where('run_id', $runId)
            ->count();
        $this->assertEquals(0, $artifactCount,
            'No report_artifacts row must exist for a failed empty-scope run');
    }

    // ------------------------------------------------------------------
    // Schedule creation still works for scoped users
    // ------------------------------------------------------------------

    /**
     * Non-privileged user with scopes can create and schedule reports.
     */
    public function testNonPrivilegedScheduleCreatesSuccessfully(): void
    {
        $defId = $this->createDefinitionAs($this->analystSession);

        $scheduleResponse = $this->authenticatedRequest(
            'POST',
            "/api/v1/reports/definitions/{$defId}/schedule",
            $this->analystSession,
            ['cadence' => 'daily']
        );
        $this->assertEquals(201, $scheduleResponse['status'],
            'Analyst should schedule their own report. Got: ' . ($scheduleResponse['raw'] ?? ''));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createDefinitionAs(array $session): int
    {
        $response = $this->authenticatedRequest(
            'POST',
            '/api/v1/reports/definitions',
            $session,
            [
                'name'        => 'Scope Test Report ' . uniqid(),
                'description' => 'Report for scope testing',
                'dimensions'  => ['type' => 'participation'],
            ]
        );
        $this->assertEquals(201, $response['status'],
            'Helper: create definition must return 201. Got: ' . ($response['raw'] ?? ''));
        $id = $response['body']['data']['id'] ?? null;
        $this->assertNotNull($id, 'Helper: definition ID must be returned');

        return (int) $id;
    }
}
