<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\auth\SessionTokenService;
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
        $authorization = (string) $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
            $claims = SessionTokenService::validate(trim($matches[1]));
            if (is_array($claims)) {
                $request->userId = (int) $claims['uid'];
                $request->roles = $claims['roles'];
                $request->tokenSiteScopes = $claims['site_scopes'];
                return $next($request);
            }
        }

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
