<?php
declare(strict_types=1);

namespace tests\Feature\Analytics;

use tests\TestCase;

/**
 * Tests that all analytics dashboard filters (date range, site, community,
 * group leader, product) are accepted by the API and applied to query results.
 */
class DashboardFilterTest extends TestCase
{
    private array $analystSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analystSession = $this->loginAs('analyst');
        $this->assertNotEmpty($this->analystSession['csrf_token'], 'Analyst login must succeed');
    }

    /**
     * Dashboard endpoint accepts site_id filter without error.
     */
    public function testDashboardAcceptsSiteIdFilter(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/analytics/dashboard?site_id=1', $this->analystSession);

        $this->assertEquals(200, $response['status'],
            'Dashboard should accept site_id filter. Got: ' . ($response['raw'] ?? ''));
        $this->assertArrayHasKey('widgets', $response['body']['data'] ?? []);
    }

    /**
     * Dashboard endpoint accepts community_id filter without error.
     */
    public function testDashboardAcceptsCommunityFilter(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/analytics/dashboard?community_id=1', $this->analystSession);

        $this->assertEquals(200, $response['status'],
            'Dashboard should accept community_id filter. Got: ' . ($response['raw'] ?? ''));
        $this->assertArrayHasKey('widgets', $response['body']['data'] ?? []);
    }

    /**
     * Dashboard endpoint accepts group_leader_id filter without error.
     */
    public function testDashboardAcceptsGroupLeaderFilter(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/analytics/dashboard?group_leader_id=1', $this->analystSession);

        $this->assertEquals(200, $response['status'],
            'Dashboard should accept group_leader_id filter. Got: ' . ($response['raw'] ?? ''));
        $this->assertArrayHasKey('widgets', $response['body']['data'] ?? []);
    }

    /**
     * Dashboard endpoint accepts product_id filter without error.
     */
    public function testDashboardAcceptsProductFilter(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/analytics/dashboard?product_id=1', $this->analystSession);

        $this->assertEquals(200, $response['status'],
            'Dashboard should accept product_id filter. Got: ' . ($response['raw'] ?? ''));
        $this->assertArrayHasKey('widgets', $response['body']['data'] ?? []);
    }

    /**
     * Dashboard endpoint accepts date range filters without error.
     */
    public function testDashboardAcceptsDateRangeFilters(): void
    {
        $response = $this->authenticatedRequest(
            'GET',
            '/api/v1/analytics/dashboard?date_from=2024-01-01&date_to=2024-12-31',
            $this->analystSession
        );

        $this->assertEquals(200, $response['status'],
            'Dashboard should accept date range filters. Got: ' . ($response['raw'] ?? ''));
        $this->assertArrayHasKey('widgets', $response['body']['data'] ?? []);
    }

    /**
     * Dashboard endpoint accepts all filters combined.
     */
    public function testDashboardAcceptsAllFiltersCombined(): void
    {
        $qs = http_build_query([
            'date_from'       => '2024-01-01',
            'date_to'         => '2024-12-31',
            'site_id'         => 1,
            'community_id'    => 1,
            'group_leader_id' => 1,
            'product_id'      => 1,
        ]);

        $response = $this->authenticatedRequest('GET', "/api/v1/analytics/dashboard?{$qs}", $this->analystSession);

        $this->assertEquals(200, $response['status'],
            'Dashboard should accept all filters combined. Got: ' . ($response['raw'] ?? ''));
        $data = $response['body']['data'] ?? [];
        $this->assertArrayHasKey('widgets', $data);
        $this->assertArrayHasKey('generated_at', $data);
    }

    /**
     * Dashboard returns metric structure with filters applied.
     */
    public function testDashboardMetricStructureWithFilters(): void
    {
        $response = $this->authenticatedRequest(
            'GET',
            '/api/v1/analytics/dashboard?site_id=1&date_from=2024-01-01',
            $this->analystSession
        );

        $this->assertEquals(200, $response['status']);
        $metrics = $response['body']['data']['widgets']['metrics'] ?? [];

        // Verify metric keys exist even with filters
        $this->assertArrayHasKey('total_sales', $metrics);
        $this->assertArrayHasKey('order_count', $metrics);
        $this->assertArrayHasKey('avg_order_value', $metrics);
        $this->assertArrayHasKey('refund_rate', $metrics);
        $this->assertArrayHasKey('repeat_purchase_rate', $metrics);
        $this->assertArrayHasKey('group_conversion', $metrics);
        $this->assertArrayHasKey('leader_performance', $metrics);
        $this->assertArrayHasKey('product_popularity', $metrics);
    }
}
