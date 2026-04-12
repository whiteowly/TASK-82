/**
 * SiteOps - Report management page
 */
layui.use(['table', 'form', 'layer', 'upload', 'laydate', 'element'], function () {
    'use strict';

    var table = layui.table;
    var form = layui.form;
    var layer = layui.layer;
    var element = layui.element;
    var laydate = layui.laydate;

    // --- Tab switching ---
    element.on('tab(reportTabs)', function (data) {
        if (data.index === 0) {
            loadDefinitions();
        } else if (data.index === 1) {
            loadRuns();
        }
    });

    // =====================
    // DEFINITIONS TAB
    // =====================

    function loadDefinitions() {
        var loadingIdx = layer.load(1);
        SiteOps.request('GET', '/api/v1/reports/definitions')
            .then(function (res) {
                layer.close(loadingIdx);
                var definitions = res.data || res;
                if (!Array.isArray(definitions)) definitions = definitions.items || [];

                table.render({
                    elem: '#definitions-table',
                    cols: [[
                        { field: 'id', title: 'ID', width: 80, sort: true },
                        { field: 'name', title: 'Name', minWidth: 200 },
                        { field: 'type', title: 'Type', width: 120 },
                        { field: 'schedule', title: 'Schedule', width: 150, templet: function (d) {
                            return d.schedule || d.cron_expression || '<span style="color:#999;">None</span>';
                        }},
                        { field: 'created_at', title: 'Created', width: 170, sort: true },
                        { title: 'Actions', width: 250, toolbar: '#defActionsTpl', fixed: 'right' }
                    ]],
                    data: definitions,
                    page: definitions.length > 20,
                    limit: 20,
                    text: { none: 'No report definitions found. Click "New Definition" to create one.' }
                });
            })
            .catch(function (err) {
                layer.close(loadingIdx);
                SiteOps.showError(err);
            });
    }

    // Toolbar templates
    if (!document.getElementById('defActionsTpl')) {
        var tpl = document.createElement('script');
        tpl.type = 'text/html';
        tpl.id = 'defActionsTpl';
        tpl.innerHTML = '<a class="layui-btn layui-btn-xs" lay-event="run">Run</a>'
            + '<a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="schedule">Schedule</a>'
            + '<a class="layui-btn layui-btn-xs layui-btn-warm" lay-event="edit">Edit</a>';
        document.body.appendChild(tpl);
    }

    if (!document.getElementById('runActionsTpl')) {
        var tpl2 = document.createElement('script');
        tpl2.type = 'text/html';
        tpl2.id = 'runActionsTpl';
        tpl2.innerHTML = '{{# if(d.status === "completed" || d.status === "done") { }}'
            + '<a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="download">Download</a>'
            + '{{# } else { }}'
            + '<span class="layui-badge layui-bg-gray">{{ d.status }}</span>'
            + '{{# } }}';
        document.body.appendChild(tpl2);
    }

    // Handle definition table events
    table.on('tool(definitionsTable)', function (obj) {
        var data = obj.data;
        switch (obj.event) {
            case 'run':
                runReport(data.id);
                break;
            case 'schedule':
                openScheduleDialog(data);
                break;
            case 'edit':
                openDefinitionForm(data);
                break;
        }
    });

    // --- Run report ---
    function runReport(definitionId) {
        layer.confirm('Run this report now?', {
            title: 'Confirm Run',
            btn: ['Run', 'Cancel']
        }, function (confirmIdx) {
            layer.close(confirmIdx);
            var loadingIdx = layer.load(1);
            SiteOps.request('POST', '/api/v1/reports/definitions/' + definitionId + '/run', {})
                .then(function (res) {
                    layer.close(loadingIdx);
                    SiteOps.showSuccess('Report run started.');
                })
                .catch(function (err) {
                    layer.close(loadingIdx);
                    SiteOps.showError(err);
                });
        });
    }

    // --- Schedule dialog ---
    function openScheduleDialog(definition) {
        var content = '<div style="padding:20px;">'
            + '<div class="layui-form" lay-filter="scheduleForm">'
            + '<div class="layui-form-item">'
            + '<label class="layui-form-label">Cron Expression</label>'
            + '<div class="layui-input-block">'
            + '<input type="text" id="schedule-cron" class="layui-input" value="' + escapeHtml(definition.cron_expression || definition.schedule || '') + '" placeholder="e.g., 0 8 * * 1 (every Monday at 8am)">'
            + '</div></div>'
            + '<div class="layui-form-item">'
            + '<label class="layui-form-label">Active</label>'
            + '<div class="layui-input-block">'
            + '<input type="checkbox" id="schedule-active" lay-skin="switch" lay-text="Yes|No" ' + (definition.schedule_active !== false ? 'checked' : '') + '>'
            + '</div></div>'
            + '</div></div>';

        layer.open({
            type: 1,
            title: 'Schedule Report: ' + escapeHtml(definition.name || ''),
            area: ['500px', '280px'],
            content: content,
            btn: ['Save Schedule', 'Cancel'],
            yes: function (index) {
                var cron = document.getElementById('schedule-cron').value.trim();
                if (!cron) {
                    layer.msg('Please enter a cron expression.', { icon: 0 });
                    return;
                }
                var active = document.getElementById('schedule-active').checked;
                var loadingIdx = layer.load(1);
                SiteOps.request('POST', '/api/v1/reports/definitions/' + definition.id + '/schedule', {
                    cron_expression: cron,
                    active: active
                })
                    .then(function () {
                        layer.close(loadingIdx);
                        layer.close(index);
                        SiteOps.showSuccess('Schedule saved.');
                        loadDefinitions();
                    })
                    .catch(function (err) {
                        layer.close(loadingIdx);
                        SiteOps.showError(err);
                    });
            }
        });
    }

    // --- New / Edit definition form ---
    var newDefBtn = document.getElementById('btn-new-definition');
    if (newDefBtn) {
        newDefBtn.addEventListener('click', function () {
            openDefinitionForm(null);
        });
    }

    function openDefinitionForm(existing) {
        var isEdit = !!existing;
        var title = isEdit ? 'Edit Definition' : 'New Report Definition';

        var content = '<div style="padding:20px;">'
            + '<div class="layui-form" lay-filter="defForm">'
            + '<div class="layui-form-item">'
            + '<label class="layui-form-label">Name *</label>'
            + '<div class="layui-input-block">'
            + '<input type="text" id="def-name" class="layui-input" value="' + escapeHtml((existing && existing.name) || '') + '" placeholder="Report name">'
            + '</div></div>'
            + '<div class="layui-form-item">'
            + '<label class="layui-form-label">Type</label>'
            + '<div class="layui-input-block">'
            + '<select id="def-type">'
            + '<option value="summary"' + (existing && existing.type === 'summary' ? ' selected' : '') + '>Summary</option>'
            + '<option value="detailed"' + (existing && existing.type === 'detailed' ? ' selected' : '') + '>Detailed</option>'
            + '<option value="financial"' + (existing && existing.type === 'financial' ? ' selected' : '') + '>Financial</option>'
            + '<option value="custom"' + (existing && existing.type === 'custom' ? ' selected' : '') + '>Custom</option>'
            + '</select>'
            + '</div></div>'
            + '<div class="layui-form-item layui-form-text">'
            + '<label class="layui-form-label">Description</label>'
            + '<div class="layui-input-block">'
            + '<textarea id="def-description" class="layui-textarea" placeholder="Report description">' + escapeHtml((existing && existing.description) || '') + '</textarea>'
            + '</div></div>'
            + '<div class="layui-form-item layui-form-text">'
            + '<label class="layui-form-label">Parameters (JSON)</label>'
            + '<div class="layui-input-block">'
            + '<textarea id="def-parameters" class="layui-textarea" placeholder=\'{"key": "value"}\'>' + escapeHtml((existing && existing.parameters) ? JSON.stringify(existing.parameters) : '') + '</textarea>'
            + '</div></div>'
            + '</div></div>';

        layer.open({
            type: 1,
            title: title,
            area: ['600px', '480px'],
            content: content,
            btn: ['Save', 'Cancel'],
            yes: function (index) {
                var name = document.getElementById('def-name').value.trim();
                if (!name) {
                    layer.msg('Name is required.', { icon: 0 });
                    return;
                }

                var params = {};
                var paramsStr = document.getElementById('def-parameters').value.trim();
                if (paramsStr) {
                    try {
                        params = JSON.parse(paramsStr);
                    } catch (e) {
                        layer.msg('Invalid JSON in Parameters field.', { icon: 0 });
                        return;
                    }
                }

                var payload = {
                    name: name,
                    type: document.getElementById('def-type').value,
                    description: document.getElementById('def-description').value.trim(),
                    parameters: params
                };

                var loadingIdx = layer.load(1);

                if (isEdit) {
                    SiteOps.request('PUT', '/api/v1/reports/definitions/' + existing.id, payload)
                        .then(function () {
                            layer.close(loadingIdx);
                            layer.close(index);
                            SiteOps.showSuccess('Definition updated.');
                            loadDefinitions();
                        })
                        .catch(function (err) {
                            layer.close(loadingIdx);
                            SiteOps.showError(err);
                        });
                } else {
                    SiteOps.request('POST', '/api/v1/reports/definitions', payload)
                        .then(function () {
                            layer.close(loadingIdx);
                            layer.close(index);
                            SiteOps.showSuccess('Definition created.');
                            loadDefinitions();
                        })
                        .catch(function (err) {
                            layer.close(loadingIdx);
                            SiteOps.showError(err);
                        });
                }
            }
        });
    }

    // =====================
    // RUNS TAB
    // =====================

    function loadRuns() {
        var loadingIdx = layer.load(1);
        SiteOps.request('GET', '/api/v1/reports/runs')
            .then(function (res) {
                layer.close(loadingIdx);
                var runs = res.data || res;
                if (!Array.isArray(runs)) runs = runs.items || [];

                table.render({
                    elem: '#runs-table',
                    cols: [[
                        { field: 'id', title: 'Run ID', width: 80, sort: true },
                        { field: 'definition_name', title: 'Report', minWidth: 180, templet: function (d) {
                            return escapeHtml(d.definition_name || d.name || d.report_name || '');
                        }},
                        { field: 'status', title: 'Status', width: 120, templet: function (d) {
                            var colors = { completed: 'layui-bg-green', running: 'layui-bg-blue', pending: 'layui-bg-gray', failed: 'layui-bg-red', done: 'layui-bg-green' };
                            var cls = colors[d.status] || '';
                            return '<span class="layui-badge ' + cls + '">' + escapeHtml(d.status || 'unknown') + '</span>';
                        }},
                        { field: 'started_at', title: 'Started', width: 170, sort: true },
                        { field: 'completed_at', title: 'Completed', width: 170 },
                        { title: 'Actions', width: 120, toolbar: '#runActionsTpl', fixed: 'right' }
                    ]],
                    data: runs,
                    page: runs.length > 20,
                    limit: 20,
                    text: { none: 'No report runs found.' }
                });
            })
            .catch(function (err) {
                layer.close(loadingIdx);
                SiteOps.showError(err);
            });
    }

    // Handle run table events
    table.on('tool(runsTable)', function (obj) {
        if (obj.event === 'download') {
            downloadRun(obj.data.id);
        }
    });

    function downloadRun(runId) {
        // Trigger file download via hidden link
        var url = '/api/v1/reports/runs/' + runId + '/download';
        var a = document.createElement('a');
        a.href = url;
        a.setAttribute('download', '');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // Refresh runs button
    var refreshRunsBtn = document.getElementById('btn-refresh-runs');
    if (refreshRunsBtn) {
        refreshRunsBtn.addEventListener('click', function () {
            loadRuns();
        });
    }

    // =====================
    // EXPORT CSV
    // =====================

    var exportCsvBtn = document.getElementById('btn-export-csv');
    if (exportCsvBtn) {
        exportCsvBtn.addEventListener('click', function () {
            openExportCsvDialog();
        });
    }

    function openExportCsvDialog() {
        var content = '<div style="padding:20px;">'
            + '<div class="layui-form" lay-filter="csvExportForm">'
            + '<div class="layui-form-item">'
            + '<label class="layui-form-label">Export Type</label>'
            + '<div class="layui-input-block">'
            + '<select id="csv-export-type">'
            + '<option value="recipes">Recipes</option>'
            + '<option value="orders">Orders</option>'
            + '<option value="analytics">Analytics</option>'
            + '<option value="settlements">Settlements</option>'
            + '</select>'
            + '</div></div>'
            + '<div class="layui-form-item">'
            + '<label class="layui-form-label">Date Range</label>'
            + '<div class="layui-input-block">'
            + '<input type="text" id="csv-date-range" class="layui-input" placeholder="Select date range" readonly>'
            + '</div></div>'
            + '</div></div>';

        var dialogIdx = layer.open({
            type: 1,
            title: 'Export CSV',
            area: ['500px', '280px'],
            content: content,
            btn: ['Export', 'Cancel'],
            success: function () {
                laydate.render({
                    elem: '#csv-date-range',
                    range: true
                });
            },
            yes: function (index) {
                var exportType = document.getElementById('csv-export-type').value;
                var dateRange = document.getElementById('csv-date-range').value;

                var payload = { type: exportType };
                if (dateRange) {
                    var parts = dateRange.split(' - ');
                    payload.date_from = parts[0] || '';
                    payload.date_to = parts[1] || '';
                }

                var loadingIdx = layer.load(1);
                SiteOps.request('POST', '/api/v1/exports/csv', payload)
                    .then(function (res) {
                        layer.close(loadingIdx);
                        layer.close(index);
                        var result = res.data || res;
                        if (result.download_url) {
                            var a = document.createElement('a');
                            a.href = result.download_url;
                            a.setAttribute('download', '');
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            SiteOps.showSuccess('CSV export started. Download will begin shortly.');
                        } else {
                            SiteOps.showSuccess('CSV export queued. Check the Runs tab for download.');
                        }
                    })
                    .catch(function (err) {
                        layer.close(loadingIdx);
                        SiteOps.showError(err);
                    });
            }
        });
    }

    // --- Utility ---
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // --- Initial load ---
    loadDefinitions();
});
