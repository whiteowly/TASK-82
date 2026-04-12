<?php
declare(strict_types=1);

namespace app\service\analytics;

use think\facade\Db;

class MetricService
{
    /**
     * Get all available metric definitions.
     *
     * @return array List of metric definition arrays with name, description, formula, and unit.
     */
    public function getMetricDefinitions(): array
    {
        return [
            [
                'name' => 'total_sales',
                'description' => 'Total revenue from all completed orders',
                'formula' => 'SUM(orders.total_amount)',
                'unit' => 'currency',
            ],
            [
                'name' => 'avg_order_value',
                'description' => 'Average value per order',
                'formula' => 'SUM(orders.total_amount) / COUNT(orders.id)',
                'unit' => 'currency',
            ],
            [
                'name' => 'refund_rate',
                'description' => 'Percentage of orders that were refunded',
                'formula' => 'COUNT(refunds.id) / COUNT(orders.id) * 100',
                'unit' => 'percent',
            ],
            [
                'name' => 'repeat_purchase_rate',
                'description' => 'Percentage of participants with more than one order',
                'formula' => 'COUNT(participants with >1 order) / COUNT(distinct participants) * 100',
                'unit' => 'percent',
            ],
            [
                'name' => 'group_conversion',
                'description' => 'Number of orders per group leader',
                'formula' => 'COUNT(orders.id) GROUP BY group_leader_id',
                'unit' => 'count',
            ],
            [
                'name' => 'leader_performance',
                'description' => 'Total revenue generated per group leader',
                'formula' => 'SUM(orders.total_amount) GROUP BY group_leader_id',
                'unit' => 'currency',
            ],
            [
                'name' => 'product_popularity',
                'description' => 'Total quantity sold per product',
                'formula' => 'SUM(order_items.quantity) GROUP BY product_id',
                'unit' => 'count',
            ],
        ];
    }

    /**
     * Generate metric snapshots for the given sites.
     * Computes metric values from real order/transaction data and inserts into metric_snapshots.
     *
     * @param array $siteIds List of site IDs to generate snapshots for.
     * @return void
     */
    public function generateSnapshots(array $siteIds): void
    {
        if (empty($siteIds)) {
            return;
        }

        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');

        foreach ($siteIds as $siteId) {
            // Total sales
            $totalSales = Db::table('orders')
                ->where('site_id', $siteId)
                ->sum('total_amount');

            Db::table('metric_snapshots')->insert([
                'site_id' => $siteId,
                'metric_type' => 'total_sales',
                'dimension_key' => 'site',
                'dimension_value' => (string) $siteId,
                'value' => $totalSales,
                'snapshot_date' => $today,
                'created_at' => $now,
            ]);

            // Average order value
            $orderCount = Db::table('orders')->where('site_id', $siteId)->count();
            $avgOrderValue = $orderCount > 0 ? round((float) $totalSales / $orderCount, 4) : 0;

            Db::table('metric_snapshots')->insert([
                'site_id' => $siteId,
                'metric_type' => 'avg_order_value',
                'dimension_key' => 'site',
                'dimension_value' => (string) $siteId,
                'value' => $avgOrderValue,
                'snapshot_date' => $today,
                'created_at' => $now,
            ]);

            // Refund rate
            $refundCount = Db::table('refunds')
                ->alias('ref')
                ->join('orders o', 'o.id = ref.order_id')
                ->where('o.site_id', $siteId)
                ->count();
            $refundRate = $orderCount > 0 ? round($refundCount / $orderCount * 100, 4) : 0;

            Db::table('metric_snapshots')->insert([
                'site_id' => $siteId,
                'metric_type' => 'refund_rate',
                'dimension_key' => 'site',
                'dimension_value' => (string) $siteId,
                'value' => $refundRate,
                'snapshot_date' => $today,
                'created_at' => $now,
            ]);

            // Repeat purchase rate
            $distinctParticipants = Db::table('orders')
                ->where('site_id', $siteId)
                ->field('participant_id')
                ->distinct(true)
                ->count();
            $repeatParticipants = 0;
            if ($distinctParticipants > 0) {
                $repeatRows = Db::table('orders')
                    ->where('site_id', $siteId)
                    ->field('participant_id, COUNT(*) as cnt')
                    ->group('participant_id')
                    ->having('cnt > 1')
                    ->select()
                    ->toArray();
                $repeatParticipants = count($repeatRows);
            }
            $repeatRate = $distinctParticipants > 0
                ? round($repeatParticipants / $distinctParticipants * 100, 4)
                : 0;

            Db::table('metric_snapshots')->insert([
                'site_id' => $siteId,
                'metric_type' => 'repeat_purchase_rate',
                'dimension_key' => 'site',
                'dimension_value' => (string) $siteId,
                'value' => $repeatRate,
                'snapshot_date' => $today,
                'created_at' => $now,
            ]);

            // Group conversion per leader
            $leaderStats = Db::table('orders')
                ->where('site_id', $siteId)
                ->field('group_leader_id, COUNT(*) as order_count')
                ->group('group_leader_id')
                ->select()
                ->toArray();

            foreach ($leaderStats as $stat) {
                Db::table('metric_snapshots')->insert([
                    'site_id' => $siteId,
                    'metric_type' => 'group_conversion',
                    'dimension_key' => 'group_leader',
                    'dimension_value' => (string) $stat['group_leader_id'],
                    'value' => $stat['order_count'],
                    'snapshot_date' => $today,
                    'created_at' => $now,
                ]);
            }

            // Leader performance (revenue per leader)
            $leaderRevenue = Db::table('orders')
                ->where('site_id', $siteId)
                ->field('group_leader_id, SUM(total_amount) as total')
                ->group('group_leader_id')
                ->select()
                ->toArray();

            foreach ($leaderRevenue as $stat) {
                Db::table('metric_snapshots')->insert([
                    'site_id' => $siteId,
                    'metric_type' => 'leader_performance',
                    'dimension_key' => 'group_leader',
                    'dimension_value' => (string) $stat['group_leader_id'],
                    'value' => $stat['total'],
                    'snapshot_date' => $today,
                    'created_at' => $now,
                ]);
            }

            // Product popularity
            $productStats = Db::table('order_items')
                ->alias('oi')
                ->join('orders o', 'o.id = oi.order_id')
                ->where('o.site_id', $siteId)
                ->field('oi.product_id, SUM(oi.quantity) as total_qty')
                ->group('oi.product_id')
                ->select()
                ->toArray();

            foreach ($productStats as $stat) {
                Db::table('metric_snapshots')->insert([
                    'site_id' => $siteId,
                    'metric_type' => 'product_popularity',
                    'dimension_key' => 'product',
                    'dimension_value' => (string) $stat['product_id'],
                    'value' => $stat['total_qty'],
                    'snapshot_date' => $today,
                    'created_at' => $now,
                ]);
            }
        }
    }
}
