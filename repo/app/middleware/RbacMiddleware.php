<?php
declare(strict_types=1);

namespace app\middleware;

use think\Request;
use think\Response;
use think\exception\HttpException;

/**
 * Role-based access control middleware.
 *
 * Usage in routes: ->middleware('rbac:admin,auditor')
 * The parameter is a comma-separated list of allowed role names.
 */
class RbacMiddleware
{
    public function handle(Request $request, \Closure $next, ?string $allowedRoles = null): Response
    {
        if (!$request->userId) {
            throw new HttpException(401, 'Authentication required');
        }

        if ($allowedRoles !== null) {
            $allowed = array_map('trim', explode(',', $allowedRoles));
            $userRoles = $request->roles ?: [];

            $hasPermission = !empty(array_intersect($userRoles, $allowed));
            if (!$hasPermission) {
                throw new HttpException(403, 'Insufficient role permissions');
            }
        }

        return $next($request);
    }
}
