<?php
$title = 'Recipe Editor - SiteOps';
$csrf_token = session('csrf_token') ?? '';
ob_start();
?>
<div class="layui-breadcrumb" style="margin-bottom: 15px;">
    <a href="/dashboard">Home</a>
    <a href="/recipes/editor">Recipes</a>
    <a><cite>Editor</cite></a>
</div>

<div id="recipe-editor">
    <div id="recipe-toolbar" style="margin-bottom: 15px;">
        <button type="button" class="layui-btn" id="btn-new-recipe">
            <i class="layui-icon layui-icon-add-1"></i> New Recipe
        </button>
        <div class="layui-inline" style="margin-left:15px;">
            <select id="recipe-status-filter" lay-filter="recipeStatusFilter">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="in_review">In Review</option>
                <option value="approved">Approved</option>
                <option value="published">Published</option>
            </select>
        </div>
    </div>

    <div id="recipe-list-container">
        <table id="recipe-table" lay-filter="recipeTable"></table>
    </div>

    <div id="recipe-form-panel" style="display:none;">
        <div class="layui-card">
            <div class="layui-card-header">
                <span id="recipe-form-title">Edit Recipe</span>
                <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="btn-back-list" style="float:right;">
                    <i class="layui-icon layui-icon-return"></i> Back to List
                </button>
            </div>
            <div class="layui-card-body">
                <form class="layui-form" lay-filter="recipeForm">
                    <input type="hidden" id="recipe-id" name="id">
                    <input type="hidden" id="recipe-version-id" name="version_id">

                    <div class="layui-form-item">
                        <label class="layui-form-label">Title <span style="color:red;">*</span></label>
                        <div class="layui-input-block">
                            <input type="text" name="title" id="recipe-title" lay-verify="required" placeholder="Recipe title" class="layui-input">
                        </div>
                    </div>

                    <div class="layui-form-item">
                        <div class="layui-inline">
                            <label class="layui-form-label">Difficulty</label>
                            <div class="layui-input-inline" style="width:150px;">
                                <select name="difficulty" id="recipe-difficulty" lay-filter="recipeDifficulty">
                                    <option value="">Select</option>
                                    <option value="easy">Easy</option>
                                    <option value="medium">Medium</option>
                                    <option value="hard">Hard</option>
                                </select>
                            </div>
                        </div>
                        <div class="layui-inline">
                            <label class="layui-form-label">Prep (min)</label>
                            <div class="layui-input-inline" style="width:100px;">
                                <input type="number" name="prep_time" id="recipe-prep-time" class="layui-input" min="0" max="720">
                            </div>
                        </div>
                        <div class="layui-inline">
                            <label class="layui-form-label">Cook (min)</label>
                            <div class="layui-input-inline" style="width:100px;">
                                <input type="number" name="cook_time" id="recipe-cook-time" class="layui-input" min="0" max="720">
                            </div>
                        </div>
                        <div class="layui-inline">
                            <label class="layui-form-label">Total (min) <span style="color:red;">*</span></label>
                            <div class="layui-input-inline" style="width:100px;">
                                <input type="number" name="total_time" id="recipe-total-time" class="layui-input" min="1" max="720">
                            </div>
                        </div>
                    </div>

                    <div class="layui-form-item">
                        <label class="layui-form-label">Tags</label>
                        <div class="layui-input-block">
                            <input type="text" name="tags" id="recipe-tags" class="layui-input" placeholder="Comma-separated tags">
                        </div>
                    </div>

                    <!-- Steps -->
                    <div class="layui-form-item layui-form-text">
                        <label class="layui-form-label">Steps <span style="color:red;">*</span></label>
                        <div class="layui-input-block">
                            <div id="recipe-steps-container"></div>
                            <button type="button" class="layui-btn layui-btn-sm layui-btn-normal" id="btn-add-step" style="margin-top:10px;">
                                <i class="layui-icon layui-icon-add-1"></i> Add Step
                            </button>
                            <span style="color:#999; font-size:12px; margin-left:10px;">1–50 steps required</span>
                        </div>
                    </div>

                    <!-- Ingredients -->
                    <div class="layui-form-item layui-form-text">
                        <label class="layui-form-label">Ingredients</label>
                        <div class="layui-input-block">
                            <div id="recipe-ingredients-container"></div>
                            <button type="button" class="layui-btn layui-btn-sm layui-btn-normal" id="btn-add-ingredient" style="margin-top:10px;">
                                <i class="layui-icon layui-icon-add-1"></i> Add Ingredient
                            </button>
                            <span style="color:#999; font-size:12px; margin-left:10px;">Valid units: g, kg, ml, l, tsp, tbsp, piece, pcs</span>
                        </div>
                    </div>

                    <!-- Rich-text content editor -->
                    <div class="layui-form-item layui-form-text">
                        <label class="layui-form-label">Content</label>
                        <div class="layui-input-block">
                            <div id="editor-toolbar" style="border:1px solid #e6e6e6; border-bottom:0; padding:6px 10px; background:#fafafa; border-radius:2px 2px 0 0;">
                                <button type="button" class="layui-btn layui-btn-xs" onclick="document.execCommand('bold')"><b>B</b></button>
                                <button type="button" class="layui-btn layui-btn-xs" onclick="document.execCommand('italic')"><i>I</i></button>
                                <button type="button" class="layui-btn layui-btn-xs" onclick="document.execCommand('underline')"><u>U</u></button>
                                <button type="button" class="layui-btn layui-btn-xs" onclick="document.execCommand('insertUnorderedList')">&#8226; List</button>
                                <button type="button" class="layui-btn layui-btn-xs" onclick="document.execCommand('insertOrderedList')">1. List</button>
                                <button type="button" class="layui-btn layui-btn-xs" id="btn-insert-image">
                                    <i class="layui-icon layui-icon-picture"></i> Insert Image
                                </button>
                                <button type="button" class="layui-btn layui-btn-xs layui-btn-primary" onclick="document.execCommand('removeFormat')">Clear Format</button>
                            </div>
                            <div id="recipe-content-editor" contenteditable="true"
                                 style="border:1px solid #e6e6e6; min-height:200px; padding:12px; outline:none; background:#fff; border-radius:0 0 2px 2px; line-height:1.8;"
                                 data-placeholder="Write your recipe content here. You can paste formatted text — it will be cleaned up automatically."></div>
                            <div style="color:#999; font-size:12px; margin-top:5px;">
                                Rich text editor: paste from word processors (formatting is cleaned), use toolbar for bold/italic/lists, insert uploaded images inline.
                            </div>
                        </div>
                    </div>

                    <!-- Image upload -->
                    <div class="layui-form-item">
                        <label class="layui-form-label">Images</label>
                        <div class="layui-input-block">
                            <button type="button" class="layui-btn layui-btn-sm" id="recipe-image-upload">
                                <i class="layui-icon layui-icon-upload"></i> Upload Image
                            </button>
                            <span style="color:#999; font-size:12px; margin-left:10px;">JPG/PNG only, max 5 MB</span>
                            <div id="recipe-image-preview" style="margin-top:10px; display:flex; flex-wrap:wrap; gap:10px;"></div>
                        </div>
                    </div>

                    <div id="recipe-validation-errors" style="display:none; color:#FF5722; margin-bottom:15px; padding:10px; background:#FFF3E0; border-radius:4px;"></div>

                    <div class="layui-form-item">
                        <div class="layui-input-block">
                            <button type="button" class="layui-btn" id="btn-save-draft">
                                <i class="layui-icon layui-icon-edit"></i> Save Draft
                            </button>
                            <button type="button" class="layui-btn layui-btn-normal" id="btn-submit-review">
                                <i class="layui-icon layui-icon-release"></i> Submit for Review
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
#recipe-content-editor:empty:before {
    content: attr(data-placeholder);
    color: #ccc;
    pointer-events: none;
}
#recipe-content-editor img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
    margin: 8px 0;
}
.uploaded-thumb {
    position: relative;
    display: inline-block;
}
.uploaded-thumb img {
    width: 100px; height: 75px; object-fit: cover; border-radius: 4px; border: 1px solid #e6e6e6;
}
.uploaded-thumb .insert-btn {
    position: absolute; bottom: 2px; right: 2px; font-size: 10px; padding: 2px 6px;
}
</style>

<?php
$content = ob_get_clean();
ob_start();
?>
<script>
layui.use(['form', 'table', 'upload', 'layer'], function () {
    'use strict';
    var form = layui.form;
    var table = layui.table;
    var upload = layui.upload;
    var layer = layui.layer;

    var currentRecipeId = null;
    var currentVersionId = null;
    var stepCounter = 0;
    var uploadedImages = [];

    // ─── Paste cleanup for contenteditable ─────────────────────────
    var editorEl = document.getElementById('recipe-content-editor');
    editorEl.addEventListener('paste', function (e) {
        e.preventDefault();
        var html = e.clipboardData.getData('text/html');
        var text = e.clipboardData.getData('text/plain');
        if (html) {
            // Strip potentially dangerous tags, keep basic formatting
            var tmp = document.createElement('div');
            tmp.innerHTML = html;
            // Remove script, style, meta, link tags
            tmp.querySelectorAll('script,style,meta,link,object,embed,iframe').forEach(function (el) { el.remove(); });
            // Remove class and style attributes except on img
            tmp.querySelectorAll('*').forEach(function (el) {
                if (el.tagName !== 'IMG') {
                    el.removeAttribute('style');
                    el.removeAttribute('class');
                    el.removeAttribute('id');
                }
            });
            document.execCommand('insertHTML', false, tmp.innerHTML);
        } else if (text) {
            document.execCommand('insertText', false, text);
        }
    });

    // ─── Recipe list table ─────────────────────────────────────────
    var actionsHtml = '<div><a class="layui-btn layui-btn-xs" lay-event="edit"><i class="layui-icon layui-icon-edit"></i> Edit</a></div>';
    var tpl = document.createElement('script');
    tpl.type = 'text/html';
    tpl.id = 'recipe-row-actions';
    tpl.innerHTML = actionsHtml;
    document.body.appendChild(tpl);

    table.render({
        elem: '#recipe-table',
        cols: [[
            { type: 'numbers', title: '#', width: 50 },
            { field: 'title', title: 'Title', minWidth: 200 },
            { field: 'status', title: 'Status', width: 110, templet: function (d) {
                var colors = { draft: '#FFB300', in_review: '#1E88E5', approved: '#43A047', published: '#009688', rejected: '#E53935' };
                var labels = { draft: 'Draft', in_review: 'In Review', approved: 'Approved', published: 'Published', rejected: 'Rejected' };
                var color = colors[d.status] || '#999';
                return '<span style="color:' + color + '; font-weight:600;">' + (labels[d.status] || d.status || '--') + '</span>';
            }},
            { field: 'created_at', title: 'Created', width: 160 },
            { title: 'Actions', width: 120, toolbar: '#recipe-row-actions' }
        ]],
        data: [],
        page: true,
        limit: 20,
        text: { none: 'No recipes found' }
    });

    var statusFilter = '';
    function loadRecipes() {
        var params = ['per_page=50'];
        if (statusFilter) params.push('status=' + encodeURIComponent(statusFilter));
        SiteOps.request('GET', '/api/v1/recipes?' + params.join('&'))
            .then(function (res) {
                var items = (res.data || res).items || [];
                table.reload('recipe-table', { data: items });
            })
            .catch(function (err) { SiteOps.showError(err); });
    }

    form.on('select(recipeStatusFilter)', function (data) {
        statusFilter = data.value;
        loadRecipes();
    });

    table.on('tool(recipeTable)', function (obj) {
        if (obj.event === 'edit') loadRecipeForEdit(obj.data.id);
    });

    // ─── New recipe ────────────────────────────────────────────────
    document.getElementById('btn-new-recipe').addEventListener('click', function () {
        var siteId = (window.USER_SITE_SCOPES && window.USER_SITE_SCOPES[0]) || 1;
        layer.prompt({ formType: 0, title: 'Recipe title', area: ['400px', '50px'] }, function (title, idx) {
            layer.close(idx);
            SiteOps.request('POST', '/api/v1/recipes', { title: title, site_id: siteId })
                .then(function (res) {
                    SiteOps.showSuccess('Recipe created');
                    loadRecipeForEdit((res.data || res).id);
                })
                .catch(function (err) { SiteOps.showError(err); });
        });
    });

    function loadRecipeForEdit(id) {
        SiteOps.request('GET', '/api/v1/recipes/' + id)
            .then(function (res) { openRecipeForm(res.data || res); })
            .catch(function (err) { SiteOps.showError(err); });
    }

    function openRecipeForm(recipe) {
        currentRecipeId = recipe.id;
        uploadedImages = [];
        document.getElementById('recipe-id').value = recipe.id || '';
        document.getElementById('recipe-title').value = recipe.title || '';
        document.getElementById('recipe-form-title').textContent = recipe.id ? 'Edit Recipe #' + recipe.id : 'New Recipe';

        var versions = recipe.versions || [];
        var latest = versions.length ? versions[0] : {};
        currentVersionId = latest.id || null;
        document.getElementById('recipe-version-id').value = currentVersionId || '';
        document.getElementById('recipe-difficulty').value = latest.difficulty || '';
        document.getElementById('recipe-prep-time').value = latest.prep_time || '';
        document.getElementById('recipe-cook-time').value = latest.cook_time || '';
        document.getElementById('recipe-total-time').value = latest.total_time || '';
        document.getElementById('recipe-tags').value = (recipe.tags || []).join(', ');

        // Content — show rich text
        editorEl.innerHTML = latest.content_json || '';

        // Steps from recipe.steps if available
        var steps = recipe.steps || [];
        renderSteps(steps);

        document.getElementById('recipe-image-preview').innerHTML = '';
        document.getElementById('recipe-validation-errors').style.display = 'none';
        document.getElementById('recipe-list-container').style.display = 'none';
        document.getElementById('recipe-toolbar').style.display = 'none';
        document.getElementById('recipe-form-panel').style.display = '';
        form.render();
    }

    function closeForm() {
        document.getElementById('recipe-form-panel').style.display = 'none';
        document.getElementById('recipe-list-container').style.display = '';
        document.getElementById('recipe-toolbar').style.display = '';
        loadRecipes();
    }
    document.getElementById('btn-back-list').addEventListener('click', closeForm);

    // ─── Steps management ──────────────────────────────────────────
    function renderSteps(steps) {
        var c = document.getElementById('recipe-steps-container');
        c.innerHTML = '';
        stepCounter = 0;
        (steps || []).forEach(function (s) { addStepRow(s.instruction || '', s.duration_minutes || ''); });
        if (!steps || !steps.length) addStepRow('', '');
    }

    function addStepRow(instruction, duration) {
        stepCounter++;
        var row = document.createElement('div');
        row.className = 'step-row';
        row.style.cssText = 'display:flex; align-items:center; margin-bottom:8px; gap:8px;';
        row.innerHTML =
            '<span style="min-width:30px; color:#999;">' + stepCounter + '.</span>' +
            '<input type="text" class="layui-input step-instruction" placeholder="Step instruction" value="' + (instruction || '').replace(/"/g, '&quot;') + '" style="flex:1;">' +
            '<input type="number" class="layui-input step-duration" placeholder="Min" value="' + (duration || '') + '" style="width:80px;" min="0">' +
            '<button type="button" class="layui-btn layui-btn-xs layui-btn-danger btn-remove-step"><i class="layui-icon layui-icon-close"></i></button>';
        document.getElementById('recipe-steps-container').appendChild(row);
    }

    document.getElementById('btn-add-step').addEventListener('click', function () {
        if (stepCounter >= 50) { SiteOps.showError('Maximum 50 steps'); return; }
        addStepRow('', '');
    });

    document.getElementById('recipe-steps-container').addEventListener('click', function (e) {
        var btn = e.target.closest('button');
        if (!btn) return;
        if (btn.classList.contains('btn-remove-step')) {
            var c = document.getElementById('recipe-steps-container');
            if (c.children.length > 1) { btn.closest('.step-row').remove(); renumberSteps(); }
        }
    });

    function renumberSteps() {
        stepCounter = 0;
        document.querySelectorAll('#recipe-steps-container .step-row').forEach(function (row) {
            stepCounter++;
            row.querySelector('span').textContent = stepCounter + '.';
        });
    }

    function collectSteps() {
        var steps = [];
        document.querySelectorAll('#recipe-steps-container .step-row').forEach(function (row) {
            var ins = row.querySelector('.step-instruction').value.trim();
            var dur = row.querySelector('.step-duration').value;
            if (ins) steps.push({ instruction: ins, duration_minutes: dur ? parseInt(dur) : null });
        });
        return steps;
    }

    // ─── Ingredients management ──────────────────────────────────
    var validUnits = ['g','kg','ml','l','tsp','tbsp','piece','pcs'];
    function addIngredientRow(name, qty, unit) {
        var c = document.getElementById('recipe-ingredients-container');
        var row = document.createElement('div');
        row.className = 'ingredient-row';
        row.style.cssText = 'display:flex; align-items:center; margin-bottom:8px; gap:8px;';
        row.innerHTML =
            '<input type="text" class="layui-input ing-name" placeholder="Name" value="' + (name||'').replace(/"/g,'&quot;') + '" style="flex:1;">' +
            '<input type="number" class="layui-input ing-qty" placeholder="Qty" value="' + (qty||'') + '" style="width:80px;" min="0" step="0.1">' +
            '<select class="layui-input ing-unit" style="width:90px;">' +
                validUnits.map(function(u){ return '<option value="'+u+'"'+(u===unit?' selected':'')+'>'+u+'</option>'; }).join('') +
            '</select>' +
            '<button type="button" class="layui-btn layui-btn-xs layui-btn-danger btn-remove-ing"><i class="layui-icon layui-icon-close"></i></button>';
        c.appendChild(row);
        // Immediate invalid-unit warning
        row.querySelector('.ing-unit').addEventListener('change', function() {
            if (validUnits.indexOf(this.value) === -1) {
                layer.msg('Invalid unit. Use: ' + validUnits.join(', '), {icon:0, time:3000});
            }
        });
    }
    document.getElementById('btn-add-ingredient').addEventListener('click', function() { addIngredientRow('','','g'); });
    document.getElementById('recipe-ingredients-container').addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-remove-ing');
        if (btn) btn.closest('.ingredient-row').remove();
    });
    function collectIngredients() {
        var ings = [];
        document.querySelectorAll('#recipe-ingredients-container .ingredient-row').forEach(function(row) {
            var name = row.querySelector('.ing-name').value.trim();
            var qty = row.querySelector('.ing-qty').value;
            var unit = row.querySelector('.ing-unit').value;
            if (name) ings.push({ name: name, quantity: qty ? parseFloat(qty) : null, unit: unit });
        });
        return ings;
    }

    // ─── Client-side validation ────────────────────────────────────
    function validateForm() {
        var errs = [];
        var steps = collectSteps();
        var total = parseInt(document.getElementById('recipe-total-time').value) || 0;
        if (steps.length < 1) errs.push('At least 1 step is required.');
        if (steps.length > 50) errs.push('Maximum 50 steps allowed.');
        if (total < 1 || total > 720) errs.push('Total time must be between 1 and 720 minutes.');
        // Validate ingredients
        var ings = collectIngredients();
        ings.forEach(function(ing, i) {
            if (!ing.quantity || ing.quantity <= 0) errs.push('Ingredient #'+(i+1)+': quantity must be > 0');
            if (validUnits.indexOf(ing.unit) === -1) errs.push('Ingredient #'+(i+1)+': invalid unit');
        });
        return errs;
    }

    // ─── Save draft ────────────────────────────────────────────────
    document.getElementById('btn-save-draft').addEventListener('click', function () {
        var errEl = document.getElementById('recipe-validation-errors');
        errEl.style.display = 'none';
        var errs = validateForm();
        if (errs.length) { errEl.innerHTML = errs.join('<br>'); errEl.style.display = ''; return; }

        var payload = {
            steps: collectSteps(),
            ingredients: collectIngredients(),
            content_json: editorEl.innerHTML,
            difficulty: document.getElementById('recipe-difficulty').value || null,
            prep_time: parseInt(document.getElementById('recipe-prep-time').value) || null,
            cook_time: parseInt(document.getElementById('recipe-cook-time').value) || null,
            total_time: parseInt(document.getElementById('recipe-total-time').value) || null
        };

        if (!currentVersionId) {
            errEl.textContent = 'No version to update. Save the recipe first.';
            errEl.style.display = '';
            return;
        }

        SiteOps.request('PUT', '/api/v1/recipe-versions/' + currentVersionId, payload)
            .then(function () { SiteOps.showSuccess('Draft saved'); })
            .catch(function (err) {
                errEl.textContent = (err.body && err.body.error) ? err.body.error.message : (err.message || 'Save failed');
                errEl.style.display = '';
            });
    });

    // ─── Submit for review ─────────────────────────────────────────
    document.getElementById('btn-submit-review').addEventListener('click', function () {
        if (!currentVersionId) return;
        layer.confirm('Submit this recipe for review?', { icon: 3, title: 'Confirm' }, function (idx) {
            layer.close(idx);
            SiteOps.request('POST', '/api/v1/recipe-versions/' + currentVersionId + '/submit-review', {})
                .then(function () { SiteOps.showSuccess('Submitted for review'); closeForm(); })
                .catch(function (err) { SiteOps.showError(err); });
        });
    });

    // ─── Image upload with preview + size warning + insert ─────────
    upload.render({
        elem: '#recipe-image-upload',
        url: '/api/v1/files/images',
        field: 'file',
        accept: 'images',
        exts: 'jpg|jpeg|png',
        size: 5120,
        headers: { 'X-CSRF-Token': SiteOps.getCsrfToken() },
        choose: function (obj) {
            obj.preview(function (index, file) {
                if (file.size > 5 * 1024 * 1024) {
                    SiteOps.showError('File "' + file.name + '" exceeds 5 MB limit.');
                    obj.resetFile(index, file);
                }
            });
        },
        before: function () { layer.load(); },
        done: function (res) {
            layer.closeAll('loading');
            var data = res.data || res;
            if (data && data.path) {
                var imgUrl = '/storage/uploads/' + data.path;
                uploadedImages.push({ path: data.path, url: imgUrl, sha256: data.sha256 });
                var preview = document.getElementById('recipe-image-preview');
                var thumb = document.createElement('div');
                thumb.className = 'uploaded-thumb';
                thumb.innerHTML =
                    '<img src="' + imgUrl + '" alt="upload">' +
                    '<button type="button" class="layui-btn layui-btn-xs layui-btn-normal insert-btn" data-url="' + imgUrl + '">Insert</button>';
                preview.appendChild(thumb);
                SiteOps.showSuccess('Image uploaded');
            } else {
                SiteOps.showError((res.error && res.error.message) || 'Upload failed');
            }
        },
        error: function () {
            layer.closeAll('loading');
            SiteOps.showError('Upload request failed');
        }
    });

    // Insert image into contenteditable editor
    document.getElementById('recipe-image-preview').addEventListener('click', function (e) {
        var btn = e.target.closest('.insert-btn');
        if (btn) {
            var url = btn.getAttribute('data-url');
            editorEl.focus();
            document.execCommand('insertImage', false, url);
        }
    });

    // Also allow toolbar insert-image button
    document.getElementById('btn-insert-image').addEventListener('click', function () {
        if (!uploadedImages.length) {
            SiteOps.showError('Upload an image first using the upload button below.');
            return;
        }
        var last = uploadedImages[uploadedImages.length - 1];
        editorEl.focus();
        document.execCommand('insertImage', false, last.url);
    });

    loadRecipes();
});
</script>
<?php
$scripts = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>
