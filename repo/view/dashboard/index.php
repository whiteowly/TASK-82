<?php
$title = 'Dashboard - SiteOps';
$csrf_token = session('csrf_token') ?? '';
ob_start();
?>
<div class="layui-breadcrumb" style="margin-bottom: 15px;">
    <a href="/dashboard">Home</a>
    <a><cite>Dashboard</cite></a>
</div>

<!-- Filters -->
<div class="layui-card">
    <div class="layui-card-body">
        <div class="layui-form layui-form-pane" lay-filter="dashboardFilter">
            <div class="layui-inline">
                <label class="layui-form-label">Date Range</label>
                <div class="layui-input-inline" style="width:240px;">
                    <input type="text" class="layui-input" id="dash-date-range" placeholder="Select date range" readonly>
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">Site</label>
                <div class="layui-input-inline" style="width:150px;">
                    <select id="dash-site-filter" lay-filter="dashSite">
                        <option value="">All Sites</option>
                    </select>
                </div>
            </div>
            <div class="layui-inline">
                <label class="layui-form-label">Community</label>
                <div class="layui-input-inline" style="width:150px;">
                    <select id="dash-community-filter" lay-filter="dashCommunity">
                        <option value="">All Communities</option>
                    </select>
                </div>
            </div>
            <div class="layui-inline">
                <button type="button" class="layui-btn layui-btn-primary" id="dash-refresh-btn">
                    <i class="layui-icon layui-icon-refresh"></i> Refresh Data
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Loading state -->
<div id="dash-loading" style="text-align:center; padding:40px; display:none;">
    <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop" style="font-size:30px;"></i>
    <p>Loading dashboard...</p>
</div>

<!-- Empty state -->
<div id="dash-empty" style="text-align:center; padding:60px; display:none;">
    <i class="layui-icon layui-icon-face-surprised" style="font-size:50px; color:#999;"></i>
    <p style="color:#999; margin-top:10px;">No dashboard data available for the selected filters.</p>
</div>

<!-- KPI Cards -->
<div class="layui-row layui-col-space15" id="dash-kpi-row" style="display:none;">
    <div class="layui-col-xs6 layui-col-md2">
        <div class="layui-card kpi-card" data-metric="total_sales" style="cursor:pointer;">
            <div class="layui-card-header" style="font-size:13px; color:#666;">Total Sales</div>
            <div class="layui-card-body">
                <div class="kpi-value" id="kpi-total-sales" style="font-size:26px; font-weight:700; color:#333; padding:8px 0;">--</div>
            </div>
        </div>
    </div>
    <div class="layui-col-xs6 layui-col-md2">
        <div class="layui-card kpi-card" data-metric="avg_order_value" style="cursor:pointer;">
            <div class="layui-card-header" style="font-size:13px; color:#666;">Avg Order Value</div>
            <div class="layui-card-body">
                <div class="kpi-value" id="kpi-avg-order" style="font-size:26px; font-weight:700; color:#333; padding:8px 0;">--</div>
            </div>
        </div>
    </div>
    <div class="layui-col-xs6 layui-col-md2">
        <div class="layui-card kpi-card" data-metric="refund_rate" style="cursor:pointer;">
            <div class="layui-card-header" style="font-size:13px; color:#666;">Refund Rate</div>
            <div class="layui-card-body">
                <div class="kpi-value" id="kpi-refund-rate" style="font-size:26px; font-weight:700; color:#333; padding:8px 0;">--</div>
            </div>
        </div>
    </div>
    <div class="layui-col-xs6 layui-col-md3">
        <div class="layui-card kpi-card" data-metric="repeat_purchase" style="cursor:pointer;">
            <div class="layui-card-header" style="font-size:13px; color:#666;">Repeat Purchase</div>
            <div class="layui-card-body">
                <div class="kpi-value" id="kpi-repeat-purchase" style="font-size:26px; font-weight:700; color:#333; padding:8px 0;">--</div>
            </div>
        </div>
    </div>
    <div class="layui-col-xs6 layui-col-md3">
        <div class="layui-card kpi-card" data-metric="conversion_rate" style="cursor:pointer;">
            <div class="layui-card-header" style="font-size:13px; color:#666;">Conversion Rate</div>
            <div class="layui-card-body">
                <div class="kpi-value" id="kpi-conversion-rate" style="font-size:26px; font-weight:700; color:#333; padding:8px 0;">--</div>
            </div>
        </div>
    </div>
</div>

<!-- Data Tables -->
<div class="layui-row layui-col-space15" id="dash-tables" style="display:none;">
    <div class="layui-col-md6">
        <div class="layui-card">
            <div class="layui-card-header">Product Popularity</div>
            <div class="layui-card-body">
                <table id="dash-product-table" lay-filter="dashProductTable"></table>
            </div>
        </div>
    </div>
    <div class="layui-col-md6">
        <div class="layui-card">
            <div class="layui-card-header">Leader Performance</div>
            <div class="layui-card-body">
                <table id="dash-leader-table" lay-filter="dashLeaderTable"></table>
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
        avg_order_value: { title: 'Average Order Value', desc: 'Total sales divided by the number of completed orders in the period.' },
        refund_rate: { title: 'Refund Rate', desc: 'Percentage of orders that were refunded relative to total orders placed.' },
        repeat_purchase: { title: 'Repeat Purchase Rate', desc: 'Percentage of customers who placed more than one order within the period.' },
        conversion_rate: { title: 'Conversion Rate', desc: 'Percentage of site visitors who completed a purchase.' }
    };

    var dateRange = '';
    var siteFilter = '';
    var refreshInProgress = false;

    laydate.render({
        elem: '#dash-date-range',
        range: true,
        done: function (value) {
            dateRange = value;
            loadDashboard();
        }
    });

    table.render({
        elem: '#dash-product-table',
        cols: [[
            { type: 'numbers', title: '#', width: 50 },
            { field: 'name', title: 'Product', minWidth: 150 },
            { field: 'orders', title: 'Orders', width: 90, sort: true },
            { field: 'revenue', title: 'Revenue', width: 110, sort: true }
        ]],
        data: [],
        page: false,
        limit: 20,
        text: { none: 'No product data available' }
    });

    table.render({
        elem: '#dash-leader-table',
        cols: [[
            { type: 'numbers', title: '#', width: 50 },
            { field: 'name', title: 'Leader', minWidth: 140 },
            { field: 'community', title: 'Community', minWidth: 100 },
            { field: 'orders', title: 'Orders', width: 80, sort: true },
            { field: 'revenue', title: 'Revenue', width: 100, sort: true }
        ]],
        data: [],
        page: false,
        limit: 20,
        text: { none: 'No leader data available' }
    });

    function showLoading() {
        document.getElementById('dash-loading').style.display = '';
        document.getElementById('dash-empty').style.display = 'none';
        document.getElementById('dash-kpi-row').style.display = 'none';
        document.getElementById('dash-tables').style.display = 'none';
    }

    function showEmpty() {
        document.getElementById('dash-loading').style.display = 'none';
        document.getElementById('dash-empty').style.display = '';
        document.getElementById('dash-kpi-row').style.display = 'none';
        document.getElementById('dash-tables').style.display = 'none';
    }

    function showContent() {
        document.getElementById('dash-loading').style.display = 'none';
        document.getElementById('dash-empty').style.display = 'none';
        document.getElementById('dash-kpi-row').style.display = '';
        document.getElementById('dash-tables').style.display = '';
    }

    function loadDashboard() {
        var params = [];
        if (dateRange) {
            var parts = dateRange.split(' - ');
            if (parts.length === 2) {
                params.push('date_from=' + encodeURIComponent(parts[0]));
                params.push('date_to=' + encodeURIComponent(parts[1]));
            }
        }
        if (siteFilter) params.push('site_id=' + encodeURIComponent(siteFilter));
        var qs = params.length ? '?' + params.join('&') : '';

        showLoading();

        SiteOps.request('GET', '/api/v1/analytics/dashboard' + qs)
            .then(function (res) {
                var data = res.data || res;
                var widgets = data.widgets || {};
                var kpis = widgets.kpis || widgets;
                var hasData = false;

                if (kpis.total_sales != null) hasData = true;
                document.getElementById('kpi-total-sales').textContent = kpis.total_sales != null ? '$' + Number(kpis.total_sales).toLocaleString() : '--';
                document.getElementById('kpi-avg-order').textContent = kpis.avg_order_value != null ? '$' + Number(kpis.avg_order_value).toFixed(2) : '--';
                document.getElementById('kpi-refund-rate').textContent = kpis.refund_rate != null ? Number(kpis.refund_rate).toFixed(1) + '%' : '--';
                document.getElementById('kpi-repeat-purchase').textContent = kpis.repeat_purchase != null ? Number(kpis.repeat_purchase).toFixed(1) + '%' : '--';
                document.getElementById('kpi-conversion-rate').textContent = kpis.conversion_rate != null ? Number(kpis.conversion_rate).toFixed(1) + '%' : '--';

                var products = widgets.product_popularity || [];
                var leaders = widgets.leader_performance || [];
                if (products.length) { hasData = true; table.reload('dash-product-table', { data: products }); }
                if (leaders.length) { hasData = true; table.reload('dash-leader-table', { data: leaders }); }

                hasData ? showContent() : showEmpty();
            })
            .catch(function (err) {
                showEmpty();
                if (err.status !== 401) SiteOps.showError(err);
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

    // Refresh with rate limit
    document.getElementById('dash-refresh-btn').addEventListener('click', function () {
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
                    loadDashboard();
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

    form.on('select(dashSite)', function (data) {
        siteFilter = data.value;
        loadDashboard();
    });

    loadDashboard();
});
</script>
<?php
$scripts = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>
