<?php
declare(strict_types=1);

namespace app\middleware;

use think\Request;
use think\Response;
use think\exception\HttpException;

/**
 * CSRF protection for state-changing requests.
 *
 * Token is validated from the X-CSRF-Token header.
 * Token generation and refresh are handled by the auth flow.
 */
class CsrfMiddleware
{
    /** Methods that require CSRF validation */
    private const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** Routes exempt from CSRF (login needs to work without a prior session) */
    private const EXEMPT_ROUTES = [
        'api/v1/auth/login',
        'api/v1/health',
    ];

    public function handle(Request $request, \Closure $next): Response
    {
        $method = strtoupper($request->method());

        if (!in_array($method, self::PROTECTED_METHODS)) {
            return $next($request);
        }

        // Check exemptions
        $path = $request->pathinfo();
        foreach (self::EXEMPT_ROUTES as $exempt) {
            if (str_starts_with($path, $exempt)) {
                return $next($request);
            }
        }

        // If no authenticated session exists, skip CSRF — AuthMiddleware will reject
        $sessionToken = session('csrf_token');
        if (empty($sessionToken)) {
            return $next($request);
        }

        $token = $request->header('X-CSRF-Token', '');
        if (empty($token) || !hash_equals($sessionToken, $token)) {
            throw new HttpException(403, 'CSRF token validation failed');
        }

        return $next($request);
    }
}
