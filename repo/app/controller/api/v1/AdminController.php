<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\controller\BaseController;
use app\model\Permission;
use app\model\PermissionChangeLog;
use app\model\Role;
use app\model\User;
use app\model\UserRole;
use app\model\UserSiteScope;
use app\service\audit\AuditService;
use app\service\auth\PasswordHashService;
use app\validate\UserValidate;
use think\exception\ValidateException;
use think\Response;

class AdminController extends BaseController
{
    /**
     * GET /api/v1/admin/roles
     *
     * List all roles.
     */
    public function roles(): Response
    {
        $items = Role::select()->toArray();

        return $this->success([
            'items' => $items,
        ]);
    }

    /**
     * GET /api/v1/admin/permissions
     *
     * List all permissions.
     */
    public function permissions(): Response
    {
        $items = Permission::select()->toArray();

        return $this->success([
            'items' => $items,
        ]);
    }

    /**
     * POST /api/v1/admin/users
     *
     * Create a new user.
     */
    public function createUser(PasswordHashService $passwordHashService, AuditService $auditService): Response
    {
        $input = json_decode($this->request->getInput(), true) ?: [];

        try {
            validate(UserValidate::class)->check($input);
        } catch (ValidateException $e) {
            return $this->error('VALIDATION_FAILED', $e->getMessage(), [], 422);
        }

        $existing = User::where('username', $input['username'])->find();
        if ($existing) {
            return $this->error('VALIDATION_FAILED', 'Username already exists.', [], 422);
        }

        $user = User::create([
            'username'      => $input['username'],
            'password_hash' => $passwordHashService->hash($input['password']),
            'display_name'  => $input['display_name'],
            'status'        => $input['status'] ?? 'active',
        ]);

        $auditService->log(
            'user.create',
            $this->request->userId,
            $this->request->roles[0] ?? 'unknown',
            null,
            'user',
            $user->id,
            $this->request->requestId,
            'Created user: ' . $input['username']
        );

        return $this->success([
            'id'      => $user->id,
            'message' => 'User created.',
        ], 201);
    }

    /**
     * PUT /api/v1/admin/users/:id
     *
     * Update an existing user.
     */
    public function updateUser($id, PasswordHashService $passwordHashService, AuditService $auditService): Response
    {
        $user = User::where('id', (int) $id)->find();

        if (!$user) {
            return $this->error('NOT_FOUND', 'User not found.', [], 404);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];

        $updateData = [];
        if (isset($input['display_name'])) {
            $updateData['display_name'] = $input['display_name'];
        }
        if (isset($input['status'])) {
            $updateData['status'] = $input['status'];
        }
        if (!empty($input['password'])) {
            $updateData['password_hash'] = $passwordHashService->hash($input['password']);
        }

        if (!empty($updateData)) {
            $user->save($updateData);
        }

        $auditService->log(
            'user.update',
            $this->request->userId,
            $this->request->roles[0] ?? 'unknown',
            null,
            'user',
            (int) $id,
            $this->request->requestId,
            'Updated user fields: ' . implode(', ', array_keys($updateData))
        );

        return $this->success([
            'id'      => (int) $id,
            'message' => 'User updated.',
        ]);
    }

    /**
     * PUT /api/v1/admin/users/:id/roles
     *
     * Assign roles to a user.
     */
    public function assignRoles($id, AuditService $auditService): Response
    {
        $user = User::where('id', (int) $id)->find();

        if (!$user) {
            return $this->error('NOT_FOUND', 'User not found.', [], 404);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];

        if (!isset($input['role_ids']) || !is_array($input['role_ids'])) {
            return $this->error('VALIDATION_FAILED', 'role_ids array is required.', [], 422);
        }

        // Get old roles for audit
        $oldRoles = UserRole::where('user_id', (int) $id)->column('role_id');

        // Replace roles
        UserRole::where('user_id', (int) $id)->delete();
        foreach ($input['role_ids'] as $roleId) {
            UserRole::create([
                'user_id' => (int) $id,
                'role_id' => (int) $roleId,
            ]);
        }

        $newRoles = $input['role_ids'];

        // Log permission change
        PermissionChangeLog::create([
            'actor_id'       => $this->request->userId,
            'target_user_id' => (int) $id,
            'change_type'    => 'role_assignment',
            'old_value'      => json_encode($oldRoles),
            'new_value'      => json_encode($newRoles),
            'request_id'     => $this->request->requestId,
        ]);

        $auditService->log(
            'user.assign_roles',
            $this->request->userId,
            $this->request->roles[0] ?? 'unknown',
            null,
            'user',
            (int) $id,
            $this->request->requestId,
            'Roles changed from [' . implode(',', $oldRoles) . '] to [' . implode(',', $newRoles) . ']'
        );

        // Resolve role names for response
        $roleNames = Role::whereIn('id', $newRoles)->column('name');

        return $this->success([
            'user_id' => (int) $id,
            'roles'   => $roleNames,
            'message' => 'Roles assigned.',
        ]);
    }

    /**
     * PUT /api/v1/admin/users/:id/site-scopes
     *
     * Assign site scopes to a user.
     */
    public function assignSiteScopes($id, AuditService $auditService): Response
    {
        $user = User::where('id', (int) $id)->find();

        if (!$user) {
            return $this->error('NOT_FOUND', 'User not found.', [], 404);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];

        if (!isset($input['site_ids']) || !is_array($input['site_ids'])) {
            return $this->error('VALIDATION_FAILED', 'site_ids array is required.', [], 422);
        }

        // Get old scopes for audit
        $oldScopes = UserSiteScope::where('user_id', (int) $id)->column('site_id');

        // Replace site scopes
        UserSiteScope::where('user_id', (int) $id)->delete();
        foreach ($input['site_ids'] as $siteId) {
            UserSiteScope::create([
                'user_id' => (int) $id,
                'site_id' => (int) $siteId,
            ]);
        }

        $newScopes = $input['site_ids'];

        // Log permission change
        PermissionChangeLog::create([
            'actor_id'       => $this->request->userId,
            'target_user_id' => (int) $id,
            'change_type'    => 'site_scope_assignment',
            'old_value'      => json_encode($oldScopes),
            'new_value'      => json_encode($newScopes),
            'request_id'     => $this->request->requestId,
        ]);

        $auditService->log(
            'user.assign_site_scopes',
            $this->request->userId,
            $this->request->roles[0] ?? 'unknown',
            null,
            'user',
            (int) $id,
            $this->request->requestId,
            'Site scopes changed from [' . implode(',', $oldScopes) . '] to [' . implode(',', $newScopes) . ']'
        );

        return $this->success([
            'user_id'     => (int) $id,
            'site_scopes' => $newScopes,
            'message'     => 'Site scopes assigned.',
        ]);
    }
}
