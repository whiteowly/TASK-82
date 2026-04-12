<?php
declare(strict_types=1);

namespace app\service\settlement;

use think\facade\Db;

class ReconciliationService
{
    /**
     * Get variances (discrepancies) for a given statement.
     *
     * @param int $statementId
     * @return array List of variance records.
     */
    public function getVariances(int $statementId): array
    {
        return Db::table('reconciliation_variances')
            ->where('statement_id', $statementId)
            ->order('created_at', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * Record a reconciliation action with notes for a statement.
     * Each entry in $notes should have: field_name, expected_value, actual_value, notes, resolved.
     *
     * @param int   $statementId
     * @param array $notes Reconciliation notes and adjustments.
     * @param int   $userId
     * @return void
     */
    public function recordReconciliation(int $statementId, array $notes, int $userId): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ($notes as $entry) {
            $existing = Db::table('reconciliation_variances')
                ->where('statement_id', $statementId)
                ->where('field_name', $entry['field_name'])
                ->find();

            if ($existing) {
                Db::table('reconciliation_variances')
                    ->where('id', $existing['id'])
                    ->update([
                        'expected_value' => $entry['expected_value'] ?? $existing['expected_value'],
                        'actual_value' => $entry['actual_value'] ?? $existing['actual_value'],
                        'notes' => $entry['notes'] ?? $existing['notes'],
                        'resolved' => isset($entry['resolved']) ? ($entry['resolved'] ? 1 : 0) : $existing['resolved'],
                        'updated_at' => $now,
                    ]);
            } else {
                Db::table('reconciliation_variances')->insert([
                    'statement_id' => $statementId,
                    'field_name' => $entry['field_name'],
                    'expected_value' => $entry['expected_value'] ?? '',
                    'actual_value' => $entry['actual_value'] ?? '',
                    'notes' => $entry['notes'] ?? null,
                    'resolved' => isset($entry['resolved']) ? ($entry['resolved'] ? 1 : 0) : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
