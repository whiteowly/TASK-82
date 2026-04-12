<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\controller\BaseController;
use app\model\Recipe;
use app\model\RecipeVersion;
use app\model\ReviewComment;
use app\service\recipe\RecipeService;
use app\service\recipe\WorkflowService;
use app\validate\RecipeValidate;
use app\validate\RecipeVersionValidate;
use think\exception\ValidateException;
use think\Response;

class RecipeController extends BaseController
{
    /** Roles allowed to mutate recipe data (auditor is read-only). */
    private const MUTATION_ROLES = ['content_editor', 'reviewer', 'administrator'];

    /**
     * GET /api/v1/recipes
     *
     * List recipes with pagination.
     */
    public function index(RecipeService $recipeService): Response
    {
        $filters = [
            'status'   => $this->request->get('status'),
            'category' => $this->request->get('category'),
            'page'     => (int) $this->request->get('page', 1),
            'per_page' => (int) $this->request->get('per_page', 20),
        ];

        $page    = max(1, $filters['page']);
        $perPage = min(100, max(1, $filters['per_page']));

        $query = Recipe::order('created_at', 'desc');
        $this->applySiteScope($query);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        $total      = $query->count();
        $totalPages = (int) ceil($total / $perPage);
        $items      = $query->page($page, $perPage)
                            ->select()
                            ->toArray();

        return $this->success([
            'items'      => $items,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    /**
     * POST /api/v1/recipes
     *
     * Create a new recipe.
     */
    public function create(RecipeService $recipeService): Response
    {
        if (empty(array_intersect($this->request->roles, self::MUTATION_ROLES))) {
            return $this->error('FORBIDDEN_ROLE', 'Your role does not permit recipe mutations.', [], 403);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];

        try {
            validate(RecipeValidate::class)->scene('create')->check($input);
        } catch (ValidateException $e) {
            return $this->error('VALIDATION_FAILED', $e->getMessage(), [], 422);
        }

        $siteId = (int) $input['site_id'];
        if (!$this->canAccessSite($siteId)) {
            return $this->error('FORBIDDEN_SITE_SCOPE', 'You do not have access to this site.', [], 403);
        }

        $data = $recipeService->create([
            'title'      => $input['title'],
            'site_id'    => $siteId,
            'status'     => 'draft',
            'created_by' => $this->request->userId,
        ], $this->request->userId);

        return $this->success([
            'id'      => $data['id'] ?? null,
            'message' => 'Recipe created.',
        ], 201);
    }

    /**
     * GET /api/v1/recipes/:id
     *
     * Read a single recipe.
     */
    public function read($id, RecipeService $recipeService): Response
    {
        $recipe = $recipeService->find((int) $id, $this->request->siteScopes);

        if (!$recipe) {
            return $this->error('NOT_FOUND', 'Recipe not found.', [], 404);
        }

        $versions = RecipeVersion::where('recipe_id', (int) $id)
            ->order('version_number', 'desc')
            ->select()
            ->toArray();

        // Extract ingredients from content_json for stable frontend rehydration
        foreach ($versions as &$ver) {
            $contentData = !empty($ver['content_json']) ? json_decode($ver['content_json'], true) : null;
            $ver['ingredients'] = (is_array($contentData) && isset($contentData['ingredients']))
                ? $contentData['ingredients']
                : [];
        }
        unset($ver);

        $recipe['versions'] = $versions;

        return $this->success($recipe);
    }

    /**
     * POST /api/v1/recipes/:id/versions
     *
     * Create a new version (draft) of an existing recipe.
     */
    public function createVersion($id, RecipeService $recipeService): Response
    {
        if (empty(array_intersect($this->request->roles, self::MUTATION_ROLES))) {
            return $this->error('FORBIDDEN_ROLE', 'Your role does not permit recipe mutations.', [], 403);
        }

        $recipe = $recipeService->find((int) $id, $this->request->siteScopes);

        if (!$recipe) {
            return $this->error('NOT_FOUND', 'Recipe not found.', [], 404);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];

        $version = $recipeService->createVersion((int) $id, $input, $this->request->userId);

        return $this->success([
            'recipe_id' => (int) $id,
            'version'   => $version,
            'message'   => 'New version created.',
        ], 201);
    }

    /**
     * PUT /api/v1/recipe-versions/:id
     *
     * Update a draft version. $id is the VERSION id from the route.
     */
    public function updateVersion($id, RecipeService $recipeService): Response
    {
        if (empty(array_intersect($this->request->roles, self::MUTATION_ROLES))) {
            return $this->error('FORBIDDEN_ROLE', 'Your role does not permit recipe mutations.', [], 403);
        }

        $version = RecipeVersion::where('id', (int) $id)->find();

        if (!$version) {
            return $this->error('NOT_FOUND', 'Version not found.', [], 404);
        }

        // Site scope check via the parent recipe
        $recipe = $recipeService->find((int) $version->recipe_id, $this->request->siteScopes);
        if (!$recipe) {
            return $this->error('NOT_FOUND', 'Recipe not found.', [], 404);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];

        try {
            validate(RecipeVersionValidate::class)->check($input);
        } catch (ValidateException $e) {
            return $this->error('VALIDATION_FAILED', $e->getMessage(), [], 422);
        }

        // Validate ingredients if provided
        if (!empty($input['ingredients']) && is_array($input['ingredients'])) {
            $ingredientError = \app\validate\RecipeVersionValidate::validateIngredients($input['ingredients']);
            if ($ingredientError) {
                return $this->error('VALIDATION_FAILED', $ingredientError, [], 422);
            }
        }

        if ($version->status !== 'draft') {
            return $this->error('WORKFLOW_CONFLICT', 'Only draft versions can be updated.', [], 409);
        }

        // Update steps as structured data in recipe_steps table
        if (!empty($input['steps']) && is_array($input['steps'])) {
            \think\facade\Db::name('recipe_steps')->where('version_id', (int)$id)->delete();
            foreach ($input['steps'] as $i => $step) {
                \think\facade\Db::name('recipe_steps')->insert([
                    'version_id' => (int)$id,
                    'step_number' => $i + 1,
                    'instruction' => $step['instruction'] ?? '',
                    'duration_minutes' => $step['duration_minutes'] ?? null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        // Persist ingredients into content_json as structured data
        $contentData = [];
        $existingContent = $version->content_json ? json_decode($version->content_json, true) : null;
        if (is_array($existingContent)) {
            $contentData = $existingContent;
        } elseif ($version->content_json) {
            $contentData = ['body' => $version->content_json];
        }

        if (isset($input['content_json'])) {
            $decoded = json_decode($input['content_json'], true);
            if (is_array($decoded)) {
                $contentData = array_merge($contentData, $decoded);
            } else {
                $contentData['body'] = $input['content_json'];
            }
        }

        if (!empty($input['ingredients']) && is_array($input['ingredients'])) {
            $contentData['ingredients'] = $input['ingredients'];
        }

        $version->save([
            'content_json' => json_encode($contentData),
            'total_time'   => $input['total_time'] ?? $version->total_time,
            'difficulty'   => $input['difficulty'] ?? $version->difficulty,
            'prep_time'    => $input['prep_time'] ?? $version->prep_time,
            'cook_time'    => $input['cook_time'] ?? $version->cook_time,
        ]);

        return $this->success([
            'version_id' => (int) $id,
            'message'    => 'Version updated.',
        ]);
    }

    /**
     * POST /api/v1/recipe-versions/:id/submit-review
     *
     * Submit a recipe version for review. $id is the VERSION id from the route.
     */
    public function submitReview($id, RecipeService $recipeService, WorkflowService $workflowService): Response
    {
        if (empty(array_intersect($this->request->roles, self::MUTATION_ROLES))) {
            return $this->error('FORBIDDEN_ROLE', 'Your role does not permit recipe mutations.', [], 403);
        }

        $version = RecipeVersion::where('id', (int) $id)->find();

        if (!$version) {
            return $this->error('NOT_FOUND', 'Version not found.', [], 404);
        }

        // Site scope check via the parent recipe
        $recipe = $recipeService->find((int) $version->recipe_id, $this->request->siteScopes);
        if (!$recipe) {
            return $this->error('NOT_FOUND', 'Recipe not found.', [], 404);
        }

        if ($version->status !== 'draft') {
            return $this->error('WORKFLOW_CONFLICT', 'Only draft versions can be submitted for review.', [], 409);
        }

        // Completeness gate: validate required structure before allowing review submission
        $completenessErrors = [];

        // total_time: required, 1..720
        $totalTime = $version->total_time;
        if ($totalTime === null || $totalTime === '' || (int) $totalTime < 1 || (int) $totalTime > 720) {
            $completenessErrors[] = 'total_time must be between 1 and 720 minutes.';
        }

        // difficulty: required
        if (empty($version->difficulty)) {
            $completenessErrors[] = 'difficulty is required.';
        }

        // steps: 1..50
        $stepCount = \think\facade\Db::name('recipe_steps')->where('version_id', (int) $id)->count();
        if ($stepCount < 1) {
            $completenessErrors[] = 'At least 1 step is required.';
        } elseif ($stepCount > 50) {
            $completenessErrors[] = 'Maximum 50 steps allowed.';
        }

        // ingredients: at least 1 via content_json
        $contentData = !empty($version->content_json) ? json_decode($version->content_json, true) : [];
        $ingredients = (is_array($contentData) && isset($contentData['ingredients'])) ? $contentData['ingredients'] : [];
        if (empty($ingredients)) {
            $completenessErrors[] = 'At least 1 ingredient is required.';
        }

        if (!empty($completenessErrors)) {
            return $this->error('VALIDATION_FAILED', 'Recipe version is incomplete and cannot be submitted for review.', $completenessErrors, 422);
        }

        try {
            $workflowService->submitForReview((int) $id, $this->request->userId);
        } catch (ValidateException $e) {
            return $this->error('WORKFLOW_CONFLICT', $e->getMessage(), [], 409);
        }

        return $this->success([
            'version_id' => (int) $id,
            'status'     => 'in_review',
            'message'    => 'Recipe submitted for review.',
        ]);
    }

    /**
     * GET /api/v1/recipe-versions/:id/diff
     *
     * Show the diff between this version and the previous version.
     * $id is the VERSION id from the route.
     */
    public function diff($id, RecipeService $recipeService): Response
    {
        $version = RecipeVersion::where('id', (int) $id)->find();

        if (!$version) {
            return $this->error('NOT_FOUND', 'Version not found.', [], 404);
        }

        // Site scope check via the parent recipe
        $recipe = $recipeService->find((int) $version->recipe_id, $this->request->siteScopes);
        if (!$recipe) {
            return $this->error('NOT_FOUND', 'Recipe not found.', [], 404);
        }

        $current = $version->toArray();

        // Get the previous version of the same recipe
        $previous = RecipeVersion::where('recipe_id', (int) $version->recipe_id)
            ->where('version_number', '<', $version->version_number)
            ->order('version_number', 'desc')
            ->find();

        if (!$previous) {
            return $this->success([
                'version_id' => (int) $id,
                'changes'    => [],
                'message'    => 'No previous version to compare.',
            ]);
        }

        $previousData = $previous->toArray();
        $changes      = [];

        $currentContent  = json_decode($current['content_json'] ?? '[]', true) ?: [];
        $previousContent = json_decode($previousData['content_json'] ?? '[]', true) ?: [];

        foreach (['total_time', 'difficulty', 'prep_time', 'cook_time'] as $field) {
            if (($current[$field] ?? null) !== ($previousData[$field] ?? null)) {
                $changes[] = [
                    'field' => $field,
                    'old'   => $previousData[$field] ?? null,
                    'new'   => $current[$field] ?? null,
                ];
            }
        }

        if ($currentContent !== $previousContent) {
            $changes[] = [
                'field' => 'steps',
                'old'   => $previousContent,
                'new'   => $currentContent,
            ];
        }

        return $this->success([
            'version_id'       => (int) $id,
            'current_version'  => $current['version_number'],
            'previous_version' => $previousData['version_number'],
            'changes'          => $changes,
        ]);
    }

    /**
     * POST /api/v1/recipe-versions/:id/comments
     *
     * Add a review comment to a version. $id is the VERSION id from the route.
     */
    public function addComment($id, RecipeService $recipeService): Response
    {
        if (empty(array_intersect($this->request->roles, self::MUTATION_ROLES))) {
            return $this->error('FORBIDDEN_ROLE', 'Your role does not permit recipe mutations.', [], 403);
        }

        $version = RecipeVersion::where('id', (int) $id)->find();

        if (!$version) {
            return $this->error('NOT_FOUND', 'Version not found.', [], 404);
        }

        // Site scope check via the parent recipe
        $recipe = $recipeService->find((int) $version->recipe_id, $this->request->siteScopes);
        if (!$recipe) {
            return $this->error('NOT_FOUND', 'Recipe not found.', [], 404);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];

        if (empty($input['content'])) {
            return $this->error('VALIDATION_FAILED', 'Comment content is required.', [], 422);
        }

        $comment = ReviewComment::create([
            'version_id'  => (int) $id,
            'author_id'   => $this->request->userId,
            'anchor_type' => $input['anchor_type'] ?? 'general',
            'anchor_ref'  => $input['anchor_ref'] ?? '',
            'content'     => $input['content'],
        ]);

        return $this->success([
            'version_id' => (int) $id,
            'comment_id' => $comment->id,
            'message'    => 'Comment added.',
        ], 201);
    }

    /**
     * GET /api/v1/recipe-versions/:id/comments
     *
     * List review comments for a version. $id is the VERSION id from the route.
     */
    public function listComments($id, RecipeService $recipeService): Response
    {
        $version = RecipeVersion::where('id', (int) $id)->find();

        if (!$version) {
            return $this->error('NOT_FOUND', 'Version not found.', [], 404);
        }

        // Site scope check via the parent recipe
        $recipe = $recipeService->find((int) $version->recipe_id, $this->request->siteScopes);
        if (!$recipe) {
            return $this->error('NOT_FOUND', 'Recipe not found.', [], 404);
        }

        $comments = ReviewComment::where('version_id', (int) $id)
            ->order('created_at', 'asc')
            ->select()
            ->toArray();

        return $this->success([
            'version_id' => (int) $id,
            'comments'   => $comments,
        ]);
    }

    /**
     * POST /api/v1/recipe-versions/:id/approve
     *
     * Approve a recipe version. $id is the VERSION id from the route.
     */
    public function approve($id, RecipeService $recipeService, WorkflowService $workflowService): Response
    {
        $allowed = ['reviewer', 'administrator'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'Only reviewers can perform this action.', [], 403);
        }

        $version = RecipeVersion::where('id', (int) $id)->find();

        if (!$version) {
            return $this->error('NOT_FOUND', 'Version not found.', [], 404);
        }

        // Site scope check via the parent recipe
        $recipe = $recipeService->find((int) $version->recipe_id, $this->request->siteScopes);
        if (!$recipe) {
            return $this->error('NOT_FOUND', 'Recipe not found.', [], 404);
        }

        if ($version->status !== 'in_review') {
            return $this->error('WORKFLOW_CONFLICT', 'No version is currently in review.', [], 409);
        }

        try {
            $workflowService->approve((int) $id, $this->request->userId);
        } catch (ValidateException $e) {
            return $this->error('WORKFLOW_CONFLICT', $e->getMessage(), [], 409);
        }

        return $this->success([
            'version_id' => (int) $id,
            'status'     => 'approved',
            'message'    => 'Recipe approved.',
        ]);
    }

    /**
     * POST /api/v1/recipe-versions/:id/reject
     *
     * Reject a recipe version. $id is the VERSION id from the route.
     */
    public function reject($id, RecipeService $recipeService, WorkflowService $workflowService): Response
    {
        $allowed = ['reviewer', 'administrator'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'Only reviewers can perform this action.', [], 403);
        }

        $version = RecipeVersion::where('id', (int) $id)->find();

        if (!$version) {
            return $this->error('NOT_FOUND', 'Version not found.', [], 404);
        }

        // Site scope check via the parent recipe
        $recipe = $recipeService->find((int) $version->recipe_id, $this->request->siteScopes);
        if (!$recipe) {
            return $this->error('NOT_FOUND', 'Recipe not found.', [], 404);
        }

        if ($version->status !== 'in_review') {
            return $this->error('WORKFLOW_CONFLICT', 'No version is currently in review.', [], 409);
        }

        try {
            $workflowService->reject((int) $id, $this->request->userId);
        } catch (ValidateException $e) {
            return $this->error('WORKFLOW_CONFLICT', $e->getMessage(), [], 409);
        }

        return $this->success([
            'version_id' => (int) $id,
            'status'     => 'rejected',
            'message'    => 'Recipe rejected.',
        ]);
    }

    /**
     * POST /api/v1/recipes/:id/publish
     *
     * Publish an approved recipe version.
     */
    public function publish($id, RecipeService $recipeService, WorkflowService $workflowService): Response
    {
        $allowed = ['reviewer', 'administrator'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'Only reviewers can perform this action.', [], 403);
        }

        $recipe = $recipeService->find((int) $id, $this->request->siteScopes);

        if (!$recipe) {
            return $this->error('NOT_FOUND', 'Recipe not found.', [], 404);
        }

        $version = RecipeVersion::where('recipe_id', (int) $id)
            ->where('status', 'approved')
            ->order('version_number', 'desc')
            ->find();

        if (!$version) {
            return $this->error('WORKFLOW_CONFLICT', 'No approved version to publish.', [], 409);
        }

        try {
            $workflowService->publish((int) $id, $this->request->userId);
        } catch (ValidateException $e) {
            return $this->error('WORKFLOW_CONFLICT', $e->getMessage(), [], 409);
        }

        return $this->success([
            'recipe_id' => (int) $id,
            'status'    => 'published',
            'message'   => 'Recipe published.',
        ]);
    }
}
