<?php
declare(strict_types=1);

namespace tests\Feature\Recipe;

use tests\TestCase;

/**
 * Tests that recipe aggregate status is synchronized when versions
 * are approved or rejected.
 */
class AggregateStatusTest extends TestCase
{
    private array $editorSession;
    private array $reviewerSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editorSession   = $this->loginAs('editor');
        $this->reviewerSession = $this->loginAs('reviewer');
    }

    /**
     * After approving a version, the parent recipe status should be 'approved'.
     */
    public function testRecipeStatusUpdatesToApprovedAfterVersionApproval(): void
    {
        $ids = $this->createAndSubmitForReview();

        // Approve as reviewer
        $approveResponse = $this->authenticatedRequest(
            'POST',
            "/api/v1/recipe-versions/{$ids['version_id']}/approve",
            $this->reviewerSession
        );
        $this->assertEquals(200, $approveResponse['status'],
            'Approve should succeed. Got: ' . ($approveResponse['raw'] ?? ''));

        // Read the recipe and verify aggregate status
        $readResponse = $this->authenticatedRequest('GET', "/api/v1/recipes/{$ids['recipe_id']}", $this->editorSession);
        $this->assertEquals(200, $readResponse['status']);
        $this->assertEquals('approved', $readResponse['body']['data']['status'] ?? '',
            'Recipe aggregate status should be "approved" after version approval');
    }

    /**
     * After rejecting a version, the parent recipe status should be 'rejected'.
     */
    public function testRecipeStatusUpdatesToRejectedAfterVersionRejection(): void
    {
        $ids = $this->createAndSubmitForReview();

        // Reject as reviewer
        $rejectResponse = $this->authenticatedRequest(
            'POST',
            "/api/v1/recipe-versions/{$ids['version_id']}/reject",
            $this->reviewerSession
        );
        $this->assertEquals(200, $rejectResponse['status'],
            'Reject should succeed. Got: ' . ($rejectResponse['raw'] ?? ''));

        // Read the recipe and verify aggregate status
        $readResponse = $this->authenticatedRequest('GET', "/api/v1/recipes/{$ids['recipe_id']}", $this->editorSession);
        $this->assertEquals(200, $readResponse['status']);
        $this->assertEquals('rejected', $readResponse['body']['data']['status'] ?? '',
            'Recipe aggregate status should be "rejected" after version rejection');
    }

    /**
     * After submitting for review, recipe status should be 'in_review'.
     */
    public function testRecipeStatusIsInReviewAfterSubmission(): void
    {
        $ids = $this->createAndSubmitForReview();

        $readResponse = $this->authenticatedRequest('GET', "/api/v1/recipes/{$ids['recipe_id']}", $this->editorSession);
        $this->assertEquals(200, $readResponse['status']);
        $this->assertEquals('in_review', $readResponse['body']['data']['status'] ?? '',
            'Recipe aggregate status should be "in_review" after submission');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createAndSubmitForReview(): array
    {
        $siteId = $this->editorSession['user']['site_scopes'][0] ?? 1;

        $createResponse = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->editorSession, [
            'title'   => 'Aggregate Status Recipe ' . uniqid(),
            'site_id' => $siteId,
        ]);
        $this->assertEquals(201, $createResponse['status'], 'Helper: create must return 201');
        $recipeId = $createResponse['body']['data']['id'];

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

        return ['recipe_id' => (int) $recipeId, 'version_id' => $versionId];
    }
}
