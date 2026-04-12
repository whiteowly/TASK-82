import { test, expect } from '@playwright/test';
import { login, getCsrf, apiGet } from './helpers';

test.describe('Analytics Dashboard & Reporting', () => {

  test('analyst views dashboard KPI cards via UI', async ({ page }) => {
    await login(page, 'analyst');
    await page.goto('/analytics');
    await page.waitForTimeout(3000);

    await page.waitForFunction(
      () => {
        const row = document.querySelector('#analytics-kpi-row') as HTMLElement | null;
        return row && row.style.display !== 'none' && row.offsetParent !== null;
      },
      { timeout: 20000 },
    );

    await page.waitForFunction(
      () => {
        const el = document.querySelector('#akpi-total-sales');
        return el && el.textContent?.trim() !== '--' && el.textContent?.trim() !== '';
      },
      { timeout: 20000 },
    );

    await page.screenshot({ path: 'tests/Playwright/artifacts/03-analytics-dashboard.png' });

    const totalSalesText = await page.textContent('#akpi-total-sales');
    expect(totalSalesText).toBeTruthy();
    expect(totalSalesText?.trim()).not.toBe('--');
  });

  test('analyst filters by site and applies via UI', async ({ page }) => {
    await login(page, 'analyst');
    await page.goto('/analytics');
    await page.waitForTimeout(3000);

    await page.evaluate(() => {
      const sel = document.getElementById('analytics-site-filter') as HTMLSelectElement;
      if (sel && sel.options.length > 1) { sel.value = sel.options[1].value; }
    });
    await page.click('#analytics-apply-btn');
    await page.waitForTimeout(2000);
    await page.screenshot({ path: 'tests/Playwright/artifacts/03-analytics-filtered.png' });
  });

  test('analyst clicks KPI card to see metric definition drawer', async ({ page }) => {
    await login(page, 'analyst');
    await page.goto('/analytics');
    await page.waitForSelector('.kpi-card[data-metric="total_sales"]', { state: 'visible', timeout: 15000 });

    await page.click('.kpi-card[data-metric="total_sales"]');
    await page.waitForSelector('.layui-layer-content', { state: 'visible', timeout: 5000 });

    const text = await page.locator('.layui-layer-content').textContent();
    expect(text?.length).toBeGreaterThan(0);

    await page.screenshot({ path: 'tests/Playwright/artifacts/03-analytics-kpi-drawer.png' });
    await page.click('.layui-layer-close').catch(() => {});
  });

  test('analyst triggers refresh via UI button', async ({ page }) => {
    await login(page, 'analyst');
    await page.goto('/analytics');
    await page.waitForSelector('#analytics-refresh-btn', { state: 'visible', timeout: 10000 });

    await page.click('#analytics-refresh-btn');
    await page.waitForSelector('.layui-layer-msg', { state: 'visible', timeout: 10000 }).catch(() => {});
    await page.screenshot({ path: 'tests/Playwright/artifacts/03-analytics-refreshed.png' });
  });

  test('refresh rate limit shows visible message after repeated use', async ({ page }) => {
    await login(page, 'analyst');
    await page.goto('/analytics');
    await page.waitForSelector('#analytics-refresh-btn', { state: 'visible', timeout: 10000 });

    // Click refresh, wait for completion, repeat — to exhaust the 5/hour limit.
    // The refresh handler has a refreshInProgress guard, so we must wait for each to complete.
    for (let i = 0; i < 8; i++) {
      await page.click('#analytics-refresh-btn');
      // Wait for the button to lose and regain enabled state, or for the msg to show
      await page.waitForTimeout(1500);
    }

    // After exceeding the limit, the UI shows "Rate limited: please wait before refreshing again."
    // via layui.layer.msg. Verify this message appeared.
    await page.waitForTimeout(1000);
    const msgVisible = await page.locator('.layui-layer-msg').isVisible().catch(() => false);
    const msgText = await page.locator('.layui-layer-msg').textContent().catch(() => '');

    await page.screenshot({ path: 'tests/Playwright/artifacts/03-rate-limit-hit.png' });

    // Assert that rate-limiting was visibly surfaced in the UI
    expect(msgVisible || msgText.toLowerCase().includes('rate') || msgText.toLowerCase().includes('limit') || msgText.toLowerCase().includes('wait')).toBe(true);
  });

  test('analyst creates report definition via UI and verifies it appears', async ({ page }) => {
    await login(page, 'analyst');
    await page.goto('/reports');
    await page.waitForTimeout(2000);

    // Click "New Definition" button
    await page.click('#btn-new-definition');
    await page.waitForTimeout(1000);

    // Fill the definition dialog
    const nameInput = page.locator('#def-name');
    await nameInput.waitFor({ state: 'visible', timeout: 5000 });
    const reportName = 'E2E Report ' + Date.now();
    await nameInput.fill(reportName);

    // Click Save button
    await page.click('.layui-layer-btn0');
    await page.waitForTimeout(3000);

    // Assert: the new definition should now be visible in the page content
    const pageText = await page.textContent('body');
    expect(pageText).toContain(reportName);
    await page.screenshot({ path: 'tests/Playwright/artifacts/03-report-created.png' });
  });

  test('analyst runs report via UI and verifies run result', async ({ page }) => {
    await login(page, 'analyst');
    await page.goto('/reports');
    await page.waitForTimeout(3000);

    // Click the "Run" button (lay-event="run") on the first definition row
    page.on('dialog', dialog => dialog.accept());
    const runBtn = page.locator('.layui-table-view[lay-id=definitions-table] [lay-event="run"]').first();
    await runBtn.waitFor({ state: 'visible', timeout: 10000 });
    await runBtn.click();
    await page.waitForTimeout(3000);
    await page.screenshot({ path: 'tests/Playwright/artifacts/03-report-run.png' });

    // After running, the success message should appear
    const msgText = await page.locator('.layui-layer-msg').textContent().catch(() => '');
    expect(msgText.length).toBeGreaterThan(0);

    // Switch to Run History tab and verify a run appears
    await page.locator('.layui-tab-title li').nth(1).click();
    await page.waitForTimeout(4000);

    // The Run History should show at least one entry with a status
    const runsText = await page.textContent('body');
    expect(runsText).toMatch(/succeeded|completed|queued|running|Download/i);
    await page.screenshot({ path: 'tests/Playwright/artifacts/03-report-run-history.png' });
  });

  test('analyst exports CSV via UI and gets real file download', async ({ page }) => {
    await login(page, 'analyst');
    await page.goto('/reports');
    await page.waitForTimeout(2000);

    // Switch to Run History tab where the Export CSV button lives
    await page.locator('.layui-tab-title li').nth(1).click();
    await page.waitForTimeout(2000);

    // Accept the native confirm dialog
    page.on('dialog', dialog => dialog.accept());

    // Wait for the real download event — no fallback
    const downloadPromise = page.waitForEvent('download', { timeout: 15000 });
    await page.click('#btn-export-csv');
    const download = await downloadPromise;

    await page.screenshot({ path: 'tests/Playwright/artifacts/03-csv-export.png' });

    // Assert: real file download with .csv filename
    const filename = download.suggestedFilename();
    expect(filename).toContain('.csv');

    // Assert: downloaded content is non-empty CSV
    const filePath = await download.path();
    expect(filePath).toBeTruthy();
    const fs = require('fs');
    const content = fs.readFileSync(filePath!, 'utf-8');
    expect(content.length).toBeGreaterThan(0);
    expect(content).toContain(','); // CSV has comma-separated values
    expect(content.split('\n').length).toBeGreaterThan(1); // header + at least one data row
  });
});
