<?php
declare(strict_types=1);

namespace app\middleware;

use think\Request;
use think\Response;
use think\exception\HttpException;

/**
 * Site scope enforcement middleware.
 *
 * Loads the user's permitted site IDs into the request object.
 * Controllers/services must use these scopes to filter data access.
 *
 * Administrators and Read-Only Auditors receive cross-site access.
 */
class SiteScopeMiddleware
{
    /** Roles with cross-site access */
    private const CROSS_SITE_ROLES = ['administrator', 'auditor'];

    public function handle(Request $request, \Closure $next): Response
    {
        if (!$request->userId) {
            throw new HttpException(401, 'Authentication required');
        }

        $roles = $request->roles ?: [];
        $hasCrossSite = !empty(array_intersect($roles, self::CROSS_SITE_ROLES));

        if ($hasCrossSite) {
            $request->siteScopes = []; // empty = all sites
        } else {
            $siteScopes = session('user_site_scopes') ?: [];
            $request->siteScopes = $siteScopes;

            if (empty($siteScopes)) {
                throw new HttpException(403, 'No site scope assigned');
            }
        }

        return $next($request);
    }
}
