<?php
declare(strict_types=1);

namespace app\service\auth;

use think\facade\Db;

class AuthService
{
    private PasswordHashService $passwordHash;

    public function __construct(PasswordHashService $passwordHash)
    {
        $this->passwordHash = $passwordHash;
    }

    /**
     * Authenticate a user by username and password.
     *
     * @return array|null User data on success, null on failure.
     */
    public function attempt(string $username, string $password): ?array
    {
        $user = Db::name('users')
            ->where('username', $username)
            ->where('status', 'active')
            ->find();

        if (!$user) {
            return null;
        }

        if (!$this->passwordHash->verify($password, $user['password_hash'])) {
            return null;
        }

        // Rehash if algorithm/cost changed
        if ($this->passwordHash->needsRehash($user['password_hash'])) {
            Db::name('users')
                ->where('id', $user['id'])
                ->update(['password_hash' => $this->passwordHash->hash($password)]);
        }

        return [
            'id'           => (int)$user['id'],
            'username'     => $user['username'],
            'display_name' => $user['display_name'],
        ];
    }

    /**
     * Resolve roles and site scopes for a user.
     */
    public function resolveUserRolesAndScopes(int $userId): array
    {
        $roles = Db::name('user_roles')
            ->alias('ur')
            ->join('roles r', 'ur.role_id = r.id')
            ->where('ur.user_id', $userId)
            ->column('r.name');

        $siteScopes = Db::name('user_site_scopes')
            ->where('user_id', $userId)
            ->column('site_id');

        return [
            'roles'       => $roles,
            'site_scopes' => $siteScopes,
        ];
    }
}
