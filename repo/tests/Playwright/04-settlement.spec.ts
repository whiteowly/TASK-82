import { test, expect } from '@playwright/test';
import { login, getCsrf, apiGet, apiPost } from './helpers';

test.describe.serial('Settlement Flow', () => {

  test('full settlement lifecycle via UI: generate -> submit -> approve -> reverse', async ({ page }) => {
    // === Finance generates statement ===
    await login(page, 'finance');
    await page.goto('/settlements');
    await page.waitForTimeout(3000);

    // Click Statements tab (second tab)
    await page.locator('.layui-tab-title li').nth(1).click();
    await page.waitForTimeout(4000);

    // Click Generate Statement button
    await page.click('#btn-generate-statement');
    await page.waitForTimeout(1000);

    // Fill the generate dialog
    await page.fill('#gen-site-id', '1');
    await page.fill('#gen-period', '2024-01');

    // Confirm
    await page.click('.layui-layer-btn0');
    await page.waitForTimeout(3000);
    await page.screenshot({ path: 'tests/Playwright/artifacts/04-statement-generated.png' });

    // Reload tabs to see new statement
    await page.locator('.layui-tab-title li').nth(0).click();
    await page.waitForTimeout(500);
    await page.locator('.layui-tab-title li').nth(1).click();
    await page.waitForTimeout(4000);

    // Click View on the draft statement
    const viewBtn = page.locator('.layui-table-view[lay-id=statements-table] .layui-btn-normal').first();
    await viewBtn.waitFor({ state: 'visible', timeout: 10000 });
    await viewBtn.click();
    await page.waitForSelector('#statement-detail-panel', { state: 'visible', timeout: 10000 });
    await page.screenshot({ path: 'tests/Playwright/artifacts/04-statement-detail-draft.png' });

    // Verify draft status in UI
    const statusText = await page.locator('#statement-status-badge').textContent();
    expect(statusText?.toLowerCase()).toContain('draft');

    // Submit via UI
    await page.click('#btn-submit-statement');
    await page.waitForSelector('.layui-layer-btn0', { state: 'visible', timeout: 5000 });
    await page.click('.layui-layer-btn0');
    await page.waitForTimeout(2000);
    await page.screenshot({ path: 'tests/Playwright/artifacts/04-statement-submitted.png' });

    // === Admin approves ===
    await page.context().clearCookies();
    await login(page, 'admin');
    await page.goto('/settlements');
    await page.waitForTimeout(3000);

    // Navigate to Statements tab
    await page.locator('.layui-tab-title li').nth(1).click();
    await page.waitForTimeout(4000);

    // Find the submitted statement via View button
    const adminViewBtn = page.locator('.layui-table-view[lay-id=statements-table] .layui-btn-normal').first();
    await adminViewBtn.waitFor({ state: 'visible', timeout: 10000 });
    await adminViewBtn.click();
    await page.waitForSelector('#statement-detail-panel', { state: 'visible', timeout: 10000 });

    // Approve via UI
    await page.click('#btn-approve-statement');
    await page.waitForSelector('.layui-layer-btn0', { state: 'visible', timeout: 5000 });
    await page.click('.layui-layer-btn0');
    await page.waitForTimeout(2000);
    await page.screenshot({ path: 'tests/Playwright/artifacts/04-statement-locked.png' });

    // Verify locked status
    const lockedText = await page.locator('#statement-status-badge').textContent();
    expect(lockedText?.toLowerCase()).toContain('approved');

    // === Reverse via UI ===
    await page.click('#btn-reverse-statement');
    await page.waitForTimeout(1000);

    // Fill reason in the prompt dialog
    const reasonInput = page.locator('.layui-layer-input, .layui-layer-content textarea').first();
    await reasonInput.fill('E2E test reversal');
    await page.click('.layui-layer-btn0');
    await page.waitForTimeout(2000);
    await page.screenshot({ path: 'tests/Playwright/artifacts/04-statement-reversed.png' });
  });

  test('locked statement rejects resubmit (423)', async ({ page }) => {
    await login(page, 'finance');
    const csrf = await getCsrf(page);
    const list = await apiGet(page, '/api/v1/finance/settlements');
    const locked = (list.body.data?.items || []).find((s: any) => s.status === 'approved_locked');
    if (locked) {
      const res = await apiPost(page, `/api/v1/finance/settlements/${locked.id}/submit`, {}, csrf);
      expect(res.status).toBe(423);
    } else {
      // If no locked statement exists (was reversed), generate -> submit -> approve -> try resubmit
      await page.context().clearCookies();
      await login(page, 'finance');
      const fCsrf = await getCsrf(page);
      const gen = await apiPost(page, '/api/v1/finance/settlements/generate', { site_id: 1, period: '2024-02' }, fCsrf);
      const newList = await apiGet(page, '/api/v1/finance/settlements');
      const draft = (newList.body.data?.items || []).find((s: any) => s.status === 'draft');
      expect(draft).toBeTruthy();
      await apiPost(page, `/api/v1/finance/settlements/${draft.id}/submit`, {}, fCsrf);

      await page.context().clearCookies();
      await login(page, 'admin');
      const aCsrf = await getCsrf(page);
      const subList = await apiGet(page, '/api/v1/finance/settlements');
      const submitted = (subList.body.data?.items || []).find((s: any) => s.status === 'submitted');
      expect(submitted).toBeTruthy();
      await apiPost(page, `/api/v1/finance/settlements/${submitted.id}/approve-final`, {}, aCsrf);

      await page.context().clearCookies();
      await login(page, 'finance');
      const f2Csrf = await getCsrf(page);
      const lockedList = await apiGet(page, '/api/v1/finance/settlements');
      const nowLocked = (lockedList.body.data?.items || []).find((s: any) => s.status === 'approved_locked');
      expect(nowLocked).toBeTruthy();
      const res = await apiPost(page, `/api/v1/finance/settlements/${nowLocked.id}/submit`, {}, f2Csrf);
      expect(res.status).toBe(423);
    }
  });

  test('finance cannot approve-final (403)', async ({ page }) => {
    await login(page, 'finance');
    const csrf = await getCsrf(page);
    const list = await apiGet(page, '/api/v1/finance/settlements');
    const submitted = (list.body.data?.items || []).find((s: any) => s.status === 'submitted');
    if (submitted) {
      const res = await apiPost(page, `/api/v1/finance/settlements/${submitted.id}/approve-final`, {}, csrf);
      expect(res.status).toBe(403);
    } else {
      // Create a submitted one to test against
      const gen = await apiPost(page, '/api/v1/finance/settlements/generate', { site_id: 1, period: '2024-03' }, csrf);
      const newList = await apiGet(page, '/api/v1/finance/settlements');
      const draft = (newList.body.data?.items || []).find((s: any) => s.status === 'draft');
      expect(draft).toBeTruthy();
      await apiPost(page, `/api/v1/finance/settlements/${draft.id}/submit`, {}, csrf);
      const res = await apiPost(page, `/api/v1/finance/settlements/${draft.id}/approve-final`, {}, csrf);
      expect(res.status).toBe(403);
    }
  });
});
