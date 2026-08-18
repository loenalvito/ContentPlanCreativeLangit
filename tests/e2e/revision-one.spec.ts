import { test, expect } from '@playwright/test';
import path from 'path';
import { login } from './helpers';

test('dependent series and role builder persist', async ({ page }) => {
  await login(page, 'admin@kolabo.id'); await page.getByRole('link', { name: 'Content Plan' }).click();
  await page.getByTestId('filter-pillar').selectOption({ label: 'Entertainment' });
  for (const label of ['Office Life','Meme','POV']) await expect(page.getByTestId('filter-series').locator('option', { hasText: label })).toHaveCount(1);
  await expect(page.getByTestId('filter-series').locator('option', { hasText: 'Kolabo Features' })).toHaveCount(0);
  await page.goto('/admin/roles'); await page.getByTestId('add-role').click();
  const role = `Test Contributor ${Date.now()}`; await page.getByTestId('role-name').fill(role); await page.getByTestId('permission-ideas.create').check(); await page.getByTestId('permission-calendar.view').check(); await page.getByTestId('role-submit').click();
  await expect(page.getByRole('heading', { name: role })).toBeVisible(); await page.reload(); await expect(page.getByRole('heading', { name: role })).toBeVisible();
});

test('bulk Ideas validation and attribution', async ({ page }) => {
  await login(page, 'sales@kolabo.id'); await page.getByRole('link', { name: 'Ideas' }).click(); await page.getByTestId('bulk-ideas').click();
  await page.getByTestId('ideas-file').setInputFiles(path.join(process.cwd(),'tests','e2e','fixtures','ideas-valid.csv')); await page.getByTestId('import-ideas').click();
  const row = page.getByTestId('idea-row').filter({ hasText: 'Playwright bulk insight' }).first(); await expect(row).toContainText('Andi Sales'); await expect(row).toContainText('Sales');
  await page.getByTestId('bulk-ideas').click(); await page.getByTestId('ideas-file').setInputFiles(path.join(process.cwd(),'tests','e2e','fixtures','ideas-invalid.csv')); await page.getByTestId('import-ideas').click();
  await expect(page.getByTestId('error')).toContainText('does not belong to Pillar');
});
