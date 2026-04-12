<?php
declare(strict_types=1);

namespace app\service\audit;

use think\facade\Db;

class AuditHashService
{
    /**
     * Compute the entry hash from entry data and the previous hash.
     * Uses SHA-256 over canonical JSON concatenated with the previous hash.
     */
    public function computeEntryHash(array $entryData, string $prevHash): string
    {
        $canonical = json_encode($entryData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash('sha256', $canonical . $prevHash);
    }

    /**
     * Get the entry_hash of the most recent audit log entry.
     * Returns a zero hash if no entries exist (genesis).
     */
    public function getLatestHash(): string
    {
        $latest = Db::name('audit_logs')
            ->order('id', 'desc')
            ->value('entry_hash');

        return $latest ?: str_repeat('0', 64);
    }

    /**
     * Verify the integrity of the hash chain between two entry IDs.
     */
    public function verifyChain(int $startId, int $endId): bool
    {
        $entries = Db::name('audit_logs')
            ->whereBetween('id', [$startId, $endId])
            ->order('id', 'asc')
            ->select()
            ->toArray();

        foreach ($entries as $entry) {
            // Reconstruct the entry data used for hashing — must match AuditService::log
            $entryData = [
                'event_type'      => $entry['event_type'],
                'actor_id'        => (int)$entry['actor_id'],
                'actor_role'      => $entry['actor_role'],
                'site_id'         => $entry['site_id'] !== null ? (int)$entry['site_id'] : null,
                'target_type'     => $entry['target_type'],
                'target_id'       => $entry['target_id'] !== null ? (int)$entry['target_id'] : null,
                'request_id'      => $entry['request_id'],
                'payload_summary' => $entry['payload_summary'],
                'created_at'      => $entry['created_at'],
            ];

            $expected = $this->computeEntryHash($entryData, $entry['prev_hash']);

            if ($expected !== $entry['entry_hash']) {
                return false;
            }
        }

        return true;
    }
}
