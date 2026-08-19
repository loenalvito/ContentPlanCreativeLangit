import { test, expect } from '@playwright/test';
import path from 'path';
import { login, failOnPageErrors } from './helpers';

test('visual QA captures redesigned primary pages without runtime errors', async ({ page }) => {
  test.setTimeout(90_000);
  await page.setViewportSize({ width: 1536, height: 1024 });
  const noErrors = failOnPageErrors(page);
  await login(page, 'admin@kolabo.id');
  const pages: Record<string, string> = {
    dashboard: '/dashboard',
    ideas: '/ideas',
    content: '/content',
    production: '/production',
    calendar: '/calendar',
    roles: '/admin/roles',
    masters: '/admin/masters',
  };
  for (const [name, url] of Object.entries(pages)) {
    const response = await page.goto(url);
    expect(response?.status()).toBe(200);
    await expect(page.locator('main h1').first()).toBeVisible();
    await page.screenshot({ path: path.join(process.cwd(), 'work', `visual-qa-${name}.png`), fullPage: true });
  }
  const detailHref = await page.goto('/content').then(async () => page.getByTestId('content-row').first().locator('a').getAttribute('href'));
  await page.goto(detailHref!);
  await page.screenshot({ path: path.join(process.cwd(), 'work', 'visual-qa-content-detail.png'), fullPage: true });
  noErrors();
});
