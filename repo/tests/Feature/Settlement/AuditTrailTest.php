<?php
declare(strict_types=1);

namespace tests\Feature\Settlement;

use tests\TestCase;

/**
 * Tests for GET /api/v1/finance/settlements/:id/audit-trail — proves the
 * SettlementController::auditTrail handler executes through the live HTTP
 * route. Generates and submits a real statement so that audit entries
 * actually exist for the target id.
 *
 * ZERO mocks/stubs.
 */
class AuditTrailTest extends TestCase
{
    private array $adminSession;
    private array $financeSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminSession   = $this->loginAs('admin');
        $this->financeSession = $this->loginAs('finance');
    }

    public function testAuditTrailReturnsEntriesForRealStatement(): void
    {
        $siteId = $this->financeSession['user']['site_scopes'][0] ?? 1;
        $period = '2026-' . str_pad((string) rand(1, 12), 2, '0', STR_PAD_LEFT);

        // Generate then submit so the audit pipeline produces at least one entry.
        $generate = $this->authenticatedRequest(
            'POST',
            '/api/v1/finance/settlements/generate',
            $this->financeSession,
            ['site_id' => $siteId, 'period' => $period]
        );
        $this->assertContains($generate['status'], [200, 202],
            'Helper: generate must succeed. Got: ' . ($generate['raw'] ?? ''));
        $statementId = (int) ($generate['body']['data']['settlement_id'] ?? 0);
        $this->assertGreaterThan(0, $statementId);

        $submit = $this->authenticatedRequest(
            'POST',
            "/api/v1/finance/settlements/{$statementId}/submit",
            $this->financeSession
        );
        $this->assertEquals(200, $submit['status'],
            'Helper: submit must succeed (creates audit trail entry). Got: ' . ($submit['raw'] ?? ''));

        // Now hit the audit-trail handler.
        $response = $this->authenticatedRequest(
            'GET',
            "/api/v1/finance/settlements/{$statementId}/audit-trail",
            $this->adminSession
        );

        $this->assertEquals(200, $response['status'],
            'Audit-trail should return 200. Got: ' . ($response['raw'] ?? ''));

        $data = $response['body']['data'] ?? [];
        $this->assertSame($statementId, (int) ($data['settlement_id'] ?? 0),
            'Response must echo the requested settlement id');
        $this->assertArrayHasKey('entries', $data, 'Response must expose entries array');
        $this->assertIsArray($data['entries']);
        $this->assertNotEmpty(
            $data['entries'],
            'Submit-then-trail flow must surface at least one audit entry for the target statement'
        );
    }

    public function testAuditTrailUnknownStatementReturns404(): void
    {
        $response = $this->authenticatedRequest(
            'GET',
            '/api/v1/finance/settlements/9999999/audit-trail',
            $this->adminSession
        );

        $this->assertEquals(404, $response['status']);
        $this->assertSame('NOT_FOUND', $response['body']['error']['code'] ?? '');
    }
}
