<?php
declare(strict_types=1);

namespace tests\Feature\Recipe;

use tests\TestCase;

/**
 * Tests that publish requires at least one reviewer-role approval.
 *
 * - Publish fails when no reviewer-role approval record exists.
 * - Publish succeeds after a reviewer approves.
 */
class PublishReviewerGateTest extends TestCase
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

    /**
     * Publish succeeds after a reviewer approves the version.
     */
    public function testPublishSucceedsAfterReviewerApproval(): void
    {
        $ids = $this->createAndApproveByReviewer();

        $publishResponse = $this->authenticatedRequest(
            'POST',
            "/api/v1/recipes/{$ids['recipe_id']}/publish",
            $this->reviewerSession
        );

        $this->assertEquals(200, $publishResponse['status'],
            'Publish should succeed after reviewer approval. Got: ' . ($publishResponse['raw'] ?? ''));
        $this->assertEquals('published', $publishResponse['body']['data']['status'] ?? '');
    }

    /**
     * After approval and publish, verify the recipe shows as published.
     */
    public function testPublishedRecipeStatusIsCorrect(): void
    {
        $ids = $this->createAndApproveByReviewer();

        $this->authenticatedRequest('POST', "/api/v1/recipes/{$ids['recipe_id']}/publish", $this->reviewerSession);

        $readResponse = $this->authenticatedRequest('GET', "/api/v1/recipes/{$ids['recipe_id']}", $this->editorSession);
        $this->assertEquals(200, $readResponse['status']);
        $this->assertEquals('published', $readResponse['body']['data']['status'] ?? '',
            'Recipe status should be published after publish');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createAndApproveByReviewer(): array
    {
        $siteId = $this->editorSession['user']['site_scopes'][0] ?? 1;

        // Create recipe
        $createResponse = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->editorSession, [
            'title'   => 'Publish Gate Recipe ' . uniqid(),
            'site_id' => $siteId,
        ]);
        $this->assertEquals(201, $createResponse['status'], 'Helper: create must return 201');
        $recipeId = $createResponse['body']['data']['id'];

        // Read to get version ID
        $readResponse = $this->authenticatedRequest('GET', "/api/v1/recipes/{$recipeId}", $this->editorSession);
        $this->assertEquals(200, $readResponse['status']);
        $versions = $readResponse['body']['data']['versions'] ?? [];
        $this->assertNotEmpty($versions);
        $versionId = (int) $versions[0]['id'];

        // Update with all required fields
        $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $this->editorSession, [
            'steps'       => [['instruction' => 'Test step', 'duration_minutes' => 5]],
            'total_time'  => 5,
            'difficulty'  => 'easy',
            'ingredients' => [['name' => 'Salt', 'quantity' => 1, 'unit' => 'tsp']],
        ]);

        // Submit for review
        $submitResponse = $this->authenticatedRequest('POST', "/api/v1/recipe-versions/{$versionId}/submit-review", $this->editorSession);
        $this->assertEquals(200, $submitResponse['status'], 'Helper: submit must succeed');

        // Approve as reviewer
        $approveResponse = $this->authenticatedRequest('POST', "/api/v1/recipe-versions/{$versionId}/approve", $this->reviewerSession);
        $this->assertEquals(200, $approveResponse['status'], 'Helper: approve must succeed');

        return ['recipe_id' => (int) $recipeId, 'version_id' => $versionId];
    }
}
