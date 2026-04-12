<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\controller\BaseController;
use app\service\search\SearchService;
use app\service\security\FieldMaskingService;
use think\Response;

class SearchController extends BaseController
{
    private const SEARCH_ROLES = ['operations_analyst', 'administrator', 'auditor'];

    public function query(SearchService $searchService): Response
    {
        if (empty(array_intersect($this->request->roles, self::SEARCH_ROLES))) {
            return $this->error('FORBIDDEN_ROLE', 'Only analyst, admin, or auditor roles can perform searches.', [], 403);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];
        $q = $input['q'] ?? $this->request->get('q', '');

        if (empty($q)) {
            return $this->error('VALIDATION_FAILED', 'Search query parameter "q" is required.', [], 422);
        }

        $filters = [
            'domains'   => $input['domains'] ?? [],
            'status'    => $input['status'] ?? null,
            'date_from' => $input['date_from'] ?? null,
            'date_to'   => $input['date_to'] ?? null,
            'page'      => (int)($input['page'] ?? 1),
            'per_page'  => min(100, (int)($input['per_page'] ?? 20)),
        ];

        $results = $searchService->query($filters, $q, $this->request->siteScopes);

        // Apply field masking to sensitive fields in search results
        $userRoles = $this->request->roles ?? [];
        $fieldMaskingService = app(FieldMaskingService::class);
        $maskableByDomain = [
            'participants' => ['phone'],
            'companies'    => ['tax_id_encrypted'],
        ];
        foreach ($maskableByDomain as $domain => $fields) {
            if (isset($results[$domain])) {
                $results[$domain] = array_map(function ($record) use ($fieldMaskingService, $fields, $userRoles) {
                    return $fieldMaskingService->applyMaskingToRecord($record, $fields, $userRoles);
                }, $results[$domain]);
            }
        }

        return $this->success([
            'query'   => $q,
            'results' => $results,
        ]);
    }
}
