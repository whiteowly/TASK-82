<?php
declare(strict_types=1);

namespace app\service\audit;

use think\facade\Db;

class AuditService
{
    protected AuditHashService $hashService;

    public function __construct(AuditHashService $hashService)
    {
        $this->hashService = $hashService;
    }

    /**
     * Append an immutable audit log entry with hash-chain integrity.
     *
     * All parameters map directly to audit_logs schema columns.
     */
    public function log(
        string $eventType,
        int    $actorId,
        string $actorRole,
        ?int   $siteId,
        string $targetType,
        ?int   $targetId,
        string $requestId,
        ?string $payloadSummary = null
    ): void {
        $prevHash = $this->hashService->getLatestHash();

        $entryData = [
            'event_type'      => $eventType,
            'actor_id'        => $actorId,
            'actor_role'      => $actorRole,
            'site_id'         => $siteId,
            'target_type'     => $targetType,
            'target_id'       => $targetId,
            'request_id'      => $requestId,
            'payload_summary' => $payloadSummary,
            'created_at'      => date('Y-m-d H:i:s'),
        ];

        $entryHash = $this->hashService->computeEntryHash($entryData, $prevHash);

        Db::name('audit_logs')->insert([
            'event_type'      => $eventType,
            'actor_id'        => $actorId,
            'actor_role'      => $actorRole,
            'site_id'         => $siteId,
            'target_type'     => $targetType,
            'target_id'       => $targetId,
            'request_id'      => $requestId,
            'payload_summary' => $payloadSummary,
            'prev_hash'       => $prevHash,
            'entry_hash'      => $entryHash,
            'created_at'      => $entryData['created_at'],
        ]);
    }

    /**
     * Query audit log entries by filters.
     */
    public function query(array $filters): array
    {
        $query = Db::name('audit_logs');

        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }
        if (!empty($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }
        if (!empty($filters['site_id'])) {
            $query->where('site_id', $filters['site_id']);
        }
        if (!empty($filters['target_type'])) {
            $query->where('target_type', $filters['target_type']);
        }

        return $query->order('id', 'desc')->limit(100)->select()->toArray();
    }

    /**
     * Find a single audit log entry by ID.
     */
    public function findEntry(int $id): ?array
    {
        $entry = Db::name('audit_logs')->where('id', $id)->find();
        return $entry ?: null;
    }
}
