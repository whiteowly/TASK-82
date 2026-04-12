<?php
declare(strict_types=1);

namespace tests\Feature\Recipe;

use tests\TestCase;

/**
 * Tests the GET /api/v1/recipe-versions/:id/comments endpoint.
 *
 * Verifies:
 * - Persisted comments are returned in order
 * - Empty comment list returns 200 with empty array
 * - Non-existent version returns 404
 */
class CommentHistoryTest extends TestCase
{
    private array $editorSession;
    private array $reviewerSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editorSession   = $this->loginAs('editor');
        $this->reviewerSession = $this->loginAs('reviewer');
    }

    public function testListCommentsReturnsPersistedComments(): void
    {
        $versionId = $this->createRecipeAndGetVersionId();

        // Add two comments
        $this->authenticatedRequest('POST', "/api/v1/recipe-versions/{$versionId}/comments", $this->editorSession, [
            'content'     => 'First comment',
            'anchor_type' => 'general',
        ]);
        $this->authenticatedRequest('POST', "/api/v1/recipe-versions/{$versionId}/comments", $this->reviewerSession, [
            'content'     => 'Second comment',
            'anchor_type' => 'step',
        ]);

        // GET comments
        $response = $this->authenticatedRequest('GET', "/api/v1/recipe-versions/{$versionId}/comments", $this->editorSession);

        $this->assertEquals(200, $response['status'],
            'List comments should return 200. Got: ' . ($response['raw'] ?? ''));

        $data = $response['body']['data'] ?? [];
        $this->assertEquals((int) $versionId, $data['version_id'] ?? 0);

        $comments = $data['comments'] ?? [];
        $this->assertCount(2, $comments, 'Should return 2 persisted comments');
        $this->assertEquals('First comment', $comments[0]['content'] ?? '');
        $this->assertEquals('Second comment', $comments[1]['content'] ?? '');
    }

    public function testListCommentsReturnsEmptyForVersionWithNoComments(): void
    {
        $versionId = $this->createRecipeAndGetVersionId();

        $response = $this->authenticatedRequest('GET', "/api/v1/recipe-versions/{$versionId}/comments", $this->editorSession);

        $this->assertEquals(200, $response['status'],
            'List comments for version with no comments should return 200. Got: ' . ($response['raw'] ?? ''));

        $comments = $response['body']['data']['comments'] ?? [];
        $this->assertEmpty($comments, 'Comment list should be empty');
    }

    public function testListCommentsReturns404ForNonExistentVersion(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/recipe-versions/999999/comments', $this->editorSession);

        $this->assertEquals(404, $response['status'],
            'Non-existent version should return 404. Got: ' . ($response['raw'] ?? ''));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createRecipeAndGetVersionId(): int
    {
        $siteId = $this->editorSession['user']['site_scopes'][0] ?? 1;

        $createResponse = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->editorSession, [
            'title'   => 'Comment Test Recipe ' . uniqid(),
            'site_id' => $siteId,
        ]);
        $this->assertEquals(201, $createResponse['status'], 'Helper: create must return 201');
        $recipeId = $createResponse['body']['data']['id'];

        $readResponse = $this->authenticatedRequest('GET', "/api/v1/recipes/{$recipeId}", $this->editorSession);
        $this->assertEquals(200, $readResponse['status']);
        $versions = $readResponse['body']['data']['versions'] ?? [];
        $this->assertNotEmpty($versions);

        return (int) $versions[0]['id'];
    }
}
