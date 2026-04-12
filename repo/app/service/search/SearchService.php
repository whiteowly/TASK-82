<?php
declare(strict_types=1);

namespace app\service\search;

use think\facade\Db;

class SearchService
{
    /**
     * Available search domains.
     */
    private const DOMAINS = [
        'positions',
        'companies',
        'participants',
        'recipes',
        'orders',
        'settlements',
    ];

    /**
     * Execute a search query across configured domains.
     *
     * @param array       $filters    Structured filters (domains, status, date_start, date_end, page, per_page).
     * @param string|null $textQuery  Free-text search query.
     * @param array       $siteScopes Site IDs the requesting user has access to.
     * @return array Search results grouped by domain.
     */
    public function query(array $filters, ?string $textQuery, array $siteScopes): array
    {
        $targetDomains = !empty($filters['domains']) ? $filters['domains'] : self::DOMAINS;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($filters['per_page'] ?? 10)));
        $results = [];

        foreach ($targetDomains as $domain) {
            if (!in_array($domain, self::DOMAINS, true)) {
                continue;
            }

            $results[$domain] = match ($domain) {
                'positions' => $this->searchPositions($textQuery, $page, $perPage),
                'companies' => $this->searchCompanies($textQuery, $page, $perPage),
                'participants' => $this->searchParticipants($textQuery, $siteScopes, $page, $perPage),
                'recipes' => $this->searchRecipes($textQuery, $siteScopes, $filters, $page, $perPage),
                'orders' => $this->searchOrders($textQuery, $siteScopes, $filters, $page, $perPage),
                'settlements' => $this->searchSettlements($textQuery, $siteScopes, $filters, $page, $perPage),
            };
        }

        return $results;
    }

    private function searchPositions(?string $textQuery, int $page, int $perPage): array
    {
        $query = Db::table('positions');
        if ($textQuery) {
            $query->where('name', 'like', '%' . $textQuery . '%');
        }
        return $query->page($page, $perPage)->select()->toArray();
    }

    private function searchCompanies(?string $textQuery, int $page, int $perPage): array
    {
        $query = Db::table('companies');
        if ($textQuery) {
            $query->where('name', 'like', '%' . $textQuery . '%');
        }
        return $query->page($page, $perPage)->select()->toArray();
    }

    private function searchParticipants(?string $textQuery, array $siteScopes, int $page, int $perPage): array
    {
        // empty siteScopes = cross-site admin, no filtering needed
        $query = Db::table('participants')->where(function($q) use ($siteScopes) { if (!empty($siteScopes)) $q->whereIn('site_id', $siteScopes); });
        if ($textQuery) {
            $query->where(function ($q) use ($textQuery) {
                $q->where('name', 'like', '%' . $textQuery . '%')
                  ->whereOr('phone', 'like', '%' . $textQuery . '%');
            });
        }
        return $query->page($page, $perPage)->select()->toArray();
    }

    private function searchRecipes(?string $textQuery, array $siteScopes, array $filters, int $page, int $perPage): array
    {
        // empty siteScopes = cross-site admin, no filtering needed
        $query = Db::table('recipes')->where(function($q) use ($siteScopes) { if (!empty($siteScopes)) $q->whereIn('site_id', $siteScopes); });
        if ($textQuery) {
            $query->where('title', 'like', '%' . $textQuery . '%');
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        return $query->page($page, $perPage)->select()->toArray();
    }

    private function searchOrders(?string $textQuery, array $siteScopes, array $filters, int $page, int $perPage): array
    {
        // empty siteScopes = cross-site admin, no filtering needed
        $query = Db::table('orders')->where(function($q) use ($siteScopes) { if (!empty($siteScopes)) $q->whereIn('site_id', $siteScopes); });
        if ($textQuery) {
            $query->where('id', 'like', '%' . $textQuery . '%');
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['date_start'])) {
            $query->where('created_at', '>=', $filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $query->where('created_at', '<=', $filters['date_end']);
        }
        return $query->page($page, $perPage)->select()->toArray();
    }

    private function searchSettlements(?string $textQuery, array $siteScopes, array $filters, int $page, int $perPage): array
    {
        // empty siteScopes = cross-site admin, no filtering needed
        $query = Db::table('settlement_statements')->where(function($q) use ($siteScopes) { if (!empty($siteScopes)) $q->whereIn('site_id', $siteScopes); });
        if ($textQuery) {
            $query->where('period', 'like', '%' . $textQuery . '%');
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        return $query->page($page, $perPage)->select()->toArray();
    }
}
