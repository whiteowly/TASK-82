<?php
declare(strict_types=1);

namespace tests\Feature\Security;

use tests\TestCase;

/**
 * Tests role-based access control on sensitive business actions.
 *
 * Verifies that:
 * - Editors cannot approve/publish recipes (reviewer-only actions)
 * - Editors cannot perform finance operations (finance_clerk-only actions)
 * - Editors cannot create report definitions (analyst/finance-only actions)
 * - Reviewers CAN approve recipes
 * - Finance clerks CAN generate settlements
 *
 * ZERO markTestSkipped / markTestIncomplete calls.
 */
class RoleIsolationTest extends TestCase
{
    private array $editorSession;
    private array $reviewerSession;
    private array $financeSession;
    private array $adminSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editorSession   = $this->loginAs('editor');
        $this->reviewerSession = $this->loginAs('reviewer');
        $this->financeSession  = $this->loginAs('finance');
        $this->adminSession    = $this->loginAs('admin');
    }

    // ------------------------------------------------------------------
    // Recipe role isolation
    // ------------------------------------------------------------------

    public function testEditorCannotApproveRecipe(): void
    {
        $versionId = $this->createRecipeInReview();

        $response = $this->authenticatedRequest(
            'POST',
            "/api/v1/recipe-versions/{$versionId}/approve",
            $this->editorSession
        );

        $this->assertEquals(403, $response['status'],
            'Editor should not be able to approve recipes. Got: ' . ($response['raw'] ?? ''));
        $this->assertEquals('FORBIDDEN_ROLE', $response['body']['error']['code'] ?? '');
    }

    public function testEditorCannotPublishRecipe(): void
    {
        $ids = $this->createApprovedRecipe();

        $response = $this->authenticatedRequest(
            'POST',
            "/api/v1/recipes/{$ids['recipe_id']}/publish",
            $this->editorSession
        );

        $this->assertEquals(403, $response['status'],
            'Editor should not be able to publish recipes. Got: ' . ($response['raw'] ?? ''));
        $this->assertEquals('FORBIDDEN_ROLE', $response['body']['error']['code'] ?? '');
    }

    public function testReviewerCanApprove(): void
    {
        $versionId = $this->createRecipeInReview();

        $response = $this->authenticatedRequest(
            'POST',
            "/api/v1/recipe-versions/{$versionId}/approve",
            $this->reviewerSession
        );

        $this->assertEquals(200, $response['status'],
            'Reviewer should be able to approve recipes. Got: ' . ($response['raw'] ?? ''));
        $this->assertEquals('approved', $response['body']['data']['status'] ?? '');
    }

    // ------------------------------------------------------------------
    // Settlement/finance role isolation
    // ------------------------------------------------------------------

    public function testEditorCannotCreateFreightRule(): void
    {
        $siteId = $this->editorSession['user']['site_scopes'][0] ?? 1;

        $response = $this->authenticatedRequest('POST', '/api/v1/finance/freight-rules', $this->editorSession, [
            'name'     => 'Unauthorized Freight Rule',
            'site_id'  => $siteId,
            'tax_rate' => 0.05,
        ]);

        $this->assertEquals(403, $response['status'],
            'Editor should not be able to create freight rules. Got: ' . ($response['raw'] ?? ''));
        $this->assertEquals('FORBIDDEN_ROLE', $response['body']['error']['code'] ?? '');
    }

    public function testEditorCannotGenerateSettlement(): void
    {
        $siteId = $this->editorSession['user']['site_scopes'][0] ?? 1;

        $response = $this->authenticatedRequest('POST', '/api/v1/finance/settlements/generate', $this->editorSession, [
            'site_id' => $siteId,
            'period'  => '2026-03',
        ]);

        $this->assertEquals(403, $response['status'],
            'Editor should not be able to generate settlements. Got: ' . ($response['raw'] ?? ''));
        $this->assertEquals('FORBIDDEN_ROLE', $response['body']['error']['code'] ?? '');
    }

    public function testFinanceCanGenerateSettlement(): void
    {
        $siteId = $this->financeSession['user']['site_scopes'][0] ?? 1;

        $response = $this->authenticatedRequest('POST', '/api/v1/finance/settlements/generate', $this->financeSession, [
            'site_id' => $siteId,
            'period'  => '2024-06',
        ]);

        $this->assertContains($response['status'], [200, 202],
            'Finance clerk should be able to generate settlements. Got: ' . ($response['raw'] ?? ''));
    }

    // ------------------------------------------------------------------
    // Report role isolation
    // ------------------------------------------------------------------

    public function testEditorCannotCreateReportDefinition(): void
    {
        $response = $this->authenticatedRequest('POST', '/api/v1/reports/definitions', $this->editorSession, [
            'name' => 'Unauthorized Report',
        ]);
        $this->assertEquals(403, $response['status'],
            'Editor cannot create report defs. Got: ' . ($response['raw'] ?? ''));
    }

    public function testEditorCannotListReportDefinitions(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/reports/definitions', $this->editorSession);
        $this->assertEquals(403, $response['status'],
            'Editor cannot list report defs. Got: ' . ($response['raw'] ?? ''));
    }

    public function testEditorCannotListReportRuns(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/reports/runs', $this->editorSession);
        $this->assertEquals(403, $response['status'],
            'Editor cannot list report runs. Got: ' . ($response['raw'] ?? ''));
    }

    public function testReviewerCannotListReportDefinitions(): void
    {
        $reviewerSession = $this->loginAs('reviewer');
        $response = $this->authenticatedRequest('GET', '/api/v1/reports/definitions', $reviewerSession);
        $this->assertEquals(403, $response['status'],
            'Reviewer cannot list report defs. Got: ' . ($response['raw'] ?? ''));
    }

    public function testAnalystCanListReportDefinitions(): void
    {
        $analystSession = $this->loginAs('analyst');
        $response = $this->authenticatedRequest('GET', '/api/v1/reports/definitions', $analystSession);
        $this->assertEquals(200, $response['status'],
            'Analyst can list report defs. Got: ' . ($response['raw'] ?? ''));
    }

    // ------------------------------------------------------------------
    // Analytics role isolation
    // ------------------------------------------------------------------

    public function testEditorCannotAccessAnalyticsDashboard(): void
    {
        $response = $this->authenticatedRequest(
            'GET',
            '/api/v1/analytics/dashboard',
            $this->editorSession
        );

        $this->assertEquals(403, $response['status'],
            'Editor should not be able to access analytics dashboard. Got: ' . ($response['raw'] ?? ''));
        $this->assertEquals('FORBIDDEN_ROLE', $response['body']['error']['code'] ?? '');
    }

    public function testAnalystCanAccessDashboard(): void
    {
        $analystSession = $this->loginAs('analyst');

        $response = $this->authenticatedRequest(
            'GET',
            '/api/v1/analytics/dashboard',
            $analystSession
        );

        $this->assertEquals(200, $response['status'],
            'Analyst should be able to access analytics dashboard. Got: ' . ($response['raw'] ?? ''));
    }

    // ------------------------------------------------------------------
    // Settlement reversal role isolation
    // ------------------------------------------------------------------

    public function testEditorCannotReverseSettlement(): void
    {
        // First generate and approve a statement as admin/finance
        $statementId = $this->createApprovedStatement();

        $response = $this->authenticatedRequest(
            'POST',
            "/api/v1/finance/settlements/{$statementId}/reverse",
            $this->editorSession,
            ['reason' => 'Unauthorized reversal attempt']
        );

        $this->assertEquals(403, $response['status'],
            'Editor should not be able to reverse settlements. Got: ' . ($response['raw'] ?? ''));
        $this->assertEquals('FORBIDDEN_ROLE', $response['body']['error']['code'] ?? '');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Create a settlement statement that is approved_locked, for reversal testing.
     */
    private function createApprovedStatement(): int
    {
        $siteId = $this->adminSession['user']['site_scopes'][0] ?? 1;
        $period = '2026-' . str_pad((string) rand(1, 12), 2, '0', STR_PAD_LEFT);

        $genResponse = $this->authenticatedRequest('POST', '/api/v1/finance/settlements/generate', $this->adminSession, [
            'site_id' => $siteId,
            'period'  => $period,
        ]);
        $this->assertContains($genResponse['status'], [200, 202],
            'Helper: generate must succeed. Got: ' . ($genResponse['raw'] ?? ''));
        $statementId = (int) ($genResponse['body']['data']['settlement_id'] ?? 0);
        $this->assertGreaterThan(0, $statementId);

        // Submit as finance
        $submitResponse = $this->authenticatedRequest('POST', "/api/v1/finance/settlements/{$statementId}/submit", $this->financeSession);
        $this->assertEquals(200, $submitResponse['status'],
            'Helper: submit must succeed. Got: ' . ($submitResponse['raw'] ?? ''));

        // Approve as admin
        $approveResponse = $this->authenticatedRequest('POST', "/api/v1/finance/settlements/{$statementId}/approve-final", $this->adminSession);
        $this->assertEquals(200, $approveResponse['status'],
            'Helper: approve must succeed. Got: ' . ($approveResponse['raw'] ?? ''));

        return $statementId;
    }

    // ------------------------------------------------------------------

    /**
     * Create a recipe and submit it for review. Returns the version ID.
     */
    private function createRecipeInReview(): int
    {
        $siteId = $this->editorSession['user']['site_scopes'][0] ?? 1;

        $createResponse = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->editorSession, [
            'title'   => 'Role Test Recipe ' . uniqid(),
            'site_id' => $siteId,
        ]);
        $this->assertEquals(201, $createResponse['status'], 'Helper: create recipe must return 201');
        $recipeId = $createResponse['body']['data']['id'];

        $readResponse = $this->authenticatedRequest('GET', "/api/v1/recipes/{$recipeId}", $this->editorSession);
        $this->assertEquals(200, $readResponse['status']);
        $versions = $readResponse['body']['data']['versions'] ?? [];
        $this->assertNotEmpty($versions);
        $versionId = (int) $versions[0]['id'];

        // Update draft (includes ingredients for completeness gate)
        $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $this->editorSession, [
            'steps'       => [['instruction' => 'Test step', 'duration_minutes' => 5]],
            'total_time'  => 5,
            'difficulty'  => 'easy',
            'ingredients' => [['name' => 'Salt', 'quantity' => 1, 'unit' => 'tsp']],
        ]);

        // Submit for review
        $submitResponse = $this->authenticatedRequest('POST', "/api/v1/recipe-versions/{$versionId}/submit-review", $this->editorSession);
        $this->assertEquals(200, $submitResponse['status'], 'Helper: submit for review must succeed');

        return $versionId;
    }

    /**
     * Create a recipe, submit, and approve it. Returns recipe_id and version_id.
     */
    private function createApprovedRecipe(): array
    {
        $versionId = $this->createRecipeInReview();

        // Approve as reviewer
        $approveResponse = $this->authenticatedRequest('POST', "/api/v1/recipe-versions/{$versionId}/approve", $this->reviewerSession);
        $this->assertEquals(200, $approveResponse['status'], 'Helper: approve must succeed');

        // Get recipe ID from the version
        $readResponse = $this->authenticatedRequest('GET', "/api/v1/recipes", $this->editorSession);
        $items = $readResponse['body']['data']['items'] ?? [];

        // Find the recipe that has this version
        $recipeId = null;
        foreach ($items as $item) {
            $recipeReadResponse = $this->authenticatedRequest('GET', "/api/v1/recipes/{$item['id']}", $this->editorSession);
            $versions = $recipeReadResponse['body']['data']['versions'] ?? [];
            foreach ($versions as $v) {
                if ((int) $v['id'] === $versionId) {
                    $recipeId = (int) $item['id'];
                    break 2;
                }
            }
        }
        $this->assertNotNull($recipeId, 'Helper: must find the recipe for the approved version');

        return ['recipe_id' => $recipeId, 'version_id' => $versionId];
    }
}
