<?php
declare(strict_types=1);

namespace tests\Feature\Recipe;

use tests\TestCase;

/**
 * Tests that submit-for-review rejects incomplete recipe versions.
 *
 * A version must have:
 * - steps: at least 1 (max 50)
 * - total_time: 1..720
 * - difficulty: set
 * - ingredients: at least 1
 */
class SubmitReviewCompletenessTest extends TestCase
{
    private array $editorSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editorSession = $this->loginAs('editor');
        $this->assertNotEmpty($this->editorSession['csrf_token'], 'Editor login must succeed');
    }

    public function testSubmitReviewRejectsVersionWithNoSteps(): void
    {
        $versionId = $this->createRecipeAndGetVersionId();

        // Update with total_time and difficulty but no steps
        $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $this->editorSession, [
            'steps'       => [['instruction' => 'Temp step', 'duration_minutes' => 5]],
            'total_time'  => 30,
            'difficulty'  => 'easy',
            'ingredients' => [['name' => 'Salt', 'quantity' => 1, 'unit' => 'tsp']],
        ]);

        // Now delete the steps by updating with valid data, then we'll test a fresh version
        // Instead, create a fresh recipe and don't add steps
        $freshVersionId = $this->createRecipeAndGetVersionId();

        // Submit without updating (no steps, no total_time, no ingredients)
        $response = $this->authenticatedRequest('POST', "/api/v1/recipe-versions/{$freshVersionId}/submit-review", $this->editorSession);

        $this->assertEquals(422, $response['status'],
            'Submit-review should reject version with missing required fields. Got: ' . ($response['raw'] ?? ''));
        $this->assertEquals('VALIDATION_FAILED', $response['body']['error']['code'] ?? '');
    }

    public function testSubmitReviewRejectsVersionWithNoIngredients(): void
    {
        $versionId = $this->createRecipeAndGetVersionId();

        // Update with steps and total_time but no ingredients
        $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $this->editorSession, [
            'steps'      => [['instruction' => 'Mix everything', 'duration_minutes' => 10]],
            'total_time' => 10,
            'difficulty' => 'easy',
        ]);

        $response = $this->authenticatedRequest('POST', "/api/v1/recipe-versions/{$versionId}/submit-review", $this->editorSession);

        $this->assertEquals(422, $response['status'],
            'Submit-review should reject version without ingredients. Got: ' . ($response['raw'] ?? ''));
        $this->assertEquals('VALIDATION_FAILED', $response['body']['error']['code'] ?? '');
    }

    public function testSubmitReviewRejectsVersionWithNoTotalTime(): void
    {
        $versionId = $this->createRecipeAndGetVersionId();

        // We can't easily set steps without total_time via the update endpoint
        // because RecipeVersionValidate requires total_time. So we test that
        // a version that was never updated (no total_time set) is rejected.
        $response = $this->authenticatedRequest('POST', "/api/v1/recipe-versions/{$versionId}/submit-review", $this->editorSession);

        $this->assertEquals(422, $response['status'],
            'Submit-review should reject version without total_time. Got: ' . ($response['raw'] ?? ''));
        $this->assertEquals('VALIDATION_FAILED', $response['body']['error']['code'] ?? '');
        $details = $response['body']['error']['details'] ?? [];
        $this->assertNotEmpty($details, 'Error details should list what is incomplete');
    }

    public function testSubmitReviewAcceptsCompleteVersion(): void
    {
        $versionId = $this->createRecipeAndGetVersionId();

        // Update with all required fields
        $updateResponse = $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $this->editorSession, [
            'steps'       => [
                ['instruction' => 'Preheat oven', 'duration_minutes' => 10],
                ['instruction' => 'Mix ingredients', 'duration_minutes' => 5],
            ],
            'total_time'  => 15,
            'difficulty'  => 'medium',
            'ingredients' => [
                ['name' => 'Flour', 'quantity' => 200, 'unit' => 'g'],
                ['name' => 'Sugar', 'quantity' => 50, 'unit' => 'g'],
            ],
        ]);
        $this->assertEquals(200, $updateResponse['status'], 'Update must succeed');

        // Submit for review should succeed
        $response = $this->authenticatedRequest('POST', "/api/v1/recipe-versions/{$versionId}/submit-review", $this->editorSession);

        $this->assertEquals(200, $response['status'],
            'Submit-review should accept complete version. Got: ' . ($response['raw'] ?? ''));
        $this->assertEquals('in_review', $response['body']['data']['status'] ?? '');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createRecipeAndGetVersionId(): int
    {
        $siteId = $this->editorSession['user']['site_scopes'][0] ?? 1;

        $createResponse = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->editorSession, [
            'title'   => 'Completeness Test Recipe ' . uniqid(),
            'site_id' => $siteId,
        ]);
        $this->assertEquals(201, $createResponse['status'], 'Helper: create recipe must return 201');
        $recipeId = $createResponse['body']['data']['id'];

        $readResponse = $this->authenticatedRequest('GET', "/api/v1/recipes/{$recipeId}", $this->editorSession);
        $this->assertEquals(200, $readResponse['status']);
        $versions = $readResponse['body']['data']['versions'] ?? [];
        $this->assertNotEmpty($versions);

        return (int) $versions[0]['id'];
    }
}
