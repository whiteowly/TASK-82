<?php
declare(strict_types=1);

namespace app\middleware;

use think\Request;
use think\Response;
use think\exception\HttpException;

/**
 * Session-based authentication middleware.
 * Validates that the user has an active session.
 */
class AuthMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        try {
            $userId = session('user_id');
        } catch (\Throwable $e) {
            throw new HttpException(401, 'Authentication required');
        }

        if (!$userId) {
            throw new HttpException(401, 'Authentication required');
        }

        $request->userId = (int)$userId;
        $request->roles = session('user_roles') ?: [];

        return $next($request);
    }
}
