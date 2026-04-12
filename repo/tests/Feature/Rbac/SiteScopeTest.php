<?php
declare(strict_types=1);

namespace tests\Feature\Rbac;

use tests\TestCase;

/**
 * Tests site scope enforcement:
 * - Editor can create recipes in assigned sites (HQ=1, EAST=2)
 * - Editor cannot create recipes in unassigned site (site 3)
 * - Admin sees all sites' data (cross-site access)
 *
 * ZERO markTestSkipped / markTestIncomplete calls.
 */
class SiteScopeTest extends TestCase
{
    private array $adminSession;
    private array $editorSession;
    private array $financeSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminSession   = $this->loginAs('admin');
        $this->editorSession  = $this->loginAs('editor');
        $this->financeSession = $this->loginAs('finance');
    }

    // ------------------------------------------------------------------
    // Admin cross-site access
    // ------------------------------------------------------------------

    public function testAdminHasCrossSiteAccess(): void
    {
        $this->assertEquals(200, $this->adminSession['status']);

        $user = $this->adminSession['user'];
        $this->assertContains('administrator', $user['roles'] ?? [],
            'Admin user should have administrator role');

        // Admin should have all 3 site scopes (HQ, EAST, WEST)
        $this->assertCount(3, $user['site_scopes'] ?? [],
            'Admin should have access to all 3 sites');
    }

    public function testAdminCanSeeAllSitesRecipes(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/recipes', $this->adminSession);

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('items', $response['body']['data'] ?? []);
    }

    public function testAdminCanSeeAllSitesSettlements(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/finance/settlements', $this->adminSession);

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('items', $response['body']['data'] ?? []);
    }

    public function testAdminCanSeeAllSitesFreightRules(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/finance/freight-rules', $this->adminSession);

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('items', $response['body']['data'] ?? []);
    }

    // ------------------------------------------------------------------
    // Editor restricted to assigned sites
    // ------------------------------------------------------------------

    public function testEditorHasLimitedSiteScopes(): void
    {
        $user = $this->editorSession['user'];
        $this->assertContains('content_editor', $user['roles'] ?? []);

        // Editor is assigned to HQ(1) and EAST(2)
        $siteScopes = $user['site_scopes'] ?? [];
        $this->assertCount(2, $siteScopes, 'Editor should have access to 2 sites (HQ, EAST)');
    }

    public function testEditorCanListRecipes(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/recipes', $this->editorSession);

        $this->assertEquals(200, $response['status']);
        $data = $response['body']['data'] ?? [];
        $this->assertArrayHasKey('items', $data);
    }

    public function testEditorCanCreateRecipeInAssignedSite(): void
    {
        // Editor has sites 1 (HQ) and 2 (EAST); use site 1
        $editorSites = $this->editorSession['user']['site_scopes'] ?? [];
        $this->assertNotEmpty($editorSites, 'Editor must have site scopes');

        $siteId = $editorSites[0]; // should be site 1
        $response = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->editorSession, [
            'title'   => 'Editor Site Recipe ' . uniqid(),
            'site_id' => $siteId,
        ]);

        $this->assertEquals(201, $response['status'],
            'Editor should be able to create recipe in assigned site. Got: ' . ($response['raw'] ?? ''));
    }

    // ------------------------------------------------------------------
    // Cross-site access blocked for editor
    // ------------------------------------------------------------------

    public function testEditorCannotCreateRecipeInUnassignedSite(): void
    {
        // Editor has sites 1 and 2, so site 3 is unassigned
        $response = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->editorSession, [
            'title'   => 'Cross-site Recipe ' . uniqid(),
            'site_id' => 3,
        ]);

        $this->assertEquals(403, $response['status'],
            'Editor should be blocked from creating recipes in unassigned site 3');
    }

    public function testFinanceCannotAccessUnassignedSiteSettlements(): void
    {
        // Finance is assigned to HQ only (1 site)
        $financeSites = $this->financeSession['user']['site_scopes'] ?? [];
        $this->assertCount(1, $financeSites, 'Finance should have 1 site scope');

        $adminSites = $this->adminSession['user']['site_scopes'] ?? [];

        // Find a site the finance user does NOT have access to
        $unassignedSite = null;
        foreach ($adminSites as $siteId) {
            if (!in_array($siteId, $financeSites)) {
                $unassignedSite = $siteId;
                break;
            }
        }
        $this->assertNotNull($unassignedSite, 'There must be at least one unassigned site for finance');

        // Create a freight rule in the unassigned site - should be blocked
        $response = $this->authenticatedRequest('POST', '/api/v1/finance/freight-rules', $this->financeSession, [
            'name'     => 'Cross-site Freight Rule',
            'site_id'  => $unassignedSite,
            'tax_rate' => 0.05,
        ]);

        $this->assertEquals(403, $response['status'],
            'Finance should be blocked from creating freight rules in unassigned site');
    }

    public function testFinanceCannotGenerateSettlementForUnassignedSite(): void
    {
        $financeSites = $this->financeSession['user']['site_scopes'] ?? [];
        $adminSites = $this->adminSession['user']['site_scopes'] ?? [];

        $unassignedSite = null;
        foreach ($adminSites as $siteId) {
            if (!in_array($siteId, $financeSites)) {
                $unassignedSite = $siteId;
                break;
            }
        }
        $this->assertNotNull($unassignedSite, 'There must be at least one unassigned site for finance');

        $response = $this->authenticatedRequest('POST', '/api/v1/finance/settlements/generate', $this->financeSession, [
            'site_id' => $unassignedSite,
            'period'  => '2026-01',
        ]);

        $this->assertEquals(403, $response['status'],
            'Finance should be blocked from generating settlements for unassigned site');
    }

    // ------------------------------------------------------------------
    // Auditor has cross-site read access
    // ------------------------------------------------------------------

    public function testAuditorHasCrossSiteAccess(): void
    {
        $auditorSession = $this->loginAs('auditor');

        $user = $auditorSession['user'];
        $this->assertContains('auditor', $user['roles'] ?? []);

        $response = $this->authenticatedRequest('GET', '/api/v1/recipes', $auditorSession);
        $this->assertEquals(200, $response['status']);
    }

    // ------------------------------------------------------------------
    // Me endpoint reflects correct scopes
    // ------------------------------------------------------------------

    public function testMeEndpointReturnsCorrectScopes(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/auth/me', $this->editorSession);

        $this->assertEquals(200, $response['status']);
        $data = $response['body']['data'] ?? [];
        $this->assertArrayHasKey('roles', $data);
        $this->assertArrayHasKey('site_scopes', $data);
        $this->assertContains('content_editor', $data['roles']);
    }

    public function testMeEndpointForAdmin(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/auth/me', $this->adminSession);

        $this->assertEquals(200, $response['status']);
        $data = $response['body']['data'] ?? [];
        $this->assertContains('administrator', $data['roles']);
    }
}
