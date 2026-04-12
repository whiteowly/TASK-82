<?php
declare(strict_types=1);

namespace tests\Feature\Security;

use tests\TestCase;

/**
 * Tests that auditor (read-only) role cannot perform recipe mutations.
 *
 * Covers: create, createVersion, updateVersion, submitReview, addComment.
 * Auditor should still be able to read recipes (GET endpoints).
 */
class AuditorRecipeMutationTest extends TestCase
{
    private array $auditorSession;
    private array $editorSession;
    private array $reviewerSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditorSession  = $this->loginAs('auditor');
        $this->editorSession   = $this->loginAs('editor');
        $this->reviewerSession = $this->loginAs('reviewer');
        $this->assertNotEmpty($this->auditorSession['csrf_token'], 'Auditor login must succeed');
    }

    public function testAuditorCannotCreateRecipe(): void
    {
        $response = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->auditorSession, [
            'title'   => 'Auditor Recipe Attempt ' . uniqid(),
            'site_id' => 1,
        ]);

        $this->assertEquals(403, $response['status'],
            'Auditor should not be able to create recipes. Got: ' . ($response['raw'] ?? ''));
        $this->assertEquals('FORBIDDEN_ROLE', $response['body']['error']['code'] ?? '');
    }

    public function testAuditorCannotCreateVersion(): void
    {
        $recipeId = $this->createRecipeAsEditor();

        $response = $this->authenticatedRequest('POST', "/api/v1/recipes/{$recipeId}/versions", $this->auditorSession, [
            'content_json' => '{"body":"auditor version"}',
        ]);

        $this->assertEquals(403, $response['status'],
            'Auditor should not be able to create recipe versions. Got: ' . ($response['raw'] ?? ''));
        $this->assertEquals('FORBIDDEN_ROLE', $response['body']['error']['code'] ?? '');
    }

    public function testAuditorCannotUpdateVersion(): void
    {
        $versionId = $this->createRecipeAndGetVersionId();

        $response = $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $this->auditorSession, [
            'steps'      => [['instruction' => 'Auditor step', 'duration_minutes' => 5]],
            'total_time' => 10,
            'difficulty' => 'easy',
        ]);

        $this->assertEquals(403, $response['status'],
            'Auditor should not be able to update recipe versions. Got: ' . ($response['raw'] ?? ''));
        $this->assertEquals('FORBIDDEN_ROLE', $response['body']['error']['code'] ?? '');
    }

    public function testAuditorCannotSubmitReview(): void
    {
        $versionId = $this->createRecipeAndGetVersionId();

        // First update the version as editor so it's complete
        $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $this->editorSession, [
            'steps'       => [['instruction' => 'Test step', 'duration_minutes' => 5]],
            'total_time'  => 5,
            'difficulty'  => 'easy',
            'ingredients' => [['name' => 'Salt', 'quantity' => 1, 'unit' => 'tsp']],
        ]);

        $response = $this->authenticatedRequest('POST', "/api/v1/recipe-versions/{$versionId}/submit-review", $this->auditorSession);

        $this->assertEquals(403, $response['status'],
            'Auditor should not be able to submit recipes for review. Got: ' . ($response['raw'] ?? ''));
        $this->assertEquals('FORBIDDEN_ROLE', $response['body']['error']['code'] ?? '');
    }

    public function testAuditorCannotAddComment(): void
    {
        $versionId = $this->createRecipeAndGetVersionId();

        $response = $this->authenticatedRequest('POST', "/api/v1/recipe-versions/{$versionId}/comments", $this->auditorSession, [
            'content' => 'Auditor comment attempt',
        ]);

        $this->assertEquals(403, $response['status'],
            'Auditor should not be able to add comments. Got: ' . ($response['raw'] ?? ''));
        $this->assertEquals('FORBIDDEN_ROLE', $response['body']['error']['code'] ?? '');
    }

    public function testAuditorCanReadRecipes(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/recipes', $this->auditorSession);

        $this->assertEquals(200, $response['status'],
            'Auditor should be able to list recipes. Got: ' . ($response['raw'] ?? ''));
    }

    public function testAuditorCanReadSingleRecipe(): void
    {
        $recipeId = $this->createRecipeAsEditor();

        $response = $this->authenticatedRequest('GET', "/api/v1/recipes/{$recipeId}", $this->auditorSession);

        $this->assertEquals(200, $response['status'],
            'Auditor should be able to read a single recipe. Got: ' . ($response['raw'] ?? ''));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createRecipeAsEditor(): int
    {
        $siteId = $this->editorSession['user']['site_scopes'][0] ?? 1;

        $response = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->editorSession, [
            'title'   => 'Auditor Test Recipe ' . uniqid(),
            'site_id' => $siteId,
        ]);
        $this->assertEquals(201, $response['status'], 'Helper: create recipe must return 201');

        return (int) $response['body']['data']['id'];
    }

    private function createRecipeAndGetVersionId(): int
    {
        $recipeId = $this->createRecipeAsEditor();

        $readResponse = $this->authenticatedRequest('GET', "/api/v1/recipes/{$recipeId}", $this->editorSession);
        $this->assertEquals(200, $readResponse['status']);
        $versions = $readResponse['body']['data']['versions'] ?? [];
        $this->assertNotEmpty($versions);

        return (int) $versions[0]['id'];
    }
}
