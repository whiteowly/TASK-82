<?php
$title = 'Audit Log - SiteOps';
$csrf_token = session('csrf_token') ?? '';
ob_start();
?>
<div class="layui-breadcrumb" style="margin-bottom: 15px;">
    <a href="/dashboard">Home</a>
    <a><cite>Audit Log</cite></a>
</div>

<div id="audit-content">
    <!-- Filter bar -->
    <div class="audit-filters" style="margin-bottom: 20px;">
        <div class="layui-form layui-form-pane" lay-filter="auditFilterForm">
            <div class="layui-inline">
                <label class="layui-form-label">Event Type</label>
                <div class="layui-input-inline" style="width:180px;">
                    <select id="audit-filter-event-type" lay-filter="auditEventType">
                        <option value="">All Events</option>
                        <option value="user.create">user.create</option>
                        <option value="user.update">user.update</option>
                        <option value="user.assign_roles">user.assign_roles</option>
                        <option value="user.assign_site_scopes">user.assign_site_scopes</option>
                        <option value="recipe.create">recipe.create</option>
                        <option value="recipe.update">recipe.update</option>
                        <option value="recipe.approve">recipe.approve</option>
                        <option value="recipe.reject">recipe.reject</option>
                        <option value="settlement.submit">settlement.submit</option>
                        <option value="settlement.approve">settlement.approve</option>
                        <option value="settlement.reverse">settlement.reverse</option>
                        <option value="report.download">report.download</option>
                        <option value="report.export_csv">report.export_csv</option>
                    </select>
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">Site</label>
                <div class="layui-input-inline" style="width:100px;">
                    <input type="number" class="layui-input" id="audit-filter-site" placeholder="Site ID" min="1">
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">Actor</label>
                <div class="layui-input-inline" style="width:100px;">
                    <input type="number" class="layui-input" id="audit-filter-actor" placeholder="User ID" min="1">
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">Target Type</label>
                <div class="layui-input-inline" style="width:160px;">
                    <select id="audit-filter-target-type" lay-filter="auditTargetType">
                        <option value="">All Types</option>
                        <option value="user">user</option>
                        <option value="recipe">recipe</option>
                        <option value="recipe_version">recipe_version</option>
                        <option value="settlement_statement">settlement_statement</option>
                        <option value="report_run">report_run</option>
                        <option value="csv_export">csv_export</option>
                    </select>
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">Date Range</label>
                <div class="layui-input-inline" style="width:240px;">
                    <input type="text" class="layui-input" id="audit-filter-date" placeholder="Select date range" readonly>
                </div>
            </div>
            <div class="layui-inline">
                <button type="button" class="layui-btn" id="btn-audit-search">
                    <i class="layui-icon layui-icon-search"></i> Search
                </button>
                <button type="button" class="layui-btn layui-btn-primary" id="btn-audit-reset">Reset</button>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="layui-tab layui-tab-brief" lay-filter="auditTabs">
        <ul class="layui-tab-title">
            <li class="layui-this">All Logs</li>
            <li>Exports</li>
            <li>Approvals</li>
            <li>Permission Changes</li>
        </ul>
        <div class="layui-tab-content">
            <div class="layui-tab-item layui-show">
                <table id="audit-log-table" lay-filter="auditLogTable"></table>
            </div>
            <div class="layui-tab-item">
                <table id="audit-exports-table" lay-filter="auditExportsTable"></table>
            </div>
            <div class="layui-tab-item">
                <table id="audit-approvals-table" lay-filter="auditApprovalsTable"></table>
            </div>
            <div class="layui-tab-item">
                <table id="audit-permissions-table" lay-filter="auditPermissionsTable"></table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
ob_start();
?>
<script>
layui.use(['laydate', 'element', 'form', 'table', 'layer'], function () {
    'use strict';
    var laydate = layui.laydate;
    var element = layui.element;
    var form = layui.form;
    var table = layui.table;
    var layer = layui.layer;

    var dateRange = '';

    laydate.render({
        elem: '#audit-filter-date',
        range: true,
        done: function (value) { dateRange = value; }
    });

    // All logs table
    table.render({
        elem: '#audit-log-table',
        cols: [[
            { type: 'numbers', title: '#', width: 50 },
            { field: 'created_at', title: 'Time', width: 170 },
            { field: 'actor_id', title: 'Actor', width: 80 },
            { field: 'event_type', title: 'Event', width: 180 },
            { field: 'target_type', title: 'Target', width: 140 },
            { field: 'target_id', title: 'Target ID', width: 80 },
            { field: 'site_id', title: 'Site', width: 60 },
            { field: 'request_id', title: 'Request ID', minWidth: 140 },
            { title: 'Detail', width: 80, toolbar: '#audit-row-detail' }
        ]],
        data: [],
        page: true,
        limit: 50,
        text: { none: 'No audit log entries found' }
    });

    var detailHtml = '<div><a class="layui-btn layui-btn-xs" lay-event="detail"><i class="layui-icon layui-icon-search"></i></a></div>';
    var tpl = document.createElement('script');
    tpl.type = 'text/html'; tpl.id = 'audit-row-detail'; tpl.innerHTML = detailHtml;
    document.body.appendChild(tpl);

    // Exports table
    table.render({
        elem: '#audit-exports-table',
        cols: [[
            { type: 'numbers', title: '#', width: 50 },
            { field: 'created_at', title: 'Time', width: 170 },
            { field: 'actor_id', title: 'Actor', width: 80 },
            { field: 'export_type', title: 'Export Type', width: 150 },
            { field: 'record_count', title: 'Records', width: 90 },
            { field: 'site_id', title: 'Site', width: 60 },
            { field: 'reason', title: 'Reason', minWidth: 200 },
            { field: 'request_id', title: 'Request ID', minWidth: 140 }
        ]],
        data: [],
        page: true,
        limit: 50,
        text: { none: 'No export records found' }
    });

    // Approvals table
    table.render({
        elem: '#audit-approvals-table',
        cols: [[
            { type: 'numbers', title: '#', width: 50 },
            { field: 'created_at', title: 'Time', width: 170 },
            { field: 'approver_id', title: 'Approver', width: 90 },
            { field: 'statement_id', title: 'Statement', width: 90 },
            { field: 'action', title: 'Action', width: 120 },
            { field: 'notes', title: 'Notes', minWidth: 200 }
        ]],
        data: [],
        page: true,
        limit: 50,
        text: { none: 'No approval records found' }
    });

    // Permission changes table
    table.render({
        elem: '#audit-permissions-table',
        cols: [[
            { type: 'numbers', title: '#', width: 50 },
            { field: 'created_at', title: 'Time', width: 170 },
            { field: 'actor_id', title: 'Actor', width: 80 },
            { field: 'target_user_id', title: 'Target User', width: 100 },
            { field: 'change_type', title: 'Change Type', width: 160 },
            { field: 'old_value', title: 'Old Value', minWidth: 150 },
            { field: 'new_value', title: 'New Value', minWidth: 150 },
            { field: 'request_id', title: 'Request ID', minWidth: 140 }
        ]],
        data: [],
        page: true,
        limit: 50,
        text: { none: 'No permission change records found' }
    });

    function loadAuditLogs() {
        var params = ['per_page=50'];
        var eventType = document.getElementById('audit-filter-event-type').value;
        var site = document.getElementById('audit-filter-site').value;
        var actor = document.getElementById('audit-filter-actor').value;
        var targetType = document.getElementById('audit-filter-target-type').value;

        if (eventType) params.push('event_type=' + encodeURIComponent(eventType));
        if (site) params.push('site=' + encodeURIComponent(site));
        if (actor) params.push('actor=' + encodeURIComponent(actor));
        if (targetType) params.push('target_type=' + encodeURIComponent(targetType));

        SiteOps.request('GET', '/api/v1/audit/logs?' + params.join('&'))
            .then(function (res) {
                var data = res.data || res;
                table.reload('audit-log-table', { data: data.items || [] });
            })
            .catch(function (err) { SiteOps.showError(err); });
    }

    function loadExports() {
        SiteOps.request('GET', '/api/v1/audit/exports?per_page=50')
            .then(function (res) {
                var data = res.data || res;
                table.reload('audit-exports-table', { data: data.items || [] });
            })
            .catch(function (err) { SiteOps.showError(err); });
    }

    function loadApprovals() {
        SiteOps.request('GET', '/api/v1/audit/approvals?per_page=50')
            .then(function (res) {
                var data = res.data || res;
                table.reload('audit-approvals-table', { data: data.items || [] });
            })
            .catch(function (err) { SiteOps.showError(err); });
    }

    function loadPermissionChanges() {
        SiteOps.request('GET', '/api/v1/audit/permission-changes?per_page=50')
            .then(function (res) {
                var data = res.data || res;
                table.reload('audit-permissions-table', { data: data.items || [] });
            })
            .catch(function (err) { SiteOps.showError(err); });
    }

    // Row detail click
    table.on('tool(auditLogTable)', function (obj) {
        if (obj.event === 'detail') {
            var loadIdx = layer.load();
            SiteOps.request('GET', '/api/v1/audit/logs/' + obj.data.id)
                .then(function (res) {
                    layer.close(loadIdx);
                    var entry = res.data || res;
                    var html = '<pre style="padding:20px; max-height:400px; overflow:auto; white-space:pre-wrap; word-break:break-all;">' +
                        JSON.stringify(entry, null, 2) + '</pre>';
                    layer.open({
                        type: 1,
                        title: 'Audit Entry #' + obj.data.id,
                        content: html,
                        area: ['600px', '480px'],
                        shadeClose: true
                    });
                })
                .catch(function (err) {
                    layer.close(loadIdx);
                    SiteOps.showError(err);
                });
        }
    });

    // Search / Reset
    document.getElementById('btn-audit-search').addEventListener('click', loadAuditLogs);
    document.getElementById('btn-audit-reset').addEventListener('click', function () {
        document.getElementById('audit-filter-event-type').value = '';
        document.getElementById('audit-filter-site').value = '';
        document.getElementById('audit-filter-actor').value = '';
        document.getElementById('audit-filter-target-type').value = '';
        document.getElementById('audit-filter-date').value = '';
        dateRange = '';
        form.render('select');
        loadAuditLogs();
    });

    // Tab switching
    element.on('tab(auditTabs)', function () {
        switch (this.index) {
            case 0: loadAuditLogs(); break;
            case 1: loadExports(); break;
            case 2: loadApprovals(); break;
            case 3: loadPermissionChanges(); break;
        }
    });

    loadAuditLogs();
});
</script>
<?php
$scripts = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>
