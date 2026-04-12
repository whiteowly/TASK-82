<?php
declare(strict_types=1);

namespace app;

class Request extends \think\Request
{
    /**
     * Current request ID, set by RequestIdMiddleware.
     */
    public string $requestId = '';

    /**
     * Current authenticated user ID, set by AuthMiddleware.
     */
    public ?int $userId = null;

    /**
     * Current user's site scope IDs, set by SiteScopeMiddleware.
     */
    public array $siteScopes = [];

    /**
     * Current user's role names, set by AuthMiddleware.
     */
    public array $roles = [];
}
