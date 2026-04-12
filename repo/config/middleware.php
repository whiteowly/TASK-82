<?php

return [
    // Alias definitions for route-level middleware
    'alias' => [
        'auth'       => \app\middleware\AuthMiddleware::class,
        'rbac'       => \app\middleware\RbacMiddleware::class,
        'site_scope' => \app\middleware\SiteScopeMiddleware::class,
        'csrf'       => \app\middleware\CsrfMiddleware::class,
    ],

    // Priority (higher = runs first)
    'priority' => [
        \app\middleware\RequestIdMiddleware::class,
        \app\middleware\AuthMiddleware::class,
        \app\middleware\CsrfMiddleware::class,
        \app\middleware\SiteScopeMiddleware::class,
        \app\middleware\RbacMiddleware::class,
    ],
];
