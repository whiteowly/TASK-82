<?php
declare(strict_types=1);

namespace app\service\settlement;

class FreightCalculatorService
{
    /**
     * Calculate freight charges for a set of items according to the given rules.
     *
     * @param array $items Line items with weight, volume, distance, etc.
     * @param array $rules Freight calculation rules (distance bands, weight/volume tiers, surcharges, tax_rate).
     * @return array Calculated freight breakdown with subtotal, tax, total, and line_items.
     */
    public function calculate(array $items, array $rules): array
    {
        $lineItems = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $lineTotal = 0.0;

            // Apply distance band rate
            $distance = $item['distance'] ?? 0;
            foreach (($rules['distance_bands'] ?? []) as $band) {
                $bandMin = $band['min'] ?? $band['min_km'] ?? 0;
                $bandMax = $band['max'] ?? $band['max_km'] ?? PHP_INT_MAX;
                $bandRate = $band['rate'] ?? $band['fee'] ?? 0;
                if ($distance >= $bandMin && $distance <= $bandMax) {
                    $lineTotal += $distance * $bandRate;
                    break;
                }
            }

            // Apply weight tier rate
            $weight = $item['weight'] ?? 0;
            foreach (($rules['weight_tiers'] ?? []) as $tier) {
                $tierMin = $tier['min'] ?? $tier['min_kg'] ?? 0;
                $tierMax = $tier['max'] ?? $tier['max_kg'] ?? PHP_INT_MAX;
                $tierRate = $tier['rate'] ?? $tier['fee'] ?? 0;
                if ($weight >= $tierMin && $weight <= $tierMax) {
                    $lineTotal += $weight * $tierRate;
                    break;
                }
            }

            // Apply volume tier rate
            $volume = $item['volume'] ?? 0;
            foreach (($rules['volume_tiers'] ?? []) as $tier) {
                if ($volume >= ($tier['min'] ?? 0) && $volume <= ($tier['max'] ?? PHP_INT_MAX)) {
                    $lineTotal += $volume * ($tier['rate'] ?? 0);
                    break;
                }
            }

            $lineItems[] = [
                'item' => $item,
                'amount' => $lineTotal,
            ];
            $subtotal += $lineTotal;
        }

        // Apply surcharges per order (each item in the input represents an order batch)
        $orderCount = count($items);
        $surcharges = $rules['surcharges'] ?? [];
        // Surcharges may be keyed as {name: amount} or [{amount: X}]
        if (is_array($surcharges)) {
            foreach ($surcharges as $key => $val) {
                $amount = is_numeric($val) ? (float)$val : (float)($val['amount'] ?? 0);
                $subtotal += $amount * $orderCount;
            }
        }

        $taxRate = (float)($rules['tax_rate'] ?? 0);
        $tax = round($subtotal * $taxRate, 2);
        $total = round($subtotal + $tax, 2);

        return [
            'line_items' => $lineItems,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
        ];
    }
}
