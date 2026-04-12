/**
 * SiteOps - Analytics dashboard page
 */
layui.use(['table', 'form', 'layer', 'upload', 'laydate', 'element'], function () {
    'use strict';

    var layer = layui.layer;
    var form = layui.form;
    var laydate = layui.laydate;

    var state = {
        filters: {
            date_from: '',
            date_to: '',
            site_id: '',
            community_id: '',
            group_leader_id: '',
            product_id: ''
        }
    };

    var els = {
        loading: document.getElementById('analytics-loading'),
        empty: document.getElementById('analytics-empty'),
        kpiCards: document.getElementById('analytics-kpi-cards'),
        refreshBtn: document.getElementById('analytics-refresh-btn')
    };

    // --- Date range picker ---
    laydate.render({
        elem: '#analytics-date-range',
        range: true,
        done: function (value) {
            if (value) {
                var parts = value.split(' - ');
                state.filters.date_from = parts[0] || '';
                state.filters.date_to = parts[1] || '';
            } else {
                state.filters.date_from = '';
                state.filters.date_to = '';
            }
            loadAnalytics();
        }
    });

    // --- Filter change handlers ---
    form.on('select(analyticsSite)', function (data) {
        state.filters.site_id = data.value;
        loadAnalytics();
    });

    form.on('select(analyticsCommunity)', function (data) {
        state.filters.community_id = data.value;
        loadAnalytics();
    });

    // --- Leader and product text input handlers ---
    var leaderInput = document.getElementById('analytics-leader-filter');
    if (leaderInput) {
        leaderInput.addEventListener('change', function () {
            state.filters.group_leader_id = this.value.trim();
        });
    }

    var productInput = document.getElementById('analytics-product-filter');
    if (productInput) {
        productInput.addEventListener('change', function () {
            state.filters.product_id = this.value.trim();
        });
    }

    // --- Refresh button ---
    if (els.refreshBtn) {
        els.refreshBtn.addEventListener('click', function () {
            refreshAnalytics();
        });
    }

    // --- UI state helpers ---
    function showLoading() {
        els.loading.style.display = 'block';
        els.empty.style.display = 'none';
        els.kpiCards.style.display = 'none';
    }

    function showEmpty() {
        els.loading.style.display = 'none';
        els.empty.style.display = 'block';
        els.kpiCards.style.display = 'none';
    }

    function showContent() {
        els.loading.style.display = 'none';
        els.empty.style.display = 'none';
        els.kpiCards.style.display = 'flex';
    }

    // --- Build query string ---
    function buildQuery() {
        var params = [];
        if (state.filters.date_from) params.push('date_from=' + encodeURIComponent(state.filters.date_from));
        if (state.filters.date_to) params.push('date_to=' + encodeURIComponent(state.filters.date_to));
        if (state.filters.site_id) params.push('site_id=' + encodeURIComponent(state.filters.site_id));
        if (state.filters.community_id) params.push('community_id=' + encodeURIComponent(state.filters.community_id));
        if (state.filters.group_leader_id) params.push('group_leader_id=' + encodeURIComponent(state.filters.group_leader_id));
        if (state.filters.product_id) params.push('product_id=' + encodeURIComponent(state.filters.product_id));
        return params.length ? '?' + params.join('&') : '';
    }

    // --- Metric definitions (all 7) ---
    var METRIC_ICONS = {
        'total_sales': 'layui-icon-dollar',
        'revenue': 'layui-icon-dollar',
        'orders': 'layui-icon-cart-simple',
        'order_count': 'layui-icon-cart-simple',
        'users': 'layui-icon-user',
        'active_users': 'layui-icon-user',
        'conversion_rate': 'layui-icon-rate',
        'conversion': 'layui-icon-rate',
        'pageviews': 'layui-icon-read',
        'sessions': 'layui-icon-login-wechat',
        'avg_order_value': 'layui-icon-dollar',
        'refund_rate': 'layui-icon-close-fill',
        'repeat_purchase': 'layui-icon-loop'
    };

    // --- Render KPI cards for all metrics ---
    function renderMetrics(metrics) {
        if (!metrics || !metrics.length) {
            showEmpty();
            return;
        }

        var html = '';
        for (var i = 0; i < metrics.length; i++) {
            var m = metrics[i];
            var iconClass = METRIC_ICONS[m.key] || 'layui-icon-chart';
            var changeClass = (m.change_pct || 0) >= 0 ? 'color:#5FB878;' : 'color:#FF5722;';
            var changeArrow = (m.change_pct || 0) >= 0 ? '&#9650;' : '&#9660;';
            var changeVal = m.change_pct != null ? (Math.abs(m.change_pct).toFixed(1) + '%') : 'N/A';

            // Use responsive grid: 7 metrics across various widths
            var colClass = metrics.length <= 4 ? 'layui-col-md3 layui-col-sm6' : 'layui-col-md3 layui-col-sm4';

            html += '<div class="' + colClass + '">'
                + '<div class="layui-card" style="cursor:pointer;">'
                + '<div class="layui-card-header">'
                + '<i class="layui-icon ' + iconClass + '" style="margin-right:5px;"></i>'
                + '<span class="analytics-metric-name" data-key="' + escapeHtml(m.key || '') + '">'
                + escapeHtml(m.name || m.key || '') + '</span>'
                + '</div>'
                + '<div class="layui-card-body" style="padding:15px;">'
                + '<div style="font-size:26px; font-weight:bold;">' + escapeHtml(formatValue(m.value, m.format)) + '</div>'
                + '<div style="font-size:12px; margin-top:5px;' + changeClass + '">'
                + changeArrow + ' ' + changeVal + ' vs prior period'
                + '</div>'
                + '</div>'
                + '</div>'
                + '</div>';
        }

        els.kpiCards.innerHTML = html;
        showContent();

        // Bind metric definition drawers
        var nameEls = els.kpiCards.querySelectorAll('.analytics-metric-name');
        for (var j = 0; j < nameEls.length; j++) {
            nameEls[j].addEventListener('click', function (e) {
                e.stopPropagation();
                openMetricDefinition(this.getAttribute('data-key'));
            });
        }
    }

    function formatValue(value, format) {
        if (value == null) return '--';
        switch (format) {
            case 'currency':
                return '$' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            case 'percent':
                return Number(value).toFixed(1) + '%';
            case 'integer':
                return Math.round(Number(value)).toLocaleString();
            default:
                return String(value);
        }
    }

    // --- Metric definition drawer ---
    function openMetricDefinition(metricKey) {
        if (!metricKey) return;
        layer.open({
            type: 1,
            title: 'Metric Definition: ' + metricKey,
            area: ['520px', '380px'],
            shadeClose: true,
            content: '<div style="padding:20px; text-align:center;">'
                + '<i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i> Loading...'
                + '</div>',
            success: function (layero, index) {
                SiteOps.request('GET', '/api/v1/analytics/metrics/' + encodeURIComponent(metricKey))
                    .then(function (res) {
                        var def = res.data || res;
                        var body = layero.find('.layui-layer-content');
                        body.html(
                            '<div style="padding:20px;">'
                            + '<h3 style="margin-bottom:10px;">' + escapeHtml(def.name || metricKey) + '</h3>'
                            + '<p style="color:#666; margin-bottom:15px;">' + escapeHtml(def.description || 'No description available.') + '</p>'
                            + '<table class="layui-table">'
                            + '<colgroup><col width="140"><col></colgroup>'
                            + '<tbody>'
                            + '<tr><td><strong>Key</strong></td><td>' + escapeHtml(def.key || metricKey) + '</td></tr>'
                            + '<tr><td><strong>Format</strong></td><td>' + escapeHtml(def.format || 'N/A') + '</td></tr>'
                            + '<tr><td><strong>Data Source</strong></td><td>' + escapeHtml(def.source || 'N/A') + '</td></tr>'
                            + '<tr><td><strong>Aggregation</strong></td><td>' + escapeHtml(def.aggregation || 'N/A') + '</td></tr>'
                            + '<tr><td><strong>Calculation</strong></td><td>' + escapeHtml(def.calculation || def.formula || 'N/A') + '</td></tr>'
                            + '</tbody></table>'
                            + '</div>'
                        );
                    })
                    .catch(function () {
                        var body = layero.find('.layui-layer-content');
                        body.html('<div style="padding:20px; color:#FF5722;">Failed to load metric definition.</div>');
                    });
            }
        });
    }

    // --- Load analytics data ---
    function loadAnalytics() {
        showLoading();
        SiteOps.request('GET', '/api/v1/analytics/dashboard' + buildQuery())
            .then(function (res) {
                var data = res.data || res;
                var metrics = data.metrics || data;
                if (Array.isArray(metrics) && metrics.length > 0) {
                    renderMetrics(metrics);
                } else {
                    showEmpty();
                }
            })
            .catch(function (err) {
                showEmpty();
                SiteOps.showError(err);
            });
    }

    // --- Refresh (POST) with rate limit handling ---
    function refreshAnalytics() {
        var loadingIdx = layer.load(1);
        SiteOps.request('POST', '/api/v1/analytics/refresh', {})
            .then(function () {
                layer.close(loadingIdx);
                SiteOps.showSuccess('Analytics data refreshed.');
                loadAnalytics();
            })
            .catch(function (err) {
                layer.close(loadingIdx);
                if (err.status === 429) {
                    var nextAllowed = '';
                    if (err.body && err.body.error && err.body.error.details) {
                        nextAllowed = err.body.error.details.next_allowed_at || err.body.error.details.retry_after || '';
                    }
                    var msg = 'Rate limited. ';
                    if (nextAllowed) {
                        msg += 'Next refresh allowed at: ' + nextAllowed;
                    } else {
                        msg += 'Please wait before refreshing again.';
                    }
                    layer.msg(msg, { icon: 0, time: 5000 });
                } else {
                    SiteOps.showError(err);
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
    loadAnalytics();
});
