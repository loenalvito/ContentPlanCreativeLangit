import { test, expect } from '@playwright/test';
import { login } from './helpers';

test('admin pages render without Laravel exception', async ({ page }) => {
  await login(page, 'admin@kolabo.id');
  for (const path of ['/dashboard','/content','/ideas','/calendar','/production','/published','/assets','/my-tasks','/team','/admin/users','/admin/roles']) {
    const response = await page.goto(path);
    expect(response?.status()).toBe(200);
    await expect(page.getByText('Internal Server Error')).toHaveCount(0);
  }
});

test.use({ viewport: { width: 390, height: 844 } });
test('mobile login, navigation, Ideas and Calendar smoke', async ({ page }) => {
  await login(page, 'sales@kolabo.id');
  await page.getByRole('button', { name: '☰' }).click();
  await expect(page.getByRole('link', { name: 'Ideas' })).toBeVisible();
  await page.getByRole('link', { name: 'Ideas' }).click();
  await expect(page.getByTestId('add-idea')).toBeVisible();
  await page.getByRole('button', { name: '☰' }).click();
  await page.getByRole('link', { name: 'Calendar' }).click();
  await expect(page.locator('#calendar')).toBeVisible();
});
