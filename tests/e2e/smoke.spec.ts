import { test, expect } from '@playwright/test';
import { login } from './helpers';

test('admin pages render without Laravel exception', async ({ page }) => {
  await login(page, 'admin@kolabo.id');
  for (const path of ['/dashboard','/content','/ideas','/calendar','/production','/published','/assets','/my-tasks','/team','/admin/users','/admin/roles','/admin/masters']) {
    const response = await page.goto(path);
    expect(response?.status()).toBe(200);
    await expect(page.getByText('Internal Server Error')).toHaveCount(0);
    await expect(page.getByText('RouteNotFoundException')).toHaveCount(0);
  }
});

test.use({ viewport: { width: 390, height: 844 } });
test('mobile login, navigation, Ideas and Calendar smoke', async ({ page }) => {
  await login(page, 'sales@kolabo.id');
  await page.getByRole('button', { name: 'Open navigation' }).click();
  await expect(page.getByRole('link', { name: 'Ideas' })).toBeVisible();
  await page.getByRole('link', { name: 'Ideas' }).click();
  await expect(page.getByTestId('add-idea')).toBeVisible();
  await page.getByTestId('add-idea').click();
  const ideaBox = await page.getByTestId('idea-modal').locator('form').boundingBox();
  expect(ideaBox!.x).toBeGreaterThanOrEqual(0); expect(ideaBox!.x + ideaBox!.width).toBeLessThanOrEqual(390);
  await page.getByTestId('idea-close').click();
  await page.getByTestId('bulk-ideas').click();
  const bulkBox = await page.getByTestId('bulk-modal').locator('form').boundingBox();
  expect(bulkBox!.x).toBeGreaterThanOrEqual(0); expect(bulkBox!.x + bulkBox!.width).toBeLessThanOrEqual(390);
  await page.getByTestId('bulk-close').click();
  await page.getByRole('button', { name: 'Open navigation' }).click();
  await page.getByRole('link', { name: 'Calendar' }).click();
  await expect(page.locator('#calendar')).toBeVisible();
});
