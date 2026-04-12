<?php
declare(strict_types=1);

namespace app\service\rbac;

use think\facade\Db;

class SiteScopeService
{
    /**
     * Get all site IDs a user has access to.
     *
     * @param int $userId
     * @return array List of site IDs.
     */
    public function getUserSiteIds(int $userId): array
    {
        $rows = Db::table('user_site_scopes')
            ->where('user_id', $userId)
            ->field('site_id')
            ->select()
            ->toArray();

        return array_column($rows, 'site_id');
    }

    /**
     * Check whether a user has access to a specific site.
     *
     * @param int $userId
     * @param int $siteId
     * @return bool
     */
    public function userHasSiteAccess(int $userId, int $siteId): bool
    {
        $count = Db::table('user_site_scopes')
            ->where('user_id', $userId)
            ->where('site_id', $siteId)
            ->count();

        return $count > 0;
    }

    /**
     * Assign a site scope to a user.
     *
     * @param int $userId
     * @param int $siteId
     * @return void
     */
    public function assignSiteScope(int $userId, int $siteId): void
    {
        $exists = Db::table('user_site_scopes')
            ->where('user_id', $userId)
            ->where('site_id', $siteId)
            ->find();

        if (!$exists) {
            Db::table('user_site_scopes')->insert([
                'user_id' => $userId,
                'site_id' => $siteId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
