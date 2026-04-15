<?php
declare(strict_types=1);

namespace tests\Feature\Catalog;

use tests\TestCase;

/**
 * Tests for GET /api/v1/catalog/recipes/:id — proves the
 * CatalogController::read handler executes through the live HTTP route.
 *
 * Resolves a published recipe id from the catalog index, then fetches
 * the detail endpoint and asserts on the response shape.
 *
 * ZERO mocks/stubs.
 */
class CatalogReadTest extends TestCase
{
    private array $adminSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminSession = $this->loginAs('admin');
    }

    public function testReadPublishedRecipeFromCatalogReturnsDetail(): void
    {
        // Find a real published catalog item so we can call read with a known id.
        $listResponse = $this->authenticatedRequest('GET', '/api/v1/catalog/recipes', $this->adminSession);
        $this->assertEquals(200, $listResponse['status']);
        $items = $listResponse['body']['data']['items'] ?? [];
        $this->assertNotEmpty($items, 'Catalog should contain at least one published recipe (seed data).');

        $recipeId = (int) ($items[0]['id'] ?? 0);
        $this->assertGreaterThan(0, $recipeId, 'Catalog item must expose an id');

        $response = $this->authenticatedRequest(
            'GET',
            "/api/v1/catalog/recipes/{$recipeId}",
            $this->adminSession
        );

        $this->assertEquals(200, $response['status'],
            'Catalog read should return 200. Got: ' . ($response['raw'] ?? ''));

        $data = $response['body']['data'] ?? [];
        $this->assertSame($recipeId, (int) ($data['id'] ?? 0),
            'Catalog detail must echo the requested recipe id');
        $this->assertSame('published', $data['status'] ?? '',
            'Only published recipes are visible in the catalog');
        $this->assertArrayHasKey('published_version', $data,
            'Catalog detail must hydrate the published_version field');
        $this->assertNotNull($data['published_version'] ?? null,
            'published_version must be populated for a catalog hit');
        $this->assertArrayHasKey('id', $data['published_version'],
            'published_version must include its own id');
    }

    public function testReadUnknownCatalogIdReturns404(): void
    {
        $response = $this->authenticatedRequest(
            'GET',
            '/api/v1/catalog/recipes/9999999',
            $this->adminSession
        );

        $this->assertEquals(404, $response['status']);
        $this->assertSame('NOT_FOUND', $response['body']['error']['code'] ?? '');
    }
}
