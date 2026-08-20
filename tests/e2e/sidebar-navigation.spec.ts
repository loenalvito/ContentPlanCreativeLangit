import { expect, Page, test } from '@playwright/test';
import { failOnPageErrors, login } from './helpers';

const adminLinks: Record<string, string> = {
  Dashboard: '/dashboard', 'Request Dashboard': '/sales-dashboard', Ideas: '/ideas',
  'Content Plan': '/content', Production: '/production', Calendar: '/calendar',
  Published: '/published', Assets: '/assets', 'My Tasks': '/my-tasks', Team: '/team',
  Users: '/admin/users', 'Roles & Permissions': '/admin/roles',
  Accounts: '/admin/accounts', 'Pillars & Series': '/admin/masters',
};

async function expectNavigation(page: Page, visible: string[], hidden: string[]) {
  const nav = page.locator('aside nav');
  for (const name of visible) {
    const link = nav.getByRole('link', { name, exact: true });
    await expect(link).toBeVisible();
    await expect(link.locator('svg')).toHaveCount(1);
  }
  for (const name of hidden) await expect(nav.getByRole('link', { name, exact: true })).toHaveCount(0);
}

test('Super Admin sidebar routes, icons, active state, and JavaScript remain healthy', async ({ page }) => {
  test.setTimeout(90_000);
  const noPageErrors = failOnPageErrors(page);
  const consoleErrors: string[] = [];
  page.on('console', message => {
    if (message.type() === 'error' && !message.text().includes('ERR_NETWORK_ACCESS_DENIED'))
      consoleErrors.push(message.text());
  });
  await login(page, 'admin@kolabo.id');
  await expectNavigation(page, Object.keys(adminLinks), []);
  for (const [name, path] of Object.entries(adminLinks)) {
    const response = await page.goto(path);
    expect(response?.status(), name + ' (' + path + ')').toBe(200);
    const link = page.locator('aside nav').getByRole('link', { name, exact: true });
    await expect(link).toHaveAttribute('href', new RegExp(path.replaceAll('/', '\\/') + '$'));
    await expect(link).toHaveClass(/\bactive\b/);
    await expect(page.locator('body')).not.toContainText(/Internal Server Error|RouteNotFoundException/);
  }
  noPageErrors();
  expect(consoleErrors, 'Console errors: ' + consoleErrors.join('\n')).toEqual([]);
});

test('Sales sidebar follows seeded permissions and restricted routes stay forbidden', async ({ page }) => {
  await login(page, 'sales@kolabo.id');
  await expectNavigation(page, ['Request Dashboard', 'Ideas', 'Calendar', 'My Tasks'],
    ['Dashboard', 'Content Plan', 'Production', 'Published', 'Assets', 'Team', 'Users',
      'Roles & Permissions', 'Accounts', 'Pillars & Series']);
  for (const path of ['/dashboard', '/content', '/production', '/published', '/assets', '/team',
    '/admin/users', '/admin/roles', '/admin/accounts', '/admin/masters'])
    expect((await page.request.get(path)).status(), path).toBe(403);
});

test('Creative Member sidebar follows seeded permissions without Administration', async ({ page }) => {
  await login(page, 'fadly@kolabo.id');
  await expectNavigation(page,
    ['Dashboard', 'Ideas', 'Content Plan', 'Production', 'Calendar', 'Published', 'Assets', 'My Tasks'],
    ['Request Dashboard', 'Team', 'Users', 'Roles & Permissions', 'Accounts', 'Pillars & Series']);
  for (const path of ['/team', '/admin/users', '/admin/roles', '/admin/accounts', '/admin/masters'])
    expect((await page.request.get(path)).status(), path).toBe(403);
});

for (const viewport of [{ name: 'tablet', width: 768, height: 1024 }, { name: 'mobile', width: 390, height: 844 }]) {
  test(viewport.name + ' drawer opens, fits, and navigates', async ({ page }) => {
    await page.setViewportSize(viewport);
    await login(page, 'sales@kolabo.id');
    const sidebar = page.locator('aside');
    await page.getByRole('button', { name: 'Open navigation' }).click();
    await expect(sidebar).toHaveClass(/\btranslate-x-0\b/);
    await page.waitForTimeout(250);
    const box = await sidebar.boundingBox();
    expect(box).not.toBeNull();
    expect(box!.x).toBeGreaterThanOrEqual(0);
    expect(box!.x + box!.width).toBeLessThanOrEqual(viewport.width);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    await sidebar.getByRole('link', { name: 'Ideas', exact: true }).click();
    await expect(page).toHaveURL(/\/ideas$/);
    await expect(page.locator('aside nav').getByRole('link', { name: 'Ideas', exact: true })).toHaveClass(/\bactive\b/);
  });
}
