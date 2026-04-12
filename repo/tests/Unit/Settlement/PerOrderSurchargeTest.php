<?php
declare(strict_types=1);

namespace tests\Unit\Settlement;

use app\service\settlement\FreightCalculatorService;
use tests\TestCase;

class PerOrderSurchargeTest extends TestCase
{
    public function testSurchargeScalesWithOrderCount(): void
    {
        $svc = new FreightCalculatorService();

        $rules = [
            'distance_bands' => [],
            'weight_tiers' => [],
            'volume_tiers' => [],
            'surcharges' => ['cold_chain' => 5],
            'tax_rate' => 0,
        ];

        // 1 order
        $r1 = $svc->calculate([['weight' => 0, 'volume' => 0, 'distance' => 0]], $rules);
        $this->assertEquals(5.0, $r1['subtotal'], 'One order = one surcharge');

        // 3 orders
        $r3 = $svc->calculate([
            ['weight' => 0, 'volume' => 0, 'distance' => 0],
            ['weight' => 0, 'volume' => 0, 'distance' => 0],
            ['weight' => 0, 'volume' => 0, 'distance' => 0],
        ], $rules);
        $this->assertEquals(15.0, $r3['subtotal'], 'Three orders = three surcharges');
    }
}
