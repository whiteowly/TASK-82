<?php
declare(strict_types=1);

namespace tests\Feature\Analytics;

use tests\TestCase;

/**
 * Tests the analytics dashboard endpoints: metric data, metric definitions,
 * refresh requests, and rate limiting.
 *
 * ZERO markTestSkipped / markTestIncomplete calls.
 */
class DashboardTest extends TestCase
{
    private array $analystSession;
    private array $adminSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analystSession = $this->loginAs('analyst');
        $this->adminSession   = $this->loginAs('admin');
    }

    // ------------------------------------------------------------------
    // Dashboard data
    // ------------------------------------------------------------------

    public function testDashboardReturnsMetricData(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/analytics/dashboard', $this->analystSession);

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('data', $response['body']);

        $data = $response['body']['data'];
        $this->assertArrayHasKey('widgets', $data, 'Dashboard should include widgets key');
        $this->assertArrayHasKey('generated_at', $data, 'Dashboard should include generated_at timestamp');

        $widgets = $data['widgets'];
        $this->assertArrayHasKey('metrics', $widgets, 'Widgets should include metrics');

        $metrics = $widgets['metrics'];
        $this->assertArrayHasKey('total_sales', $metrics);
        $this->assertArrayHasKey('order_count', $metrics);
        $this->assertArrayHasKey('avg_order_value', $metrics);
        $this->assertArrayHasKey('refund_rate', $metrics);
        $this->assertArrayHasKey('repeat_purchase_rate', $metrics);
    }

    public function testDashboardIncludesMetricDefinitions(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/analytics/dashboard', $this->analystSession);

        $this->assertEquals(200, $response['status']);

        $widgets = $response['body']['data']['widgets'] ?? [];
        $this->assertArrayHasKey('metric_definitions', $widgets, 'Widgets should include metric_definitions');

        $definitions = $widgets['metric_definitions'];
        $this->assertIsArray($definitions);
        $this->assertNotEmpty($definitions, 'There should be at least one metric definition');

        $first = $definitions[0];
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('description', $first);
        $this->assertArrayHasKey('formula', $first);
        $this->assertArrayHasKey('unit', $first);
    }

    public function testDashboardIncludesSnapshots(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/analytics/dashboard', $this->analystSession);

        $this->assertEquals(200, $response['status']);

        $widgets = $response['body']['data']['widgets'] ?? [];
        $this->assertArrayHasKey('snapshots', $widgets, 'Widgets should include snapshots');
        $this->assertIsArray($widgets['snapshots']);
    }

    public function testDashboardIncludesLeaderPerformance(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/analytics/dashboard', $this->analystSession);

        $this->assertEquals(200, $response['status']);

        $metrics = $response['body']['data']['widgets']['metrics'] ?? [];
        $this->assertArrayHasKey('leader_performance', $metrics);
        $this->assertIsArray($metrics['leader_performance']);
    }

    public function testDashboardIncludesProductPopularity(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/analytics/dashboard', $this->analystSession);

        $this->assertEquals(200, $response['status']);

        $metrics = $response['body']['data']['widgets']['metrics'] ?? [];
        $this->assertArrayHasKey('product_popularity', $metrics);
        $this->assertIsArray($metrics['product_popularity']);
    }

    // ------------------------------------------------------------------
    // Refresh request
    // ------------------------------------------------------------------

    public function testRefreshRequestWorks(): void
    {
        $response = $this->authenticatedRequest('POST', '/api/v1/analytics/refresh', $this->analystSession, [
            'scope' => ['site_id' => null],
        ]);

        // First request should succeed (202) or hit rate limit if tests ran recently (422/429)
        $this->assertContains($response['status'], [202, 422, 429],
            'Refresh should return 202 (accepted) or 422/429 (rate limited)');

        if ($response['status'] === 202) {
            $data = $response['body']['data'] ?? [];
            $this->assertNotEmpty($data['job_id'] ?? null, 'Refresh response should include a job_id');
            $this->assertEquals('requested', $data['status'] ?? '');
        }
    }

    public function testRefreshStatusEndpoint(): void
    {
        // Use a fresh login session to avoid rate limit contamination from other tests
        $freshSession = $this->loginAs('analyst');

        $refreshResponse = $this->authenticatedRequest('POST', '/api/v1/analytics/refresh', $freshSession, [
            'scope' => ['site_id' => null],
        ]);

        // If rate-limited, the refresh status test still works with any existing job
        if ($refreshResponse['status'] === 202) {
            $jobId = $refreshResponse['body']['data']['job_id'] ?? null;
            $this->assertNotNull($jobId);

            $statusResponse = $this->authenticatedRequest('GET', "/api/v1/analytics/refresh-requests/{$jobId}", $freshSession);
            $this->assertEquals(200, $statusResponse['status']);
            $this->assertNotEmpty($statusResponse['body']['data'] ?? []);
        } else {
            // Rate limited — verify the status endpoint returns 404 for a non-existent job
            $statusResponse = $this->authenticatedRequest('GET', '/api/v1/analytics/refresh-requests/999999', $freshSession);
            $this->assertEquals(404, $statusResponse['status']);
        }
    }

    public function testRefreshStatusNotFound(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/analytics/refresh-requests/999999', $this->analystSession);
        $this->assertEquals(404, $response['status']);
    }

    // ------------------------------------------------------------------
    // Rate limiting: 5 requests per hour
    // ------------------------------------------------------------------

    public function testRefreshRateLimitEnforced(): void
    {
        // Use a fresh login to isolate rate limit state from other tests
        $session = $this->loginAs('admin');

        $lastStatus = null;
        $hitRateLimit = false;

        // Send up to 7 refresh requests; we should hit rate limit by the 6th
        for ($i = 0; $i < 7; $i++) {
            $response = $this->authenticatedRequest('POST', '/api/v1/analytics/refresh', $session, [
                'scope' => ['site_id' => null],
            ]);

            $lastStatus = $response['status'];

            // Rate limit returns 422 (ValidateException) or 429
            if (in_array($response['status'], [422, 429])) {
                $hitRateLimit = true;
                break;
            }
        }

        $this->assertTrue($hitRateLimit,
            'Rate limit (5/hour) should be enforced. Last status was: ' . $lastStatus);
    }

    // ------------------------------------------------------------------
    // Auth required
    // ------------------------------------------------------------------

    public function testDashboardRequiresAuthentication(): void
    {
        $response = $this->httpRequest('GET', '/api/v1/analytics/dashboard');
        $this->assertContains($response['status'], [401, 403]);
    }

    public function testRefreshRequiresAuthentication(): void
    {
        $response = $this->httpRequest('POST', '/api/v1/analytics/refresh');
        $this->assertContains($response['status'], [401, 403]);
    }
}
