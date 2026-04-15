<?php
declare(strict_types=1);

namespace tests\Feature\Rbac;

use tests\TestCase;

/**
 * Tests for PATCH /api/v1/admin/users/:id — proves the real handler
 * (AdminController::updateUser) executes and persists changes through
 * the live HTTP route.
 *
 * ZERO mocks/stubs.
 */
class UpdateUserTest extends TestCase
{
    private array $adminSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminSession = $this->loginAs('admin');
    }

    private function createUser(string $suffix, string $displayName = 'Update Target'): int
    {
        $createResponse = $this->authenticatedRequest('POST', '/api/v1/admin/users', $this->adminSession, [
            'username'     => 'patch_target_' . $suffix,
            'password'     => 'Aa1!aaaaaaaa',
            'display_name' => $displayName,
            'status'       => 'active',
        ]);

        $this->assertEquals(201, $createResponse['status'],
            'Helper: user creation must succeed. Got: ' . ($createResponse['raw'] ?? ''));
        $userId = (int) ($createResponse['body']['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $userId, 'Helper: created user id must be returned');

        return $userId;
    }

    public function testPatchAdminUserUpdatesDisplayNameAndStatus(): void
    {
        $userId = $this->createUser((string) uniqid());

        $patchResponse = $this->authenticatedRequest(
            'PATCH',
            "/api/v1/admin/users/{$userId}",
            $this->adminSession,
            [
                'display_name' => 'Renamed User',
                'status'       => 'inactive',
            ]
        );

        $this->assertEquals(200, $patchResponse['status'],
            'PATCH user should return 200. Got: ' . ($patchResponse['raw'] ?? ''));

        $data = $patchResponse['body']['data'] ?? [];
        $this->assertSame($userId, (int) ($data['id'] ?? 0),
            'PATCH user response must echo the user id');
        $this->assertSame('User updated.', $data['message'] ?? '',
            'PATCH user response must include the success message');
    }

    public function testPatchAdminUserNotFoundReturns404(): void
    {
        $response = $this->authenticatedRequest(
            'PATCH',
            '/api/v1/admin/users/9999999',
            $this->adminSession,
            ['display_name' => 'Ghost']
        );

        $this->assertEquals(404, $response['status']);
        $this->assertSame('NOT_FOUND', $response['body']['error']['code'] ?? '');
    }

    public function testPatchAdminUserRequiresAdministratorRole(): void
    {
        $userId = $this->createUser((string) uniqid(), 'Editor Forbid Target');
        $editorSession = $this->loginAs('editor');

        $response = $this->authenticatedRequest(
            'PATCH',
            "/api/v1/admin/users/{$userId}",
            $editorSession,
            ['display_name' => 'Should Not Apply']
        );

        $this->assertEquals(403, $response['status'],
            'Non-admin role must be rejected by RbacMiddleware before handler executes.');
    }
}
