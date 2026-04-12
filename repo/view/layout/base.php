<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token ?? ''; ?>">
    <title><?php echo $title ?? 'SiteOps'; ?></title>
    <link rel="stylesheet" href="/static/layui/css/layui.css">
    <link rel="stylesheet" href="/static/css/app.css">
</head>
<body class="layui-layout-body">
<div class="layui-layout layui-layout-admin">

    <div class="layui-header">
        <div class="layui-logo">SiteOps</div>
        <ul class="layui-nav layui-layout-right">
            <li class="layui-nav-item">
                <a href="javascript:;">
                    <span id="header-username"><?php echo $username ?? 'User'; ?></span>
                </a>
                <dl class="layui-nav-child">
                    <dd><a href="javascript:;" id="btn-logout">Logout</a></dd>
                </dl>
            </li>
        </ul>
    </div>

    <div class="layui-side layui-bg-black">
        <div class="layui-side-scroll">
            <ul class="layui-nav layui-nav-tree" lay-filter="sideNav">
                <li class="layui-nav-item" data-permission="dashboard.view">
                    <a href="/dashboard">Dashboard</a>
                </li>
                <li class="layui-nav-item" data-permission="recipe.view">
                    <a href="javascript:;">Recipes</a>
                    <dl class="layui-nav-child">
                        <dd data-permission="recipe.edit"><a href="/recipes/editor">Editor</a></dd>
                        <dd data-permission="recipe.review"><a href="/recipes/review">Review</a></dd>
                        <dd data-permission="catalog.view"><a href="/catalog">Catalog</a></dd>
                    </dl>
                </li>
                <li class="layui-nav-item" data-permission="analytics.view">
                    <a href="/analytics">Analytics</a>
                </li>
                <li class="layui-nav-item" data-permission="report.view">
                    <a href="/reports">Reports</a>
                </li>
                <li class="layui-nav-item" data-permission="settlement.view">
                    <a href="/settlements">Settlements</a>
                </li>
                <li class="layui-nav-item" data-permission="audit.view">
                    <a href="/audit">Audit</a>
                </li>
                <li class="layui-nav-item" data-permission="admin.view">
                    <a href="/admin">Admin</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="layui-body">
        <div class="site-content">
            <?php echo $content ?? ''; ?>
        </div>
    </div>

    <div class="layui-footer">
        &copy; <?php echo date('Y'); ?> SiteOps
    </div>
</div>

<script src="/static/layui/layui.js"></script>
<script src="/static/js/app.js"></script>
<script>
// Logout handler: POST with CSRF token, then redirect to login
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('btn-logout');
    if (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            SiteOps.request('POST', '/api/v1/auth/logout', {})
                .then(function () {
                    window.location.href = '/login';
                })
                .catch(function () {
                    // Session may already be gone — redirect anyway
                    window.location.href = '/login';
                });
        });
    }
});
</script>
<script>
window.USER_PERMISSIONS = <?php echo json_encode($user_permissions ?? []); ?>;
window.USER_ROLES = <?php echo json_encode($user_roles ?? []); ?>;
window.USER_SITE_SCOPES = <?php echo json_encode($user_site_scopes ?? []); ?>;
</script>
<?php echo $scripts ?? ''; ?>
</body>
</html>
