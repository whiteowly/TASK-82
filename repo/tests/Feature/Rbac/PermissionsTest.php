<?php
declare(strict_types=1);

namespace tests\Feature\Rbac;

use tests\TestCase;

/**
 * Tests for GET /api/v1/rbac/permissions — proves the
 * AdminController::permissions handler executes through the live HTTP
 * route. The handler-level evidence is the response body shape and the
 * presence of seeded permission rows.
 *
 * ZERO mocks/stubs.
 */
class PermissionsTest extends TestCase
{
    private array $adminSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminSession = $this->loginAs('admin');
    }

    public function testAdminCanListPermissionsWithSeededShape(): void
    {
        $response = $this->authenticatedRequest('GET', '/api/v1/rbac/permissions', $this->adminSession);

        $this->assertEquals(200, $response['status'],
            'Permissions listing should return 200. Got: ' . ($response['raw'] ?? ''));

        $data = $response['body']['data'] ?? [];
        $this->assertArrayHasKey('items', $data, 'Response must contain items array');
        $this->assertIsArray($data['items']);
        $this->assertNotEmpty(
            $data['items'],
            'Seeded permissions must be returned by the handler (not blocked by guards).'
        );

        // Inspect the first row to prove this is the real handler payload, not
        // a guard-only short-circuit.
        $first = $data['items'][0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('module', $first);

        // A known seeded permission should be present.
        $names = array_column($data['items'], 'name');
        $this->assertContains('admin.manage', $names,
            'Seeded permission "admin.manage" must appear in the handler output.');
    }

    public function testNonAdminRoleIsRejected(): void
    {
        $editorSession = $this->loginAs('editor');

        $response = $this->authenticatedRequest('GET', '/api/v1/rbac/permissions', $editorSession);

        $this->assertEquals(403, $response['status'],
            'Non-administrator role must be rejected by the RbacMiddleware before the handler executes.');
    }
}
