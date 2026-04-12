<?php
$title = 'Published Recipes - SiteOps';
$csrf_token = session('csrf_token') ?? '';
ob_start();
?>
<div class="layui-breadcrumb" style="margin-bottom: 15px;">
    <a href="/dashboard">Home</a>
    <a><cite>Published Recipes</cite></a>
</div>

<div id="catalog-content">
    <!-- Search/Filter bar -->
    <div class="layui-card" style="margin-bottom:15px;">
        <div class="layui-card-body">
            <div class="layui-form" lay-filter="catalogFilter">
                <div class="layui-inline">
                    <div class="layui-input-inline" style="width:280px;">
                        <input type="text" class="layui-input" id="catalog-search" placeholder="Search published recipes...">
                    </div>
                </div>
                <div class="layui-inline">
                    <select id="catalog-difficulty-filter" lay-filter="catalogDifficulty">
                        <option value="">All Difficulties</option>
                        <option value="easy">Easy</option>
                        <option value="medium">Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
                <div class="layui-inline">
                    <button type="button" class="layui-btn layui-btn-sm" id="catalog-search-btn">
                        <i class="layui-icon layui-icon-search"></i> Search
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading -->
    <div id="catalog-loading" style="text-align:center; padding:40px; display:none;">
        <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop" style="font-size:30px;"></i>
        <p>Loading catalog...</p>
    </div>

    <!-- Empty -->
    <div id="catalog-empty" style="text-align:center; padding:60px; display:none;">
        <i class="layui-icon layui-icon-face-surprised" style="font-size:50px; color:#999;"></i>
        <p style="color:#999; margin-top:10px;">No published recipes found.</p>
    </div>

    <!-- Recipe grid -->
    <div class="layui-row layui-col-space15" id="catalog-grid"></div>

    <!-- Pagination -->
    <div id="catalog-pagination" style="text-align:center; margin-top:20px;"></div>
</div>

<?php
$content = ob_get_clean();
ob_start();
?>
<script>
layui.use(['layer', 'laypage', 'form'], function () {
    'use strict';
    var layer = layui.layer;
    var laypage = layui.laypage;
    var form = layui.form;

    var currentPage = 1;
    var perPage = 12;
    var totalItems = 0;
    var searchQuery = '';
    var difficultyFilter = '';

    function loadCatalog() {
        var params = ['page=' + currentPage, 'per_page=' + perPage];
        document.getElementById('catalog-loading').style.display = '';
        document.getElementById('catalog-empty').style.display = 'none';
        document.getElementById('catalog-grid').innerHTML = '';

        SiteOps.request('GET', '/api/v1/catalog/recipes?' + params.join('&'))
            .then(function (res) {
                document.getElementById('catalog-loading').style.display = 'none';
                var data = res.data || res;
                var items = data.items || [];
                totalItems = (data.pagination && data.pagination.total) || items.length;

                // Client-side filtering
                if (searchQuery) {
                    var q = searchQuery.toLowerCase();
                    items = items.filter(function (r) {
                        return (r.title || '').toLowerCase().indexOf(q) !== -1 ||
                               (r.category || '').toLowerCase().indexOf(q) !== -1;
                    });
                }
                if (difficultyFilter) {
                    items = items.filter(function (r) { return r.difficulty === difficultyFilter; });
                }

                if (!items.length) {
                    document.getElementById('catalog-empty').style.display = '';
                    return;
                }

                var grid = document.getElementById('catalog-grid');
                items.forEach(function (recipe) {
                    var tags = '';
                    if (recipe.tags) {
                        var tagArr = typeof recipe.tags === 'string' ? recipe.tags.split(',') : (recipe.tags || []);
                        tagArr.forEach(function (t) {
                            t = t.trim();
                            if (t) tags += '<span class="layui-badge layui-bg-gray" style="margin-right:4px;">' + t + '</span>';
                        });
                    }
                    var diffColors = { easy: '#43A047', medium: '#FFB300', hard: '#E53935' };
                    var diffColor = diffColors[recipe.difficulty] || '#999';

                    var col = document.createElement('div');
                    col.className = 'layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3';
                    col.innerHTML =
                        '<div class="layui-card" style="cursor:pointer;" data-id="' + recipe.id + '">' +
                            '<div class="layui-card-header" style="font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + (recipe.title || 'Untitled') + '</div>' +
                            '<div class="layui-card-body" style="min-height:100px;">' +
                                '<p><span style="color:' + diffColor + '; font-weight:600;">' + (recipe.difficulty || '--') + '</span></p>' +
                                '<p style="color:#666; font-size:13px;">Cook time: ' + (recipe.cook_time || '--') + ' min</p>' +
                                '<div style="margin-top:8px;">' + tags + '</div>' +
                            '</div>' +
                        '</div>';
                    grid.appendChild(col);

                    col.querySelector('.layui-card').addEventListener('click', function () {
                        openRecipeDetail(recipe.id);
                    });
                });

                // Pagination
                if (totalItems > perPage) {
                    laypage.render({
                        elem: 'catalog-pagination',
                        count: totalItems,
                        limit: perPage,
                        curr: currentPage,
                        jump: function (obj, first) {
                            if (!first) {
                                currentPage = obj.curr;
                                loadCatalog();
                            }
                        }
                    });
                }
            })
            .catch(function (err) {
                document.getElementById('catalog-loading').style.display = 'none';
                document.getElementById('catalog-empty').style.display = '';
                SiteOps.showError(err);
            });
    }

    function openRecipeDetail(id) {
        var loadIdx = layer.load();
        SiteOps.request('GET', '/api/v1/catalog/recipes/' + id)
            .then(function (res) {
                layer.close(loadIdx);
                var recipe = res.data || res;
                var pv = recipe.published_version || {};
                var steps = [];
                try { steps = JSON.parse(pv.content_json || '[]'); } catch (e) {}

                var stepsHtml = '';
                if (steps.length) {
                    stepsHtml = '<ol style="padding-left:20px;">';
                    steps.forEach(function (s) {
                        stepsHtml += '<li style="margin-bottom:6px;">' + (s.instruction || '') +
                            (s.duration ? ' <span style="color:#999;">(' + s.duration + ' min)</span>' : '') + '</li>';
                    });
                    stepsHtml += '</ol>';
                } else {
                    stepsHtml = '<p style="color:#999;">No steps available.</p>';
                }

                var html =
                    '<div style="padding:20px;">' +
                        '<p><strong>Difficulty:</strong> ' + (pv.difficulty || recipe.difficulty || '--') + '</p>' +
                        '<p><strong>Prep Time:</strong> ' + (pv.prep_time || '--') + ' min</p>' +
                        '<p><strong>Cook Time:</strong> ' + (pv.cook_time || '--') + ' min</p>' +
                        '<p><strong>Total Time:</strong> ' + (pv.total_time || '--') + ' min</p>' +
                        '<hr>' +
                        '<h4>Steps</h4>' + stepsHtml +
                    '</div>';

                layer.open({
                    type: 1,
                    title: recipe.title || 'Recipe Detail',
                    content: html,
                    area: ['600px', '500px'],
                    shadeClose: true
                });
            })
            .catch(function (err) {
                layer.close(loadIdx);
                SiteOps.showError(err);
            });
    }

    // Search
    document.getElementById('catalog-search-btn').addEventListener('click', function () {
        searchQuery = document.getElementById('catalog-search').value.trim();
        currentPage = 1;
        loadCatalog();
    });

    document.getElementById('catalog-search').addEventListener('keyup', function (e) {
        if (e.key === 'Enter') {
            searchQuery = this.value.trim();
            currentPage = 1;
            loadCatalog();
        }
    });

    form.on('select(catalogDifficulty)', function (data) {
        difficultyFilter = data.value;
        currentPage = 1;
        loadCatalog();
    });

    loadCatalog();
});
</script>
<?php
$scripts = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>
