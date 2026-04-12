/**
 * SiteOps - Shared utilities and request client
 */
var SiteOps = (function () {
    'use strict';

    /**
     * Read CSRF token from meta tag.
     */
    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /**
     * Perform an HTTP request and return a Promise resolving to parsed JSON.
     *
     * @param {string} method - HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param {string} url - Request URL
     * @param {Object|null} data - Request body (ignored for GET/HEAD)
     * @returns {Promise<Object>}
     */
    function request(method, url, data) {
        var headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': getCsrfToken()
        };

        var opts = {
            method: method,
            headers: headers,
            credentials: 'include'
        };

        if (data && method !== 'GET' && method !== 'HEAD') {
            opts.body = JSON.stringify(data);
        }

        return fetch(url, opts)
            .then(function (response) {
                return response.json().then(function (body) {
                    if (!response.ok) {
                        var err = body.error || body.message || 'Request failed';
                        throw { status: response.status, message: err, body: body };
                    }
                    return body;
                });
            })
            .catch(function (err) {
                if (err && err.status) {
                    throw err;
                }
                throw { status: 0, message: err.message || 'Network error', body: null };
            });
    }

    /**
     * Display an error message using layui.layer.
     */
    function showError(errorObj) {
        var msg = typeof errorObj === 'string' ? errorObj : (errorObj.message || 'An error occurred');
        layui.use('layer', function () {
            layui.layer.msg(msg, { icon: 2, time: 3000 });
        });
    }

    /**
     * Display a success message using layui.layer.
     */
    function showSuccess(message) {
        layui.use('layer', function () {
            layui.layer.msg(message, { icon: 1, time: 2000 });
        });
    }

    /**
     * Initialise sidebar navigation visibility based on permissions.
     *
     * Reads permissions from the global `window.USER_PERMISSIONS` array
     * (an array of permission strings like "dashboard.view"). Any nav item
     * whose data-permission attribute is not in the list will be hidden.
     */
    function initNavigation() {
        var permissions = window.USER_PERMISSIONS || [];

        // If no permissions data is present, show everything (dev mode)
        if (!permissions.length) {
            return;
        }

        var items = document.querySelectorAll('[data-permission]');
        for (var i = 0; i < items.length; i++) {
            var required = items[i].getAttribute('data-permission');
            if (permissions.indexOf(required) === -1) {
                items[i].style.display = 'none';
            }
        }
    }

    // Auto-initialise on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function () {
        initNavigation();
    });

    return {
        request: request,
        getCsrfToken: getCsrfToken,
        showError: showError,
        showSuccess: showSuccess,
        initNavigation: initNavigation
    };
})();
