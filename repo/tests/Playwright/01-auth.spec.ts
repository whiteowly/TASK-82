import { test, expect } from '@playwright/test';
import { getPassword, login } from './helpers';

test.describe('Authentication & Session', () => {
  test('login page renders', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('input[name="username"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await page.screenshot({ path: 'tests/Playwright/artifacts/01-login-page.png' });
  });

  test('invalid login shows error', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="username"]', 'baduser');
    await page.fill('input[name="password"]', 'badpass');
    await page.click('button[lay-submit]');
    // Should stay on login and show error
    await page.waitForTimeout(2000);
    await page.screenshot({ path: 'tests/Playwright/artifacts/01-login-error.png' });
    // Should not have navigated to dashboard
    expect(page.url()).toContain('/login');
  });

  test('valid login redirects to dashboard', async ({ page }) => {
    await login(page, 'admin');
    expect(page.url()).toContain('/dashboard');
    await page.screenshot({ path: 'tests/Playwright/artifacts/01-dashboard-after-login.png' });
    // Verify the header shows username
    await expect(page.locator('#header-username')).toContainText('Administrator');
  });

  test('unauthenticated redirect to login', async ({ page }) => {
    await page.goto('/dashboard');
    await page.waitForURL('**/login', { timeout: 5000 });
    expect(page.url()).toContain('/login');
  });

  test('logout destroys session', async ({ page }) => {
    await login(page, 'admin');
    // Hover the user nav item to reveal the dropdown containing logout
    await page.hover('.layui-layout-right .layui-nav-item');
    await page.waitForTimeout(500);
    await page.click('#btn-logout');
    await page.waitForURL('**/login', { timeout: 10000 });
    expect(page.url()).toContain('/login');
    await page.screenshot({ path: 'tests/Playwright/artifacts/01-after-logout.png' });
  });

  test('CSRF rejection on protected mutation', async ({ page }) => {
    await login(page, 'admin');
    // Direct API call without CSRF token
    const response = await page.request.post('http://127.0.0.1:8080/api/v1/auth/logout', {
      headers: { 'Content-Type': 'application/json' },
    });
    expect(response.status()).toBe(403);
  });
});
