<?php

use think\facade\Route;

// ─── Health ──────────────────────────────────────────────────────────
Route::get('api/v1/health', 'api.v1.Health/index');

// ─── Authentication ──────────────────────────────────────────────────
Route::post('api/v1/auth/login', 'api.v1.Auth/login');
Route::post('api/v1/auth/logout', 'api.v1.Auth/logout')->middleware(['auth', 'csrf']);
Route::get('api/v1/auth/me', 'api.v1.Auth/me')->middleware('auth');

// ─── Authenticated API routes ────────────────────────────────────────
Route::group('api/v1', function () {

    // RBAC reference — admin-only
    Route::get('rbac/roles', 'api.v1.Admin/roles')
        ->middleware(\app\middleware\RbacMiddleware::class, 'administrator');
    Route::get('rbac/permissions', 'api.v1.Admin/permissions')
        ->middleware(\app\middleware\RbacMiddleware::class, 'administrator');

    // Admin user management
    Route::group('admin', function () {
        Route::post('users', 'api.v1.Admin/createUser');
        Route::patch('users/:id', 'api.v1.Admin/updateUser');
        Route::post('users/:id/roles', 'api.v1.Admin/assignRoles');
        Route::post('users/:id/site-scopes', 'api.v1.Admin/assignSiteScopes');
    })->middleware(\app\middleware\RbacMiddleware::class, 'administrator');

    // Recipe workflow
    Route::group('recipes', function () {
        Route::get('', 'api.v1.Recipe/index');
        Route::post('', 'api.v1.Recipe/create');
        Route::get(':id', 'api.v1.Recipe/read');
        Route::post(':id/versions', 'api.v1.Recipe/createVersion');
        Route::post(':id/publish', 'api.v1.Recipe/publish');
    });

    Route::group('recipe-versions', function () {
        Route::put(':id', 'api.v1.Recipe/updateVersion');
        Route::post(':id/submit-review', 'api.v1.Recipe/submitReview');
        Route::get(':id/diff', 'api.v1.Recipe/diff');
        Route::get(':id/comments', 'api.v1.Recipe/listComments');
        Route::post(':id/comments', 'api.v1.Recipe/addComment');
        Route::post(':id/approve', 'api.v1.Recipe/approve');
        Route::post(':id/reject', 'api.v1.Recipe/reject');
    });

    // Internal published catalog
    Route::get('catalog/recipes', 'api.v1.Catalog/index');
    Route::get('catalog/recipes/:id', 'api.v1.Catalog/read');

    // File uploads
    Route::post('files/images', 'api.v1.File/uploadImage');

    // Analytics
    Route::get('analytics/dashboard', 'api.v1.Analytics/dashboard');
    Route::post('analytics/refresh', 'api.v1.Analytics/refresh');
    Route::get('analytics/refresh-requests/:id', 'api.v1.Analytics/refreshStatus');

    // Search
    Route::post('search/query', 'api.v1.Search/query');

    // Reports
    Route::group('reports', function () {
        Route::post('definitions/:id/run', 'api.v1.Report/runReport');
        Route::post('definitions/:id/schedule', 'api.v1.Report/scheduleReport');
        Route::get('definitions/:id', 'api.v1.Report/readDefinition');
        Route::patch('definitions/:id', 'api.v1.Report/updateDefinition');
        Route::get('definitions', 'api.v1.Report/listDefinitions');
        Route::post('definitions', 'api.v1.Report/createDefinition');
        Route::get('runs/:id/download', 'api.v1.Report/download');
        Route::get('runs/:id', 'api.v1.Report/readRun');
        Route::get('runs', 'api.v1.Report/listRuns');
    });

    // CSV export
    Route::post('exports/csv', 'api.v1.Report/exportCsv');

    // Settlement & finance
    Route::group('finance', function () {
        Route::post('freight-rules', 'api.v1.Settlement/createFreightRule');
        Route::get('freight-rules', 'api.v1.Settlement/listFreightRules');
        Route::patch('freight-rules/:id', 'api.v1.Settlement/updateFreightRule');
        Route::post('settlements/generate', 'api.v1.Settlement/generate');
        Route::get('settlements/:id', 'api.v1.Settlement/read');
        Route::get('settlements', 'api.v1.Settlement/listStatements');
        Route::post('settlements/:id/reconcile', 'api.v1.Settlement/reconcile');
        Route::post('settlements/:id/submit', 'api.v1.Settlement/submit');
        Route::post('settlements/:id/approve-final', 'api.v1.Settlement/approveFinal')
            ->middleware(\app\middleware\RbacMiddleware::class, 'administrator');
        Route::post('settlements/:id/reverse', 'api.v1.Settlement/reverse');
        Route::get('settlements/:id/audit-trail', 'api.v1.Settlement/auditTrail');
    });

    // Audit
    Route::group('audit', function () {
        Route::get('logs/:id', 'api.v1.Audit/logDetail');
        Route::get('logs', 'api.v1.Audit/logs');
        Route::get('exports', 'api.v1.Audit/exports');
        Route::get('approvals', 'api.v1.Audit/approvals');
        Route::get('permission-changes', 'api.v1.Audit/permissionChanges');
    })->middleware(\app\middleware\RbacMiddleware::class, 'administrator,auditor');

})->middleware(['auth', 'csrf', 'site_scope']);

// ─── Web routes (pages served by ThinkPHP view engine) ───────────────
Route::get('/', 'Index/index');
Route::get('login', 'Index/login');
Route::get('dashboard', 'Index/dashboard');
Route::get('recipes/editor', 'Index/recipeEditor');
Route::get('recipes/review', 'Index/recipeReview');
Route::get('catalog', 'Index/catalog');
Route::get('analytics', 'Index/analytics');
Route::get('reports', 'Index/reports');
Route::get('settlements', 'Index/settlements');
Route::get('audit', 'Index/audit');
Route::get('admin', 'Index/admin');

// Catch-all for API 404
Route::miss(function () {
    $request = request();
    $path = $request->pathinfo();
    $requestId = $request->requestId ?? generate_request_id();

    if (str_starts_with($path, 'api/')) {
        return json([
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'Endpoint not found',
                'details' => [],
            ],
            'meta' => ['request_id' => $requestId],
        ])->code(404);
    }

    return redirect('/login');
});
