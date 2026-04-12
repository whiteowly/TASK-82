<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token ?? ''; ?>">
    <title>Login - SiteOps</title>
    <link rel="stylesheet" href="/static/layui/css/layui.css">
    <link rel="stylesheet" href="/static/css/app.css">
</head>
<body class="login-page">
<div class="login-container">
    <h2 class="login-title">SiteOps</h2>

    <div id="login-error" class="layui-hide" style="color: #FF5722; margin-bottom: 15px; text-align: center;"></div>

    <form class="layui-form" id="login-form" lay-filter="loginForm">
        <div class="layui-form-item">
            <label class="layui-form-label">Username</label>
            <div class="layui-input-block">
                <input type="text" name="username" required lay-verify="required" placeholder="Username" autocomplete="username" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">Password</label>
            <div class="layui-input-block">
                <input type="password" name="password" required lay-verify="required" placeholder="Password" autocomplete="current-password" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <div class="layui-input-block">
                <button type="submit" class="layui-btn layui-btn-fluid" lay-submit lay-filter="doLogin" id="btn-login">Log In</button>
            </div>
        </div>
    </form>
</div>

<script src="/static/layui/layui.js"></script>
<script src="/static/js/app.js"></script>
<script src="/static/js/auth.js"></script>
</body>
</html>
