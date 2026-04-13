<?php
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private const REQUEST_TIMEOUT_SECONDS = 30;
    private const LOGIN_RETRIES = 3;

    /**
     * Make an HTTP request to the running application.
     */
    protected function httpRequest(string $method, string $path, array $data = [], array $headers = []): array
    {
        $url = 'http://127.0.0.1:8080' . $path;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT_SECONDS);
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
     * Captures the session cookie directly from the Set-Cookie header
     * instead of relying on curl cookie jar files.
     *
     * @param string $username One of: admin, editor, reviewer, analyst, finance, auditor
     * @return array{cookie_file: string, cookie: string, csrf_token: string, access_token: string, user: array}
     */
    protected function loginAs(string $username): array
    {
        $password = bootstrap_config('seed_admin_password', '');

        $lastResult = [
            'cookie_file' => '',
            'cookie'      => '',
            'csrf_token'  => '',
            'access_token'=> '',
            'user'        => [],
            'status'      => 0,
            'raw'         => '',
        ];

        for ($attempt = 1; $attempt <= self::LOGIN_RETRIES; $attempt++) {
            $cookies = [];
            $ch = curl_init('http://127.0.0.1:8080/api/v1/auth/login');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT_SECONDS);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'username' => $username,
                'password' => $password,
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
            ]);
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$cookies) {
                if (stripos($header, 'Set-Cookie:') === 0) {
                    $value = trim(substr($header, strlen('Set-Cookie:')));
                    $pair = explode(';', $value, 2)[0];
                    $cookies[] = trim($pair);
                }
                return strlen($header);
            });

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $cookie = implode('; ', $cookies);
            $body = json_decode($response ?: '', true) ?? [];

            $lastResult = [
                'cookie_file' => '',
                'cookie'      => $cookie,
                'csrf_token'  => $body['data']['csrf_token'] ?? '',
                'access_token'=> $body['data']['access_token'] ?? '',
                'user'        => $body['data']['user'] ?? [],
                'status'      => $httpCode,
                'raw'         => $response ?: '',
            ];

            if ($httpCode === 200 && $lastResult['access_token'] !== '') {
                return $lastResult;
            }

            if ($attempt < self::LOGIN_RETRIES) {
                usleep(200000);
            }
        }

        throw new \RuntimeException(sprintf(
            'loginAs(%s) failed after %d attempt(s): status=%d cookie_present=%s csrf_present=%s token_present=%s raw=%s',
            $username,
            self::LOGIN_RETRIES,
            (int) $lastResult['status'],
            $lastResult['cookie'] !== '' ? 'yes' : 'no',
            $lastResult['csrf_token'] !== '' ? 'yes' : 'no',
            $lastResult['access_token'] !== '' ? 'yes' : 'no',
            (string) $lastResult['raw']
        ));
    }

    /**
     * Make an authenticated HTTP request using session credentials from loginAs().
     *
     * Sends the session cookie directly as a header instead of using cookie jar files.
     */
    protected function authenticatedRequest(string $method, string $path, array $session, array $data = []): array
    {
        $url = 'http://127.0.0.1:8080' . $path;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT_SECONDS);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if (!empty($session['cookie'])) {
            $headers[] = 'Cookie: ' . $session['cookie'];
        }
        if (!empty($session['access_token'])) {
            $headers[] = 'Authorization: Bearer ' . $session['access_token'];
        }
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
