<?php
declare(strict_types=1);

namespace app\service\analytics;

use think\facade\Db;
use think\exception\ValidateException;

class AnalyticsService
{
    /**
     * Get aggregated dashboard data filtered by criteria and site scopes.
     *
     * @param array $filters    Query filters (date_start, date_end, site_id, community_id, group_leader_id, product_id).
     * @param array $siteScopes Site IDs the requesting user has access to.
     * @return array Dashboard data structure with computed metrics, snapshots, and definitions.
     */
    public function getDashboardData(array $filters, array $siteScopes): array
    {
        $dateStart = $filters['date_start'] ?? null;
        $dateEnd = $filters['date_end'] ?? null;

        // Base order query builder with scope and date filtering
        $buildOrderQuery = function () use ($siteScopes, $filters, $dateStart, $dateEnd) {
            $q = Db::table('orders');
            if (!empty($siteScopes)) {
                $q->whereIn('site_id', $siteScopes);
            }
            if ($dateStart) {
                $q->where('created_at', '>=', $dateStart);
            }
            if ($dateEnd) {
                $q->where('created_at', '<=', $dateEnd);
            }
            if (!empty($filters['site_id'])) {
                $q->where('site_id', $filters['site_id']);
            }
            if (!empty($filters['community_id'])) {
                $q->whereExists(function ($sub) use ($filters) {
                    $sub->table('group_leaders')
                        ->whereColumn('group_leaders.id', 'orders.group_leader_id')
                        ->where('group_leaders.community_id', $filters['community_id']);
                });
            }
            if (!empty($filters['group_leader_id'])) {
                $q->where('group_leader_id', $filters['group_leader_id']);
            }
            return $q;
        };

        // 1. Total sales
        $totalSales = (clone $buildOrderQuery())->sum('total_amount');

        // 2. Order count
        $orderCount = (clone $buildOrderQuery())->count();

        // 3. Average order value
        $avgOrderValue = $orderCount > 0 ? round((float) $totalSales / $orderCount, 2) : 0;

        // 4. Refund rate
        $refundQuery = Db::table('refunds')
            ->alias('ref')
            ->join('orders o', 'o.id = ref.order_id');
        if (!empty($siteScopes)) {
            $refundQuery->whereIn('o.site_id', $siteScopes);
        }
        if ($dateStart) {
            $refundQuery->where('ref.created_at', '>=', $dateStart);
        }
        if ($dateEnd) {
            $refundQuery->where('ref.created_at', '<=', $dateEnd);
        }
        $refundCount = $refundQuery->count();
        $refundRate = $orderCount > 0 ? round($refundCount / $orderCount * 100, 2) : 0;

        // 5. Repeat purchase rate
        $totalParticipants = (clone $buildOrderQuery())->field('participant_id')->distinct(true)->count();
        $repeatParticipants = 0;
        if ($totalParticipants > 0) {
            $repeatRows = (clone $buildOrderQuery())
                ->field('participant_id, COUNT(*) as cnt')
                ->group('participant_id')
                ->having('cnt > 1')
                ->select()
                ->toArray();
            $repeatParticipants = count($repeatRows);
        }
        $repeatPurchaseRate = $totalParticipants > 0
            ? round($repeatParticipants / $totalParticipants * 100, 2)
            : 0;

        // 6. Group conversion (orders per group leader)
        $leaderStats = (clone $buildOrderQuery())
            ->field('group_leader_id, COUNT(*) as order_count, SUM(total_amount) as total')
            ->group('group_leader_id')
            ->order('total', 'desc')
            ->limit(20)
            ->select()
            ->toArray();

        // Calculate group_conversion: percentage of leaders that have at least one order
        $totalLeaders = Db::table('group_leaders');
        if (!empty($siteScopes)) {
            $totalLeaders->whereIn('site_id', $siteScopes);
        }
        if (!empty($filters['site_id'])) {
            $totalLeaders->where('site_id', $filters['site_id']);
        }
        $totalLeadersCount = $totalLeaders->count();

        $leadersWithOrders = count($leaderStats);
        $groupConversion = $totalLeadersCount > 0
            ? round($leadersWithOrders / $totalLeadersCount * 100, 2)
            : 0;

        // 7. Product popularity
        $productQuery = Db::table('order_items')
            ->alias('oi')
            ->join('orders o', 'o.id = oi.order_id');
        if (!empty($siteScopes)) {
            $productQuery->whereIn('o.site_id', $siteScopes);
        }
        if ($dateStart) {
            $productQuery->where('o.created_at', '>=', $dateStart);
        }
        if ($dateEnd) {
            $productQuery->where('o.created_at', '<=', $dateEnd);
        }
        if (!empty($filters['product_id'])) {
            $productQuery->where('oi.product_id', $filters['product_id']);
        }
        $productPopularity = $productQuery
            ->field('oi.product_id, SUM(oi.quantity) as total_quantity, SUM(oi.subtotal) as total_revenue')
            ->group('oi.product_id')
            ->order('total_quantity', 'desc')
            ->limit(20)
            ->select()
            ->toArray();

        // Metric snapshots
        $snapshotQuery = Db::table('metric_snapshots');
        if (!empty($siteScopes)) {
            $snapshotQuery->whereIn('site_id', $siteScopes);
        }
        if ($dateStart) {
            $snapshotQuery->where('snapshot_date', '>=', $dateStart);
        }
        if ($dateEnd) {
            $snapshotQuery->where('snapshot_date', '<=', $dateEnd);
        }
        $snapshots = $snapshotQuery->order('snapshot_date', 'desc')->limit(200)->select()->toArray();

        // Metric definitions
        $metricService = new MetricService();
        $definitions = $metricService->getMetricDefinitions();

        return [
            'metrics' => [
                'total_sales' => (float) $totalSales,
                'order_count' => $orderCount,
                'avg_order_value' => $avgOrderValue,
                'refund_rate' => $refundRate,
                'repeat_purchase_rate' => $repeatPurchaseRate,
                'group_conversion' => $groupConversion,
                'leader_performance' => $leaderStats,
                'product_popularity' => $productPopularity,
            ],
            'snapshots' => $snapshots,
            'metric_definitions' => $definitions,
        ];
    }

    /**
     * Request an asynchronous data refresh for the given scope.
     * Rate limited to 5 requests per hour per user.
     *
     * @param int   $userId
     * @param array $scope  Refresh scope parameters (site_id).
     * @return array Refresh request record including request ID and status.
     */
    public function requestRefresh(int $userId, array $scope): array
    {
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $recentCount = Db::table('analytics_refresh_requests')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $oneHourAgo)
            ->count();

        if ($recentCount >= 5) {
            throw new ValidateException('Rate limit exceeded. Maximum 5 refresh requests per hour.');
        }

        $now = date('Y-m-d H:i:s');
        $id = Db::table('analytics_refresh_requests')->insertGetId([
            'user_id' => $userId,
            'site_id' => $scope['site_id'] ?? null,
            'status' => 'requested',
            'created_at' => $now,
        ]);

        return [
            'id' => $id,
            'user_id' => $userId,
            'site_id' => $scope['site_id'] ?? null,
            'status' => 'requested',
            'created_at' => $now,
        ];
    }

    /**
     * Get the status of a previously requested refresh.
     *
     * @param int $requestId
     * @return array|null Refresh status or null if not found.
     */
    public function getRefreshStatus(int $requestId): ?array
    {
        $record = Db::table('analytics_refresh_requests')
            ->where('id', $requestId)
            ->find();

        return $record ?: null;
    }
}
