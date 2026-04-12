<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\controller\BaseController;
use app\model\Recipe;
use app\model\RecipeVersion;
use think\Response;

class CatalogController extends BaseController
{
    /**
     * GET /api/v1/catalog
     *
     * List published recipes in the catalog.
     */
    public function index(): Response
    {
        $page    = max(1, (int) $this->request->get('page', 1));
        $perPage = min(100, max(1, (int) $this->request->get('per_page', 20)));

        $query = Recipe::whereNotNull('published_version_id');
        $this->applySiteScope($query);

        $total      = $query->count();
        $totalPages = (int) ceil($total / $perPage);
        $items      = $query->order('updated_at', 'desc')
                            ->page($page, $perPage)
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
     * GET /api/v1/catalog/:id
     *
     * Read a single published recipe from the catalog.
     */
    public function read($id): Response
    {
        $query = Recipe::whereNotNull('published_version_id')
            ->where('id', (int) $id);
        $this->applySiteScope($query);
        $recipe = $query->find();

        if (!$recipe) {
            return $this->error('NOT_FOUND', 'Published recipe not found.', [], 404);
        }

        $version = RecipeVersion::where('id', $recipe->published_version_id)->find();

        $recipeData = $recipe->toArray();
        $recipeData['published_version'] = $version ? $version->toArray() : null;

        return $this->success($recipeData);
    }
}
