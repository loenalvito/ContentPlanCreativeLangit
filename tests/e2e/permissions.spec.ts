import { test, expect } from '@playwright/test';
import { login } from './helpers';

test('Sales navigation and backend authorization', async ({ page }) => {
  await login(page, 'sales@kolabo.id');
  await expect(page.getByRole('link', { name: 'Ideas' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Calendar' })).toBeVisible();
  for (const name of ['Production', 'Assets', 'Users', 'Roles & Permissions', 'Content Plan']) {
    await expect(page.getByRole('link', { name, exact: true })).toHaveCount(0);
  }
  for (const path of ['/production', '/assets', '/admin/users', '/admin/roles', '/content']) {
    const response = await page.goto(path);
    expect(response?.status(), `${path} must be forbidden`).toBe(403);
  }
});
