<?php
$title = 'Review Queue - SiteOps';
$csrf_token = session('csrf_token') ?? '';
ob_start();
?>
<div class="layui-breadcrumb" style="margin-bottom: 15px;">
    <a href="/dashboard">Home</a>
    <a href="/recipes/editor">Recipes</a>
    <a><cite>Review Queue</cite></a>
</div>

<div id="review-queue">
    <!-- Toolbar -->
    <div style="margin-bottom: 15px;">
        <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="btn-refresh-queue">
            <i class="layui-icon layui-icon-refresh"></i> Refresh Queue
        </button>
    </div>

    <!-- Loading -->
    <div id="review-loading" style="text-align:center; padding:40px; display:none;">
        <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop" style="font-size:30px;"></i>
        <p>Loading review queue...</p>
    </div>

    <!-- Empty -->
    <div id="review-empty" style="text-align:center; padding:60px; display:none;">
        <i class="layui-icon layui-icon-face-smile" style="font-size:50px; color:#999;"></i>
        <p style="color:#999; margin-top:10px;">No recipes pending review.</p>
    </div>

    <!-- Queue table -->
    <table id="review-table" lay-filter="reviewTable"></table>

    <!-- Review detail panel (hidden until row click) -->
    <div id="review-detail-panel" style="display:none;">
        <div class="layui-card">
            <div class="layui-card-header">
                <span id="review-recipe-title" style="font-weight:600;"></span>
                <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="btn-back-queue" style="float:right;">
                    <i class="layui-icon layui-icon-return"></i> Back to Queue
                </button>
            </div>
            <div class="layui-card-body">
                <!-- Version comparison -->
                <div style="margin-bottom:20px; border:1px solid #e6e6e6; padding:15px; border-radius:4px;">
                    <h4 style="margin-bottom:10px;">Version Changes</h4>
                    <div id="review-diff-content">
                        <div id="review-diff-loading" style="text-align:center; padding:20px;">
                            <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i> Loading diff...
                        </div>
                    </div>
                </div>

                <!-- Comment form -->
                <div style="margin-bottom:20px;">
                    <h4 style="margin-bottom:10px;">Add Comment</h4>
                    <div class="layui-form" lay-filter="reviewCommentForm">
                        <div class="layui-form-item">
                            <div class="layui-inline">
                                <label class="layui-form-label" style="width:90px;">Anchor Type</label>
                                <div class="layui-input-inline" style="width:150px;">
                                    <select id="review-anchor-type" lay-filter="reviewAnchorType">
                                        <option value="general">General</option>
                                        <option value="step">Step</option>
                                        <option value="field">Field</option>
                                        <option value="image">Image</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="layui-form-item layui-form-text">
                            <div class="layui-input-block" style="margin-left:0;">
                                <textarea id="review-comment-text" class="layui-textarea" placeholder="Enter your review comments..." rows="3"></textarea>
                            </div>
                        </div>
                        <button type="button" class="layui-btn layui-btn-sm" id="btn-add-comment">
                            <i class="layui-icon layui-icon-reply-fill"></i> Add Comment
                        </button>
                    </div>
                </div>

                <!-- Comment history -->
                <div style="margin-bottom:20px;">
                    <h4 style="margin-bottom:10px;">Comment History</h4>
                    <div id="review-comments-list" style="max-height:300px; overflow-y:auto;">
                        <p style="color:#999;">No comments yet.</p>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="layui-form-item" style="border-top:1px solid #e6e6e6; padding-top:15px;">
                    <button type="button" class="layui-btn layui-btn-normal" id="btn-approve-recipe">
                        <i class="layui-icon layui-icon-ok"></i> Approve
                    </button>
                    <button type="button" class="layui-btn layui-btn-warm" id="btn-publish-recipe" style="display:none;">
                        <i class="layui-icon layui-icon-release"></i> Publish
                    </button>
                    <button type="button" class="layui-btn layui-btn-danger" id="btn-reject-recipe">
                        <i class="layui-icon layui-icon-close"></i> Reject
                    </button>
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
layui.use(['table', 'layer', 'form'], function () {
    'use strict';
    var table = layui.table;
    var layer = layui.layer;
    var form = layui.form;

    var currentRecipeId = null;
    var currentVersionId = null;

    // Review table
    table.render({
        elem: '#review-table',
        cols: [[
            { type: 'numbers', title: '#', width: 50 },
            { field: 'title', title: 'Recipe', minWidth: 200 },
            { field: 'status', title: 'Status', width: 110, templet: function (d) {
                return '<span style="color:#1E88E5; font-weight:600;">In Review</span>';
            }},
            { field: 'created_at', title: 'Submitted', width: 160 },
            { field: 'updated_at', title: 'Updated', width: 160 },
            { title: 'Actions', width: 120, toolbar: '#review-row-actions' }
        ]],
        data: [],
        page: true,
        limit: 20,
        text: { none: 'No recipes pending review' }
    });

    var actionsHtml = '<div><a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="review"><i class="layui-icon layui-icon-search"></i> Review</a></div>';
    var tpl = document.createElement('script');
    tpl.type = 'text/html';
    tpl.id = 'review-row-actions';
    tpl.innerHTML = actionsHtml;
    document.body.appendChild(tpl);

    function loadQueue() {
        document.getElementById('review-loading').style.display = '';
        document.getElementById('review-empty').style.display = 'none';
        document.getElementById('review-table').closest('.layui-table-view')
            && (document.getElementById('review-table').closest('.layui-table-view').style.display = 'none');

        SiteOps.request('GET', '/api/v1/recipes?status=in_review&per_page=50')
            .then(function (res) {
                var data = res.data || res;
                var items = data.items || [];
                document.getElementById('review-loading').style.display = 'none';
                if (!items.length) {
                    document.getElementById('review-empty').style.display = '';
                } else {
                    var tableView = document.getElementById('review-table').closest('.layui-table-view');
                    if (tableView) tableView.style.display = '';
                }
                table.reload('review-table', { data: items });
            })
            .catch(function (err) {
                document.getElementById('review-loading').style.display = 'none';
                SiteOps.showError(err);
            });
    }

    table.on('tool(reviewTable)', function (obj) {
        if (obj.event === 'review') openReviewPanel(obj.data);
    });

    function openReviewPanel(recipe) {
        currentRecipeId = recipe.id;
        document.getElementById('review-recipe-title').textContent = 'Review: ' + (recipe.title || 'Recipe #' + recipe.id);

        // Hide table, show detail
        var tableView = document.getElementById('review-table');
        if (tableView) { var tv = tableView.closest('.layui-table-view'); if (tv) tv.style.display = 'none'; }
        var toolbar = document.querySelector('#review-queue > div:first-child');
        if (toolbar) toolbar.style.display = 'none';
        var emptyEl = document.getElementById('review-empty');
        if (emptyEl) emptyEl.style.display = 'none';
        document.getElementById('review-detail-panel').style.display = '';

        // Load diff
        document.getElementById('review-diff-content').innerHTML = '<div style="text-align:center; padding:20px;"><i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i> Loading diff...</div>';

        SiteOps.request('GET', '/api/v1/recipes/' + recipe.id)
            .then(function (res) {
                var rd = res.data || res;
                var versions = rd.versions || [];
                currentVersionId = versions.length ? versions[0].id : null;

                // Load diff
                return SiteOps.request('GET', '/api/v1/recipe-versions/' + (currentVersionId || recipe.id) + '/diff');
            })
            .then(function (res) {
                var data = res.data || res;
                var changes = data.changes || [];
                if (!changes.length) {
                    document.getElementById('review-diff-content').innerHTML = '<p style="color:#999;">No previous version to compare. This is the first version.</p>';
                } else {
                    var html = '<table class="layui-table"><thead><tr><th>Field</th><th>Previous</th><th>Current</th></tr></thead><tbody>';
                    changes.forEach(function (c) {
                        var oldVal = c.field === 'steps' ? JSON.stringify(c.old, null, 2) : (c.old || '--');
                        var newVal = c.field === 'steps' ? JSON.stringify(c['new'], null, 2) : (c['new'] || '--');
                        html += '<tr><td><strong>' + c.field + '</strong></td><td style="color:#c62828;">' + oldVal + '</td><td style="color:#2e7d32;">' + newVal + '</td></tr>';
                    });
                    html += '</tbody></table>';
                    document.getElementById('review-diff-content').innerHTML = html;
                }
            })
            .catch(function (err) {
                document.getElementById('review-diff-content').innerHTML = '<p style="color:#F44336;">Failed to load diff.</p>';
            });

        // Load persisted comment history
        var commentsContainer = document.getElementById('review-comments-list');
        commentsContainer.innerHTML = '<p style="color:#999;">Loading comments...</p>';
        SiteOps.request('GET', '/api/v1/recipe-versions/' + versionId + '/comments')
            .then(function (res) {
                var data = res.data || res;
                var comments = data.comments || [];
                if (!comments.length) {
                    commentsContainer.innerHTML = '<p style="color:#999;">No comments yet.</p>';
                } else {
                    commentsContainer.innerHTML = '';
                    comments.forEach(function (c) {
                        var div = document.createElement('div');
                        div.style.cssText = 'padding:8px 12px; background:#f7f7f7; border-radius:4px; margin-bottom:8px;';
                        div.innerHTML = '<strong>[' + (c.anchor_type || 'general') + ']</strong> '
                            + (c.content || '')
                            + ' <span style="color:#999; font-size:12px;">' + (c.created_at || '') + '</span>';
                        commentsContainer.appendChild(div);
                    });
                }
            })
            .catch(function () {
                commentsContainer.innerHTML = '<p style="color:#999;">No comments yet.</p>';
            });
        form.render();
    }

    function closeReviewPanel() {
        document.getElementById('review-detail-panel').style.display = 'none';
        document.querySelector('#review-queue > div:first-child').style.display = '';
        var tableView = document.getElementById('review-table').closest('.layui-table-view');
        if (tableView) tableView.style.display = '';
        loadQueue();
    }

    document.getElementById('btn-back-queue').addEventListener('click', closeReviewPanel);
    document.getElementById('btn-refresh-queue').addEventListener('click', loadQueue);

    // Add comment
    document.getElementById('btn-add-comment').addEventListener('click', function () {
        var content = document.getElementById('review-comment-text').value.trim();
        if (!content) { layer.msg('Please enter a comment.', { icon: 0 }); return; }
        var anchorType = document.getElementById('review-anchor-type').value;

        SiteOps.request('POST', '/api/v1/recipe-versions/' + (currentVersionId || currentRecipeId) + '/comments', {
            content: content,
            anchor_type: anchorType
        })
        .then(function (res) {
            SiteOps.showSuccess('Comment added');
            document.getElementById('review-comment-text').value = '';
            // Append to list
            var list = document.getElementById('review-comments-list');
            if (list.querySelector('p')) list.innerHTML = '';
            var div = document.createElement('div');
            div.style.cssText = 'padding:8px 12px; background:#f7f7f7; border-radius:4px; margin-bottom:8px;';
            div.innerHTML = '<strong>[' + anchorType + ']</strong> ' + content + ' <span style="color:#999; font-size:12px;">just now</span>';
            list.appendChild(div);
        })
        .catch(function (err) { SiteOps.showError(err); });
    });

    // Approve
    document.getElementById('btn-approve-recipe').addEventListener('click', function () {
        if (!confirm('Approve this recipe?')) return;
        SiteOps.request('POST', '/api/v1/recipe-versions/' + (currentVersionId || currentRecipeId) + '/approve', {})
            .then(function () {
                document.getElementById('btn-publish-recipe').style.display = '';
                document.getElementById('btn-approve-recipe').style.display = 'none';
                document.getElementById('btn-reject-recipe').style.display = 'none';
                try { layer.msg('Recipe approved — you can now publish it', { icon: 1 }); } catch (e) {}
            })
            .catch(function (err) { SiteOps.showError(err); });
    });

    // Publish (shown after approval)
    document.getElementById('btn-publish-recipe').addEventListener('click', function () {
        if (!confirm('Publish this recipe to the catalog?')) return;
        SiteOps.request('POST', '/api/v1/recipes/' + currentRecipeId + '/publish', {})
            .then(function () {
                try { layer.msg('Recipe published to catalog', { icon: 1 }); } catch (e) {}
                closeReviewPanel();
            })
            .catch(function (err) { SiteOps.showError(err); });
    });

    // Reject
    document.getElementById('btn-reject-recipe').addEventListener('click', function () {
        layer.confirm('Reject this recipe? It will be returned to draft.', { icon: 0, title: 'Confirm Rejection' }, function (index) {
            layer.close(index);
            SiteOps.request('POST', '/api/v1/recipe-versions/' + (currentVersionId || currentRecipeId) + '/reject', {})
                .then(function () {
                    SiteOps.showSuccess('Recipe rejected');
                    closeReviewPanel();
                })
                .catch(function (err) { SiteOps.showError(err); });
        });
    });

    loadQueue();
});
</script>
<?php
$scripts = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>
