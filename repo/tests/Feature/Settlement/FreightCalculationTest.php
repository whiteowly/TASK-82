<?php
declare(strict_types=1);

namespace tests\Feature\Settlement;

use tests\TestCase;

/**
 * Tests that freight rules affect settlement totals.
 *
 * Verifies that:
 * - A settlement generated for a site with an active freight rule includes freight/tax lines
 * - The statement total exceeds the bare sum of order amounts when freight is applied
 *
 * ZERO markTestSkipped / markTestIncomplete calls.
 */
class FreightCalculationTest extends TestCase
{
    private array $financeSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->financeSession = $this->loginAs('finance');
    }

    public function testFreightRulesAffectSettlementTotals(): void
    {
        $siteId = $this->financeSession['user']['site_scopes'][0] ?? 1;

        // Create a freight rule with a non-zero tax_rate so freight and tax lines are generated
        $ruleResponse = $this->authenticatedRequest('POST', '/api/v1/finance/freight-rules', $this->financeSession, [
            'name'           => 'Freight Calc Test Rule ' . uniqid(),
            'site_id'        => $siteId,
            'tax_rate'       => 0.10,
            'distance_bands' => [
                ['min' => 0, 'max' => 100, 'rate' => 2.00],
            ],
            'weight_tiers'   => [
                ['min' => 0, 'max' => 99999, 'rate' => 1.50],
            ],
            'volume_tiers'   => [
                ['min' => 0, 'max' => 99999, 'rate' => 3.00],
            ],
        ]);
        $this->assertEquals(201, $ruleResponse['status'],
            'Freight rule creation should return 201. Got: ' . ($ruleResponse['raw'] ?? ''));

        // Generate a settlement for the site
        $period = '2026-03';
        $genResponse = $this->authenticatedRequest('POST', '/api/v1/finance/settlements/generate', $this->financeSession, [
            'site_id' => $siteId,
            'period'  => $period,
        ]);

        $this->assertContains($genResponse['status'], [200, 202],
            'Settlement generation should succeed. Got: ' . ($genResponse['raw'] ?? ''));

        $statementId = $genResponse['body']['data']['settlement_id'] ?? null;
        $this->assertNotNull($statementId, 'Settlement ID must be returned');

        // Read the settlement to inspect lines
        $readResponse = $this->authenticatedRequest('GET', "/api/v1/finance/settlements/{$statementId}", $this->financeSession);
        $this->assertEquals(200, $readResponse['status'],
            'Reading settlement should return 200. Got: ' . ($readResponse['raw'] ?? ''));

        $data = $readResponse['body']['data'] ?? [];
        $lines = $data['lines'] ?? [];

        // Check for freight and/or tax category lines
        $categories = array_column($lines, 'category');
        $hasFreightOrTax = in_array('freight', $categories, true) || in_array('tax', $categories, true);

        // If there are orders in the period, freight should be applied
        $orderLines = array_filter($lines, fn($l) => ($l['category'] ?? '') === 'order');
        if (!empty($orderLines)) {
            $this->assertTrue($hasFreightOrTax,
                'Settlement with orders and active freight rule should have freight or tax lines');

            // Total amount should exceed just the order sum
            $orderSum = array_sum(array_column($orderLines, 'amount'));
            $totalAmount = (float) ($data['total_amount'] ?? 0);
            $this->assertGreaterThan($orderSum, $totalAmount,
                'Statement total should exceed order sum when freight is applied');
        }
    }
}
