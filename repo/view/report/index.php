<?php
$title = 'Reports - SiteOps';
$csrf_token = session('csrf_token') ?? '';
ob_start();
?>
<div class="layui-breadcrumb" style="margin-bottom: 15px;">
    <a href="/dashboard">Home</a>
    <a><cite>Reports</cite></a>
</div>

<div id="report-content">
    <div class="layui-tab layui-tab-brief" lay-filter="reportTabs">
        <ul class="layui-tab-title">
            <li class="layui-this">Definitions</li>
            <li>Run History</li>
        </ul>
        <div class="layui-tab-content">
            <!-- Definitions tab -->
            <div class="layui-tab-item layui-show">
                <div style="margin-bottom:15px;">
                    <button type="button" class="layui-btn" id="btn-new-definition">
                        <i class="layui-icon layui-icon-add-1"></i> New Definition
                    </button>
                </div>
                <table id="definitions-table" lay-filter="definitionsTable"></table>
            </div>

            <!-- Run History tab -->
            <div class="layui-tab-item">
                <div style="margin-bottom:15px;">
                    <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="btn-refresh-runs">
                        <i class="layui-icon layui-icon-refresh"></i> Refresh
                    </button>
                    <button type="button" class="layui-btn layui-btn-sm layui-btn-warm" id="btn-export-csv">
                        <i class="layui-icon layui-icon-export"></i> Export CSV
                    </button>
                </div>
                <table id="runs-table" lay-filter="runsTable"></table>
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

    // Definitions table
    table.render({
        elem: '#definitions-table',
        cols: [[
            { type: 'numbers', title: '#', width: 50 },
            { field: 'name', title: 'Report Name', minWidth: 180 },
            { field: 'description', title: 'Description', minWidth: 200 },
            { field: 'created_at', title: 'Created', width: 160 },
            { title: 'Actions', width: 260, toolbar: '#def-row-actions' }
        ]],
        data: [],
        page: true,
        limit: 20,
        text: { none: 'No report definitions found' }
    });

    // Row actions
    var defActionsHtml =
        '<div>' +
            '<a class="layui-btn layui-btn-xs" lay-event="edit"><i class="layui-icon layui-icon-edit"></i> Edit</a>' +
            '<a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="run"><i class="layui-icon layui-icon-triangle-r"></i> Run</a>' +
            '<a class="layui-btn layui-btn-xs layui-btn-warm" lay-event="schedule"><i class="layui-icon layui-icon-date"></i> Schedule</a>' +
        '</div>';
    var tpl = document.createElement('script');
    tpl.type = 'text/html'; tpl.id = 'def-row-actions'; tpl.innerHTML = defActionsHtml;
    document.body.appendChild(tpl);

    // Runs table
    var runsTableRendered = false;
    table.render({
        elem: '#runs-table',
        cols: [[
            { type: 'numbers', title: '#', width: 50 },
            { field: 'definition_id', title: 'Def ID', width: 80 },
            { field: 'status', title: 'Status', width: 110, templet: function (d) {
                var colors = { queued: '#FFB300', running: '#1E88E5', succeeded: '#43A047', failed: '#E53935' };
                var c = colors[d.status] || '#999';
                return '<span style="color:' + c + '; font-weight:600;">' + (d.status || '--') + '</span>';
            }},
            { field: 'created_at', title: 'Started', width: 160 },
            { field: 'completed_at', title: 'Completed', width: 160 },
            { title: 'Actions', width: 150, toolbar: '#run-row-actions' }
        ]],
        data: [],
        page: true,
        limit: 20,
        text: { none: 'No report runs found' }
    });

    var runActionsHtml = '<div><a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="download"><i class="layui-icon layui-icon-download-circle"></i> Download</a></div>';
    var tpl2 = document.createElement('script');
    tpl2.type = 'text/html'; tpl2.id = 'run-row-actions'; tpl2.innerHTML = runActionsHtml;
    document.body.appendChild(tpl2);

    function loadDefinitions() {
        SiteOps.request('GET', '/api/v1/reports/definitions')
            .then(function (res) {
                var data = res.data || res;
                table.reload('definitions-table', { data: data.items || [] });
            })
            .catch(function (err) { SiteOps.showError(err); });
    }

    function loadRuns() {
        SiteOps.request('GET', '/api/v1/reports/runs?per_page=50')
            .then(function (res) {
                var data = res.data || res;
                var items = data.items || data.data || [];
                if (!runsTableRendered) {
                    runsTableRendered = true;
                    table.render({
                        elem: '#runs-table',
                        cols: [[
                            { type: 'numbers', title: '#', width: 50 },
                            { field: 'definition_id', title: 'Def ID', width: 80 },
                            { field: 'status', title: 'Status', width: 110, templet: function (d) {
                                var colors = { queued: '#FFB300', running: '#1E88E5', succeeded: '#43A047', failed: '#E53935' };
                                var c = colors[d.status] || '#999';
                                return '<span style="color:' + c + '; font-weight:600;">' + (d.status || '--') + '</span>';
                            }},
                            { field: 'created_at', title: 'Started', width: 160 },
                            { field: 'completed_at', title: 'Completed', width: 160 },
                            { title: 'Actions', width: 150, toolbar: '#run-row-actions' }
                        ]],
                        data: items,
                        page: items.length > 20,
                        limit: 20,
                        text: { none: 'No report runs found' }
                    });
                } else {
                    table.reload('runs-table', { data: items });
                }
            })
            .catch(function (err) { SiteOps.showError(err); });
    }

    // New definition
    document.getElementById('btn-new-definition').addEventListener('click', function () {
        openDefinitionDialog(null);
    });

    function openDefinitionDialog(existing) {
        var isEdit = !!existing;
        var html =
            '<div style="padding:20px;">' +
                '<form class="layui-form" lay-filter="defForm">' +
                    '<div class="layui-form-item">' +
                        '<label class="layui-form-label">Name <span style="color:red;">*</span></label>' +
                        '<div class="layui-input-block"><input type="text" id="def-name" class="layui-input" value="' + (existing ? existing.name || '' : '') + '" lay-verify="required"></div>' +
                    '</div>' +
                    '<div class="layui-form-item">' +
                        '<label class="layui-form-label">Description</label>' +
                        '<div class="layui-input-block"><textarea id="def-description" class="layui-textarea">' + (existing ? existing.description || '' : '') + '</textarea></div>' +
                    '</div>' +
                    '<div class="layui-form-item">' +
                        '<label class="layui-form-label">Dimensions</label>' +
                        '<div class="layui-input-block"><input type="text" id="def-dimensions" class="layui-input" placeholder="Comma-separated (e.g., site, product, leader)" value="' + (existing && existing.dimensions ? existing.dimensions.join(', ') : '') + '"></div>' +
                    '</div>' +
                    '<div class="layui-form-item">' +
                        '<label class="layui-form-label">Filters</label>' +
                        '<div class="layui-input-block"><textarea id="def-filters" class="layui-textarea" placeholder="JSON filter object">' + (existing && existing.filters ? JSON.stringify(existing.filters) : '') + '</textarea></div>' +
                    '</div>' +
                    '<div class="layui-form-item">' +
                        '<label class="layui-form-label">Columns</label>' +
                        '<div class="layui-input-block"><input type="text" id="def-columns" class="layui-input" placeholder="Comma-separated column names" value="' + (existing && existing.columns ? existing.columns.join(', ') : '') + '"></div>' +
                    '</div>' +
                '</form>' +
            '</div>';

        layer.open({
            type: 1,
            title: isEdit ? 'Edit Report Definition' : 'New Report Definition',
            content: html,
            area: ['550px', '480px'],
            btn: ['Save', 'Cancel'],
            yes: function (index) {
                var name = document.getElementById('def-name').value.trim();
                if (!name) { layer.msg('Name is required.', { icon: 0 }); return; }

                var dimensions = document.getElementById('def-dimensions').value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
                var columns = document.getElementById('def-columns').value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
                var filtersStr = document.getElementById('def-filters').value.trim();
                var filters = {};
                if (filtersStr) { try { filters = JSON.parse(filtersStr); } catch (e) { layer.msg('Invalid JSON in filters.', { icon: 0 }); return; } }

                var payload = {
                    name: name,
                    description: document.getElementById('def-description').value.trim(),
                    dimensions: dimensions,
                    filters: filters,
                    columns: columns
                };

                var req = isEdit
                    ? SiteOps.request('PATCH', '/api/v1/reports/definitions/' + existing.id, payload)
                    : SiteOps.request('POST', '/api/v1/reports/definitions', payload);

                req.then(function (res) {
                    layer.close(index);
                    SiteOps.showSuccess(isEdit ? 'Definition updated' : 'Definition created');
                    loadDefinitions();
                }).catch(function (err) { SiteOps.showError(err); });
            }
        });
    }

    // Definition row actions
    table.on('tool(definitionsTable)', function (obj) {
        if (obj.event === 'edit') {
            // Load full definition
            SiteOps.request('GET', '/api/v1/reports/definitions/' + obj.data.id)
                .then(function (res) { openDefinitionDialog(res.data || res); })
                .catch(function (err) { SiteOps.showError(err); });
        } else if (obj.event === 'run') {
            if (!confirm('Run this report now?')) return;
            SiteOps.request('POST', '/api/v1/reports/definitions/' + obj.data.id + '/run', {})
                .then(function (res) {
                    var data = res.data || res;
                    layer.msg(data.message || 'Report execution queued', { icon: 1, time: 3000 });
                    loadRuns();
                })
                .catch(function (err) { SiteOps.showError(err); });
        } else if (obj.event === 'schedule') {
            openScheduleDialog(obj.data.id);
        }
    });

    function openScheduleDialog(defId) {
        var html =
            '<div style="padding:20px;">' +
                '<div class="layui-form" lay-filter="scheduleForm">' +
                    '<div class="layui-form-item">' +
                        '<label class="layui-form-label">Cadence</label>' +
                        '<div class="layui-input-block">' +
                            '<select id="schedule-cadence">' +
                                '<option value="daily">Daily</option>' +
                                '<option value="weekly">Weekly</option>' +
                                '<option value="monthly">Monthly</option>' +
                            '</select>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

        layer.open({
            type: 1,
            title: 'Schedule Report',
            content: html,
            area: ['400px', '220px'],
            btn: ['Save Schedule', 'Cancel'],
            success: function () { form.render('select'); },
            yes: function (index) {
                var cadence = document.getElementById('schedule-cadence').value;
                SiteOps.request('POST', '/api/v1/reports/definitions/' + defId + '/schedule', { cadence: cadence })
                    .then(function (res) {
                        layer.close(index);
                        SiteOps.showSuccess('Schedule created');
                    })
                    .catch(function (err) { SiteOps.showError(err); });
            }
        });
    }

    // Run row actions
    table.on('tool(runsTable)', function (obj) {
        if (obj.event === 'download') {
            if (obj.data.status !== 'succeeded') {
                layer.msg('Only completed reports can be downloaded.', { icon: 0 });
                return;
            }
            window.open('/api/v1/reports/runs/' + obj.data.id + '/download', '_blank');
        }
    });

    document.getElementById('btn-refresh-runs').addEventListener('click', loadRuns);

    // CSV Export — exports orders data
    document.getElementById('btn-export-csv').addEventListener('click', function () {
        if (!confirm('Export orders data as CSV?')) return;
        var loadIdx = layer.load(1);
        // Use fetch with blob handling for real file download
        fetch('/api/v1/exports/csv', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': SiteOps.getCsrfToken()
            },
            body: JSON.stringify({ type: 'orders', filters: {} })
        })
        .then(function (response) {
            layer.close(loadIdx);
            if (!response.ok) {
                return response.json().then(function (err) {
                    SiteOps.showError(err.error ? err.error.message : 'Export failed');
                });
            }
            return response.blob().then(function (blob) {
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'export_orders_' + new Date().toISOString().slice(0,10) + '.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                layer.msg('CSV downloaded', { icon: 1, time: 2000 });
            });
        })
        .catch(function (err) {
            layer.close(loadIdx);
            SiteOps.showError('Download failed');
        });
    });

    // Tab change
    element.on('tab(reportTabs)', function (data) {
        if (data.index === 1) loadRuns();
    });

    loadDefinitions();
    loadRuns();
});
</script>
<?php
$scripts = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>
