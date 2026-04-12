<?php
declare(strict_types=1);

namespace tests\Feature\Reports;

use tests\TestCase;

/**
 * Tests report definition and run scope isolation.
 *
 * Verifies that:
 * - Non-privileged users can only see their own report definitions
 * - Administrators can see all report definitions
 *
 * ZERO markTestSkipped / markTestIncomplete calls.
 */
class ReportScopeTest extends TestCase
{
    private array $analystSession;
    private array $financeSession;
    private array $adminSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analystSession  = $this->loginAs('analyst');
        $this->financeSession  = $this->loginAs('finance');
        $this->adminSession    = $this->loginAs('admin');
    }

    // ------------------------------------------------------------------
    // Ownership scoping
    // ------------------------------------------------------------------

    public function testUserCanOnlySeeOwnDefinitions(): void
    {
        // Analyst creates a report definition
        $createResponse = $this->authenticatedRequest('POST', '/api/v1/reports/definitions', $this->analystSession, [
            'name'        => 'Analyst Scoped Report ' . uniqid(),
            'description' => 'Created by analyst for scope test',
        ]);
        $this->assertEquals(201, $createResponse['status'],
            'Analyst should be able to create report definition. Got: ' . ($createResponse['raw'] ?? ''));
        $analystDefId = $createResponse['body']['data']['id'] ?? null;
        $this->assertNotNull($analystDefId, 'Report definition ID must be returned');

        // Finance lists definitions -- should NOT see the analyst's definition
        $financeListResponse = $this->authenticatedRequest('GET', '/api/v1/reports/definitions', $this->financeSession);
        $this->assertEquals(200, $financeListResponse['status']);
        $financeItems = $financeListResponse['body']['data']['items'] ?? [];

        $found = false;
        foreach ($financeItems as $item) {
            if (($item['id'] ?? null) == $analystDefId) {
                $found = true;
                break;
            }
        }
        $this->assertFalse($found,
            "Finance user should NOT see analyst's report definition (id={$analystDefId}) in their list");

        // Finance tries to read the analyst's definition directly -- should get 404
        $financeReadResponse = $this->authenticatedRequest('GET', "/api/v1/reports/definitions/{$analystDefId}", $this->financeSession);
        $this->assertEquals(404, $financeReadResponse['status'],
            'Finance user should not be able to read analyst\'s definition directly');
    }

    public function testAdminCanSeeAllDefinitions(): void
    {
        // Analyst creates a report definition
        $createResponse = $this->authenticatedRequest('POST', '/api/v1/reports/definitions', $this->analystSession, [
            'name'        => 'Admin Visibility Report ' . uniqid(),
            'description' => 'Created by analyst, should be visible to admin',
        ]);
        $this->assertEquals(201, $createResponse['status']);
        $defId = $createResponse['body']['data']['id'] ?? null;
        $this->assertNotNull($defId);

        // Admin lists definitions -- should see the analyst's definition
        $adminListResponse = $this->authenticatedRequest('GET', '/api/v1/reports/definitions', $this->adminSession);
        $this->assertEquals(200, $adminListResponse['status']);
        $adminItems = $adminListResponse['body']['data']['items'] ?? [];

        $found = false;
        foreach ($adminItems as $item) {
            if (($item['id'] ?? null) == $defId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found,
            "Admin should see analyst's report definition (id={$defId}) in their list");

        // Admin can also read the definition directly
        $adminReadResponse = $this->authenticatedRequest('GET', "/api/v1/reports/definitions/{$defId}", $this->adminSession);
        $this->assertEquals(200, $adminReadResponse['status'],
            'Admin should be able to read any report definition');
    }

    // ------------------------------------------------------------------
    // Cross-user scope isolation
    // ------------------------------------------------------------------

    public function testCannotReadOthersDefinition(): void
    {
        // Analyst creates a definition
        $createResponse = $this->authenticatedRequest('POST', '/api/v1/reports/definitions', $this->analystSession, [
            'name'        => 'Analyst Private Def ' . uniqid(),
            'description' => 'Should not be readable by finance',
        ]);
        $this->assertEquals(201, $createResponse['status']);
        $defId = $createResponse['body']['data']['id'] ?? null;
        $this->assertNotNull($defId);

        // Finance tries to read it directly -- should get 404
        $readResponse = $this->authenticatedRequest('GET', "/api/v1/reports/definitions/{$defId}", $this->financeSession);
        $this->assertEquals(404, $readResponse['status'],
            'Finance should not be able to read analyst\'s definition');
    }

    public function testCannotUpdateOthersDefinition(): void
    {
        // Analyst creates a definition
        $createResponse = $this->authenticatedRequest('POST', '/api/v1/reports/definitions', $this->analystSession, [
            'name'        => 'Analyst Update Scope ' . uniqid(),
            'description' => 'Should not be updatable by finance',
        ]);
        $this->assertEquals(201, $createResponse['status']);
        $defId = $createResponse['body']['data']['id'] ?? null;
        $this->assertNotNull($defId);

        // Finance tries to update it -- should get 403 or 404
        $updateResponse = $this->authenticatedRequest('PATCH', "/api/v1/reports/definitions/{$defId}", $this->financeSession, [
            'name' => 'Hijacked Name',
        ]);
        $this->assertContains($updateResponse['status'], [403, 404],
            'Finance should not be able to update analyst\'s definition. Got: ' . ($updateResponse['raw'] ?? ''));
    }

    public function testCannotScheduleOthersDefinition(): void
    {
        // Analyst creates a definition
        $createResponse = $this->authenticatedRequest('POST', '/api/v1/reports/definitions', $this->analystSession, [
            'name'        => 'Analyst Schedule Scope ' . uniqid(),
            'description' => 'Should not be schedulable by finance',
        ]);
        $this->assertEquals(201, $createResponse['status']);
        $defId = $createResponse['body']['data']['id'] ?? null;
        $this->assertNotNull($defId);

        // Finance tries to schedule it -- should get 403 or 404
        $scheduleResponse = $this->authenticatedRequest('POST', "/api/v1/reports/definitions/{$defId}/schedule", $this->financeSession, [
            'cadence' => 'weekly',
        ]);
        $this->assertContains($scheduleResponse['status'], [403, 404],
            'Finance should not be able to schedule analyst\'s definition. Got: ' . ($scheduleResponse['raw'] ?? ''));
    }
}
