import { test, expect } from '@playwright/test';
import path from 'path';
import { login, failOnPageErrors } from './helpers';

test('Content Plan search, dependent filters, platform and status filters work', async ({ page }) => {
  await login(page, 'lead@kolabo.id');
  await page.goto('/content');
  await page.getByTestId('content-search').fill('Kolabo Daily Use');
  await page.getByTestId('content-filter-submit').click();
  await expect(page.getByTestId('content-row')).toHaveCount(1);
  await expect(page.getByTestId('content-row')).toContainText('Kolabo Daily Use');

  await page.goto('/content');
  await page.getByTestId('filter-pillar').selectOption({ label: 'Entertainment' });
  for (const name of ['Office Life', 'Meme', 'POV']) await expect(page.getByTestId('filter-series').locator('option', { hasText: name })).toHaveCount(1);
  await expect(page.getByTestId('filter-series').locator('option', { hasText: 'Kolabo Features' })).toHaveCount(0);
  await page.goto('/content');
  const platform = 'Instagram';
  await expect(page.getByTestId('filter-platform').locator('option', { hasText: platform })).toHaveCount(1);
  await page.getByTestId('filter-platform').selectOption({ label: platform });
  await page.getByTestId('content-filter-submit').click();
  const rows = page.getByTestId('content-row');
  expect(await rows.count()).toBeGreaterThan(0);
  for (let index = 0; index < await rows.count(); index++) await expect(rows.nth(index)).toContainText(platform);

  await page.goto('/content');
  await page.getByTestId('filter-status').selectOption('published');
  await page.getByTestId('content-filter-submit').click();
  for (let index = 0; index < await page.getByTestId('content-status').count(); index++) await expect(page.getByTestId('content-status').nth(index)).toHaveValue('published');
});

test('Calendar navigation, comments, and activity remain separate', async ({ page }) => {
  const noErrors = failOnPageErrors(page);
  await login(page, 'lead@kolabo.id');
  await page.goto('/calendar');
  await expect(page.locator('#calendar')).toBeVisible();
  const event = page.locator('.fc-event').first();
  await expect(event).toBeVisible();
  await event.click();
  await expect(page).toHaveURL(/\/content\/\d+$/);
  const comment = `Playwright discussion ${Date.now()}`;
  await page.getByTestId('tab-comments').click();
  await page.getByTestId('comment-body').fill(comment);
  await page.getByTestId('comment-submit').click();
  await page.getByTestId('tab-comments').click();
  await expect(page.getByTestId('comment').filter({ hasText: comment })).toContainText('Dina Lead');
  await page.reload();
  await page.getByTestId('tab-comments').click();
  await expect(page.getByTestId('comment').filter({ hasText: comment })).toBeVisible();
  await page.getByTestId('tab-activity').click();
  await expect(page.locator('div[x-show="tab===\'activity\'"]').getByText(comment)).toHaveCount(0);
  noErrors();
});

test('Bulk Upload modal is centered and dirty-state confirmation works', async ({ page }) => {
  await login(page, 'sales@kolabo.id');
  await page.goto('/ideas');
  await page.getByTestId('bulk-ideas').click();
  const modalBox = await page.getByTestId('bulk-modal').locator('form').boundingBox();
  const viewport = page.viewportSize()!;
  expect(Math.abs(modalBox!.x + modalBox!.width / 2 - viewport.width / 2)).toBeLessThan(40);
  expect(Math.abs(modalBox!.y + modalBox!.height / 2 - viewport.height / 2)).toBeLessThan(80);
  await expect(page.getByTestId('bulk-close')).toBeVisible();
  await expect(page.getByRole('link', { name: 'Download Excel Template' })).toBeVisible();
  await page.getByTestId('bulk-close').click();
  await expect(page.getByTestId('bulk-modal')).toBeHidden();

  await page.getByTestId('bulk-ideas').click();
  await page.getByTestId('ideas-file').setInputFiles(path.join(process.cwd(), 'tests', 'e2e', 'fixtures', 'ideas-valid.csv'));
  await page.getByTestId('bulk-close').click();
  await expect(page.getByTestId('discard-confirm')).toBeVisible();
  await page.getByTestId('keep-editing').click();
  await expect(page.getByTestId('bulk-modal')).toBeVisible();
  await page.getByTestId('bulk-close').click();
  await page.getByTestId('discard').click();
  await expect(page.getByTestId('bulk-modal')).toBeHidden();
});

test('Super Admin can create and deactivate a user with an existing role', async ({ page }) => {
  const unique = Date.now();
  const email = `playwright-${unique}@kolabo.test`;
  await login(page, 'admin@kolabo.id');
  await page.goto('/admin/users');
  await expect(page.getByTestId('user-row').filter({ hasText: 'admin@kolabo.id' })).toBeVisible();
  await page.getByTestId('add-user').click();
  await page.getByTestId('user-name').fill(`Playwright User ${unique}`);
  await page.getByTestId('user-email').fill(email);
  await page.getByTestId('user-password').fill('password123');
  await page.getByTestId('user-department').selectOption({ label: 'Sales' });
  await page.getByTestId('user-role').selectOption({ label: 'Viewer' });
  await page.getByTestId('user-submit').click();
  let row = page.getByTestId('user-row').filter({ hasText: email });
  await expect(row).toContainText('Active');
  await expect(row).toContainText('Viewer');
  await page.reload();
  row = page.getByTestId('user-row').filter({ hasText: email });
  await row.getByTestId('toggle-user').click();
  await expect(page.getByTestId('user-row').filter({ hasText: email })).toContainText('Inactive');
});
