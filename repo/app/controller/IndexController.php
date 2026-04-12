<?php
declare(strict_types=1);

namespace app\controller;

use think\facade\Db;
use think\facade\View;
use think\Response;
use app\service\rbac\RbacService;

class IndexController extends BaseController
{
    /**
     * Assign common session-derived variables to the view for authenticated pages.
     */
    private function assignAuthViewData(): void
    {
        $userId = session('user_id');
        $rbacService = app(RbacService::class);

        $user = Db::table('users')->where('id', $userId)->find();
        $displayName = $user['display_name'] ?? 'User';

        View::assign([
            'csrf_token'       => session('csrf_token'),
            'username'         => $displayName,
            'user_roles'       => session('user_roles') ?? [],
            'user_permissions' => $rbacService->getUserPermissions((int)$userId),
            'user_site_scopes' => session('user_site_scopes') ?? [],
        ]);
    }

    /**
     * Check if the user is authenticated; if not, redirect to /login.
     */
    private function requireAuth(): ?Response
    {
        if (!session('user_id')) {
            return redirect('/login');
        }
        return null;
    }

    public function index(): Response
    {
        if (session('user_id')) {
            return redirect('/dashboard');
        }
        return redirect('/login');
    }

    public function login()
    {
        if (session('user_id')) {
            return redirect('/dashboard');
        }
        return View::fetch('auth/login');
    }

    public function dashboard()
    {
        if ($r = $this->requireAuth()) return $r;
        $this->assignAuthViewData();
        return View::fetch('dashboard/index');
    }

    public function recipeEditor()
    {
        if ($r = $this->requireAuth()) return $r;
        $this->assignAuthViewData();
        return View::fetch('recipe/editor');
    }

    public function recipeReview()
    {
        if ($r = $this->requireAuth()) return $r;
        $this->assignAuthViewData();
        return View::fetch('recipe/review');
    }

    public function catalog()
    {
        if ($r = $this->requireAuth()) return $r;
        $this->assignAuthViewData();
        return View::fetch('catalog/index');
    }

    public function analytics()
    {
        if ($r = $this->requireAuth()) return $r;
        $this->assignAuthViewData();
        return View::fetch('analytics/dashboard');
    }

    public function reports()
    {
        if ($r = $this->requireAuth()) return $r;
        $this->assignAuthViewData();
        return View::fetch('report/index');
    }

    public function settlements()
    {
        if ($r = $this->requireAuth()) return $r;
        $this->assignAuthViewData();
        return View::fetch('settlement/index');
    }

    public function audit()
    {
        if ($r = $this->requireAuth()) return $r;
        $this->assignAuthViewData();
        return View::fetch('audit/index');
    }

    public function admin()
    {
        if ($r = $this->requireAuth()) return $r;
        $roles = session('user_roles') ?? [];
        if (!in_array('administrator', $roles, true)) {
            return redirect('/dashboard');
        }
        $this->assignAuthViewData();
        return View::fetch('admin/index');
    }
}
