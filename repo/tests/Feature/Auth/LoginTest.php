<?php
declare(strict_types=1);

namespace tests\Feature\Auth;

use tests\TestCase;

class LoginTest extends TestCase
{
    public function testInvalidCredentialsReturns401(): void
    {
        $response = $this->httpRequest('POST', '/api/v1/auth/login', [
            'username' => 'nonexistent',
            'password' => 'wrongpassword',
        ]);

        $this->assertEquals(401, $response['status']);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertEquals('AUTH_INVALID_CREDENTIALS', $response['body']['error']['code']);
    }

    public function testLoginRequiresCredentials(): void
    {
        $response = $this->httpRequest('POST', '/api/v1/auth/login', []);

        $this->assertEquals(422, $response['status']);
        $this->assertEquals('VALIDATION_FAILED', $response['body']['error']['code']);
    }

    public function testValidLoginReturnsUserAndCsrfToken(): void
    {
        $password = bootstrap_config('seed_admin_password', '');
        $this->assertNotEmpty($password, 'seed_admin_password must be set in bootstrap config');

        $response = $this->httpRequest('POST', '/api/v1/auth/login', [
            'username' => 'admin',
            'password' => $password,
        ]);

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('data', $response['body']);
        $this->assertArrayHasKey('user', $response['body']['data']);
        $this->assertArrayHasKey('csrf_token', $response['body']['data']);
        $this->assertNotEmpty($response['body']['data']['csrf_token']);
        $this->assertEquals('admin', $response['body']['data']['user']['username']);
        $this->assertContains('administrator', $response['body']['data']['user']['roles']);
    }

    public function testMeEndpointRequiresAuth(): void
    {
        $response = $this->httpRequest('GET', '/api/v1/auth/me');

        $this->assertEquals(401, $response['status']);
        $this->assertEquals('AUTH_REQUIRED', $response['body']['error']['code']);
    }
}
