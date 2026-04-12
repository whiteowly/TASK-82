import { Page, expect } from '@playwright/test';
import { readFileSync } from 'fs';
import * as path from 'path';

export function getPassword(): string {
  try {
    return readFileSync('/tmp/siteops_test_password', 'utf-8').trim();
  } catch {
    throw new Error('Password file not found at /tmp/siteops_test_password');
  }
}

export async function login(page: Page, username: string): Promise<void> {
  const password = getPassword();
  // Clear existing session cookies to ensure clean login
  await page.context().clearCookies();
  await page.goto('/login', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await page.waitForSelector('input[name="username"]', { state: 'visible', timeout: 15000 });
  await page.fill('input[name="username"]', username);
  await page.fill('input[name="password"]', password);
  await page.click('button[lay-submit]');
  await page.waitForURL('**/dashboard', { timeout: 15000 });
}

// API helpers — use ONLY for setup/teardown, never for the action under test
export async function apiPost(page: Page, path: string, data: any, csrf: string): Promise<any> {
  const r = await page.request.post(`http://127.0.0.1:8080${path}`, {
    data,
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
  });
  try { return { status: r.status(), body: await r.json() }; }
  catch { return { status: r.status(), body: await r.text() }; }
}

export async function apiGet(page: Page, p: string): Promise<any> {
  const r = await page.request.get(`http://127.0.0.1:8080${p}`);
  try { return { status: r.status(), body: await r.json() }; }
  catch { return { status: r.status(), body: await r.text() }; }
}

export function getCsrf(page: Page): Promise<string> {
  return page.getAttribute('meta[name="csrf-token"]', 'content').then(v => v || '');
}

export function testImagePath(): string {
  const png = path.resolve(__dirname, 'test-image.png');
  const jpg = path.resolve(__dirname, 'test-image.jpg');
  // Prefer PNG which is a valid image file
  return require('fs').existsSync(png) ? png : jpg;
}
