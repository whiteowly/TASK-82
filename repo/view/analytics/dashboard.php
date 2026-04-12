<?php
$title = 'Analytics - SiteOps';
$csrf_token = session('csrf_token') ?? '';
ob_start();
?>
<div class="layui-breadcrumb" style="margin-bottom: 15px;">
    <a href="/dashboard">Home</a>
    <a><cite>Analytics</cite></a>
</div>

<div id="analytics-content">
    <!-- Filters -->
    <div class="analytics-filters" style="margin-bottom: 20px;">
        <div class="layui-form layui-form-pane" lay-filter="analyticsFilter">
            <div class="layui-inline">
                <label class="layui-form-label">Date Range</label>
                <div class="layui-input-inline" style="width:240px;">
                    <input type="text" class="layui-input" id="analytics-date-range" placeholder="Select date range" readonly>
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">Site</label>
                <div class="layui-input-inline" style="width:150px;">
                    <select id="analytics-site-filter" lay-filter="analyticsSite">
                        <option value="">All Sites</option>
                    </select>
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">Community</label>
                <div class="layui-input-inline" style="width:150px;">
                    <select id="analytics-community-filter" lay-filter="analyticsCommunity">
                        <option value="">All Communities</option>
                    </select>
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">Leader</label>
                <div class="layui-input-inline" style="width:150px;">
                    <input type="text" class="layui-input" id="analytics-leader-filter" placeholder="Leader name">
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">Product</label>
                <div class="layui-input-inline" style="width:150px;">
                    <input type="text" class="layui-input" id="analytics-product-filter" placeholder="Product name">
                </div>
            </div>
            <div class="layui-inline">
                <button type="button" class="layui-btn" id="analytics-apply-btn">
                    <i class="layui-icon layui-icon-search"></i> Apply
                </button>
                <button type="button" class="layui-btn layui-btn-primary" id="analytics-refresh-btn">
                    <i class="layui-icon layui-icon-refresh"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Loading -->
    <div id="analytics-loading" style="text-align:center; padding:40px; display:none;">
        <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop" style="font-size:30px;"></i>
        <p>Loading analytics data...</p>
    </div>

    <!-- Empty -->
    <div id="analytics-empty" style="text-align:center; padding:60px; display:none;">
        <i class="layui-icon layui-icon-face-surprised" style="font-size:50px; color:#999;"></i>
        <p style="color:#999; margin-top:10px;">No analytics data available for the selected filters.</p>
    </div>

    <!-- Error -->
    <div id="analytics-error" style="text-align:center; padding:60px; display:none;">
        <i class="layui-icon layui-icon-close-fill" style="font-size:50px; color:#F44336;"></i>
        <p style="color:#F44336; margin-top:10px;" id="analytics-error-msg">Failed to load analytics data.</p>
    </div>

    <!-- KPI cards (clickable for metric definition) -->
    <div class="layui-row layui-col-space15" id="analytics-kpi-row" style="display:none;">
        <div class="layui-col-xs6 layui-col-md2">
            <div class="layui-card kpi-card" data-metric="total_sales" style="cursor:pointer;">
                <div class="layui-card-header" style="font-size:13px; color:#666;">Total Sales</div>
                <div class="layui-card-body"><div class="kpi-val" id="akpi-total-sales" style="font-size:26px; font-weight:700; padding:8px 0;">--</div></div>
            </div>
        </div>
        <div class="layui-col-xs6 layui-col-md2">
            <div class="layui-card kpi-card" data-metric="avg_order_value" style="cursor:pointer;">
                <div class="layui-card-header" style="font-size:13px; color:#666;">Avg Order Value</div>
                <div class="layui-card-body"><div class="kpi-val" id="akpi-avg-order" style="font-size:26px; font-weight:700; padding:8px 0;">--</div></div>
            </div>
        </div>
        <div class="layui-col-xs6 layui-col-md2">
            <div class="layui-card kpi-card" data-metric="refund_rate" style="cursor:pointer;">
                <div class="layui-card-header" style="font-size:13px; color:#666;">Refund Rate</div>
                <div class="layui-card-body"><div class="kpi-val" id="akpi-refund-rate" style="font-size:26px; font-weight:700; padding:8px 0;">--</div></div>
            </div>
        </div>
        <div class="layui-col-xs6 layui-col-md3">
            <div class="layui-card kpi-card" data-metric="repeat_purchase" style="cursor:pointer;">
                <div class="layui-card-header" style="font-size:13px; color:#666;">Repeat Purchase</div>
                <div class="layui-card-body"><div class="kpi-val" id="akpi-repeat-purchase" style="font-size:26px; font-weight:700; padding:8px 0;">--</div></div>
            </div>
        </div>
        <div class="layui-col-xs6 layui-col-md3">
            <div class="layui-card kpi-card" data-metric="conversion_rate" style="cursor:pointer;">
                <div class="layui-card-header" style="font-size:13px; color:#666;">Conversion Rate</div>
                <div class="layui-card-body"><div class="kpi-val" id="akpi-conversion-rate" style="font-size:26px; font-weight:700; padding:8px 0;">--</div></div>
            </div>
        </div>
    </div>

    <!-- Data tables -->
    <div class="layui-row layui-col-space15" id="analytics-tables" style="display:none;">
        <div class="layui-col-md6">
            <div class="layui-card">
                <div class="layui-card-header">Leader Performance</div>
                <div class="layui-card-body">
                    <table id="analytics-leader-table" lay-filter="analyticsLeaderTable"></table>
                </div>
            </div>
        </div>
        <div class="layui-col-md6">
            <div class="layui-card">
                <div class="layui-card-header">Product Popularity</div>
                <div class="layui-card-body">
                    <table id="analytics-product-table" lay-filter="analyticsProductTable"></table>
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
layui.use(['laydate', 'table', 'layer', 'form'], function () {
    'use strict';
    var laydate = layui.laydate;
    var table = layui.table;
    var layer = layui.layer;
    var form = layui.form;

    var METRIC_DEFS = {
        total_sales: { title: 'Total Sales', desc: 'Sum of all completed order amounts within the selected period, excluding refunded orders.' },
        avg_order_value: { title: 'Average Order Value', desc: 'Total sales divided by the number of completed orders.' },
        refund_rate: { title: 'Refund Rate', desc: 'Percentage of orders refunded relative to total orders.' },
        repeat_purchase: { title: 'Repeat Purchase Rate', desc: 'Percentage of customers with more than one order in the period.' },
        conversion_rate: { title: 'Conversion Rate', desc: 'Percentage of site visitors who completed a purchase.' }
    };

    var dateRange = '';
    var refreshInProgress = false;

    laydate.render({
        elem: '#analytics-date-range',
        range: true,
        done: function (value) { dateRange = value; }
    });

    table.render({
        elem: '#analytics-leader-table',
        cols: [[
            { type: 'numbers', title: '#', width: 50 },
            { field: 'name', title: 'Leader', minWidth: 140 },
            { field: 'community', title: 'Community', minWidth: 100 },
            { field: 'orders', title: 'Orders', width: 80, sort: true },
            { field: 'revenue', title: 'Revenue', width: 100, sort: true }
        ]],
        data: [], page: false, limit: 20,
        text: { none: 'No data available' }
    });

    table.render({
        elem: '#analytics-product-table',
        cols: [[
            { type: 'numbers', title: '#', width: 50 },
            { field: 'name', title: 'Product', minWidth: 150 },
            { field: 'orders', title: 'Orders', width: 90, sort: true },
            { field: 'revenue', title: 'Revenue', width: 110, sort: true }
        ]],
        data: [], page: false, limit: 20,
        text: { none: 'No data available' }
    });

    function setState(state) {
        document.getElementById('analytics-loading').style.display = state === 'loading' ? '' : 'none';
        document.getElementById('analytics-empty').style.display = state === 'empty' ? '' : 'none';
        document.getElementById('analytics-error').style.display = state === 'error' ? '' : 'none';
        document.getElementById('analytics-kpi-row').style.display = state === 'content' ? '' : 'none';
        document.getElementById('analytics-tables').style.display = state === 'content' ? '' : 'none';
    }

    function loadAnalytics() {
        var params = [];
        if (dateRange) {
            var parts = dateRange.split(' - ');
            if (parts.length === 2) {
                params.push('date_from=' + encodeURIComponent(parts[0]));
                params.push('date_to=' + encodeURIComponent(parts[1]));
            }
        }
        var siteVal = document.getElementById('analytics-site-filter').value;
        if (siteVal) params.push('site_id=' + encodeURIComponent(siteVal));
        var communityVal = document.getElementById('analytics-community-filter').value;
        if (communityVal) params.push('community_id=' + encodeURIComponent(communityVal));
        var leaderVal = document.getElementById('analytics-leader-filter').value.trim();
        if (leaderVal) params.push('group_leader_id=' + encodeURIComponent(leaderVal));
        var productVal = document.getElementById('analytics-product-filter').value.trim();
        if (productVal) params.push('product_id=' + encodeURIComponent(productVal));
        var qs = params.length ? '?' + params.join('&') : '';
        setState('loading');

        SiteOps.request('GET', '/api/v1/analytics/dashboard' + qs)
            .then(function (res) {
                var data = res.data || res;
                var widgets = data.widgets || {};
                var metrics = widgets.metrics || widgets.kpis || widgets;
                var hasData = false;

                if (metrics.total_sales != null) hasData = true;
                document.getElementById('akpi-total-sales').textContent = metrics.total_sales != null ? '$' + Number(metrics.total_sales).toLocaleString() : '--';
                document.getElementById('akpi-avg-order').textContent = metrics.avg_order_value != null ? '$' + Number(metrics.avg_order_value).toFixed(2) : '--';
                document.getElementById('akpi-refund-rate').textContent = metrics.refund_rate != null ? Number(metrics.refund_rate).toFixed(1) + '%' : '--';
                document.getElementById('akpi-repeat-purchase').textContent = metrics.repeat_purchase_rate != null ? Number(metrics.repeat_purchase_rate).toFixed(1) + '%' : '--';
                document.getElementById('akpi-conversion-rate').textContent = metrics.group_conversion != null ? Number(metrics.group_conversion).toFixed(1) : '--';

                var leaders = metrics.leader_performance || widgets.leader_performance || [];
                var products = metrics.product_popularity || widgets.product_popularity || [];
                if (leaders.length) { hasData = true; table.reload('analytics-leader-table', { data: leaders }); }
                if (products.length) { hasData = true; table.reload('analytics-product-table', { data: products }); }

                setState(hasData ? 'content' : 'empty');
            })
            .catch(function (err) {
                if (err.status === 401) return;
                document.getElementById('analytics-error-msg').textContent = err.message || 'Failed to load analytics data.';
                setState('error');
            });
    }

    // Metric definition drawer
    document.querySelectorAll('.kpi-card').forEach(function (card) {
        card.addEventListener('click', function () {
            var metric = card.getAttribute('data-metric');
            var def = METRIC_DEFS[metric];
            if (!def) return;
            layer.open({
                type: 1,
                title: 'Metric Definition: ' + def.title,
                content: '<div style="padding:20px; line-height:1.8;">' + def.desc + '</div>',
                area: ['420px', '200px'],
                shadeClose: true
            });
        });
    });

    // Apply filters
    document.getElementById('analytics-apply-btn').addEventListener('click', loadAnalytics);

    // Refresh with rate limit
    document.getElementById('analytics-refresh-btn').addEventListener('click', function () {
        if (refreshInProgress) {
            layer.msg('A refresh is already in progress. Please wait.', { icon: 0, time: 2000 });
            return;
        }
        refreshInProgress = true;
        var btn = this;
        btn.classList.add('layui-btn-disabled');

        SiteOps.request('POST', '/api/v1/analytics/refresh', {})
            .then(function (res) {
                var data = res.data || res;
                SiteOps.showSuccess(data.message || 'Refresh queued');
                setTimeout(function () {
                    loadAnalytics();
                    refreshInProgress = false;
                    btn.classList.remove('layui-btn-disabled');
                }, 3000);
            })
            .catch(function (err) {
                refreshInProgress = false;
                btn.classList.remove('layui-btn-disabled');
                if (err.status === 429) {
                    layer.msg('Rate limited: please wait before refreshing again.', { icon: 0, time: 3000 });
                } else {
                    SiteOps.showError(err);
                }
            });
    });

    loadAnalytics();
});
</script>
<?php
$scripts = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>
