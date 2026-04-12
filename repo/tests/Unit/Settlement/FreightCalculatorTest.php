<?php
declare(strict_types=1);

namespace tests\Unit\Settlement;

use app\service\settlement\FreightCalculatorService;
use tests\TestCase;

class FreightCalculatorTest extends TestCase
{
    private FreightCalculatorService $service;

    protected function setUp(): void
    {
        $this->service = new FreightCalculatorService();
    }

    public function testCalculateReturnsArrayStructure(): void
    {
        $items = [
            ['weight' => 10.0, 'volume' => 5.0, 'distance' => 100],
        ];
        $rules = [
            'distance_bands' => [['min' => 0, 'max' => 200, 'rate' => 1.5]],
            'weight_tiers' => [['min' => 0, 'max' => 50, 'rate' => 0.5]],
            'volume_tiers' => [['min' => 0, 'max' => 20, 'rate' => 0.3]],
            'surcharges' => [],
            'tax_rate' => 0.06,
        ];

        $result = $this->service->calculate($items, $rules);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('subtotal', $result);
        $this->assertArrayHasKey('tax', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('line_items', $result);
    }

    public function testCalculateWithEmptyItemsReturnsZero(): void
    {
        $result = $this->service->calculate([], []);

        $this->assertEquals(0, $result['subtotal']);
        $this->assertEquals(0, $result['total']);
    }
}
