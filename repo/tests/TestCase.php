<?php
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @var string[] Cookie files to clean up after each test */
    private array $cookieFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->cookieFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->cookieFiles = [];
        parent::tearDown();
    }

    /**
     * Make an HTTP request to the running application.
     * With host networking, the app listens on 127.0.0.1:8080 both
     * from inside the container and from the host.
     */
    protected function httpRequest(string $method, string $path, array $data = [], array $headers = []): array
    {
        $url = 'http://127.0.0.1:8080' . $path;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
            ['Content-Type: application/json', 'Accept: application/json'],
            $headers
        ));

        if (!empty($data) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'body' => json_decode($response ?: '', true) ?? [],
            'raw' => $response,
        ];
    }

    /**
     * Log in as a seeded user and return session credentials.
     *
     * @param string $username One of: admin, editor, reviewer, analyst, finance, auditor
     * @return array{cookie_file: string, csrf_token: string, user: array}
     */
    protected function loginAs(string $username): array
    {
        $password = bootstrap_config('seed_admin_password', '');

        $cookieFile = tempnam(sys_get_temp_dir(), 'test_cookie_');
        $this->cookieFiles[] = $cookieFile;

        $ch = curl_init('http://127.0.0.1:8080/api/v1/auth/login');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'username' => $username,
            'password' => $password,
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $body = json_decode($response ?: '', true) ?? [];

        return [
            'cookie_file' => $cookieFile,
            'csrf_token'  => $body['data']['csrf_token'] ?? '',
            'user'        => $body['data']['user'] ?? [],
            'status'      => $httpCode,
        ];
    }

    /**
     * Make an authenticated HTTP request using session credentials from loginAs().
     *
     * @param string $method  HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param string $path    URL path (e.g. /api/v1/recipes)
     * @param array  $session Session credentials from loginAs()
     * @param array  $data    Request body data (for POST/PUT/PATCH)
     * @return array{status: int, body: array, raw: string}
     */
    protected function authenticatedRequest(string $method, string $path, array $session, array $data = []): array
    {
        $url = 'http://127.0.0.1:8080' . $path;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_COOKIEFILE, $session['cookie_file']);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $session['cookie_file']);

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if (!empty($session['csrf_token'])) {
            $headers[] = 'X-CSRF-Token: ' . $session['csrf_token'];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (!empty($data) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'body'   => json_decode($response ?: '', true) ?? [],
            'raw'    => $response,
        ];
    }
}
