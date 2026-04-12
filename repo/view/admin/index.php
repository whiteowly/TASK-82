<?php
$title = 'Administration - SiteOps';
$csrf_token = session('csrf_token') ?? '';
ob_start();
?>
<div class="layui-breadcrumb" style="margin-bottom: 15px;">
    <a href="/dashboard">Home</a>
    <a><cite>Administration</cite></a>
</div>

<div id="admin-content">
    <div class="layui-tab layui-tab-brief" lay-filter="adminTabs">
        <ul class="layui-tab-title">
            <li class="layui-this">Users</li>
            <li>Roles</li>
            <li>Permissions</li>
        </ul>
        <div class="layui-tab-content">
            <!-- Users tab -->
            <div class="layui-tab-item layui-show">
                <div style="margin-bottom:15px;">
                    <button type="button" class="layui-btn" id="btn-create-user">
                        <i class="layui-icon layui-icon-add-1"></i> Create User
                    </button>
                </div>
                <table id="admin-users-table" lay-filter="adminUsersTable"></table>
            </div>

            <!-- Roles tab -->
            <div class="layui-tab-item">
                <table id="admin-roles-table" lay-filter="adminRolesTable"></table>
            </div>

            <!-- Permissions tab -->
            <div class="layui-tab-item">
                <table id="admin-permissions-table" lay-filter="adminPermissionsTable"></table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
ob_start();
?>
<script>
layui.use(['element', 'table', 'layer', 'form'], function () {
    'use strict';
    var element = layui.element;
    var table = layui.table;
    var layer = layui.layer;
    var form = layui.form;

    var rolesCache = [];
    var permissionsCache = [];

    // Users table
    table.render({
        elem: '#admin-users-table',
        cols: [[
            { type: 'numbers', title: '#', width: 50 },
            { field: 'id', title: 'ID', width: 60 },
            { field: 'username', title: 'Username', minWidth: 140 },
            { field: 'display_name', title: 'Display Name', minWidth: 140 },
            { field: 'status', title: 'Status', width: 90, templet: function (d) {
                var c = d.status === 'active' ? '#43A047' : '#999';
                return '<span style="color:' + c + '; font-weight:600;">' + (d.status || '--') + '</span>';
            }},
            { field: 'created_at', title: 'Created', width: 160 },
            { title: 'Actions', width: 280, toolbar: '#user-row-actions' }
        ]],
        data: [],
        page: true,
        limit: 20,
        text: { none: 'No users found. Use the API or Create User button to add users.' }
    });

    var userActionsHtml =
        '<div>' +
            '<a class="layui-btn layui-btn-xs" lay-event="edit"><i class="layui-icon layui-icon-edit"></i> Edit</a>' +
            '<a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="roles"><i class="layui-icon layui-icon-group"></i> Roles</a>' +
            '<a class="layui-btn layui-btn-xs layui-btn-warm" lay-event="scopes"><i class="layui-icon layui-icon-website"></i> Sites</a>' +
        '</div>';
    var tpl1 = document.createElement('script');
    tpl1.type = 'text/html'; tpl1.id = 'user-row-actions'; tpl1.innerHTML = userActionsHtml;
    document.body.appendChild(tpl1);

    // Roles table (read-only)
    table.render({
        elem: '#admin-roles-table',
        cols: [[
            { type: 'numbers', title: '#', width: 50 },
            { field: 'id', title: 'ID', width: 60 },
            { field: 'name', title: 'Role Name', minWidth: 180 },
            { field: 'description', title: 'Description', minWidth: 250 },
            { field: 'permissions', title: 'Permissions', minWidth: 200, templet: function (d) {
                var perms = d.permissions || [];
                if (typeof perms === 'string') { try { perms = JSON.parse(perms); } catch (e) { perms = []; } }
                return perms.join(', ') || '--';
            }}
        ]],
        data: [],
        page: false,
        limit: 50,
        text: { none: 'No roles found' }
    });

    // Permissions table (read-only)
    table.render({
        elem: '#admin-permissions-table',
        cols: [[
            { type: 'numbers', title: '#', width: 50 },
            { field: 'id', title: 'ID', width: 60 },
            { field: 'name', title: 'Permission', minWidth: 200 },
            { field: 'description', title: 'Description', minWidth: 300 }
        ]],
        data: [],
        page: false,
        limit: 100,
        text: { none: 'No permissions found' }
    });

    function loadRoles() {
        SiteOps.request('GET', '/api/v1/rbac/roles')
            .then(function (res) {
                var data = res.data || res;
                rolesCache = data.items || [];
                table.reload('admin-roles-table', { data: rolesCache });
            })
            .catch(function (err) { SiteOps.showError(err); });
    }

    function loadPermissions() {
        SiteOps.request('GET', '/api/v1/rbac/permissions')
            .then(function (res) {
                var data = res.data || res;
                permissionsCache = data.items || [];
                table.reload('admin-permissions-table', { data: permissionsCache });
            })
            .catch(function (err) { SiteOps.showError(err); });
    }

    // Create user
    document.getElementById('btn-create-user').addEventListener('click', function () {
        var html =
            '<div style="padding:20px;">' +
                '<form class="layui-form">' +
                    '<div class="layui-form-item">' +
                        '<label class="layui-form-label">Username <span style="color:red;">*</span></label>' +
                        '<div class="layui-input-block"><input type="text" id="cu-username" class="layui-input" lay-verify="required"></div>' +
                    '</div>' +
                    '<div class="layui-form-item">' +
                        '<label class="layui-form-label">Password <span style="color:red;">*</span></label>' +
                        '<div class="layui-input-block"><input type="password" id="cu-password" class="layui-input" lay-verify="required"></div>' +
                    '</div>' +
                    '<div class="layui-form-item">' +
                        '<label class="layui-form-label">Display Name <span style="color:red;">*</span></label>' +
                        '<div class="layui-input-block"><input type="text" id="cu-display-name" class="layui-input" lay-verify="required"></div>' +
                    '</div>' +
                    '<div class="layui-form-item">' +
                        '<label class="layui-form-label">Status</label>' +
                        '<div class="layui-input-block">' +
                            '<select id="cu-status"><option value="active">Active</option><option value="disabled">Disabled</option></select>' +
                        '</div>' +
                    '</div>' +
                '</form>' +
            '</div>';

        layer.open({
            type: 1,
            title: 'Create User',
            content: html,
            area: ['480px', '380px'],
            btn: ['Create', 'Cancel'],
            success: function () { form.render('select'); },
            yes: function (index) {
                var username = document.getElementById('cu-username').value.trim();
                var password = document.getElementById('cu-password').value;
                var displayName = document.getElementById('cu-display-name').value.trim();
                var status = document.getElementById('cu-status').value;

                if (!username || !password || !displayName) {
                    layer.msg('All required fields must be filled.', { icon: 0 });
                    return;
                }

                SiteOps.request('POST', '/api/v1/admin/users', {
                    username: username,
                    password: password,
                    display_name: displayName,
                    status: status
                })
                .then(function (res) {
                    layer.close(index);
                    SiteOps.showSuccess('User created');
                })
                .catch(function (err) { SiteOps.showError(err); });
            }
        });
    });

    // User row actions
    table.on('tool(adminUsersTable)', function (obj) {
        var user = obj.data;

        if (obj.event === 'edit') {
            var html =
                '<div style="padding:20px;">' +
                    '<form class="layui-form">' +
                        '<div class="layui-form-item">' +
                            '<label class="layui-form-label">Display Name</label>' +
                            '<div class="layui-input-block"><input type="text" id="eu-display-name" class="layui-input" value="' + (user.display_name || '') + '"></div>' +
                        '</div>' +
                        '<div class="layui-form-item">' +
                            '<label class="layui-form-label">Status</label>' +
                            '<div class="layui-input-block">' +
                                '<select id="eu-status">' +
                                    '<option value="active"' + (user.status === 'active' ? ' selected' : '') + '>Active</option>' +
                                    '<option value="disabled"' + (user.status === 'disabled' ? ' selected' : '') + '>Disabled</option>' +
                                '</select>' +
                            '</div>' +
                        '</div>' +
                        '<div class="layui-form-item">' +
                            '<label class="layui-form-label">New Password</label>' +
                            '<div class="layui-input-block"><input type="password" id="eu-password" class="layui-input" placeholder="Leave blank to keep current"></div>' +
                        '</div>' +
                    '</form>' +
                '</div>';

            layer.open({
                type: 1,
                title: 'Edit User: ' + user.username,
                content: html,
                area: ['480px', '320px'],
                btn: ['Save', 'Cancel'],
                success: function () { form.render('select'); },
                yes: function (index) {
                    var payload = {
                        display_name: document.getElementById('eu-display-name').value.trim(),
                        status: document.getElementById('eu-status').value
                    };
                    var pwd = document.getElementById('eu-password').value;
                    if (pwd) payload.password = pwd;

                    SiteOps.request('PATCH', '/api/v1/admin/users/' + user.id, payload)
                        .then(function () {
                            layer.close(index);
                            SiteOps.showSuccess('User updated');
                        })
                        .catch(function (err) { SiteOps.showError(err); });
                }
            });
        }

        if (obj.event === 'roles') {
            // Build checkboxes from cached roles
            var html = '<div style="padding:20px;">';
            if (!rolesCache.length) {
                html += '<p style="color:#999;">No roles available. Load the Roles tab first.</p>';
            } else {
                html += '<form class="layui-form" lay-filter="roleAssignForm">';
                rolesCache.forEach(function (role) {
                    html += '<div class="layui-form-item"><input type="checkbox" name="role_' + role.id + '" title="' + role.name + '" class="role-cb" data-id="' + role.id + '"></div>';
                });
                html += '</form>';
            }
            html += '</div>';

            layer.open({
                type: 1,
                title: 'Assign Roles: ' + user.username,
                content: html,
                area: ['400px', '350px'],
                btn: ['Save', 'Cancel'],
                success: function () { form.render('checkbox'); },
                yes: function (index) {
                    var roleIds = [];
                    document.querySelectorAll('.role-cb').forEach(function (cb) {
                        if (cb.checked) roleIds.push(parseInt(cb.getAttribute('data-id')));
                    });

                    SiteOps.request('POST', '/api/v1/admin/users/' + user.id + '/roles', { role_ids: roleIds })
                        .then(function (res) {
                            layer.close(index);
                            var data = res.data || res;
                            SiteOps.showSuccess('Roles assigned: ' + (data.roles || []).join(', '));
                        })
                        .catch(function (err) { SiteOps.showError(err); });
                }
            });
        }

        if (obj.event === 'scopes') {
            layer.prompt({
                formType: 0,
                title: 'Assign Site Scopes for ' + user.username + ' (comma-separated IDs)',
                value: '',
                area: ['400px', '50px']
            }, function (value, index) {
                layer.close(index);
                var siteIds = value.split(',').map(function (s) { return parseInt(s.trim()); }).filter(function (n) { return !isNaN(n) && n > 0; });

                SiteOps.request('POST', '/api/v1/admin/users/' + user.id + '/site-scopes', { site_ids: siteIds })
                    .then(function (res) {
                        var data = res.data || res;
                        SiteOps.showSuccess('Site scopes assigned: ' + (data.site_scopes || []).join(', '));
                    })
                    .catch(function (err) { SiteOps.showError(err); });
            });
        }
    });

    // Tab switching
    element.on('tab(adminTabs)', function () {
        if (this.index === 1) loadRoles();
        if (this.index === 2) loadPermissions();
    });

    // Initial loads
    loadRoles();
    loadPermissions();
});
</script>
<?php
$scripts = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>
