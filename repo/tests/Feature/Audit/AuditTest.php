<?php
declare(strict_types=1);

namespace tests\Feature\Audit;

use tests\TestCase;

class AuditTest extends TestCase
{
    private array $adminSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminSession = $this->loginAs('admin');
        $this->assertEquals(200, $this->adminSession['status'], 'Admin login must succeed');

        // Ensure audit entries exist by triggering an auditable action
        $this->triggerAuditableAction();
    }

    // ─── Access control ────────────────────────────────────────────

    public function testAuditLogsAccessibleByAdmin(): void
    {
        $r = $this->authenticatedRequest('GET', '/api/v1/audit/logs', $this->adminSession);
        $this->assertEquals(200, $r['status']);
        $this->assertArrayHasKey('items', $r['body']['data']);
    }

    public function testAuditLogsAccessibleByAuditor(): void
    {
        $s = $this->loginAs('auditor');
        $r = $this->authenticatedRequest('GET', '/api/v1/audit/logs', $s);
        $this->assertEquals(200, $r['status']);
    }

    public function testAuditLogsDeniedForEditor(): void
    {
        $s = $this->loginAs('editor');
        $r = $this->authenticatedRequest('GET', '/api/v1/audit/logs', $s);
        $this->assertEquals(403, $r['status']);
    }

    public function testAuditLogsDeniedForFinance(): void
    {
        $s = $this->loginAs('finance');
        $r = $this->authenticatedRequest('GET', '/api/v1/audit/logs', $s);
        $this->assertEquals(403, $r['status']);
    }

    public function testAuditLogsRequiresAuth(): void
    {
        $r = $this->httpRequest('GET', '/api/v1/audit/logs');
        $this->assertContains($r['status'], [401, 403]);
    }

    // ─── Log entries ───────────────────────────────────────────────

    public function testAuditLogsContainEntries(): void
    {
        $r = $this->authenticatedRequest('GET', '/api/v1/audit/logs', $this->adminSession);
        $items = $r['body']['data']['items'] ?? [];
        $this->assertNotEmpty($items, 'Audit logs must contain entries (seeded + triggered)');
    }

    public function testAuditLogDetailReturnsEntry(): void
    {
        $list = $this->authenticatedRequest('GET', '/api/v1/audit/logs', $this->adminSession);
        $items = $list['body']['data']['items'] ?? [];
        $this->assertNotEmpty($items, 'Need at least one audit entry');

        $entryId = $items[0]['id'];
        $r = $this->authenticatedRequest('GET', "/api/v1/audit/logs/{$entryId}", $this->adminSession);

        $this->assertEquals(200, $r['status']);
        $entry = $r['body']['data'] ?? [];
        $this->assertNotEmpty($entry['id']);
        $this->assertNotEmpty($entry['event_type']);
        $this->assertNotEmpty($entry['actor_id']);
    }

    public function testAuditLogDetailNotFound(): void
    {
        $r = $this->authenticatedRequest('GET', '/api/v1/audit/logs/999999', $this->adminSession);
        $this->assertEquals(404, $r['status']);
    }

    public function testAuditLogsFilterByEventType(): void
    {
        $r = $this->authenticatedRequest('GET', '/api/v1/audit/logs?event_type=user.login', $this->adminSession);
        $this->assertEquals(200, $r['status']);
        $items = $r['body']['data']['items'] ?? [];
        foreach ($items as $item) {
            $this->assertEquals('user.login', $item['event_type']);
        }
    }

    // ─── Hash chain ────────────────────────────────────────────────

    public function testAuditEntriesHaveHashFields(): void
    {
        $r = $this->authenticatedRequest('GET', '/api/v1/audit/logs', $this->adminSession);
        $items = $r['body']['data']['items'] ?? [];
        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $this->assertArrayHasKey('prev_hash', $item);
            $this->assertArrayHasKey('entry_hash', $item);
            $this->assertEquals(64, strlen($item['entry_hash']), 'entry_hash must be 64-char SHA-256 hex');
            $this->assertEquals(64, strlen($item['prev_hash']), 'prev_hash must be 64-char SHA-256 hex');
        }
    }

    public function testHashChainIsLinked(): void
    {
        $r = $this->authenticatedRequest('GET', '/api/v1/audit/logs', $this->adminSession);
        $items = $r['body']['data']['items'] ?? [];
        $this->assertGreaterThanOrEqual(2, count($items), 'Need at least 2 audit entries for chain test');

        // Items are ordered desc by id. items[0] is newest, items[1] is next older.
        $newer = $items[0];
        $older = $items[1];
        $this->assertEquals($older['entry_hash'], $newer['prev_hash'],
            'Newer entry prev_hash must equal older entry entry_hash (hash chain linkage)');
    }

    // ─── Subresources ──────────────────────────────────────────────

    public function testPermissionChangesEndpoint(): void
    {
        $r = $this->authenticatedRequest('GET', '/api/v1/audit/permission-changes', $this->adminSession);
        $this->assertEquals(200, $r['status']);
        $this->assertArrayHasKey('items', $r['body']['data']);
    }

    public function testApprovalsEndpoint(): void
    {
        $r = $this->authenticatedRequest('GET', '/api/v1/audit/approvals', $this->adminSession);
        $this->assertEquals(200, $r['status']);
        $this->assertArrayHasKey('items', $r['body']['data']);
    }

    public function testExportsEndpoint(): void
    {
        $r = $this->authenticatedRequest('GET', '/api/v1/audit/exports', $this->adminSession);
        $this->assertEquals(200, $r['status']);
        $this->assertArrayHasKey('items', $r['body']['data']);
    }

    // ─── Helper ────────────────────────────────────────────────────

    private function triggerAuditableAction(): void
    {
        $period = '2024-' . str_pad((string)rand(1, 12), 2, '0', STR_PAD_LEFT);
        $gen = $this->authenticatedRequest('POST', '/api/v1/finance/settlements/generate', $this->adminSession, [
            'site_id' => 1,
            'period'  => $period,
        ]);
        $stmtId = $gen['body']['data']['settlement_id'] ?? null;
        if ($stmtId) {
            $this->authenticatedRequest('POST', "/api/v1/finance/settlements/{$stmtId}/submit", $this->adminSession);
        }
    }
}
