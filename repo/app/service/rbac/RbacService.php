<?php
declare(strict_types=1);

namespace app\service\rbac;

use think\facade\Db;

class RbacService
{
    /**
     * Check whether a user has a specific permission.
     *
     * @param int    $userId
     * @param string $permission Permission name.
     * @return bool
     */
    public function userHasPermission(int $userId, string $permission): bool
    {
        $count = Db::table('user_roles')
            ->alias('ur')
            ->join('role_permissions rp', 'rp.role_id = ur.role_id')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('ur.user_id', $userId)
            ->where('p.name', $permission)
            ->count();

        return $count > 0;
    }

    /**
     * Get all permissions assigned to a user (via their roles).
     *
     * @param int $userId
     * @return array List of permission name strings.
     */
    public function getUserPermissions(int $userId): array
    {
        $rows = Db::table('user_roles')
            ->alias('ur')
            ->join('role_permissions rp', 'rp.role_id = ur.role_id')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('ur.user_id', $userId)
            ->distinct(true)
            ->field('p.name')
            ->select()
            ->toArray();

        return array_column($rows, 'name');
    }

    /**
     * Assign a role to a user.
     *
     * @param int $userId
     * @param int $roleId
     * @return void
     */
    public function assignRole(int $userId, int $roleId): void
    {
        $exists = Db::table('user_roles')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->find();

        if (!$exists) {
            Db::table('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $roleId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Remove a role from a user.
     *
     * @param int $userId
     * @param int $roleId
     * @return void
     */
    public function removeRole(int $userId, int $roleId): void
    {
        Db::table('user_roles')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->delete();
    }
}
