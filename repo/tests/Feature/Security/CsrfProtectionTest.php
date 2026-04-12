<?php
declare(strict_types=1);

namespace tests\Feature\Security;

use tests\TestCase;

class CsrfProtectionTest extends TestCase
{
    public function testPostWithoutCsrfTokenIsRejectedOnProtectedRoute(): void
    {
        // POST to logout (a CSRF-protected route) without token
        $response = $this->httpRequest('POST', '/api/v1/auth/logout');

        // Should be 403 (CSRF) or 401 (no session)
        $this->assertContains($response['status'], [401, 403]);
    }

    public function testLoginIsExemptFromCsrf(): void
    {
        // Login should work without CSRF token (it's exempt)
        $response = $this->httpRequest('POST', '/api/v1/auth/login', [
            'username' => 'nobody',
            'password' => 'wrong',
        ]);

        // Should get 401 (invalid credentials), not 403 (CSRF)
        $this->assertEquals(401, $response['status']);
    }
}
