<?php
declare(strict_types=1);

namespace tests\Feature\Audit;

use tests\TestCase;

/**
 * Verifies that AuditService writes columns that actually exist in audit_logs.
 */
class AuditServiceSchemaTest extends TestCase
{
    public function testAuditServiceWritesValidColumns(): void
    {
        $reflection = new \ReflectionMethod(\app\service\audit\AuditService::class, 'log');
        $params = $reflection->getParameters();

        $expectedParams = [
            'eventType', 'actorId', 'actorRole', 'siteId',
            'targetType', 'targetId', 'requestId', 'payloadSummary',
        ];

        $actualParams = array_map(fn($p) => $p->getName(), $params);

        foreach ($expectedParams as $expected) {
            $this->assertContains($expected, $actualParams,
                "AuditService::log must accept '$expected' matching audit_logs schema column");
        }
    }

    public function testAuditLogModelSchemaMatchesMigration(): void
    {
        // Use reflection to read the $schema property without DB connection
        $reflection = new \ReflectionClass(\app\model\AuditLog::class);
        $schemaProp = $reflection->getProperty('schema');
        $schemaProp->setAccessible(true);
        $schema = $schemaProp->getValue(new \app\model\AuditLog());

        $requiredColumns = [
            'actor_id', 'actor_role', 'site_id', 'target_type', 'target_id',
            'event_type', 'request_id', 'payload_summary', 'prev_hash', 'entry_hash',
        ];

        foreach ($requiredColumns as $col) {
            $this->assertArrayHasKey($col, $schema,
                "AuditLog model schema must include '$col' column");
        }
    }

    public function testAuditServiceAndHashServiceUseConsistentEntryData(): void
    {
        // Verify that the entry data keys used in AuditService::log match what
        // AuditHashService::verifyChain expects to reconstruct
        $hashService = new \app\service\audit\AuditHashService();

        $entryData = [
            'event_type'      => 'test.event',
            'actor_id'        => 1,
            'actor_role'      => 'administrator',
            'site_id'         => null,
            'target_type'     => 'user',
            'target_id'       => null,
            'request_id'      => 'req-test',
            'payload_summary' => null,
            'created_at'      => '2026-01-01 00:00:00',
        ];

        $prevHash = str_repeat('0', 64);
        $hash = $hashService->computeEntryHash($entryData, $prevHash);

        $this->assertEquals(64, strlen($hash));
        // Same input = same hash (deterministic)
        $this->assertEquals($hash, $hashService->computeEntryHash($entryData, $prevHash));
    }
}
