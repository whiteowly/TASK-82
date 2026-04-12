/**
 * SiteOps - Login page handler
 */
layui.use(['form', 'layer'], function () {
    'use strict';

    var form = layui.form;
    var submitting = false;

    form.on('submit(doLogin)', function (formData) {
        if (submitting) {
            return false;
        }

        submitting = true;
        var btn = document.getElementById('btn-login');
        var errorEl = document.getElementById('login-error');

        btn.disabled = true;
        btn.innerHTML = 'Logging in...';
        errorEl.classList.add('layui-hide');

        SiteOps.request('POST', '/api/v1/auth/login', {
            username: formData.field.username,
            password: formData.field.password
        })
        .then(function () {
            window.location.href = '/dashboard';
        })
        .catch(function (err) {
            errorEl.textContent = err.message || 'Login failed. Please try again.';
            errorEl.classList.remove('layui-hide');
            btn.disabled = false;
            btn.innerHTML = 'Log In';
            submitting = false;
        });

        return false; // Prevent default form submission
    });
});
