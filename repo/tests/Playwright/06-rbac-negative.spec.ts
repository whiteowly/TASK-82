import { test, expect } from '@playwright/test';
import { login, apiPost, apiGet, getCsrf } from './helpers';

test.describe('RBAC & Site Scope Negative Checks', () => {

  test('editor cannot create recipe in unassigned site', async ({ page }) => {
    await login(page, 'editor');
    const csrf = await getCsrf(page);
    // editor has sites 1,2 but not 3
    const res = await apiPost(page, '/api/v1/recipes', { title: 'Forbidden', site_id: 3 }, csrf);
    expect(res.status).toBe(403);
  });

  test('finance cannot access admin routes', async ({ page }) => {
    await login(page, 'finance');
    const csrf = await getCsrf(page);
    const res = await apiPost(page, '/api/v1/admin/users', {
      username: 'hacker', password: 'password', display_name: 'Hacker',
    }, csrf);
    expect(res.status).toBe(403);
  });

  test('analyst cannot approve settlements', async ({ page }) => {
    await login(page, 'analyst');
    const csrf = await getCsrf(page);
    const res = await apiPost(page, '/api/v1/finance/settlements/1/approve-final', {}, csrf);
    expect(res.status).toBe(403);
  });

  test('editor cannot access audit logs', async ({ page }) => {
    await login(page, 'editor');
    const res = await apiGet(page, '/api/v1/audit/logs');
    expect(res.status).toBe(403);
  });

  test('recipe validation rejects bad step count', async ({ page }) => {
    await login(page, 'editor');
    const csrf = await getCsrf(page);
    // Create recipe first (setup)
    const create = await apiPost(page, '/api/v1/recipes', { title: 'Validate Test', site_id: 1 }, csrf);
    const recipeId = create.body.data.id;
    const read = await apiGet(page, `/api/v1/recipes/${recipeId}`);
    const versionId = read.body.data.versions[0].id;

    const res = await page.request.put(`http://127.0.0.1:8080/api/v1/recipe-versions/${versionId}`, {
      data: { total_time: 0 },
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
    });
    expect(res.status()).toBe(422);
  });

  test('recipe validation rejects bad total time', async ({ page }) => {
    await login(page, 'editor');
    const csrf = await getCsrf(page);
    const create = await apiPost(page, '/api/v1/recipes', { title: 'Time Test', site_id: 1 }, csrf);
    const recipeId = create.body.data.id;
    const read = await apiGet(page, `/api/v1/recipes/${recipeId}`);
    const versionId = read.body.data.versions[0].id;

    const res = await page.request.put(`http://127.0.0.1:8080/api/v1/recipe-versions/${versionId}`, {
      data: { total_time: 999 },
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
    });
    expect(res.status()).toBe(422);
  });

  test('401 on protected route without auth', async ({ page }) => {
    const res = await page.request.get('http://127.0.0.1:8080/api/v1/recipes');
    expect(res.status()).toBe(401);
  });

  test('404 on nonexistent API endpoint', async ({ page }) => {
    await login(page, 'admin');
    const res = await apiGet(page, '/api/v1/nonexistent');
    expect(res.status).toBe(404);
    expect(res.body.error.code).toBe('NOT_FOUND');
  });
});
