<?php
declare(strict_types=1);

namespace app\service\auth;

final class SessionTokenService
{
    private const TTL_SECONDS = 7200;

    public static function issue(int $userId, array $roles, array $siteScopes): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'uid' => $userId,
            'roles' => array_values($roles),
            'site_scopes' => array_values($siteScopes),
            'iat' => time(),
            'exp' => time() + self::TTL_SECONDS,
        ];

        $headerB64 = self::b64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadB64 = self::b64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $headerB64 . '.' . $payloadB64, self::signingKey(), true);

        return $headerB64 . '.' . $payloadB64 . '.' . self::b64UrlEncode($signature);
    }

    public static function validate(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;
        $providedSig = self::b64UrlDecode($sigB64);
        if ($providedSig === null) {
            return null;
        }

        $expectedSig = hash_hmac('sha256', $headerB64 . '.' . $payloadB64, self::signingKey(), true);
        if (!hash_equals($expectedSig, $providedSig)) {
            return null;
        }

        $payloadJson = self::b64UrlDecode($payloadB64);
        if ($payloadJson === null) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        $exp = (int)($payload['exp'] ?? 0);
        if ($exp <= 0 || time() >= $exp) {
            return null;
        }

        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) {
            return null;
        }

        return [
            'uid' => $uid,
            'roles' => is_array($payload['roles'] ?? null) ? $payload['roles'] : [],
            'site_scopes' => is_array($payload['site_scopes'] ?? null) ? $payload['site_scopes'] : [],
        ];
    }

    private static function signingKey(): string
    {
        return (string) bootstrap_config('app_key', 'dev-fallback-key');
    }

    private static function b64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64UrlDecode(string $b64): ?string
    {
        $remainder = strlen($b64) % 4;
        if ($remainder > 0) {
            $b64 .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($b64, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }
}
