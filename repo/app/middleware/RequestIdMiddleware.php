<?php
declare(strict_types=1);

namespace app\middleware;

use think\Request;
use think\Response;

/**
 * Assigns a unique request ID to every request for traceability.
 */
class RequestIdMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        $requestId = generate_request_id();
        $request->requestId = $requestId;

        /** @var Response $response */
        $response = $next($request);

        return $response->header([
            'X-Request-Id' => $requestId,
        ]);
    }
}
