<?php
declare(strict_types=1);

namespace tests\Feature\Search;

use tests\TestCase;

/**
 * Cross-domain search tests: verify that search spans multiple domains
 * and that site-scope enforcement filters results correctly.
 */
class CrossDomainSearchTest extends TestCase
{
    private array $adminSession;
    private array $analystSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminSession   = $this->loginAs('admin');
        $this->analystSession = $this->loginAs('analyst');
        $this->assertNotEmpty($this->adminSession['csrf_token'], 'Admin login must succeed');
        $this->assertNotEmpty($this->analystSession['csrf_token'], 'Analyst login must succeed');
    }

    /**
     * Admin searching for a recipe-related term should find recipe results.
     */
    public function testSearchFindsRecipesByTitle(): void
    {
        // First, ensure there is at least one recipe by creating one
        $siteId = $this->adminSession['user']['site_scopes'][0] ?? 1;
        $uniqueTitle = 'Soup Special ' . uniqid();

        $createResponse = $this->authenticatedRequest('POST', '/api/v1/recipes', $this->adminSession, [
            'title'   => $uniqueTitle,
            'site_id' => $siteId,
        ]);
        $this->assertContains($createResponse['status'], [200, 201],
            'Recipe creation must succeed. Got: ' . ($createResponse['raw'] ?? ''));

        // Search for the unique title
        $searchResponse = $this->authenticatedRequest('POST', '/api/v1/search/query', $this->adminSession, [
            'q'       => $uniqueTitle,
            'domains' => ['recipes'],
        ]);

        $this->assertEquals(200, $searchResponse['status'], 'Search must succeed');
        $results = $searchResponse['body']['data']['results'] ?? [];
        $this->assertArrayHasKey('recipes', $results, 'Results should contain recipes domain');

        $recipeResults = $results['recipes'];
        $this->assertNotEmpty($recipeResults, 'Search should find the recipe we just created');

        $found = false;
        foreach ($recipeResults as $recipe) {
            if (str_contains($recipe['title'] ?? '', $uniqueTitle)) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Search results must include the recipe with the searched title');
    }

    /**
     * Admin searching for participant-related terms should find participants.
     */
    public function testSearchFindsParticipants(): void
    {
        $searchResponse = $this->authenticatedRequest('POST', '/api/v1/search/query', $this->adminSession, [
            'q'       => 'a',
            'domains' => ['participants'],
        ]);

        $this->assertEquals(200, $searchResponse['status'], 'Search must succeed');
        $results = $searchResponse['body']['data']['results'] ?? [];
        $this->assertArrayHasKey('participants', $results, 'Results should contain participants domain');
        // Admin has access to all sites, so if there are participants they should show up
        // We just verify the structure is correct and the domain is present
        $this->assertIsArray($results['participants'], 'Participants results should be an array');
    }

    /**
     * Analyst (non-cross-site) should only see results from their scoped sites.
     * Editor is not in SEARCH_ROLES so cannot search at all.
     */
    public function testSearchRespectsSiteScope(): void
    {
        $analystScopes = $this->analystSession['user']['site_scopes'] ?? [];

        // Analyst is operations_analyst (in SEARCH_ROLES) but not cross-site
        $searchResponse = $this->authenticatedRequest('POST', '/api/v1/search/query', $this->analystSession, [
            'q' => 'a',
        ]);

        $this->assertEquals(200, $searchResponse['status'], 'Analyst search must succeed');
        $results = $searchResponse['body']['data']['results'] ?? [];

        // If analyst has scoped sites, verify results are limited to those sites
        if (!empty($analystScopes)) {
            $participantResults = $results['participants'] ?? [];
            foreach ($participantResults as $participant) {
                $this->assertContains((int) ($participant['site_id'] ?? 0), $analystScopes,
                    'Analyst should only see participants from their scoped sites. Found site_id: ' . ($participant['site_id'] ?? 'null'));
            }

            $orderResults = $results['orders'] ?? [];
            foreach ($orderResults as $order) {
                $this->assertContains((int) ($order['site_id'] ?? 0), $analystScopes,
                    'Analyst should only see orders from their scoped sites. Found site_id: ' . ($order['site_id'] ?? 'null'));
            }
        }
    }

    /**
     * Editor is not in SEARCH_ROLES and should be denied access.
     */
    public function testEditorCannotSearch(): void
    {
        $editorSession = $this->loginAs('editor');
        $this->assertNotEmpty($editorSession['csrf_token'], 'Editor login must succeed');

        $searchResponse = $this->authenticatedRequest('POST', '/api/v1/search/query', $editorSession, [
            'q' => 'test',
        ]);

        $this->assertEquals(403, $searchResponse['status'],
            'Editor should be denied search access. Got: ' . ($searchResponse['raw'] ?? ''));
    }

    /**
     * Admin with access to all sites should see results across all sites.
     */
    public function testCrossSiteAdminSeesAllResults(): void
    {
        $adminScopes = $this->adminSession['user']['site_scopes'] ?? [];

        // Admin should have access to all sites (more than sites 1,2)
        $this->assertNotEmpty($adminScopes, 'Admin should have site scopes');

        // Search across all domains
        $searchResponse = $this->authenticatedRequest('POST', '/api/v1/search/query', $this->adminSession, [
            'q' => 'a',
        ]);

        $this->assertEquals(200, $searchResponse['status'], 'Admin search must succeed');
        $results = $searchResponse['body']['data']['results'] ?? [];

        // Admin results should span multiple domains
        $populatedDomains = 0;
        foreach ($results as $domain => $items) {
            if (!empty($items)) {
                $populatedDomains++;
            }
        }

        // Admin should see results from at least one domain
        $this->assertGreaterThanOrEqual(1, $populatedDomains,
            'Admin search should return results from at least one domain');

        // Verify the response contains the expected domain keys
        $expectedDomains = ['positions', 'companies', 'participants', 'recipes', 'orders', 'settlements'];
        foreach ($expectedDomains as $domain) {
            $this->assertArrayHasKey($domain, $results,
                "Admin search results should include the '{$domain}' domain key");
        }
    }
}
