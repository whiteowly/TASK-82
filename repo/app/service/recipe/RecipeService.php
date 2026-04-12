<?php
declare(strict_types=1);

namespace app\service\recipe;

use think\facade\Db;
use think\exception\ValidateException;

class RecipeService
{
    /**
     * List recipes filtered by criteria and restricted to the given site scopes.
     *
     * @param array $filters  Query filters (status, tag, text search, page, per_page).
     * @param array $siteScopes  Site IDs the requesting user has access to.
     * @return array Paginated list of recipe summaries with version info.
     */
    public function list(array $filters, array $siteScopes): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));

        $query = Db::table('recipes')
            ->alias('r')
            ->leftJoin('recipe_versions rv', 'rv.id = r.published_version_id');

        if (!empty($siteScopes)) {
            $query->whereIn('r.site_id', $siteScopes);
        }

        if (!empty($filters['status'])) {
            $query->where('r.status', $filters['status']);
        }

        if (!empty($filters['text'])) {
            $query->where('r.title', 'like', '%' . $filters['text'] . '%');
        }

        if (!empty($filters['tag'])) {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->table('recipe_versions')
                    ->alias('rv2')
                    ->join('recipe_version_tags rvt', 'rvt.version_id = rv2.id')
                    ->join('recipe_tags rt', 'rt.id = rvt.tag_id')
                    ->whereColumn('rv2.recipe_id', 'r.id')
                    ->where('rt.name', $filters['tag']);
            });
        }

        $total = $query->count();

        $rows = $query->field([
                'r.id', 'r.site_id', 'r.title', 'r.status', 'r.published_version_id',
                'r.created_by', 'r.created_at', 'r.updated_at',
                'rv.version_number', 'rv.difficulty', 'rv.total_time',
            ])
            ->order('r.updated_at', 'desc')
            ->page($page, $perPage)
            ->select()
            ->toArray();

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Create a new recipe with an initial draft version.
     *
     * @param array $data  Recipe attributes (title, site_id, content_json, prep_time, cook_time, total_time, difficulty, tags, steps).
     * @param int   $userId  Creating user ID.
     * @return array The created recipe data.
     */
    public function create(array $data, int $userId): array
    {
        if (empty($data['title'])) {
            throw new ValidateException('Title is required.');
        }
        if (empty($data['site_id'])) {
            throw new ValidateException('Site ID is required.');
        }

        Db::startTrans();
        try {
            $now = date('Y-m-d H:i:s');

            $recipeId = Db::table('recipes')->insertGetId([
                'site_id' => $data['site_id'],
                'title' => $data['title'],
                'status' => 'draft',
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $versionId = Db::table('recipe_versions')->insertGetId([
                'recipe_id' => $recipeId,
                'version_number' => 1,
                'status' => 'draft',
                'content_json' => $data['content_json'] ?? null,
                'prep_time' => $data['prep_time'] ?? null,
                'cook_time' => $data['cook_time'] ?? null,
                'total_time' => $data['total_time'] ?? null,
                'difficulty' => $data['difficulty'] ?? null,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (!empty($data['steps']) && is_array($data['steps'])) {
                foreach ($data['steps'] as $i => $step) {
                    Db::table('recipe_steps')->insert([
                        'version_id' => $versionId,
                        'step_number' => $i + 1,
                        'instruction' => $step['instruction'],
                        'duration_minutes' => $step['duration_minutes'] ?? null,
                        'created_at' => $now,
                    ]);
                }
            }

            if (!empty($data['tags']) && is_array($data['tags'])) {
                foreach ($data['tags'] as $tagName) {
                    $tag = Db::table('recipe_tags')->where('name', $tagName)->find();
                    if (!$tag) {
                        $tagId = Db::table('recipe_tags')->insertGetId([
                            'name' => $tagName,
                            'created_at' => $now,
                        ]);
                    } else {
                        $tagId = $tag['id'];
                    }
                    Db::table('recipe_version_tags')->insert([
                        'version_id' => $versionId,
                        'tag_id' => $tagId,
                    ]);
                }
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return Db::table('recipes')->where('id', $recipeId)->find();
    }

    /**
     * Find a single recipe by ID, restricted to the given site scopes.
     * Includes current version data, steps, tags, and images.
     *
     * @param int   $id
     * @param array $siteScopes  Site IDs the requesting user has access to.
     * @return array|null Recipe data or null if not found / not accessible.
     */
    public function find(int $id, array $siteScopes): ?array
    {
        $query = Db::table('recipes')
            ->where('id', $id);

        if (!empty($siteScopes)) {
            $query->whereIn('site_id', $siteScopes);
        }

        $recipe = $query->find();

        if (!$recipe) {
            return null;
        }

        // Get the latest version (published if available, otherwise most recent)
        $versionId = $recipe['published_version_id'];
        if ($versionId) {
            $version = Db::table('recipe_versions')->where('id', $versionId)->find();
        } else {
            $version = Db::table('recipe_versions')
                ->where('recipe_id', $id)
                ->order('version_number', 'desc')
                ->find();
        }

        if ($version) {
            $recipe['current_version'] = $version;

            $recipe['current_version']['steps'] = Db::table('recipe_steps')
                ->where('version_id', $version['id'])
                ->order('step_number', 'asc')
                ->select()
                ->toArray();

            $recipe['current_version']['tags'] = Db::table('recipe_version_tags')
                ->alias('rvt')
                ->join('recipe_tags rt', 'rt.id = rvt.tag_id')
                ->where('rvt.version_id', $version['id'])
                ->field('rt.id, rt.name')
                ->select()
                ->toArray();

            $recipe['current_version']['images'] = Db::table('recipe_images')
                ->where('version_id', $version['id'])
                ->order('sort_order', 'asc')
                ->select()
                ->toArray();
        }

        return $recipe;
    }

    /**
     * Create a new version of an existing recipe.
     *
     * @param int   $recipeId
     * @param array $data  Version attributes (content_json, prep_time, cook_time, total_time, difficulty, steps, tags).
     * @param int   $userId  Creating user ID.
     * @return array The created version data.
     */
    public function createVersion(int $recipeId, array $data, int $userId): array
    {
        $recipe = Db::table('recipes')->where('id', $recipeId)->find();
        if (!$recipe) {
            throw new ValidateException('Recipe not found.');
        }

        $latestVersion = Db::table('recipe_versions')
            ->where('recipe_id', $recipeId)
            ->order('version_number', 'desc')
            ->find();

        $nextNumber = $latestVersion ? $latestVersion['version_number'] + 1 : 1;
        $now = date('Y-m-d H:i:s');

        Db::startTrans();
        try {
            // Build content_json with ingredients if provided
            $contentData = [];
            if (isset($data['content_json'])) {
                $decoded = json_decode($data['content_json'], true);
                if (is_array($decoded)) {
                    $contentData = $decoded;
                } else {
                    $contentData['body'] = $data['content_json'];
                }
            }
            if (!empty($data['ingredients']) && is_array($data['ingredients'])) {
                $contentData['ingredients'] = $data['ingredients'];
            }

            $versionId = Db::table('recipe_versions')->insertGetId([
                'recipe_id' => $recipeId,
                'version_number' => $nextNumber,
                'status' => 'draft',
                'content_json' => !empty($contentData) ? json_encode($contentData) : null,
                'prep_time' => $data['prep_time'] ?? null,
                'cook_time' => $data['cook_time'] ?? null,
                'total_time' => $data['total_time'] ?? null,
                'difficulty' => $data['difficulty'] ?? null,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (!empty($data['steps']) && is_array($data['steps'])) {
                foreach ($data['steps'] as $i => $step) {
                    Db::table('recipe_steps')->insert([
                        'version_id' => $versionId,
                        'step_number' => $i + 1,
                        'instruction' => $step['instruction'],
                        'duration_minutes' => $step['duration_minutes'] ?? null,
                        'created_at' => $now,
                    ]);
                }
            }

            if (!empty($data['tags']) && is_array($data['tags'])) {
                foreach ($data['tags'] as $tagName) {
                    $tag = Db::table('recipe_tags')->where('name', $tagName)->find();
                    if (!$tag) {
                        $tagId = Db::table('recipe_tags')->insertGetId([
                            'name' => $tagName,
                            'created_at' => $now,
                        ]);
                    } else {
                        $tagId = $tag['id'];
                    }
                    Db::table('recipe_version_tags')->insert([
                        'version_id' => $versionId,
                        'tag_id' => $tagId,
                    ]);
                }
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return Db::table('recipe_versions')->where('id', $versionId)->find();
    }
}
