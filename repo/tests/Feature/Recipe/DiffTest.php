<?php
declare(strict_types=1);

namespace tests\Feature\Recipe;

use tests\TestCase;

/**
 * Tests for GET /api/v1/recipe-versions/:id/diff — proves the
 * RecipeController::diff handler executes through the live HTTP route.
 *
 * Creates a recipe, edits the draft v1, creates a v2, edits v2,
 * then asks for the diff and asserts on the response shape and content.
 *
 * ZERO mocks/stubs.
 */
class DiffTest extends TestCase
{
    private array $editorSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editorSession = $this->loginAs('editor');
    }

    public function testDiffBetweenTwoVersionsReturnsChanges(): void
    {
        $siteId = $this->editorSession['user']['site_scopes'][0] ?? 1;

        // Step 1: Create a recipe (v1 draft is auto-created).
        $createResponse = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->editorSession, [
            'title'   => 'Diff Recipe ' . uniqid(),
            'site_id' => $siteId,
        ]);
        $this->assertEquals(201, $createResponse['status'], 'Recipe creation must succeed');
        $recipeId = (int) ($createResponse['body']['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $recipeId);

        // Step 2: Read recipe to get version 1 id.
        $readResponse = $this->authenticatedRequest('GET', "/api/v1/recipes/{$recipeId}", $this->editorSession);
        $this->assertEquals(200, $readResponse['status']);
        $versions = $readResponse['body']['data']['versions'] ?? [];
        $this->assertNotEmpty($versions, 'Recipe must have an initial version');
        $v1Id = (int) $versions[0]['id'];

        // Step 3: Update v1 draft with concrete fields.
        $updateV1 = $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$v1Id}", $this->editorSession, [
            'steps'      => [['instruction' => 'Original step', 'duration_minutes' => 10]],
            'total_time' => 30,
            'difficulty' => 'easy',
            'prep_time'  => 10,
            'cook_time'  => 20,
        ]);
        $this->assertEquals(200, $updateV1['status'], 'Update v1 must succeed');

        // Step 4: Create v2.
        $newVersionResponse = $this->authenticatedRequest(
            'POST',
            "/api/v1/recipes/{$recipeId}/versions",
            $this->editorSession,
            []
        );
        $this->assertEquals(201, $newVersionResponse['status'],
            'Create v2 must succeed. Got: ' . ($newVersionResponse['raw'] ?? ''));
        $v2Id = (int) ($newVersionResponse['body']['data']['version']['id']
            ?? $newVersionResponse['body']['data']['version_id']
            ?? 0);
        $this->assertGreaterThan(0, $v2Id, 'v2 id must be returned');

        // Step 5: Modify v2 so the diff produces concrete changes.
        $updateV2 = $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$v2Id}", $this->editorSession, [
            'steps'      => [['instruction' => 'Updated step', 'duration_minutes' => 25]],
            'total_time' => 60,
            'difficulty' => 'medium',
            'prep_time'  => 20,
            'cook_time'  => 40,
        ]);
        $this->assertEquals(200, $updateV2['status'], 'Update v2 must succeed');

        // Step 6: Hit the diff handler.
        $diffResponse = $this->authenticatedRequest(
            'GET',
            "/api/v1/recipe-versions/{$v2Id}/diff",
            $this->editorSession
        );

        $this->assertEquals(200, $diffResponse['status'],
            'Diff endpoint should return 200. Got: ' . ($diffResponse['raw'] ?? ''));

        $data = $diffResponse['body']['data'] ?? [];
        $this->assertSame($v2Id, (int) ($data['version_id'] ?? 0),
            'Diff response must echo the requested version_id');
        $this->assertArrayHasKey('current_version', $data, 'Diff must include current_version number');
        $this->assertArrayHasKey('previous_version', $data, 'Diff must include previous_version number');
        $this->assertArrayHasKey('changes', $data, 'Diff must include changes array');
        $this->assertIsArray($data['changes']);
        $this->assertNotEmpty($data['changes'],
            'Diff between modified versions must report at least one change');

        // Confirm the changes payload references the modified fields.
        $fields = array_column($data['changes'], 'field');
        $this->assertContains('total_time', $fields, 'total_time change must appear in diff');
        $this->assertContains('difficulty', $fields, 'difficulty change must appear in diff');
    }

    public function testDiffOnFirstVersionReturnsEmptyChanges(): void
    {
        $siteId = $this->editorSession['user']['site_scopes'][0] ?? 1;

        $createResponse = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->editorSession, [
            'title'   => 'Diff First-Only Recipe ' . uniqid(),
            'site_id' => $siteId,
        ]);
        $this->assertEquals(201, $createResponse['status']);
        $recipeId = (int) $createResponse['body']['data']['id'];

        $readResponse = $this->authenticatedRequest('GET', "/api/v1/recipes/{$recipeId}", $this->editorSession);
        $v1Id = (int) ($readResponse['body']['data']['versions'][0]['id'] ?? 0);
        $this->assertGreaterThan(0, $v1Id);

        $diffResponse = $this->authenticatedRequest(
            'GET',
            "/api/v1/recipe-versions/{$v1Id}/diff",
            $this->editorSession
        );

        $this->assertEquals(200, $diffResponse['status']);
        $data = $diffResponse['body']['data'] ?? [];
        $this->assertSame($v1Id, (int) ($data['version_id'] ?? 0));
        $this->assertSame([], $data['changes'] ?? null,
            'First version diff must produce empty changes (no previous version).');
    }
}
