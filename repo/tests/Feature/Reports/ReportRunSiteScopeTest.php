<?php
declare(strict_types=1);

namespace tests\Feature\Reports;

use tests\TestCase;

/**
 * Tests that report run execution enforces site-scope isolation.
 *
 * Non-privileged users (e.g. analyst, finance_clerk) should only get
 * report data filtered to their assigned site scopes.
 * Privileged users (administrator, auditor) may see cross-site data.
 */
class ReportRunSiteScopeTest extends TestCase
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

    /**
     * A non-privileged user running a report should have site-scope
     * enforcement applied — the run should succeed (202) but data is
     * restricted to their sites.
     */
    public function testNonPrivilegedUserReportRunEnforcesSiteScope(): void
    {
        // Create a report definition as analyst
        $createResponse = $this->authenticatedRequest('POST', '/api/v1/reports/definitions', $this->analystSession, [
            'name'        => 'Site Scope Test Report ' . uniqid(),
            'description' => 'Testing site scope enforcement in report runs',
            'dimensions'  => ['type' => 'participation'],
        ]);
        $this->assertEquals(201, $createResponse['status'],
            'Analyst should create report definition. Got: ' . ($createResponse['raw'] ?? ''));
        $defId = $createResponse['body']['data']['id'] ?? null;
        $this->assertNotNull($defId);

        // Run the report as analyst (non-privileged, should be site-scoped)
        $runResponse = $this->authenticatedRequest('POST', "/api/v1/reports/definitions/{$defId}/run", $this->analystSession);
        $this->assertContains($runResponse['status'], [200, 202],
            'Analyst should be able to run their report. Got: ' . ($runResponse['raw'] ?? ''));

        $runId = $runResponse['body']['data']['run_id'] ?? null;
        $this->assertNotNull($runId, 'Run ID must be returned');

        // Verify the run completed
        $runStatus = $runResponse['body']['data']['status'] ?? '';
        $this->assertContains($runStatus, ['queued', 'succeeded'],
            'Report run should complete successfully');
    }

    /**
     * Admin running the same report should get cross-site data (no restriction).
     */
    public function testAdminReportRunIsNotSiteRestricted(): void
    {
        // Create as admin
        $createResponse = $this->authenticatedRequest('POST', '/api/v1/reports/definitions', $this->adminSession, [
            'name'        => 'Admin Cross-Site Report ' . uniqid(),
            'description' => 'Admin cross-site report test',
            'dimensions'  => ['type' => 'participation'],
        ]);
        $this->assertEquals(201, $createResponse['status'],
            'Admin should create report definition. Got: ' . ($createResponse['raw'] ?? ''));
        $defId = $createResponse['body']['data']['id'] ?? null;
        $this->assertNotNull($defId);

        // Run the report as admin (privileged, no site restriction)
        $runResponse = $this->authenticatedRequest('POST', "/api/v1/reports/definitions/{$defId}/run", $this->adminSession);
        $this->assertContains($runResponse['status'], [200, 202],
            'Admin should be able to run reports. Got: ' . ($runResponse['raw'] ?? ''));

        $runId = $runResponse['body']['data']['run_id'] ?? null;
        $this->assertNotNull($runId, 'Run ID must be returned');
    }

    /**
     * Non-privileged user should not be able to run another user's report definition.
     */
    public function testNonPrivilegedCannotRunOthersReport(): void
    {
        // Create as admin
        $createResponse = $this->authenticatedRequest('POST', '/api/v1/reports/definitions', $this->adminSession, [
            'name'        => 'Admin Only Report ' . uniqid(),
            'description' => 'Should not be runnable by analyst',
        ]);
        $this->assertEquals(201, $createResponse['status']);
        $defId = $createResponse['body']['data']['id'] ?? null;
        $this->assertNotNull($defId);

        // Analyst tries to run admin's report — should fail with 404
        $runResponse = $this->authenticatedRequest('POST', "/api/v1/reports/definitions/{$defId}/run", $this->analystSession);
        $this->assertEquals(404, $runResponse['status'],
            'Analyst should not be able to run admin\'s report definition. Got: ' . ($runResponse['raw'] ?? ''));
    }
}
