/**
 * SiteOps - Settlement management page
 */
layui.use(['table', 'form', 'layer', 'upload', 'laydate', 'element'], function () {
    'use strict';

    var table = layui.table;
    var form = layui.form;
    var layer = layui.layer;
    var element = layui.element;
    var laydate = layui.laydate;

    // --- Tab switching ---
    element.on('tab(settlementTabs)', function (data) {
        if (data.index === 0) {
            loadFreightRules();
        } else if (data.index === 1) {
            loadStatements();
        }
    });

    // =====================
    // FREIGHT RULES TAB
    // =====================

    function loadFreightRules() {
        var loadingIdx = layer.load(1);
        SiteOps.request('GET', '/api/v1/finance/freight-rules')
            .then(function (res) {
                layer.close(loadingIdx);
                var rules = res.data || res;
                if (!Array.isArray(rules)) rules = rules.items || [];

                table.render({
                    elem: '#freight-rules-table',
                    cols: [[
                        { field: 'id', title: 'ID', width: 70, sort: true },
                        { field: 'name', title: 'Rule Name', minWidth: 180 },
                        { field: 'origin', title: 'Origin', width: 120 },
                        { field: 'destination', title: 'Destination', width: 120 },
                        { field: 'rate_type', title: 'Rate Type', width: 110 },
                        { field: 'rate', title: 'Rate', width: 100, templet: function (d) {
                            return d.rate != null ? '$' + Number(d.rate).toFixed(2) : '--';
                        }},
                        { field: 'effective_date', title: 'Effective', width: 120 },
                        { field: 'status', title: 'Status', width: 100, templet: function (d) {
                            var colors = { active: 'layui-bg-green', inactive: 'layui-bg-gray', expired: 'layui-bg-red' };
                            var cls = colors[d.status] || '';
                            return '<span class="layui-badge ' + cls + '">' + escapeHtml(d.status || '') + '</span>';
                        }},
                        { title: 'Actions', width: 100, toolbar: '#freightRuleTpl', fixed: 'right' }
                    ]],
                    data: rules,
                    page: rules.length > 20,
                    limit: 20,
                    text: { none: 'No freight rules found.' }
                });
            })
            .catch(function (err) {
                layer.close(loadingIdx);
                SiteOps.showError(err);
            });
    }

    // Toolbar templates
    if (!document.getElementById('freightRuleTpl')) {
        var tpl = document.createElement('script');
        tpl.type = 'text/html';
        tpl.id = 'freightRuleTpl';
        tpl.innerHTML = '<a class="layui-btn layui-btn-xs" lay-event="edit">Edit</a>';
        document.body.appendChild(tpl);
    }

    if (!document.getElementById('statementTpl')) {
        var tpl2 = document.createElement('script');
        tpl2.type = 'text/html';
        tpl2.id = 'statementTpl';
        tpl2.innerHTML = '<a class="layui-btn layui-btn-xs" lay-event="view">View</a>';
        document.body.appendChild(tpl2);
    }

    // Handle freight rule table events
    table.on('tool(freightRulesTable)', function (obj) {
        if (obj.event === 'edit') {
            openFreightRuleForm(obj.data);
        }
    });

    // --- New freight rule ---
    var newRuleBtn = document.getElementById('btn-new-freight-rule');
    if (newRuleBtn) {
        newRuleBtn.addEventListener('click', function () {
            openFreightRuleForm(null);
        });
    }

    function openFreightRuleForm(existing) {
        var isEdit = !!existing;
        var title = isEdit ? 'Edit Freight Rule' : 'New Freight Rule';

        var content = '<div style="padding:20px;">'
            + '<div class="layui-form" lay-filter="freightRuleForm">'
            + '<div class="layui-form-item">'
            + '<label class="layui-form-label">Name *</label>'
            + '<div class="layui-input-block">'
            + '<input type="text" id="fr-name" class="layui-input" value="' + escapeHtml((existing && existing.name) || '') + '" placeholder="Rule name">'
            + '</div></div>'
            + '<div class="layui-form-item">'
            + '<div class="layui-inline">'
            + '<label class="layui-form-label">Origin</label>'
            + '<div class="layui-input-inline">'
            + '<input type="text" id="fr-origin" class="layui-input" value="' + escapeHtml((existing && existing.origin) || '') + '" placeholder="Origin">'
            + '</div></div>'
            + '<div class="layui-inline">'
            + '<label class="layui-form-label">Destination</label>'
            + '<div class="layui-input-inline">'
            + '<input type="text" id="fr-destination" class="layui-input" value="' + escapeHtml((existing && existing.destination) || '') + '" placeholder="Destination">'
            + '</div></div>'
            + '</div>'
            + '<div class="layui-form-item">'
            + '<div class="layui-inline">'
            + '<label class="layui-form-label">Rate Type</label>'
            + '<div class="layui-input-inline">'
            + '<select id="fr-rate-type">'
            + '<option value="flat"' + (existing && existing.rate_type === 'flat' ? ' selected' : '') + '>Flat</option>'
            + '<option value="per_unit"' + (existing && existing.rate_type === 'per_unit' ? ' selected' : '') + '>Per Unit</option>'
            + '<option value="percentage"' + (existing && existing.rate_type === 'percentage' ? ' selected' : '') + '>Percentage</option>'
            + '<option value="tiered"' + (existing && existing.rate_type === 'tiered' ? ' selected' : '') + '>Tiered</option>'
            + '</select>'
            + '</div></div>'
            + '<div class="layui-inline">'
            + '<label class="layui-form-label">Rate</label>'
            + '<div class="layui-input-inline" style="width:120px;">'
            + '<input type="number" id="fr-rate" class="layui-input" step="0.01" value="' + ((existing && existing.rate) || '') + '" placeholder="0.00">'
            + '</div></div>'
            + '</div>'
            + '<div class="layui-form-item">'
            + '<label class="layui-form-label">Effective Date</label>'
            + '<div class="layui-input-block">'
            + '<input type="text" id="fr-effective-date" class="layui-input" value="' + escapeHtml((existing && existing.effective_date) || '') + '" placeholder="Select date" readonly>'
            + '</div></div>'
            + '</div></div>';

        var dialogIdx = layer.open({
            type: 1,
            title: title,
            area: ['620px', '440px'],
            content: content,
            btn: ['Save', 'Cancel'],
            success: function () {
                laydate.render({
                    elem: '#fr-effective-date',
                    type: 'date'
                });
            },
            yes: function (index) {
                var name = document.getElementById('fr-name').value.trim();
                if (!name) {
                    layer.msg('Name is required.', { icon: 0 });
                    return;
                }

                var payload = {
                    name: name,
                    origin: document.getElementById('fr-origin').value.trim(),
                    destination: document.getElementById('fr-destination').value.trim(),
                    rate_type: document.getElementById('fr-rate-type').value,
                    rate: parseFloat(document.getElementById('fr-rate').value) || 0,
                    effective_date: document.getElementById('fr-effective-date').value
                };

                var loadingIdx = layer.load(1);

                if (isEdit) {
                    SiteOps.request('PUT', '/api/v1/finance/freight-rules/' + existing.id, payload)
                        .then(function () {
                            layer.close(loadingIdx);
                            layer.close(index);
                            SiteOps.showSuccess('Freight rule updated.');
                            loadFreightRules();
                        })
                        .catch(function (err) {
                            layer.close(loadingIdx);
                            SiteOps.showError(err);
                        });
                } else {
                    SiteOps.request('POST', '/api/v1/finance/freight-rules', payload)
                        .then(function () {
                            layer.close(loadingIdx);
                            layer.close(index);
                            SiteOps.showSuccess('Freight rule created.');
                            loadFreightRules();
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
    // STATEMENTS TAB
    // =====================

    function loadStatements() {
        var loadingIdx = layer.load(1);
        SiteOps.request('GET', '/api/v1/finance/settlements')
            .then(function (res) {
                layer.close(loadingIdx);
                var statements = res.data || res;
                if (!Array.isArray(statements)) statements = statements.items || [];

                table.render({
                    elem: '#statements-table',
                    cols: [[
                        { field: 'id', title: 'ID', width: 70, sort: true },
                        { field: 'period', title: 'Period', width: 150, templet: function (d) {
                            return escapeHtml(d.period || d.period_start + ' - ' + d.period_end || '');
                        }},
                        { field: 'total_amount', title: 'Amount', width: 120, templet: function (d) {
                            return d.total_amount != null ? '$' + Number(d.total_amount).toFixed(2) : '--';
                        }},
                        { field: 'status', title: 'Status', width: 130, templet: function (d) {
                            var colors = {
                                draft: '', generated: 'layui-bg-blue', reconciled: 'layui-bg-cyan',
                                submitted: 'layui-bg-orange', approved: 'layui-bg-green', reversed: 'layui-bg-red'
                            };
                            var cls = colors[d.status] || '';
                            return '<span class="layui-badge ' + cls + '">' + escapeHtml(d.status || '') + '</span>';
                        }},
                        { field: 'created_at', title: 'Created', width: 170, sort: true },
                        { title: 'Actions', width: 100, toolbar: '#statementTpl', fixed: 'right' }
                    ]],
                    data: statements,
                    page: statements.length > 20,
                    limit: 20,
                    text: { none: 'No statements found.' }
                });
            })
            .catch(function (err) {
                layer.close(loadingIdx);
                SiteOps.showError(err);
            });
    }

    // Handle statement table events
    table.on('tool(statementsTable)', function (obj) {
        if (obj.event === 'view') {
            openStatementDetail(obj.data.id);
        }
    });

    // --- Generate statement ---
    var generateBtn = document.getElementById('btn-generate-statement');
    if (generateBtn) {
        generateBtn.addEventListener('click', function () {
            openGenerateDialog();
        });
    }

    function openGenerateDialog() {
        var siteScopes = window.USER_SITE_SCOPES || [];
        var siteOptions = siteScopes.map(function (id) {
            return '<option value="' + id + '">Site ' + id + '</option>';
        }).join('');
        if (!siteOptions) siteOptions = '<option value="1">Site 1</option>';

        var content = '<div style="padding:20px;">'
            + '<div class="layui-form">'
            + '<div class="layui-form-item">'
            + '<label class="layui-form-label">Site</label>'
            + '<div class="layui-input-block">'
            + '<select id="gen-site-id" class="layui-input">' + siteOptions + '</select>'
            + '</div></div>'
            + '<div class="layui-form-item">'
            + '<label class="layui-form-label">Period</label>'
            + '<div class="layui-input-block">'
            + '<input type="text" id="gen-period" class="layui-input" placeholder="YYYY-MM (e.g. 2026-03)">'
            + '</div></div>'
            + '</div></div>';

        layer.open({
            type: 1,
            title: 'Generate Settlement Statement',
            area: ['500px', '300px'],
            content: content,
            btn: ['Generate', 'Cancel'],
            yes: function (index) {
                var siteId = document.getElementById('gen-site-id').value;
                var period = document.getElementById('gen-period').value;
                if (!siteId || !period) {
                    layer.msg('Please fill site and period.', { icon: 0 });
                    return;
                }

                var loadingIdx = layer.load(1);
                SiteOps.request('POST', '/api/v1/finance/settlements/generate', {
                    site_id: parseInt(siteId),
                    period: period
                })
                    .then(function (res) {
                        layer.close(loadingIdx);
                        layer.close(index);
                        SiteOps.showSuccess('Statement generated.');
                        loadStatements();
                    })
                    .catch(function (err) {
                        layer.close(loadingIdx);
                        SiteOps.showError(err);
                    });
            }
        });
    }

    // Refresh statements
    var refreshStatementsBtn = document.getElementById('btn-refresh-statements');
    if (refreshStatementsBtn) {
        refreshStatementsBtn.addEventListener('click', function () {
            loadStatements();
        });
    }

    // --- Statement detail ---
    function openStatementDetail(statementId) {
        var loadingIdx = layer.load(1);
        SiteOps.request('GET', '/api/v1/finance/settlements/' + statementId)
            .then(function (res) {
                layer.close(loadingIdx);
                var stmt = res.data || res;
                showStatementDetailDialog(stmt);
            })
            .catch(function (err) {
                layer.close(loadingIdx);
                SiteOps.showError(err);
            });
    }

    function showStatementDetailDialog(stmt) {
        var statusBtns = buildStatusButtons(stmt);

        var content = '<div style="padding:20px;">'
            + '<table class="layui-table">'
            + '<colgroup><col width="150"><col></colgroup>'
            + '<tbody>'
            + '<tr><td><strong>ID</strong></td><td>' + escapeHtml(String(stmt.id || '')) + '</td></tr>'
            + '<tr><td><strong>Period</strong></td><td>' + escapeHtml(stmt.period || (stmt.period_start + ' - ' + stmt.period_end) || '') + '</td></tr>'
            + '<tr><td><strong>Total Amount</strong></td><td>$' + (stmt.total_amount != null ? Number(stmt.total_amount).toFixed(2) : '--') + '</td></tr>'
            + '<tr><td><strong>Status</strong></td><td>' + escapeHtml(stmt.status || '') + '</td></tr>'
            + '<tr><td><strong>Created</strong></td><td>' + escapeHtml(stmt.created_at || '') + '</td></tr>'
            + '</tbody></table>'
            + '<div id="stmt-line-items" style="margin-top:15px;"></div>'
            + '<div style="margin-top:15px;">' + statusBtns + '</div>'
            + '<div style="margin-top:15px;">'
            + '<a href="javascript:;" id="btn-view-audit-trail" style="color:#1E88E5;">View Audit Trail</a>'
            + '</div>'
            + '</div>';

        var dialogIdx = layer.open({
            type: 1,
            title: 'Settlement Statement #' + stmt.id,
            area: ['700px', '550px'],
            content: content,
            success: function (layero) {
                // Render line items if available
                if (stmt.line_items && stmt.line_items.length) {
                    var itemsHtml = '<h4>Line Items</h4><table class="layui-table">'
                        + '<thead><tr><th>Description</th><th>Amount</th></tr></thead><tbody>';
                    for (var i = 0; i < stmt.line_items.length; i++) {
                        var item = stmt.line_items[i];
                        itemsHtml += '<tr><td>' + escapeHtml(item.description || '') + '</td>'
                            + '<td>$' + Number(item.amount || 0).toFixed(2) + '</td></tr>';
                    }
                    itemsHtml += '</tbody></table>';
                    layero.find('#stmt-line-items').html(itemsHtml);
                }

                // Bind action buttons
                bindStatementActions(layero, stmt.id, dialogIdx);
            }
        });
    }

    function buildStatusButtons(stmt) {
        var btns = '';
        var s = stmt.status;

        if (s === 'generated' || s === 'draft') {
            btns += '<button type="button" class="layui-btn layui-btn-sm" data-action="reconcile">Reconcile</button> ';
        }
        if (s === 'reconciled') {
            btns += '<button type="button" class="layui-btn layui-btn-sm layui-btn-normal" data-action="submit">Submit</button> ';
        }
        if (s === 'submitted') {
            btns += '<button type="button" class="layui-btn layui-btn-sm layui-btn-normal" data-action="approve">Approve</button> ';
        }
        if (s === 'approved') {
            btns += '<button type="button" class="layui-btn layui-btn-sm layui-btn-danger" data-action="reverse">Reverse</button> ';
        }

        return btns;
    }

    function bindStatementActions(layero, statementId, dialogIdx) {
        var actions = {
            reconcile: { url: '/api/v1/finance/settlements/' + statementId + '/reconcile', msg: 'Statement reconciled.' },
            submit: { url: '/api/v1/finance/settlements/' + statementId + '/submit', msg: 'Statement submitted.' },
            approve: { url: '/api/v1/finance/settlements/' + statementId + '/approve-final', msg: 'Statement approved.' },
            reverse: { url: '/api/v1/finance/settlements/' + statementId + '/reverse', msg: 'Statement reversed.' }
        };

        var buttons = layero.find('[data-action]');
        buttons.each(function () {
            var btn = this;
            var action = btn.getAttribute('data-action');
            var config = actions[action];
            if (!config) return;

            btn.addEventListener('click', function () {
                var confirmMsg = action === 'reverse'
                    ? 'Are you sure you want to reverse this statement? This cannot be undone.'
                    : 'Confirm ' + action + ' for this statement?';

                layer.confirm(confirmMsg, {
                    title: 'Confirm Action',
                    btn: ['Confirm', 'Cancel']
                }, function (confirmIdx) {
                    layer.close(confirmIdx);
                    var loadingIdx = layer.load(1);
                    SiteOps.request('POST', config.url, {})
                        .then(function () {
                            layer.close(loadingIdx);
                            layer.close(dialogIdx);
                            SiteOps.showSuccess(config.msg);
                            loadStatements();
                        })
                        .catch(function (err) {
                            layer.close(loadingIdx);
                            SiteOps.showError(err);
                        });
                });
            });
        });

        // Audit trail link
        var auditLink = layero.find('#btn-view-audit-trail');
        auditLink.on('click', function () {
            openAuditTrail(statementId);
        });
    }

    // --- Audit trail ---
    function openAuditTrail(statementId) {
        var loadingIdx = layer.load(1);
        SiteOps.request('GET', '/api/v1/finance/settlements/' + statementId + '/audit-trail')
            .then(function (res) {
                layer.close(loadingIdx);
                var trail = res.data || res;
                if (!Array.isArray(trail)) trail = trail.items || trail.entries || [];
                showAuditTrailDialog(trail, statementId);
            })
            .catch(function (err) {
                layer.close(loadingIdx);
                SiteOps.showError(err);
            });
    }

    function showAuditTrailDialog(trail, statementId) {
        var html = '<div style="padding:20px;">';
        if (!trail.length) {
            html += '<p style="color:#999;">No audit trail entries found.</p>';
        } else {
            html += '<table class="layui-table">'
                + '<thead><tr><th>Date</th><th>Actor</th><th>Action</th><th>Details</th></tr></thead><tbody>';
            for (var i = 0; i < trail.length; i++) {
                var entry = trail[i];
                html += '<tr>'
                    + '<td>' + escapeHtml(entry.timestamp || entry.created_at || '') + '</td>'
                    + '<td>' + escapeHtml(entry.actor || entry.user || '') + '</td>'
                    + '<td>' + escapeHtml(entry.action || entry.event || '') + '</td>'
                    + '<td>' + escapeHtml(entry.details || entry.description || '') + '</td>'
                    + '</tr>';
            }
            html += '</tbody></table>';
        }
        html += '</div>';

        layer.open({
            type: 1,
            title: 'Audit Trail - Statement #' + statementId,
            area: ['700px', '450px'],
            content: html,
            shadeClose: true
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
    loadFreightRules();
});
