<?php
$title = 'Settlements - SiteOps';
$csrf_token = session('csrf_token') ?? '';
ob_start();
?>
<div class="layui-breadcrumb" style="margin-bottom: 15px;">
    <a href="/dashboard">Home</a>
    <a><cite>Settlements</cite></a>
</div>

<div id="settlement-content">
    <div class="layui-tab layui-tab-brief" lay-filter="settlementTabs">
        <ul class="layui-tab-title">
            <li class="layui-this">Freight Rules</li>
            <li>Statements</li>
        </ul>
        <div class="layui-tab-content">
            <!-- Freight Rules Tab -->
            <div class="layui-tab-item layui-show">
                <div style="margin-bottom:15px;">
                    <button type="button" class="layui-btn" id="btn-new-freight-rule">
                        <i class="layui-icon layui-icon-add-1"></i> New Freight Rule
                    </button>
                </div>
                <table id="freight-rules-table" lay-filter="freightRulesTable"></table>
            </div>

            <!-- Statements Tab -->
            <div class="layui-tab-item">
                <div style="margin-bottom:15px;">
                    <button type="button" class="layui-btn" id="btn-generate-statement">
                        <i class="layui-icon layui-icon-add-1"></i> Generate Statement
                    </button>
                    <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="btn-refresh-statements">
                        <i class="layui-icon layui-icon-refresh"></i> Refresh
                    </button>
                </div>
                <table id="statements-table" lay-filter="statementsTable"></table>

                <!-- Statement detail panel -->
                <div id="statement-detail-panel" style="display:none; margin-top:15px;">
                    <div class="layui-card">
                        <div class="layui-card-header">
                            <span id="statement-detail-title" style="font-weight:600;">Statement Detail</span>
                            <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="btn-close-detail" style="float:right;">Close</button>
                        </div>
                        <div class="layui-card-body">
                            <div style="margin-bottom:15px;">
                                <span id="statement-status-badge" class="layui-badge" style="font-size:14px;"></span>
                                <span id="statement-lock-indicator" style="margin-left:10px; color:#999;"></span>
                            </div>

                            <h4 style="margin-bottom:10px;">Statement Lines</h4>
                            <table id="statement-lines-table" lay-filter="statementLinesTable"></table>

                            <h4 style="margin:15px 0 10px;">Variances</h4>
                            <div id="statement-variances"><p style="color:#999;">No variances.</p></div>

                            <h4 style="margin:15px 0 10px;">Approval Trail</h4>
                            <div id="statement-audit-trail"><p style="color:#999;">No entries.</p></div>

                            <div style="margin-top:20px; border-top:1px solid #e6e6e6; padding-top:15px;">
                                <button type="button" class="layui-btn layui-btn-normal" id="btn-submit-statement">
                                    <i class="layui-icon layui-icon-release"></i> Submit for Approval
                                </button>
                                <button type="button" class="layui-btn" id="btn-approve-statement">
                                    <i class="layui-icon layui-icon-ok"></i> Approve (Final)
                                </button>
                                <button type="button" class="layui-btn layui-btn-danger" id="btn-reverse-statement">
                                    <i class="layui-icon layui-icon-return"></i> Reverse
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
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

    var currentStatementId = null;

    // Freight rules table
    table.render({
        elem: '#freight-rules-table',
        cols: [[
            { type: 'numbers', title: '#', width: 50 },
            { field: 'name', title: 'Rule Name', minWidth: 180 },
            { field: 'site_id', title: 'Site', width: 80 },
            { field: 'tax_rate', title: 'Tax Rate', width: 100 },
            { field: 'active', title: 'Active', width: 80, templet: function (d) {
                return d.active ? '<span style="color:#43A047;">Yes</span>' : '<span style="color:#999;">No</span>';
            }},
            { field: 'created_at', title: 'Created', width: 160 },
            { title: 'Actions', width: 100, toolbar: '#freight-row-actions' }
        ]],
        data: [],
        page: true,
        limit: 20,
        text: { none: 'No freight rules found' }
    });

    var frActionsHtml = '<div><a class="layui-btn layui-btn-xs" lay-event="edit"><i class="layui-icon layui-icon-edit"></i> Edit</a></div>';
    var tpl1 = document.createElement('script');
    tpl1.type = 'text/html'; tpl1.id = 'freight-row-actions'; tpl1.innerHTML = frActionsHtml;
    document.body.appendChild(tpl1);

    // Statements table — rendered lazily when the Statements tab is shown
    // (Layui tables in hidden tabs render with 0 width)
    var statementsTableRendered = false;
    function renderStatementsTable(data) {
        statementsTableRendered = true;
        table.render({
            elem: '#statements-table',
            cols: [[
                { type: 'numbers', title: '#', width: 50 },
                { field: 'id', title: 'ID', width: 60 },
                { field: 'site_id', title: 'Site', width: 80 },
                { field: 'period', title: 'Period', width: 120 },
                { field: 'status', title: 'Status', width: 120, templet: function (d) {
                    var colors = { draft: '#FFB300', submitted: '#1E88E5', approved_locked: '#43A047', reversed: '#E53935' };
                    var c = colors[d.status] || '#999';
                    return '<span class="layui-badge" style="background-color:' + c + ';">' + (d.status || '--') + '</span>';
                }},
                { field: 'total_amount', title: 'Total', width: 120 },
                { field: 'created_at', title: 'Created', width: 160 },
                { title: 'Actions', width: 100, toolbar: '#stmt-row-actions' }
            ]],
            data: data,
            page: data.length > 20,
            limit: 20,
            text: { none: 'No statements found' }
        });
    }

    var stmtActionsHtml = '<div><a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="view"><i class="layui-icon layui-icon-search"></i> View</a></div>';
    var tpl2 = document.createElement('script');
    tpl2.type = 'text/html'; tpl2.id = 'stmt-row-actions'; tpl2.innerHTML = stmtActionsHtml;
    document.body.appendChild(tpl2);

    // Statement lines table
    table.render({
        elem: '#statement-lines-table',
        cols: [[
            { type: 'numbers', title: '#', width: 50 },
            { field: 'description', title: 'Description', minWidth: 200 },
            { field: 'quantity', title: 'Qty', width: 70 },
            { field: 'unit_price', title: 'Unit Price', width: 100 },
            { field: 'amount', title: 'Amount', width: 110 }
        ]],
        data: [],
        page: false, limit: 100,
        text: { none: 'No line items' }
    });

    function loadFreightRules() {
        SiteOps.request('GET', '/api/v1/finance/freight-rules?per_page=50')
            .then(function (res) {
                var data = res.data || res;
                table.reload('freight-rules-table', { data: data.items || [] });
            })
            .catch(function (err) { SiteOps.showError(err); });
    }

    function loadStatements() {
        SiteOps.request('GET', '/api/v1/finance/settlements?per_page=50')
            .then(function (res) {
                var data = res.data || res;
                var items = data.items || [];
                if (!statementsTableRendered) {
                    renderStatementsTable(items);
                } else {
                    table.reload('statements-table', { data: items });
                }
            })
            .catch(function (err) { SiteOps.showError(err); });
    }

    // New freight rule dialog
    document.getElementById('btn-new-freight-rule').addEventListener('click', function () {
        openFreightRuleDialog(null);
    });

    function openFreightRuleDialog(existing) {
        var isEdit = !!existing;
        var html =
            '<div style="padding:20px;">' +
                '<form class="layui-form">' +
                    '<div class="layui-form-item">' +
                        '<label class="layui-form-label">Name <span style="color:red;">*</span></label>' +
                        '<div class="layui-input-block"><input type="text" id="fr-name" class="layui-input" value="' + (existing ? (existing.name || '') : '') + '"></div>' +
                    '</div>' +
                    '<div class="layui-form-item">' +
                        '<label class="layui-form-label">Site ID <span style="color:red;">*</span></label>' +
                        '<div class="layui-input-inline" style="width:100px;"><input type="number" id="fr-site-id" class="layui-input" value="' + (existing ? (existing.site_id || 1) : 1) + '" min="1"></div>' +
                    '</div>' +
                    '<div class="layui-form-item">' +
                        '<label class="layui-form-label">Tax Rate <span style="color:red;">*</span></label>' +
                        '<div class="layui-input-inline" style="width:120px;"><input type="number" id="fr-tax-rate" class="layui-input" step="0.01" min="0" max="1" value="' + (existing ? (existing.tax_rate || '0.10') : '0.10') + '"></div>' +
                    '</div>' +
                    '<div class="layui-form-item layui-form-text">' +
                        '<label class="layui-form-label">Distance Bands</label>' +
                        '<div class="layui-input-block"><textarea id="fr-distance-bands" class="layui-textarea" rows="3" placeholder="JSON array">' + (existing && existing.distance_band_json ? existing.distance_band_json : '[]') + '</textarea></div>' +
                    '</div>' +
                    '<div class="layui-form-item layui-form-text">' +
                        '<label class="layui-form-label">Weight Tiers</label>' +
                        '<div class="layui-input-block"><textarea id="fr-weight-tiers" class="layui-textarea" rows="3" placeholder="JSON array">' + (existing && existing.weight_tiers_json ? existing.weight_tiers_json : '[]') + '</textarea></div>' +
                    '</div>' +
                '</form>' +
            '</div>';

        layer.open({
            type: 1,
            title: isEdit ? 'Edit Freight Rule' : 'New Freight Rule',
            content: html,
            area: ['550px', '520px'],
            btn: ['Save', 'Cancel'],
            yes: function (index) {
                var name = document.getElementById('fr-name').value.trim();
                var siteId = parseInt(document.getElementById('fr-site-id').value) || 1;
                var taxRate = parseFloat(document.getElementById('fr-tax-rate').value) || 0;
                if (!name) { layer.msg('Name is required.', { icon: 0 }); return; }

                var distanceBands, weightTiers;
                try { distanceBands = JSON.parse(document.getElementById('fr-distance-bands').value || '[]'); } catch (e) { layer.msg('Invalid distance bands JSON.', { icon: 0 }); return; }
                try { weightTiers = JSON.parse(document.getElementById('fr-weight-tiers').value || '[]'); } catch (e) { layer.msg('Invalid weight tiers JSON.', { icon: 0 }); return; }

                var payload = { name: name, site_id: siteId, tax_rate: taxRate, distance_bands: distanceBands, weight_tiers: weightTiers };

                var req = isEdit
                    ? SiteOps.request('PATCH', '/api/v1/finance/freight-rules/' + existing.id, payload)
                    : SiteOps.request('POST', '/api/v1/finance/freight-rules', payload);

                req.then(function () {
                    layer.close(index);
                    SiteOps.showSuccess(isEdit ? 'Freight rule updated' : 'Freight rule created');
                    loadFreightRules();
                }).catch(function (err) { SiteOps.showError(err); });
            }
        });
    }

    table.on('tool(freightRulesTable)', function (obj) {
        if (obj.event === 'edit') openFreightRuleDialog(obj.data);
    });

    // Generate statement
    document.getElementById('btn-generate-statement').addEventListener('click', function () {
        var html =
            '<div style="padding:20px;">' +
                '<div class="layui-form">' +
                    '<div class="layui-form-item">' +
                        '<label class="layui-form-label">Site ID</label>' +
                        '<div class="layui-input-inline" style="width:100px;"><input type="number" id="gen-site-id" class="layui-input" value="1" min="1"></div>' +
                    '</div>' +
                    '<div class="layui-form-item">' +
                        '<label class="layui-form-label">Period</label>' +
                        '<div class="layui-input-inline" style="width:160px;"><input type="text" id="gen-period" class="layui-input" placeholder="e.g., 2026-04"></div>' +
                    '</div>' +
                '</div>' +
            '</div>';

        layer.open({
            type: 1,
            title: 'Generate Settlement Statement',
            content: html,
            area: ['420px', '240px'],
            btn: ['Generate', 'Cancel'],
            yes: function (index) {
                var siteId = parseInt(document.getElementById('gen-site-id').value) || 1;
                var period = document.getElementById('gen-period').value.trim();
                if (!period) { layer.msg('Period is required.', { icon: 0 }); return; }

                SiteOps.request('POST', '/api/v1/finance/settlements/generate', { site_id: siteId, period: period })
                    .then(function (res) {
                        layer.close(index);
                        var data = res.data || res;
                        SiteOps.showSuccess(data.message || 'Statement generation started');
                        if (data.settlement_id) openStatementDetail(data.settlement_id);
                    })
                    .catch(function (err) { SiteOps.showError(err); });
            }
        });
    });

    // Statement view
    table.on('tool(statementsTable)', function (obj) {
        if (obj.event === 'view') openStatementDetail(obj.data.id);
    });

    function openStatementDetail(id) {
        currentStatementId = id;
        document.getElementById('statement-detail-panel').style.display = '';
        document.getElementById('statement-detail-title').textContent = 'Statement #' + id;

        SiteOps.request('GET', '/api/v1/finance/settlements/' + id)
            .then(function (res) {
                var data = res.data || res;
                var statusEl = document.getElementById('statement-status-badge');
                var statusColors = { draft: '#FFB300', submitted: '#1E88E5', approved_locked: '#43A047', reversed: '#E53935' };
                statusEl.textContent = data.status || '--';
                statusEl.style.backgroundColor = statusColors[data.status] || '#999';

                var lockEl = document.getElementById('statement-lock-indicator');
                lockEl.textContent = data.status === 'approved_locked' ? 'Locked' : '';
                lockEl.style.color = data.status === 'approved_locked' ? '#E53935' : '#999';

                table.reload('statement-lines-table', { data: data.lines || [] });

                var variances = data.variances || [];
                var varHtml = '';
                if (variances.length) {
                    varHtml = '<table class="layui-table"><thead><tr><th>Type</th><th>Expected</th><th>Actual</th><th>Difference</th></tr></thead><tbody>';
                    variances.forEach(function (v) {
                        varHtml += '<tr><td>' + (v.type || '--') + '</td><td>' + (v.expected || '--') + '</td><td>' + (v.actual || '--') + '</td><td>' + (v.difference || '--') + '</td></tr>';
                    });
                    varHtml += '</tbody></table>';
                } else {
                    varHtml = '<p style="color:#999;">No variances detected.</p>';
                }
                document.getElementById('statement-variances').innerHTML = varHtml;

                return SiteOps.request('GET', '/api/v1/finance/settlements/' + id + '/audit-trail');
            })
            .then(function (res) {
                var data = res.data || res;
                var entries = data.entries || [];
                var html = '';
                if (entries.length) {
                    entries.forEach(function (e) {
                        html += '<div style="padding:8px 0; border-bottom:1px solid #f0f0f0;">' +
                            '<strong>' + (e.event_type || e.event || '--') + '</strong> by user ' +
                            (e.actor_id || '--') + ' at ' + (e.created_at || '--') +
                            (e.description ? ' - ' + e.description : '') +
                            '</div>';
                    });
                } else {
                    html = '<p style="color:#999;">No audit trail entries.</p>';
                }
                document.getElementById('statement-audit-trail').innerHTML = html;
            })
            .catch(function (err) { SiteOps.showError(err); });
    }

    document.getElementById('btn-close-detail').addEventListener('click', function () {
        document.getElementById('statement-detail-panel').style.display = 'none';
        currentStatementId = null;
    });

    document.getElementById('btn-submit-statement').addEventListener('click', function () {
        if (!currentStatementId) return;
        layer.confirm('Submit this statement for approval?', { icon: 3 }, function (index) {
            layer.close(index);
            SiteOps.request('POST', '/api/v1/finance/settlements/' + currentStatementId + '/submit', {})
                .then(function () { SiteOps.showSuccess('Statement submitted'); openStatementDetail(currentStatementId); })
                .catch(function (err) { SiteOps.showError(err); });
        });
    });

    document.getElementById('btn-approve-statement').addEventListener('click', function () {
        if (!currentStatementId) return;
        layer.confirm('Give final approval? This will lock the statement.', { icon: 3 }, function (index) {
            layer.close(index);
            SiteOps.request('POST', '/api/v1/finance/settlements/' + currentStatementId + '/approve-final', {})
                .then(function () { SiteOps.showSuccess('Statement approved and locked'); openStatementDetail(currentStatementId); })
                .catch(function (err) { SiteOps.showError(err); });
        });
    });

    document.getElementById('btn-reverse-statement').addEventListener('click', function () {
        if (!currentStatementId) return;
        layer.prompt({
            formType: 2,
            title: 'Reversal reason (required)',
            area: ['400px', '100px']
        }, function (reason, index) {
            layer.close(index);
            SiteOps.request('POST', '/api/v1/finance/settlements/' + currentStatementId + '/reverse', { reason: reason })
                .then(function () { SiteOps.showSuccess('Statement reversed'); openStatementDetail(currentStatementId); })
                .catch(function (err) { SiteOps.showError(err); });
        });
    });

    document.getElementById('btn-refresh-statements').addEventListener('click', loadStatements);

    element.on('tab(settlementTabs)', function (data) {
        if (data.index === 1) loadStatements();
    });

    loadFreightRules();
});
</script>
<?php
$scripts = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>
