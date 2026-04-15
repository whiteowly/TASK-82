<?php
declare(strict_types=1);

namespace tests\Feature\Auth;

use tests\TestCase;

/**
 * Tests for POST /api/v1/auth/logout — proves the
 * AuthController::logout handler executes through the live HTTP route
 * (handler-level evidence: success message body + cookie-session
 * invalidation visible on a follow-up /auth/me probe).
 *
 * ZERO mocks/stubs.
 */
class LogoutTest extends TestCase
{
    /**
     * Cookie-only HTTP call — the shared authenticatedRequest() also sends
     * the Bearer access_token, which would bypass session-cookie
     * invalidation and obscure handler evidence.
     *
     * @return array{status:int, body:array, raw:string}
     */
    private function cookieRequest(string $method, string $path, string $cookie, string $csrf = ''): array
    {
        $url = 'http://127.0.0.1:8080' . $path;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Cookie: ' . $cookie,
        ];
        if ($csrf !== '') {
            $headers[] = 'X-CSRF-Token: ' . $csrf;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'body'   => json_decode($response ?: '', true) ?? [],
            'raw'    => (string) ($response ?: ''),
        ];
    }

    public function testLogoutSuccessReturnsHandlerMessageAndInvalidatesCookieSession(): void
    {
        $session = $this->loginAs('admin');
        $this->assertNotEmpty($session['cookie'], 'Login must yield a session cookie');

        // Sanity: cookie session is alive.
        $meBefore = $this->cookieRequest('GET', '/api/v1/auth/me', $session['cookie']);
        $this->assertEquals(200, $meBefore['status'],
            'Pre-logout /auth/me must succeed via the cookie session');

        // Hit the real logout handler. Body assertion proves handler ran
        // (not just guard pass-through) — the success message comes from
        // AuthController::logout().
        $logout = $this->cookieRequest(
            'POST',
            '/api/v1/auth/logout',
            $session['cookie'],
            $session['csrf_token']
        );

        $this->assertEquals(200, $logout['status'],
            'Logout should return 200. Got: ' . $logout['raw']);
        $this->assertSame(
            'Logged out successfully.',
            $logout['body']['data']['message'] ?? '',
            'Handler must return its own success message body.'
        );

        // Behavioural evidence: cookie session is now invalid.
        $meAfter = $this->cookieRequest('GET', '/api/v1/auth/me', $session['cookie']);
        $this->assertEquals(401, $meAfter['status'],
            'After logout, the same cookie must no longer authenticate.');
    }

    public function testLogoutRequiresAuthentication(): void
    {
        $response = $this->httpRequest('POST', '/api/v1/auth/logout');
        $this->assertContains($response['status'], [401, 403],
            'Unauthenticated logout must be rejected before reaching the handler.');
    }
}
