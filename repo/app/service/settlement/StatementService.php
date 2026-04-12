<?php
declare(strict_types=1);

namespace app\service\settlement;

use think\facade\Db;
use think\exception\ValidateException;

class StatementService
{
    /**
     * Generate a settlement statement for a site and period.
     * Creates settlement_lines from orders for that site+period and calculates total.
     *
     * @param int    $siteId
     * @param string $period Period identifier (e.g. '2026-03').
     * @param int    $userId Generating user ID.
     * @return array The generated statement record.
     */
    public function generate(int $siteId, string $period, int $userId): array
    {
        $now = date('Y-m-d H:i:s');

        // Determine date range from period (YYYY-MM format)
        $periodStart = $period . '-01';
        $periodEnd = date('Y-m-t 23:59:59', strtotime($periodStart));
        $periodStart .= ' 00:00:00';

        Db::startTrans();
        try {
            // Create statement with draft status
            $statementId = Db::table('settlement_statements')->insertGetId([
                'site_id' => $siteId,
                'period' => $period,
                'status' => 'draft',
                'total_amount' => 0,
                'generated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Fetch orders for this site and period
            $orders = Db::table('orders')
                ->where('site_id', $siteId)
                ->where('created_at', '>=', $periodStart)
                ->where('created_at', '<=', $periodEnd)
                ->select()
                ->toArray();

            $totalAmount = 0;

            foreach ($orders as $order) {
                $amount = (float) $order['total_amount'];
                $totalAmount += $amount;

                Db::table('settlement_lines')->insert([
                    'statement_id' => $statementId,
                    'description' => 'Order #' . $order['id'],
                    'amount' => $amount,
                    'category' => 'order',
                    'created_at' => $now,
                ]);
            }

            // Account for refunds in the period
            $refunds = Db::table('refunds')
                ->alias('ref')
                ->join('orders o', 'o.id = ref.order_id')
                ->where('o.site_id', $siteId)
                ->where('ref.created_at', '>=', $periodStart)
                ->where('ref.created_at', '<=', $periodEnd)
                ->field('ref.*')
                ->select()
                ->toArray();

            foreach ($refunds as $refund) {
                $amount = -1 * abs((float) $refund['amount']);
                $totalAmount += $amount;

                Db::table('settlement_lines')->insert([
                    'statement_id' => $statementId,
                    'description' => 'Refund for Order #' . $refund['order_id'],
                    'amount' => $amount,
                    'category' => 'refund',
                    'created_at' => $now,
                ]);
            }

            // Apply freight rules for this site
            $freightRule = Db::table('freight_rules')
                ->where('site_id', $siteId)
                ->where('active', 1)
                ->order('id', 'asc')
                ->find();

            if ($freightRule && !empty($orders)) {
                $rules = [
                    'distance_bands' => !empty($freightRule['distance_band_json'])
                        ? json_decode($freightRule['distance_band_json'], true) : [],
                    'weight_tiers' => !empty($freightRule['weight_tiers_json'])
                        ? json_decode($freightRule['weight_tiers_json'], true) : [],
                    'volume_tiers' => !empty($freightRule['volume_tiers_json'])
                        ? json_decode($freightRule['volume_tiers_json'], true) : [],
                    'surcharges' => !empty($freightRule['surcharges_json'])
                        ? json_decode($freightRule['surcharges_json'], true) : [],
                    'tax_rate' => (float) ($freightRule['tax_rate'] ?? 0),
                ];

                $freightCalculator = new FreightCalculatorService();

                // Compute aggregate freight inputs from order_items (schema-supported data)
                $orderIds = array_column($orders, 'id');
                $totalQuantity = 0;
                if (!empty($orderIds)) {
                    $totalQuantity = (float) Db::table('order_items')
                        ->whereIn('order_id', $orderIds)
                        ->sum('quantity');
                }
                $orderCount = (float) count($orders);
                $defaultDistance = 50.0; // within-site delivery distance proxy

                // Build ONE freight calculation call with aggregates
                // One freight item per order so surcharges scale correctly
                $freightItems = [];
                foreach ($orders as $order) {
                    $orderQty = (float)Db::table('order_items')
                        ->where('order_id', $order['id'])
                        ->sum('quantity');
                    $freightItems[] = [
                        'weight'   => $orderQty,
                        'volume'   => 1.0,
                        'distance' => $defaultDistance,
                    ];
                }

                $freightResult = $freightCalculator->calculate($freightItems, $rules);

                $freightSubtotal = round((float) $freightResult['subtotal'], 2);
                $freightTax = round((float) $freightResult['tax'], 2);

                if ($freightSubtotal != 0) {
                    Db::table('settlement_lines')->insert([
                        'statement_id' => $statementId,
                        'description' => 'Freight (' . $freightRule['name'] . ')',
                        'amount' => $freightSubtotal,
                        'category' => 'freight',
                        'created_at' => $now,
                    ]);
                    $totalAmount += $freightSubtotal;
                }

                if ($freightTax != 0) {
                    Db::table('settlement_lines')->insert([
                        'statement_id' => $statementId,
                        'description' => 'Tax on Freight',
                        'amount' => $freightTax,
                        'category' => 'tax',
                        'created_at' => $now,
                    ]);
                    $totalAmount += $freightTax;
                }
            }

            // Update statement total
            Db::table('settlement_statements')
                ->where('id', $statementId)
                ->update(['total_amount' => round($totalAmount, 2)]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return Db::table('settlement_statements')->where('id', $statementId)->find();
    }

    /**
     * Submit a statement for approval (clerk action).
     * Transitions: draft -> submitted.
     *
     * @param int $statementId
     * @param int $clerkId
     * @return void
     * @throws ValidateException If the statement is not in draft status.
     */
    public function submit(int $statementId, int $clerkId): void
    {
        $statement = Db::table('settlement_statements')->where('id', $statementId)->find();
        if (!$statement) {
            throw new ValidateException('Statement not found.');
        }
        if ($statement['status'] !== 'draft') {
            throw new ValidateException('Only draft statements can be submitted.');
        }

        $now = date('Y-m-d H:i:s');

        Db::startTrans();
        try {
            Db::table('settlement_statements')
                ->where('id', $statementId)
                ->update([
                    'status' => 'submitted',
                    'submitted_by' => $clerkId,
                    'submitted_at' => $now,
                    'updated_at' => $now,
                ]);

            Db::table('settlement_approvals')->insert([
                'statement_id' => $statementId,
                'actor_id' => $clerkId,
                'action' => 'submitted',
                'created_at' => $now,
            ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * Give final approval to a statement (admin action).
     * Transitions: submitted -> approved_locked.
     *
     * @param int $statementId
     * @param int $adminId
     * @return void
     * @throws ValidateException If the statement is not in submitted status.
     */
    public function approveFinal(int $statementId, int $adminId): void
    {
        $statement = Db::table('settlement_statements')->where('id', $statementId)->find();
        if (!$statement) {
            throw new ValidateException('Statement not found.');
        }
        if ($statement['status'] !== 'submitted') {
            throw new ValidateException('Only submitted statements can be approved.');
        }

        $now = date('Y-m-d H:i:s');

        Db::startTrans();
        try {
            Db::table('settlement_statements')
                ->where('id', $statementId)
                ->update([
                    'status' => 'approved_locked',
                    'approved_by' => $adminId,
                    'approved_at' => $now,
                    'updated_at' => $now,
                ]);

            Db::table('settlement_approvals')->insert([
                'statement_id' => $statementId,
                'actor_id' => $adminId,
                'action' => 'approved',
                'created_at' => $now,
            ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * Reverse a previously approved statement.
     * Creates a settlement_reversal and a new draft replacement statement.
     * Only works on approved_locked statements.
     *
     * @param int    $statementId
     * @param int    $actorId
     * @param string $reason Reason for reversal.
     * @return array The reversal record including replacement_statement_id.
     * @throws ValidateException If the statement is not in approved_locked status.
     */
    public function reverse(int $statementId, int $actorId, string $reason): array
    {
        $statement = Db::table('settlement_statements')->where('id', $statementId)->find();
        if (!$statement) {
            throw new ValidateException('Statement not found.');
        }
        if ($statement['status'] !== 'approved_locked') {
            throw new ValidateException('Only approved_locked statements can be reversed.');
        }

        $now = date('Y-m-d H:i:s');

        Db::startTrans();
        try {
            // Mark original as reversed
            Db::table('settlement_statements')
                ->where('id', $statementId)
                ->update([
                    'status' => 'reversed',
                    'updated_at' => $now,
                ]);

            // Create new draft replacement statement
            $replacementId = Db::table('settlement_statements')->insertGetId([
                'site_id' => $statement['site_id'],
                'period' => $statement['period'],
                'status' => 'draft',
                'total_amount' => $statement['total_amount'],
                'generated_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Copy settlement lines to replacement
            $lines = Db::table('settlement_lines')
                ->where('statement_id', $statementId)
                ->select()
                ->toArray();

            foreach ($lines as $line) {
                Db::table('settlement_lines')->insert([
                    'statement_id' => $replacementId,
                    'description' => $line['description'],
                    'amount' => $line['amount'],
                    'category' => $line['category'],
                    'created_at' => $now,
                ]);
            }

            // Create reversal record
            $reversalId = Db::table('settlement_reversals')->insertGetId([
                'original_statement_id' => $statementId,
                'replacement_statement_id' => $replacementId,
                'reason' => $reason,
                'reversed_by' => $actorId,
                'created_at' => $now,
            ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return Db::table('settlement_reversals')->where('id', $reversalId)->find();
    }
}
