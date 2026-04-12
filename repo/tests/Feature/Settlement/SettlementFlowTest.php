<?php
declare(strict_types=1);

namespace tests\Feature\Settlement;

use tests\TestCase;

/**
 * Tests the settlement flow: freight rules, statement generation,
 * submit, approve-lock, immutability, reversal, and role enforcement.
 *
 * ZERO markTestSkipped / markTestIncomplete calls.
 */
class SettlementFlowTest extends TestCase
{
    private array $adminSession;
    private array $financeSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminSession   = $this->loginAs('admin');
        $this->financeSession = $this->loginAs('finance');
    }

    // ------------------------------------------------------------------
    // Freight rules
    // ------------------------------------------------------------------

    public function testCreateFreightRule(): void
    {
        $siteId = $this->financeSession['user']['site_scopes'][0] ?? 1;

        $response = $this->authenticatedRequest('POST', '/api/v1/finance/freight-rules', $this->financeSession, [
            'name'           => 'Test Freight Rule ' . uniqid(),
            'site_id'        => $siteId,
            'tax_rate'       => 0.08,
            'distance_bands' => [
                ['min_km' => 0, 'max_km' => 50, 'rate' => 5.00],
                ['min_km' => 51, 'max_km' => 200, 'rate' => 10.00],
            ],
            'weight_tiers'   => [
                ['min_kg' => 0, 'max_kg' => 10, 'rate' => 2.00],
            ],
        ]);

        $this->assertEquals(201, $response['status'],
            'Freight rule creation should return 201. Got: ' . ($response['raw'] ?? ''));
        $this->assertNotEmpty($response['body']['data']['id'] ?? null);
    }

    public function testCreateFreightRuleRequiresName(): void
    {
        $siteId = $this->financeSession['user']['site_scopes'][0] ?? 1;

        $response = $this->authenticatedRequest('POST', '/api/v1/finance/freight-rules', $this->financeSession, [
            'site_id'  => $siteId,
            'tax_rate' => 0.08,
        ]);

        $this->assertEquals(422, $response['status']);
    }

    public function testCreateFreightRuleRequiresTaxRate(): void
    {
        $siteId = $this->financeSession['user']['site_scopes'][0] ?? 1;

        $response = $this->authenticatedRequest('POST', '/api/v1/finance/freight-rules', $this->financeSession, [
            'name'    => 'Missing Tax Rate Rule',
            'site_id' => $siteId,
        ]);

        $this->assertEquals(422, $response['status']);
    }

    public function testListFreightRules(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/finance/freight-rules', $this->financeSession);

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('items', $response['body']['data'] ?? []);
        $this->assertArrayHasKey('pagination', $response['body']['data'] ?? []);
    }

    public function testUpdateFreightRule(): void
    {
        $siteId = $this->financeSession['user']['site_scopes'][0] ?? 1;
        $createResponse = $this->authenticatedRequest('POST', '/api/v1/finance/freight-rules', $this->financeSession, [
            'name'     => 'Rule To Update ' . uniqid(),
            'site_id'  => $siteId,
            'tax_rate' => 0.05,
        ]);
        $this->assertEquals(201, $createResponse['status'], 'Freight rule creation must succeed');

        $ruleId = $createResponse['body']['data']['id'];

        $updateResponse = $this->authenticatedRequest('PATCH', "/api/v1/finance/freight-rules/{$ruleId}", $this->financeSession, [
            'name'     => 'Updated Rule Name',
            'tax_rate' => 0.10,
        ]);

        $this->assertEquals(200, $updateResponse['status']);
    }

    // ------------------------------------------------------------------
    // Statement generation
    // ------------------------------------------------------------------

    public function testGenerateStatement(): void
    {
        $siteId = $this->financeSession['user']['site_scopes'][0] ?? 1;

        $response = $this->authenticatedRequest('POST', '/api/v1/finance/settlements/generate', $this->financeSession, [
            'site_id' => $siteId,
            'period'  => '2026-03',
        ]);

        $this->assertEquals(202, $response['status'],
            'Generate should return 202. Got: ' . ($response['raw'] ?? ''));
        $this->assertNotEmpty($response['body']['data']['settlement_id'] ?? null);
    }

    public function testGenerateStatementRequiresSiteId(): void
    {
        $response = $this->authenticatedRequest('POST', '/api/v1/finance/settlements/generate', $this->financeSession, [
            'period' => '2026-03',
        ]);

        $this->assertEquals(422, $response['status']);
    }

    public function testGenerateStatementRequiresPeriod(): void
    {
        $siteId = $this->financeSession['user']['site_scopes'][0] ?? 1;

        $response = $this->authenticatedRequest('POST', '/api/v1/finance/settlements/generate', $this->financeSession, [
            'site_id' => $siteId,
        ]);

        $this->assertEquals(422, $response['status']);
    }

    // ------------------------------------------------------------------
    // Full lifecycle: generate -> submit -> approve -> locked -> reversal
    // ------------------------------------------------------------------

    public function testFullSettlementLifecycle(): void
    {
        // Step 1: Generate a statement (as finance)
        $statementId = $this->generateTestStatement();

        // Step 2: Submit the statement (as finance)
        $submitResponse = $this->authenticatedRequest(
            'POST',
            "/api/v1/finance/settlements/{$statementId}/submit",
            $this->financeSession
        );
        $this->assertEquals(200, $submitResponse['status'],
            'Submit should succeed for draft statement. Got: ' . ($submitResponse['raw'] ?? ''));
        $this->assertEquals('submitted', $submitResponse['body']['data']['status'] ?? '');

        // Step 3: Approve-final (as admin)
        $approveResponse = $this->authenticatedRequest(
            'POST',
            "/api/v1/finance/settlements/{$statementId}/approve-final",
            $this->adminSession
        );
        $this->assertEquals(200, $approveResponse['status'],
            'Admin approve-final should succeed. Got: ' . ($approveResponse['raw'] ?? ''));
        // After approval the status is approved_locked
        $this->assertContains(
            $approveResponse['body']['data']['status'] ?? '',
            ['approved', 'approved_locked'],
            'Status after approval should indicate locked state'
        );

        // Step 4: Verify locked statement can NOT be resubmitted
        $resubmitResponse = $this->authenticatedRequest(
            'POST',
            "/api/v1/finance/settlements/{$statementId}/submit",
            $this->financeSession
        );
        $this->assertContains($resubmitResponse['status'], [422, 423],
            'Locked statement should not accept resubmission');

        // Step 5: Reversal creates a new draft
        $reversalResponse = $this->authenticatedRequest(
            'POST',
            "/api/v1/finance/settlements/{$statementId}/reverse",
            $this->adminSession,
            ['reason' => 'Incorrect amounts detected']
        );
        $this->assertEquals(200, $reversalResponse['status'],
            'Reversal should succeed for approved_locked statement. Got: ' . ($reversalResponse['raw'] ?? ''));
        $this->assertEquals('reversed', $reversalResponse['body']['data']['status'] ?? '');
        $this->assertNotEmpty(
            $reversalResponse['body']['data']['reversal']['replacement_statement_id'] ?? null,
            'Reversal should create a replacement draft statement'
        );
    }

    // ------------------------------------------------------------------
    // Role enforcement: finance cannot approve
    // ------------------------------------------------------------------

    public function testFinanceCannotApproveFinal(): void
    {
        $statementId = $this->generateAndSubmitStatement();

        $response = $this->authenticatedRequest(
            'POST',
            "/api/v1/finance/settlements/{$statementId}/approve-final",
            $this->financeSession
        );

        $this->assertEquals(403, $response['status'],
            'Non-admin (finance) should not be able to approve-final');
    }

    // ------------------------------------------------------------------
    // Locked statement cannot be re-approved
    // ------------------------------------------------------------------

    public function testLockedStatementCannotBeApprovedAgain(): void
    {
        $statementId = $this->generateSubmitAndApproveStatement();

        $response = $this->authenticatedRequest(
            'POST',
            "/api/v1/finance/settlements/{$statementId}/approve-final",
            $this->adminSession
        );

        $this->assertContains($response['status'], [422, 423],
            'Already-locked statement should reject re-approval');
    }

    // ------------------------------------------------------------------
    // Reversal requires reason
    // ------------------------------------------------------------------

    public function testReversalRequiresReason(): void
    {
        $statementId = $this->generateSubmitAndApproveStatement();

        $response = $this->authenticatedRequest(
            'POST',
            "/api/v1/finance/settlements/{$statementId}/reverse",
            $this->adminSession,
            [] // No reason
        );

        $this->assertEquals(422, $response['status'], 'Reversal without reason should fail validation');
    }

    // ------------------------------------------------------------------
    // Read settlement
    // ------------------------------------------------------------------

    public function testReadSettlement(): void
    {
        $statementId = $this->generateTestStatement();

        $response = $this->authenticatedRequest('GET', "/api/v1/finance/settlements/{$statementId}", $this->financeSession);

        $this->assertEquals(200, $response['status']);
        $data = $response['body']['data'] ?? [];
        $this->assertNotEmpty($data['id'] ?? null);
        $this->assertArrayHasKey('lines', $data);
        $this->assertArrayHasKey('variances', $data);
    }

    public function testReadNonExistentSettlement(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/finance/settlements/999999', $this->financeSession);
        $this->assertEquals(404, $response['status']);
    }

    public function testListStatements(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/finance/settlements', $this->financeSession);

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('items', $response['body']['data'] ?? []);
        $this->assertArrayHasKey('pagination', $response['body']['data'] ?? []);
    }

    // ------------------------------------------------------------------
    // Auth
    // ------------------------------------------------------------------

    public function testSettlementEndpointsRequireAuth(): void
    {
        $response = $this->httpRequest('GET', '/api/v1/finance/settlements');
        $this->assertContains($response['status'], [401, 403]);
    }

    // ------------------------------------------------------------------
    // Helpers — no skips, assertions enforce preconditions
    // ------------------------------------------------------------------

    private function generateTestStatement(): int
    {
        $siteId = $this->financeSession['user']['site_scopes'][0] ?? 1;
        $period = '2026-' . str_pad((string) rand(1, 12), 2, '0', STR_PAD_LEFT);

        $response = $this->authenticatedRequest('POST', '/api/v1/finance/settlements/generate', $this->adminSession, [
            'site_id' => $siteId,
            'period'  => $period,
        ]);

        $this->assertContains($response['status'], [200, 202],
            'Helper: generate statement must succeed. Got: ' . ($response['raw'] ?? ''));

        $statementId = (int) ($response['body']['data']['settlement_id'] ?? 0);
        $this->assertGreaterThan(0, $statementId, 'Helper: settlement_id must be returned');

        return $statementId;
    }

    private function generateAndSubmitStatement(): int
    {
        $statementId = $this->generateTestStatement();

        $submitResponse = $this->authenticatedRequest(
            'POST',
            "/api/v1/finance/settlements/{$statementId}/submit",
            $this->financeSession
        );
        $this->assertEquals(200, $submitResponse['status'],
            'Helper: submit must succeed. Got: ' . ($submitResponse['raw'] ?? ''));

        return $statementId;
    }

    private function generateSubmitAndApproveStatement(): int
    {
        $statementId = $this->generateAndSubmitStatement();

        $approveResponse = $this->authenticatedRequest(
            'POST',
            "/api/v1/finance/settlements/{$statementId}/approve-final",
            $this->adminSession
        );
        $this->assertEquals(200, $approveResponse['status'],
            'Helper: approve-final must succeed. Got: ' . ($approveResponse['raw'] ?? ''));

        return $statementId;
    }
}
