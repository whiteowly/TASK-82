<?php
declare(strict_types=1);

namespace app\controller;

use think\App;
use think\Response;

abstract class BaseController
{
    protected App $app;
    protected \app\Request $request;

    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $app->request;
        $this->initialize();
    }

    /**
     * Hook for subclass initialization.
     */
    protected function initialize(): void
    {
    }

    /**
     * Check if the current user has cross-site access (empty siteScopes = all sites).
     */
    protected function hasCrossSiteAccess(): bool
    {
        return empty($this->request->siteScopes);
    }

    /**
     * Check if user can access a specific site.
     */
    protected function canAccessSite(int $siteId): bool
    {
        return $this->hasCrossSiteAccess() || in_array($siteId, $this->request->siteScopes, true);
    }

    /**
     * Apply site scope filter to a query. Skips filter for cross-site roles.
     */
    protected function applySiteScope($query, string $column = 'site_id')
    {
        if (!$this->hasCrossSiteAccess()) {
            $query->whereIn($column, $this->request->siteScopes);
        }
        return $query;
    }

    /**
     * Return a success envelope.
     *
     * @param  mixed  $data
     * @param  int    $code  HTTP status code
     * @return Response
     */
    protected function success($data = [], int $code = 200): Response
    {
        return json([
            'data' => $data,
            'meta' => [
                'request_id' => $this->request->requestId,
            ],
        ], $code);
    }

    /**
     * Return an error envelope.
     *
     * @param  string  $errorCode  Machine-readable error code
     * @param  string  $message    Human-readable message
     * @param  array   $details    Optional structured details
     * @param  int     $httpCode   HTTP status code
     * @return Response
     */
    protected function error(string $errorCode, string $message, array $details = [], int $httpCode = 400): Response
    {
        return json([
            'error' => [
                'code'    => $errorCode,
                'message' => $message,
                'details' => $details,
            ],
            'meta' => [
                'request_id' => $this->request->requestId,
            ],
        ], $httpCode);
    }
}
