<?php
declare(strict_types=1);

namespace tests\Feature\Recipe;

use tests\TestCase;

/**
 * Tests the complete recipe workflow: create, update, review, approve,
 * publish, immutability, catalog visibility, and validation.
 *
 * All requests go through the real HTTP endpoints.
 * ZERO markTestSkipped / markTestIncomplete calls.
 */
class WorkflowTest extends TestCase
{
    private array $editorSession;
    private array $reviewerSession;
    private array $adminSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editorSession   = $this->loginAs('editor');
        $this->reviewerSession = $this->loginAs('reviewer');
        $this->adminSession    = $this->loginAs('admin');
    }

    // ------------------------------------------------------------------
    // Authentication prerequisite
    // ------------------------------------------------------------------

    public function testLoginReturnsSessionAndCsrfToken(): void
    {
        $this->assertEquals(200, $this->editorSession['status']);
        $this->assertNotEmpty($this->editorSession['csrf_token'], 'Login must return a CSRF token');
        $this->assertNotEmpty($this->editorSession['user']['id'] ?? null, 'Login must return user data');
    }

    // ------------------------------------------------------------------
    // Recipe creation
    // ------------------------------------------------------------------

    public function testCreateRecipeReturnsId(): void
    {
        $siteId = $this->editorSession['user']['site_scopes'][0] ?? 1;

        $response = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->editorSession, [
            'title'   => 'Workflow Test Recipe ' . uniqid(),
            'site_id' => $siteId,
        ]);

        $this->assertEquals(201, $response['status'],
            'Create recipe should return 201. Got: ' . ($response['raw'] ?? ''));
        $this->assertNotEmpty($response['body']['data']['id'] ?? null,
            'Response must include recipe id');
    }

    public function testCreateRecipeRequiresTitle(): void
    {
        $siteId = $this->editorSession['user']['site_scopes'][0] ?? 1;

        $response = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->editorSession, [
            'site_id' => $siteId,
        ]);

        $this->assertEquals(422, $response['status']);
    }

    public function testCreateRecipeRequiresSiteId(): void
    {
        $response = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->editorSession, [
            'title' => 'Missing Site Recipe',
        ]);

        $this->assertEquals(422, $response['status']);
    }

    // ------------------------------------------------------------------
    // Version update with validation
    // ------------------------------------------------------------------

    public function testUpdateVersionValidatesStepCountMin(): void
    {
        $versionId = $this->createTestRecipeAndGetVersionId();

        $response = $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $this->editorSession, [
            'steps'      => [],
            'total_time' => 30,
            'difficulty' => 'easy',
        ]);

        $this->assertEquals(422, $response['status'], 'Empty steps should fail validation (min:1)');
    }

    public function testUpdateVersionValidatesStepCountMax(): void
    {
        $versionId = $this->createTestRecipeAndGetVersionId();

        $steps = [];
        for ($i = 0; $i < 51; $i++) {
            $steps[] = ['instruction' => "Step $i", 'duration_minutes' => 1];
        }

        $response = $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $this->editorSession, [
            'steps'      => $steps,
            'total_time' => 30,
            'difficulty' => 'easy',
        ]);

        $this->assertEquals(422, $response['status'], 'More than 50 steps should fail validation');
    }

    public function testUpdateVersionValidatesTotalTimeMin(): void
    {
        $versionId = $this->createTestRecipeAndGetVersionId();

        $response = $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $this->editorSession, [
            'steps'      => [['instruction' => 'Mix ingredients', 'duration_minutes' => 5]],
            'total_time' => 0,
            'difficulty' => 'easy',
        ]);

        $this->assertEquals(422, $response['status'], 'total_time of 0 should fail validation (min:1)');
    }

    public function testUpdateVersionValidatesTotalTimeMax(): void
    {
        $versionId = $this->createTestRecipeAndGetVersionId();

        $response = $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $this->editorSession, [
            'steps'      => [['instruction' => 'Slow cook', 'duration_minutes' => 5]],
            'total_time' => 721,
            'difficulty' => 'easy',
        ]);

        $this->assertEquals(422, $response['status'], 'total_time over 720 should fail validation');
    }

    public function testUpdateVersionWithValidData(): void
    {
        $versionId = $this->createTestRecipeAndGetVersionId();

        $response = $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $this->editorSession, [
            'steps'      => [
                ['instruction' => 'Preheat oven', 'duration_minutes' => 10],
                ['instruction' => 'Mix dry ingredients', 'duration_minutes' => 5],
                ['instruction' => 'Bake', 'duration_minutes' => 25],
            ],
            'total_time' => 40,
            'difficulty' => 'medium',
        ]);

        $this->assertEquals(200, $response['status'],
            'Update draft version should succeed with 200. Got: ' . ($response['raw'] ?? ''));
    }

    // ------------------------------------------------------------------
    // Full workflow: create -> update -> submit -> approve -> publish -> catalog
    // ------------------------------------------------------------------

    public function testCompleteRecipeWorkflow(): void
    {
        // Step 1: Create recipe
        $siteId = $this->editorSession['user']['site_scopes'][0] ?? 1;
        $createResponse = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->editorSession, [
            'title'   => 'Full Workflow Recipe ' . uniqid(),
            'site_id' => $siteId,
        ]);
        $this->assertEquals(201, $createResponse['status'], 'Create recipe must succeed');
        $recipeId = $createResponse['body']['data']['id'];

        // Step 2: Get recipe details to find the version ID
        $readResponse = $this->authenticatedRequest('GET', "/api/v1/recipes/{$recipeId}", $this->editorSession);
        $this->assertEquals(200, $readResponse['status'], 'Read recipe must succeed');
        $versions = $readResponse['body']['data']['versions'] ?? [];
        $this->assertNotEmpty($versions, 'Recipe should have at least one version');
        $versionId = $versions[0]['id'];

        // Step 3: Update the draft version (using version ID)
        $updateResponse = $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $this->editorSession, [
            'steps'       => [
                ['instruction' => 'Chop vegetables', 'duration_minutes' => 10],
                ['instruction' => 'Saute onions', 'duration_minutes' => 8],
            ],
            'total_time'  => 18,
            'difficulty'  => 'easy',
            'ingredients' => [['name' => 'Onion', 'quantity' => 2, 'unit' => 'piece']],
        ]);
        $this->assertEquals(200, $updateResponse['status'], 'Update draft version must succeed');

        // Step 4: Submit for review (using version ID)
        $submitResponse = $this->authenticatedRequest('POST', "/api/v1/recipe-versions/{$versionId}/submit-review", $this->editorSession);
        $this->assertEquals(200, $submitResponse['status'],
            'Submit for review must succeed. Got: ' . ($submitResponse['raw'] ?? ''));
        $this->assertEquals('in_review', $submitResponse['body']['data']['status'] ?? '');

        // Step 5: Login as reviewer, approve (using version ID)
        $approveResponse = $this->authenticatedRequest('POST', "/api/v1/recipe-versions/{$versionId}/approve", $this->reviewerSession);
        $this->assertEquals(200, $approveResponse['status'],
            'Approve must succeed. Got: ' . ($approveResponse['raw'] ?? ''));
        $this->assertEquals('approved', $approveResponse['body']['data']['status'] ?? '');

        // Step 6: Login as reviewer, publish recipe (using recipe ID) — publish requires reviewer/admin role
        $publishResponse = $this->authenticatedRequest('POST', "/api/v1/recipes/{$recipeId}/publish", $this->reviewerSession);
        $this->assertEquals(200, $publishResponse['status'],
            'Publish must succeed. Got: ' . ($publishResponse['raw'] ?? ''));
        $this->assertEquals('published', $publishResponse['body']['data']['status'] ?? '');

        // Step 7: Verify published recipe appears in catalog
        $catalogResponse = $this->authenticatedRequest('GET', '/api/v1/catalog/recipes', $this->editorSession);
        $this->assertEquals(200, $catalogResponse['status']);
        $catalogItems = $catalogResponse['body']['data']['items'] ?? [];
        $found = false;
        foreach ($catalogItems as $item) {
            if (($item['id'] ?? null) == $recipeId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, "Published recipe {$recipeId} should appear in catalog");
    }

    // ------------------------------------------------------------------
    // Approved version immutability
    // ------------------------------------------------------------------

    public function testUpdatingApprovedVersionFails409(): void
    {
        $ids = $this->createAndApproveRecipe();
        $versionId = $ids['version_id'];

        // Attempt to update the approved version — should get 409
        $response = $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $this->editorSession, [
            'steps'      => [['instruction' => 'Should not work', 'duration_minutes' => 1]],
            'total_time' => 5,
            'difficulty' => 'easy',
        ]);

        $this->assertEquals(409, $response['status'],
            'Updating an approved version should return 409 conflict');
    }

    public function testPublishedRecipeAppearsInCatalog(): void
    {
        $ids = $this->createAndApproveRecipe();
        $recipeId = $ids['recipe_id'];

        // Publish — requires reviewer/admin role
        $publishResponse = $this->authenticatedRequest('POST', "/api/v1/recipes/{$recipeId}/publish", $this->reviewerSession);
        $this->assertEquals(200, $publishResponse['status'], 'Publish must succeed');

        // Verify in catalog
        $catalogResponse = $this->authenticatedRequest('GET', '/api/v1/catalog/recipes', $this->editorSession);
        $this->assertEquals(200, $catalogResponse['status']);

        $items = $catalogResponse['body']['data']['items'] ?? [];
        $found = false;
        foreach ($items as $item) {
            if (($item['id'] ?? null) == $recipeId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, "Published recipe {$recipeId} should appear in catalog");
    }

    // ------------------------------------------------------------------
    // Auth required
    // ------------------------------------------------------------------

    public function testUnauthenticatedCannotCreateRecipe(): void
    {
        $response = $this->httpRequest('POST', '/api/v1/recipes', [
            'title'   => 'Unauthorized Recipe',
            'site_id' => 1,
        ]);

        $this->assertContains($response['status'], [401, 403],
            'Unauthenticated requests should be rejected');
    }

    public function testListRecipesRequiresAuth(): void
    {
        $response = $this->httpRequest('GET', '/api/v1/recipes');
        $this->assertContains($response['status'], [401, 403]);
    }

    // ------------------------------------------------------------------
    // Helper methods — no skips, assertions enforce preconditions
    // ------------------------------------------------------------------

    /**
     * Create a recipe and return the version ID from the versions array.
     */
    private function createTestRecipeAndGetVersionId(): int
    {
        $siteId = $this->editorSession['user']['site_scopes'][0] ?? 1;

        $createResponse = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->editorSession, [
            'title'   => 'Test Recipe ' . uniqid(),
            'site_id' => $siteId,
        ]);
        $this->assertEquals(201, $createResponse['status'], 'Helper: create recipe must return 201');
        $recipeId = $createResponse['body']['data']['id'];

        $readResponse = $this->authenticatedRequest('GET', "/api/v1/recipes/{$recipeId}", $this->editorSession);
        $this->assertEquals(200, $readResponse['status'], 'Helper: read recipe must return 200');

        $versions = $readResponse['body']['data']['versions'] ?? [];
        $this->assertNotEmpty($versions, 'Helper: recipe must have at least one version');

        return (int) $versions[0]['id'];
    }

    /**
     * Create a recipe, update its draft version, submit for review, and approve it.
     * Returns both the recipe_id and version_id.
     *
     * @return array{recipe_id: int, version_id: int}
     */
    private function createAndApproveRecipe(): array
    {
        $siteId = $this->editorSession['user']['site_scopes'][0] ?? 1;

        // Create recipe
        $createResponse = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->editorSession, [
            'title'   => 'Approvable Recipe ' . uniqid(),
            'site_id' => $siteId,
        ]);
        $this->assertEquals(201, $createResponse['status'], 'Helper: create recipe must return 201');
        $recipeId = $createResponse['body']['data']['id'];

        // Read to get version ID
        $readResponse = $this->authenticatedRequest('GET', "/api/v1/recipes/{$recipeId}", $this->editorSession);
        $this->assertEquals(200, $readResponse['status']);
        $versions = $readResponse['body']['data']['versions'] ?? [];
        $this->assertNotEmpty($versions);
        $versionId = (int) $versions[0]['id'];

        // Update the draft version (includes ingredients for completeness gate)
        $updateResponse = $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $this->editorSession, [
            'steps'       => [['instruction' => 'Test step', 'duration_minutes' => 5]],
            'total_time'  => 5,
            'difficulty'  => 'easy',
            'ingredients' => [['name' => 'Salt', 'quantity' => 1, 'unit' => 'tsp']],
        ]);
        $this->assertEquals(200, $updateResponse['status'], 'Helper: update version must succeed');

        // Submit for review
        $submitResponse = $this->authenticatedRequest('POST', "/api/v1/recipe-versions/{$versionId}/submit-review", $this->editorSession);
        $this->assertEquals(200, $submitResponse['status'], 'Helper: submit for review must succeed');

        // Approve
        $approveResponse = $this->authenticatedRequest('POST', "/api/v1/recipe-versions/{$versionId}/approve", $this->reviewerSession);
        $this->assertEquals(200, $approveResponse['status'], 'Helper: approve must succeed');

        return ['recipe_id' => (int) $recipeId, 'version_id' => $versionId];
    }
}
