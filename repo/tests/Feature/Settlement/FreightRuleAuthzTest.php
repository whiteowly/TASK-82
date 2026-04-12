<?php
declare(strict_types=1);

namespace tests\Feature\Settlement;

use tests\TestCase;

/**
 * Tests role authorization on the freight-rule update endpoint.
 *
 * Only finance_clerk and administrator should be allowed to update.
 * All other roles (editor, reviewer, analyst, auditor) should get 403.
 */
class FreightRuleAuthzTest extends TestCase
{
    private array $financeSession;
    private array $adminSession;
    private array $editorSession;
    private array $reviewerSession;
    private array $analystSession;
    private array $auditorSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->financeSession  = $this->loginAs('finance');
        $this->adminSession    = $this->loginAs('admin');
        $this->editorSession   = $this->loginAs('editor');
        $this->reviewerSession = $this->loginAs('reviewer');
        $this->analystSession  = $this->loginAs('analyst');
        $this->auditorSession  = $this->loginAs('auditor');
    }

    private function createFreightRule(): int
    {
        $siteId = $this->financeSession['user']['site_scopes'][0] ?? 1;

        $response = $this->authenticatedRequest('POST', '/api/v1/finance/freight-rules', $this->financeSession, [
            'name'           => 'AuthZ Test Rule ' . uniqid(),
            'site_id'        => $siteId,
            'tax_rate'       => 0.05,
            'distance_bands' => [['min_km' => 0, 'max_km' => 100, 'rate' => 5.00]],
        ]);
        $this->assertContains($response['status'], [200, 201],
            'Helper: create freight rule must succeed. Got: ' . ($response['raw'] ?? ''));

        return (int) ($response['body']['data']['id'] ?? 0);
    }

    public function testEditorCannotUpdateFreightRule(): void
    {
        $ruleId = $this->createFreightRule();

        $response = $this->authenticatedRequest('PATCH', "/api/v1/finance/freight-rules/{$ruleId}", $this->editorSession, [
            'name' => 'Hijacked Name',
        ]);
        $this->assertEquals(403, $response['status'],
            'Editor should not update freight rules. Got: ' . ($response['raw'] ?? ''));
        $this->assertEquals('FORBIDDEN_ROLE', $response['body']['error']['code'] ?? '');
    }

    public function testReviewerCannotUpdateFreightRule(): void
    {
        $ruleId = $this->createFreightRule();

        $response = $this->authenticatedRequest('PATCH', "/api/v1/finance/freight-rules/{$ruleId}", $this->reviewerSession, [
            'name' => 'Hijacked Name',
        ]);
        $this->assertEquals(403, $response['status'],
            'Reviewer should not update freight rules. Got: ' . ($response['raw'] ?? ''));
    }

    public function testAnalystCannotUpdateFreightRule(): void
    {
        $ruleId = $this->createFreightRule();

        $response = $this->authenticatedRequest('PATCH', "/api/v1/finance/freight-rules/{$ruleId}", $this->analystSession, [
            'name' => 'Hijacked Name',
        ]);
        $this->assertEquals(403, $response['status'],
            'Analyst should not update freight rules. Got: ' . ($response['raw'] ?? ''));
    }

    public function testAuditorCannotUpdateFreightRule(): void
    {
        $ruleId = $this->createFreightRule();

        $response = $this->authenticatedRequest('PATCH', "/api/v1/finance/freight-rules/{$ruleId}", $this->auditorSession, [
            'name' => 'Hijacked Name',
        ]);
        $this->assertEquals(403, $response['status'],
            'Auditor should not update freight rules. Got: ' . ($response['raw'] ?? ''));
    }

    public function testFinanceCanUpdateFreightRule(): void
    {
        $ruleId = $this->createFreightRule();

        $response = $this->authenticatedRequest('PATCH', "/api/v1/finance/freight-rules/{$ruleId}", $this->financeSession, [
            'name' => 'Updated Name ' . uniqid(),
        ]);
        $this->assertEquals(200, $response['status'],
            'Finance should update freight rules. Got: ' . ($response['raw'] ?? ''));
    }

    public function testAdminCanUpdateFreightRule(): void
    {
        $ruleId = $this->createFreightRule();

        $response = $this->authenticatedRequest('PATCH', "/api/v1/finance/freight-rules/{$ruleId}", $this->adminSession, [
            'name' => 'Admin Updated ' . uniqid(),
        ]);
        $this->assertEquals(200, $response['status'],
            'Admin should update freight rules. Got: ' . ($response['raw'] ?? ''));
    }
}
