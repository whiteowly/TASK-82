/**
 * SiteOps - Recipe review queue page
 */
layui.use(['table', 'form', 'layer', 'upload', 'laydate', 'element'], function () {
    'use strict';

    var table = layui.table;
    var layer = layui.layer;

    var currentVersionId = null;

    var els = {
        loading: document.getElementById('review-loading'),
        empty: document.getElementById('review-empty'),
        detailPanel: document.getElementById('review-detail-panel'),
        diffContent: document.getElementById('review-diff-content'),
        commentsList: document.getElementById('review-comments-list'),
        recipeTitle: document.getElementById('review-recipe-title')
    };

    // --- Load review queue ---
    function loadQueue() {
        showQueue();
        els.loading.style.display = 'block';

        SiteOps.request('GET', '/api/v1/recipes?status=in_review')
            .then(function (res) {
                els.loading.style.display = 'none';
                var recipes = res.data || res;
                if (!Array.isArray(recipes)) recipes = [];

                if (recipes.length === 0) {
                    els.empty.style.display = 'block';
                    return;
                }

                els.empty.style.display = 'none';
                table.render({
                    elem: '#review-table',
                    cols: [[
                        { field: 'id', title: 'ID', width: 80, sort: true },
                        { field: 'title', title: 'Recipe', minWidth: 200 },
                        { field: 'author', title: 'Author', width: 150, templet: function (d) {
                            return escapeHtml(d.author_name || d.author || '');
                        }},
                        { field: 'submitted_at', title: 'Submitted', width: 170, sort: true },
                        { field: 'version_id', title: 'Version', width: 100 },
                        { title: 'Actions', width: 120, toolbar: '#reviewTpl', fixed: 'right' }
                    ]],
                    data: recipes,
                    page: recipes.length > 20,
                    limit: 20,
                    text: { none: 'No recipes pending review.' }
                });
            })
            .catch(function (err) {
                els.loading.style.display = 'none';
                SiteOps.showError(err);
            });
    }

    // Toolbar template
    if (!document.getElementById('reviewTpl')) {
        var tpl = document.createElement('script');
        tpl.type = 'text/html';
        tpl.id = 'reviewTpl';
        tpl.innerHTML = '<a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="review">Review</a>';
        document.body.appendChild(tpl);
    }

    table.on('tool(reviewTable)', function (obj) {
        if (obj.event === 'review') {
            openReview(obj.data);
        }
    });

    // --- Show/hide panels ---
    function showQueue() {
        document.getElementById('review-table').parentNode.style.display = '';
        els.detailPanel.style.display = 'none';
        els.empty.style.display = 'none';
        currentVersionId = null;
    }

    function showDetail() {
        // Hide the table wrapper - layui wraps the table
        var tableNext = document.getElementById('review-table').nextElementSibling;
        if (tableNext && tableNext.className.indexOf('layui-table-view') >= 0) {
            tableNext.style.display = 'none';
        }
        document.getElementById('review-table').style.display = 'none';
        els.empty.style.display = 'none';
        els.detailPanel.style.display = 'block';
    }

    // --- Open review for a recipe ---
    function openReview(recipe) {
        currentVersionId = recipe.version_id || recipe.current_version_id || recipe.id;
        els.recipeTitle.textContent = recipe.title || 'Recipe #' + recipe.id;
        showDetail();
        loadDiff(currentVersionId);
        loadComments(currentVersionId);
    }

    // --- Load diff ---
    function loadDiff(versionId) {
        els.diffContent.innerHTML = '<div style="text-align:center; padding:20px;"><i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i> Loading diff...</div>';

        SiteOps.request('GET', '/api/v1/recipe-versions/' + versionId + '/diff')
            .then(function (res) {
                var diff = res.data || res;
                renderDiff(diff);
            })
            .catch(function (err) {
                els.diffContent.innerHTML = '<div style="color:#FF5722;">Failed to load version diff.</div>';
            });
    }

    function renderDiff(diff) {
        if (!diff || (!diff.changes && !diff.fields)) {
            els.diffContent.innerHTML = '<div style="color:#999;">No changes to display.</div>';
            return;
        }

        var changes = diff.changes || diff.fields || diff;
        var html = '<table class="layui-table"><colgroup><col width="140"><col><col></colgroup>'
            + '<thead><tr><th>Field</th><th>Previous</th><th>Current</th></tr></thead><tbody>';

        if (Array.isArray(changes)) {
            for (var i = 0; i < changes.length; i++) {
                var c = changes[i];
                html += '<tr>'
                    + '<td><strong>' + escapeHtml(c.field || c.name || '') + '</strong></td>'
                    + '<td style="background:#ffeef0;">' + escapeHtml(formatDiffValue(c.old_value || c.previous)) + '</td>'
                    + '<td style="background:#e6ffec;">' + escapeHtml(formatDiffValue(c.new_value || c.current)) + '</td>'
                    + '</tr>';
            }
        } else if (typeof changes === 'object') {
            for (var key in changes) {
                if (changes.hasOwnProperty(key)) {
                    var val = changes[key];
                    html += '<tr>'
                        + '<td><strong>' + escapeHtml(key) + '</strong></td>'
                        + '<td style="background:#ffeef0;">' + escapeHtml(formatDiffValue(val.old || val.previous || '')) + '</td>'
                        + '<td style="background:#e6ffec;">' + escapeHtml(formatDiffValue(val.new || val.current || '')) + '</td>'
                        + '</tr>';
                }
            }
        }

        html += '</tbody></table>';
        els.diffContent.innerHTML = html;
    }

    function formatDiffValue(val) {
        if (val == null) return '(empty)';
        if (typeof val === 'object') return JSON.stringify(val);
        return String(val);
    }

    // --- Load comments ---
    function loadComments(versionId) {
        els.commentsList.innerHTML = '<div style="color:#999;">Loading comments...</div>';

        SiteOps.request('GET', '/api/v1/recipe-versions/' + versionId + '/comments')
            .then(function (res) {
                var comments = res.data || res;
                if (!Array.isArray(comments)) comments = [];
                renderComments(comments);
            })
            .catch(function () {
                els.commentsList.innerHTML = '<div style="color:#999;">No comments yet.</div>';
            });
    }

    function renderComments(comments) {
        if (!comments.length) {
            els.commentsList.innerHTML = '<div style="color:#999;">No comments yet.</div>';
            return;
        }

        var html = '';
        for (var i = 0; i < comments.length; i++) {
            var c = comments[i];
            html += '<div style="border:1px solid #e6e6e6; padding:10px; margin-bottom:8px; border-radius:4px;">'
                + '<div style="font-size:12px; color:#999;">'
                + escapeHtml(c.author || c.user || 'Unknown') + ' - '
                + escapeHtml(c.created_at || '')
                + '</div>'
                + '<div style="margin-top:5px;">' + escapeHtml(c.text || c.body || c.comment || '') + '</div>'
                + '</div>';
        }
        els.commentsList.innerHTML = html;
    }

    // --- Add comment ---
    var addCommentBtn = document.getElementById('btn-add-comment');
    if (addCommentBtn) {
        addCommentBtn.addEventListener('click', function () {
            if (!currentVersionId) return;
            var textEl = document.getElementById('review-comment-text');
            var text = textEl.value.trim();
            if (!text) {
                layer.msg('Please enter a comment.', { icon: 0 });
                return;
            }

            var loadingIdx = layer.load(1);
            SiteOps.request('POST', '/api/v1/recipe-versions/' + currentVersionId + '/comments', { text: text })
                .then(function () {
                    layer.close(loadingIdx);
                    textEl.value = '';
                    SiteOps.showSuccess('Comment added.');
                    loadComments(currentVersionId);
                })
                .catch(function (err) {
                    layer.close(loadingIdx);
                    SiteOps.showError(err);
                });
        });
    }

    // --- Approve ---
    var approveBtn = document.getElementById('btn-approve-recipe');
    if (approveBtn) {
        approveBtn.addEventListener('click', function () {
            if (!currentVersionId) return;
            layer.confirm('Approve this recipe version?', {
                title: 'Confirm Approval',
                btn: ['Approve', 'Cancel']
            }, function (confirmIdx) {
                layer.close(confirmIdx);
                var loadingIdx = layer.load(1);
                SiteOps.request('POST', '/api/v1/recipe-versions/' + currentVersionId + '/approve', {})
                    .then(function () {
                        layer.close(loadingIdx);
                        SiteOps.showSuccess('Recipe approved.');
                        loadQueue();
                    })
                    .catch(function (err) {
                        layer.close(loadingIdx);
                        SiteOps.showError(err);
                    });
            });
        });
    }

    // --- Reject ---
    var rejectBtn = document.getElementById('btn-reject-recipe');
    if (rejectBtn) {
        rejectBtn.addEventListener('click', function () {
            if (!currentVersionId) return;
            layer.prompt({
                formType: 2,
                title: 'Rejection Reason',
                area: ['400px', '150px'],
                placeholder: 'Please provide a reason for rejection...'
            }, function (reason, promptIdx) {
                layer.close(promptIdx);
                var loadingIdx = layer.load(1);
                SiteOps.request('POST', '/api/v1/recipe-versions/' + currentVersionId + '/reject', { reason: reason })
                    .then(function () {
                        layer.close(loadingIdx);
                        SiteOps.showSuccess('Recipe rejected.');
                        loadQueue();
                    })
                    .catch(function (err) {
                        layer.close(loadingIdx);
                        SiteOps.showError(err);
                    });
            });
        });
    }

    // --- Back to queue ---
    var backBtn = document.getElementById('btn-back-queue');
    if (backBtn) {
        backBtn.addEventListener('click', function () {
            loadQueue();
        });
    }

    // --- Refresh queue ---
    var refreshBtn = document.getElementById('btn-refresh-queue');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            loadQueue();
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
    loadQueue();
});
