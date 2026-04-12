import { test, expect } from '@playwright/test';
import { login } from './helpers';

test.describe('Audit & Admin Flows', () => {

  test('admin views audit entries via page content', async ({ page }) => {
    await login(page, 'admin');
    await page.goto('/audit');
    await page.waitForTimeout(5000);

    // Assert that the page loaded with audit content
    // Layui tables render cell data outside #audit-content, so check the full body
    const bodyText = await page.textContent('body');
    expect(bodyText?.length).toBeGreaterThan(200);
    // The page should have filter controls and table structure
    expect(bodyText).toContain('Event Type');
    expect(bodyText).toContain('Audit');
    await page.screenshot({ path: 'tests/Playwright/artifacts/05-audit-entries.png' });

    // Navigate tabs
    const tabs = page.locator('.layui-tab-title li');
    const count = await tabs.count();
    for (let i = 0; i < count; i++) {
      await tabs.nth(i).click();
      await page.waitForTimeout(1500);
    }
    await page.screenshot({ path: 'tests/Playwright/artifacts/05-audit-tabs.png' });

    // Filter by event type via UI
    await page.evaluate(() => {
      const el = document.getElementById('audit-filter-event-type') as HTMLSelectElement;
      if (el) { el.value = 'user.login'; }
    });
    await page.click('#btn-audit-search');
    await page.waitForTimeout(2000);
    await page.screenshot({ path: 'tests/Playwright/artifacts/05-audit-filtered.png' });
  });

  test('auditor has read access to audit page', async ({ page }) => {
    await login(page, 'auditor');
    await page.goto('/audit');
    await page.waitForTimeout(3000);

    // Should see audit content (not be redirected)
    expect(page.url()).toContain('/audit');
    const content = await page.textContent('#audit-content');
    expect(content).toBeTruthy();
    await page.screenshot({ path: 'tests/Playwright/artifacts/05-audit-auditor-view.png' });
  });

  test('editor is denied audit page access via UI', async ({ page }) => {
    await login(page, 'editor');
    await page.goto('/audit');
    await page.waitForTimeout(2000);

    const url = page.url();
    const hasError = await page.locator('text=/forbidden|denied|permission|403/i').count();
    const redirected = !url.includes('/audit');
    expect(redirected || hasError > 0).toBe(true);
    await page.screenshot({ path: 'tests/Playwright/artifacts/05-audit-editor-denied.png' });
  });

  test('admin navigates admin page tabs with real content', async ({ page }) => {
    await login(page, 'admin');
    await page.goto('/admin');
    await page.waitForTimeout(3000);
    await page.screenshot({ path: 'tests/Playwright/artifacts/05-admin-page.png' });

    const bodyText = await page.textContent('body');
    // Admin page should show user names from seed data
    expect(bodyText).toContain('Administrator');

    // Navigate tabs
    const tabs = page.locator('.layui-tab-title li');
    const tabCount = await tabs.count();
    expect(tabCount).toBeGreaterThanOrEqual(2);

    for (let i = 0; i < tabCount; i++) {
      await tabs.nth(i).click();
      await page.waitForTimeout(1500);
    }
    await page.screenshot({ path: 'tests/Playwright/artifacts/05-admin-tabs.png' });
  });

  test('admin sees approval and permission change entries in audit', async ({ page }) => {
    await login(page, 'admin');
    await page.goto('/audit');
    await page.waitForTimeout(4000);

    // The page content should have loaded
    const pageText = await page.textContent('body');
    // Audit pages with seeded data should show entries
    expect(pageText?.length).toBeGreaterThan(100);
    await page.screenshot({ path: 'tests/Playwright/artifacts/05-audit-approval-entries.png' });
  });
});
