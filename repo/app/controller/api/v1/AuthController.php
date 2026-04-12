<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\controller\BaseController;
use app\service\auth\AuthService;
use think\Response;

class AuthController extends BaseController
{
    /**
     * POST /api/v1/auth/login
     */
    public function login(AuthService $authService): Response
    {
        $input = $this->request->getInput();
        $json = json_decode($input, true) ?: [];
        $username = $json['username'] ?? $this->request->post('username', '');
        $password = $json['password'] ?? $this->request->post('password', '');

        if (empty($username) || empty($password)) {
            return $this->error('VALIDATION_FAILED', 'Username and password are required.', [], 422);
        }

        $user = $authService->attempt($username, $password);

        if (!$user) {
            return $this->error('AUTH_INVALID_CREDENTIALS', 'Invalid credentials.', [], 401);
        }

        // Resolve roles and scopes
        $rolesAndScopes = $authService->resolveUserRolesAndScopes($user['id']);

        // Establish session
        session('user_id', $user['id']);
        session('user_roles', $rolesAndScopes['roles']);
        session('user_site_scopes', $rolesAndScopes['site_scopes']);

        // Generate CSRF token for session
        $csrfToken = bin2hex(random_bytes(32));
        session('csrf_token', $csrfToken);

        return $this->success([
            'user' => [
                'id'           => $user['id'],
                'username'     => $user['username'],
                'display_name' => $user['display_name'],
                'roles'        => $rolesAndScopes['roles'],
                'site_scopes'  => $rolesAndScopes['site_scopes'],
            ],
            'csrf_token' => $csrfToken,
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(): Response
    {
        session(null);
        return $this->success(['message' => 'Logged out successfully.']);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(): Response
    {
        return $this->success([
            'user_id'     => $this->request->userId,
            'roles'       => $this->request->roles,
            'site_scopes' => !empty($this->request->siteScopes)
                ? $this->request->siteScopes
                : (session('user_site_scopes') ?: []),
            'csrf_token'  => session('csrf_token') ?: '',
        ]);
    }
}
