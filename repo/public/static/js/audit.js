/**
 * SiteOps - Audit log page
 */
layui.use(['table', 'form', 'layer', 'upload', 'laydate', 'element'], function () {
    'use strict';

    var table = layui.table;
    var form = layui.form;
    var layer = layui.layer;
    var element = layui.element;
    var laydate = layui.laydate;

    var currentPage = 1;
    var pageSize = 20;
    var currentTab = 'all';

    // Tab-to-filter mapping
    var tabFilters = {
        'all': null,
        'exports': 'export',
        'approvals': 'approval',
        'permissions': 'permission_change'
    };

    // --- Date range picker ---
    laydate.render({
        elem: '#audit-filter-date',
        range: true,
        done: function () {
            currentPage = 1;
            loadAuditLogs();
        }
    });

    // --- Tab switching ---
    element.on('tab(auditTabs)', function (data) {
        var tabs = ['all', 'exports', 'approvals', 'permissions'];
        currentTab = tabs[data.index] || 'all';
        currentPage = 1;
        loadAuditLogs();
    });

    // --- Filter controls ---
    form.on('select(auditEventType)', function () {
        currentPage = 1;
        loadAuditLogs();
    });

    var searchBtn = document.getElementById('btn-audit-search');
    if (searchBtn) {
        searchBtn.addEventListener('click', function () {
            currentPage = 1;
            loadAuditLogs();
        });
    }

    var resetBtn = document.getElementById('btn-audit-reset');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            document.getElementById('audit-filter-event-type').value = '';
            document.getElementById('audit-filter-site').value = '';
            document.getElementById('audit-filter-actor').value = '';
            document.getElementById('audit-filter-date').value = '';
            form.render('select');
            currentPage = 1;
            currentTab = 'all';
            loadAuditLogs();
        });
    }

    // --- Build query params ---
    function buildQuery() {
        var params = [];
        params.push('page=' + currentPage);
        params.push('per_page=' + pageSize);

        // Event type from select or from tab
        var eventType = document.getElementById('audit-filter-event-type').value;
        if (!eventType && tabFilters[currentTab]) {
            eventType = tabFilters[currentTab];
        }
        if (eventType) params.push('event_type=' + encodeURIComponent(eventType));

        var site = document.getElementById('audit-filter-site').value.trim();
        if (site) params.push('site=' + encodeURIComponent(site));

        var actor = document.getElementById('audit-filter-actor').value.trim();
        if (actor) params.push('actor=' + encodeURIComponent(actor));

        var dateRange = document.getElementById('audit-filter-date').value;
        if (dateRange) {
            var parts = dateRange.split(' - ');
            if (parts[0]) params.push('date_from=' + encodeURIComponent(parts[0]));
            if (parts[1]) params.push('date_to=' + encodeURIComponent(parts[1]));
        }

        return '?' + params.join('&');
    }

    // --- Load audit logs ---
    function loadAuditLogs() {
        var loadingIdx = layer.load(1);

        SiteOps.request('GET', '/api/v1/audit/logs' + buildQuery())
            .then(function (res) {
                layer.close(loadingIdx);
                var data = res.data || res;
                var logs = Array.isArray(data) ? data : (data.items || data.logs || []);
                var total = data.total || data.total_count || logs.length;

                table.render({
                    elem: '#audit-table',
                    cols: [[
                        { field: 'id', title: 'ID', width: 70, sort: true },
                        { field: 'timestamp', title: 'Timestamp', width: 180, sort: true, templet: function (d) {
                            return escapeHtml(d.timestamp || d.created_at || '');
                        }},
                        { field: 'event_type', title: 'Event Type', width: 150, templet: function (d) {
                            var type = d.event_type || d.action || '';
                            var colors = {
                                create: 'layui-bg-green', update: 'layui-bg-blue', delete: 'layui-bg-red',
                                export: 'layui-bg-cyan', approval: 'layui-bg-orange',
                                permission_change: '#9C27B0', login: 'layui-bg-gray', logout: 'layui-bg-gray'
                            };
                            var cls = colors[type] || '';
                            if (cls.indexOf('#') === 0) {
                                return '<span class="layui-badge" style="background:' + cls + ';">' + escapeHtml(type) + '</span>';
                            }
                            return '<span class="layui-badge ' + cls + '">' + escapeHtml(type) + '</span>';
                        }},
                        { field: 'actor', title: 'Actor', width: 140, templet: function (d) {
                            return escapeHtml(d.actor || d.user || d.actor_name || '');
                        }},
                        { field: 'resource', title: 'Resource', minWidth: 180, templet: function (d) {
                            return escapeHtml(d.resource || d.resource_type || '') + (d.resource_id ? ' #' + d.resource_id : '');
                        }},
                        { field: 'site', title: 'Site', width: 120 },
                        { title: 'Details', width: 100, toolbar: '#auditDetailTpl', fixed: 'right' }
                    ]],
                    data: logs,
                    page: false,
                    limit: pageSize,
                    text: { none: 'No audit log entries found for the selected filters.' }
                });

                // Render custom pagination
                renderPagination(total);
            })
            .catch(function (err) {
                layer.close(loadingIdx);
                SiteOps.showError(err);
            });
    }

    // Toolbar template
    if (!document.getElementById('auditDetailTpl')) {
        var tpl = document.createElement('script');
        tpl.type = 'text/html';
        tpl.id = 'auditDetailTpl';
        tpl.innerHTML = '<a class="layui-btn layui-btn-xs layui-btn-primary" lay-event="detail">View</a>';
        document.body.appendChild(tpl);
    }

    // Handle table events
    table.on('tool(auditTable)', function (obj) {
        if (obj.event === 'detail') {
            showLogDetail(obj.data);
        }
    });

    // --- Log detail dialog ---
    function showLogDetail(log) {
        var html = '<div style="padding:20px;">'
            + '<table class="layui-table">'
            + '<colgroup><col width="130"><col></colgroup>'
            + '<tbody>'
            + '<tr><td><strong>ID</strong></td><td>' + escapeHtml(String(log.id || '')) + '</td></tr>'
            + '<tr><td><strong>Timestamp</strong></td><td>' + escapeHtml(log.timestamp || log.created_at || '') + '</td></tr>'
            + '<tr><td><strong>Event Type</strong></td><td>' + escapeHtml(log.event_type || log.action || '') + '</td></tr>'
            + '<tr><td><strong>Actor</strong></td><td>' + escapeHtml(log.actor || log.user || '') + '</td></tr>'
            + '<tr><td><strong>Resource</strong></td><td>' + escapeHtml(log.resource || log.resource_type || '') + (log.resource_id ? ' #' + log.resource_id : '') + '</td></tr>'
            + '<tr><td><strong>Site</strong></td><td>' + escapeHtml(log.site || '') + '</td></tr>'
            + '<tr><td><strong>IP Address</strong></td><td>' + escapeHtml(log.ip_address || log.ip || '') + '</td></tr>'
            + '<tr><td><strong>Request ID</strong></td><td>' + escapeHtml(log.request_id || '') + '</td></tr>'
            + '</tbody></table>';

        // Show changes/details if available
        var details = log.details || log.changes || log.metadata;
        if (details) {
            html += '<h4 style="margin-top:15px;">Details</h4>'
                + '<pre style="background:#f8f8f8; padding:10px; border-radius:4px; overflow-x:auto; font-size:12px;">'
                + escapeHtml(typeof details === 'string' ? details : JSON.stringify(details, null, 2))
                + '</pre>';
        }

        html += '</div>';

        layer.open({
            type: 1,
            title: 'Audit Log Entry #' + (log.id || ''),
            area: ['600px', '500px'],
            content: html,
            shadeClose: true
        });
    }

    // --- Custom pagination ---
    function renderPagination(total) {
        var paginationEl = document.getElementById('audit-pagination');
        if (!paginationEl) return;

        var totalPages = Math.ceil(total / pageSize);
        if (totalPages <= 1) {
            paginationEl.innerHTML = '';
            return;
        }

        var html = '<div style="text-align:center; margin-top:20px;">';
        html += '<div class="layui-btn-group">';

        // Previous
        if (currentPage > 1) {
            html += '<button type="button" class="layui-btn layui-btn-sm layui-btn-primary" data-page="' + (currentPage - 1) + '">Prev</button>';
        }

        // Page numbers (show max 7 pages)
        var startPage = Math.max(1, currentPage - 3);
        var endPage = Math.min(totalPages, startPage + 6);
        startPage = Math.max(1, endPage - 6);

        for (var i = startPage; i <= endPage; i++) {
            var activeClass = i === currentPage ? 'layui-btn-normal' : 'layui-btn-primary';
            html += '<button type="button" class="layui-btn layui-btn-sm ' + activeClass + '" data-page="' + i + '">' + i + '</button>';
        }

        // Next
        if (currentPage < totalPages) {
            html += '<button type="button" class="layui-btn layui-btn-sm layui-btn-primary" data-page="' + (currentPage + 1) + '">Next</button>';
        }

        html += '</div>';
        html += '<div style="margin-top:5px; color:#999; font-size:12px;">Total: ' + total + ' entries</div>';
        html += '</div>';

        paginationEl.innerHTML = html;

        // Bind page clicks
        var pageButtons = paginationEl.querySelectorAll('[data-page]');
        for (var j = 0; j < pageButtons.length; j++) {
            pageButtons[j].addEventListener('click', function () {
                currentPage = parseInt(this.getAttribute('data-page'), 10);
                loadAuditLogs();
            });
        }
    }

    // --- Utility ---
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // --- Initial load ---
    loadAuditLogs();
});
