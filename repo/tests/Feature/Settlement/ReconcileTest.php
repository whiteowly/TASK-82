<?php
declare(strict_types=1);

namespace tests\Feature\Settlement;

use tests\TestCase;

/**
 * Tests for POST /api/v1/finance/settlements/:id/reconcile — proves the
 * SettlementController::reconcile handler executes through the live HTTP
 * route by generating a real statement first and then reconciling it.
 *
 * ZERO mocks/stubs.
 */
class ReconcileTest extends TestCase
{
    private array $financeSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->financeSession = $this->loginAs('finance');
    }

    private function generateStatement(): int
    {
        $siteId = $this->financeSession['user']['site_scopes'][0] ?? 1;
        $period = '2026-' . str_pad((string) rand(1, 12), 2, '0', STR_PAD_LEFT);

        $generateResponse = $this->authenticatedRequest(
            'POST',
            '/api/v1/finance/settlements/generate',
            $this->financeSession,
            [
                'site_id' => $siteId,
                'period'  => $period,
            ]
        );

        $this->assertContains($generateResponse['status'], [200, 202],
            'Helper: generate must succeed. Got: ' . ($generateResponse['raw'] ?? ''));

        $statementId = (int) ($generateResponse['body']['data']['settlement_id'] ?? 0);
        $this->assertGreaterThan(0, $statementId, 'Helper: settlement_id must be returned');

        return $statementId;
    }

    public function testReconcileRecordsReconciliationAndReturnsStatus(): void
    {
        $statementId = $this->generateStatement();

        $response = $this->authenticatedRequest(
            'POST',
            "/api/v1/finance/settlements/{$statementId}/reconcile",
            $this->financeSession,
            [
                'notes' => [
                    [
                        'field_name'     => 'sales_total',
                        'expected_value' => '1000.00',
                        'actual_value'   => '1000.00',
                        'notes'          => 'Verified against POS export',
                        'resolved'       => true,
                    ],
                    [
                        'field_name'     => 'freight_total',
                        'expected_value' => '120.00',
                        'actual_value'   => '120.00',
                        'notes'          => 'Matches carrier invoice',
                        'resolved'       => true,
                    ],
                ],
            ]
        );

        $this->assertEquals(200, $response['status'],
            'Reconcile should return 200. Got: ' . ($response['raw'] ?? ''));

        $data = $response['body']['data'] ?? [];
        $this->assertSame($statementId, (int) ($data['settlement_id'] ?? 0),
            'Response must echo the statement id');
        $this->assertSame('reconciled', $data['status'] ?? '',
            'Handler must report the reconciled status');
        $this->assertSame('Reconciliation recorded.', $data['message'] ?? '',
            'Handler must include the success message');
    }

    public function testReconcileUnknownStatementReturns404(): void
    {
        $response = $this->authenticatedRequest(
            'POST',
            '/api/v1/finance/settlements/9999999/reconcile',
            $this->financeSession,
            ['notes' => []]
        );

        $this->assertEquals(404, $response['status']);
        $this->assertSame('NOT_FOUND', $response['body']['error']['code'] ?? '');
    }
}
