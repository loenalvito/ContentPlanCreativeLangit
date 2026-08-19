import { test, expect } from '@playwright/test';
import path from 'path';
import { login } from './helpers';

test('dependent series and role builder persist', async ({ page }) => {
  await login(page, 'admin@kolabo.id'); await page.getByRole('link', { name: 'Content Plan' }).click();
  await page.getByTestId('filter-pillar').selectOption({ label: 'Entertainment' });
  for (const label of ['Office Life','Meme','POV']) await expect(page.getByTestId('filter-series').locator('option', { hasText: label })).toHaveCount(1);
  await expect(page.getByTestId('filter-series').locator('option', { hasText: 'Kolabo Features' })).toHaveCount(0);
  await page.goto('/admin/roles'); await expect(page.getByTestId('role-selector')).toBeVisible(); await page.getByTestId('add-role').click();
  const role = `Test Contributor ${Date.now()}`; await page.getByTestId('role-name').fill(role); await page.getByTestId('new-permission-ideas.create').check(); await page.getByTestId('new-permission-calendar.view').check(); await page.getByTestId('role-submit').click();
  await expect(page.getByTestId('role-selector').locator('option', { hasText: role })).toHaveCount(1); await page.reload(); await page.getByTestId('role-selector').selectOption({label:role});
  const editor=page.locator('section:visible'); await expect(editor.getByTestId('permission-ideas.create')).toBeChecked(); await editor.getByTestId('permission-calendar.view').uncheck(); await editor.getByTestId('role-save').click();
  await page.getByTestId('role-selector').selectOption({label:role}); await expect(page.locator('section:visible').getByTestId('permission-calendar.view')).not.toBeChecked();
});

test('bulk Ideas validation and attribution', async ({ page }) => {
  await login(page, 'sales@kolabo.id'); await page.getByRole('link', { name: 'Ideas' }).click(); await page.getByTestId('bulk-ideas').click();
  const downloadPromise=page.waitForEvent('download');await page.getByRole('link',{name:'Download Excel Template'}).click();const template=await downloadPromise;const templatePath=path.join(process.cwd(),'test-results',`ideas-template-${Date.now()}.xlsx`);await template.saveAs(templatePath);
  await page.getByTestId('ideas-file').setInputFiles(templatePath); await page.getByTestId('preview-ideas').click(); await expect(page.getByTestId('preview-row')).toHaveCount(2); await page.getByTestId('import-ideas').click();
  const row = page.getByTestId('idea-row').filter({ hasText: 'Tips Follow Up Lead' }).first(); await expect(row).toContainText('Andi Sales'); await expect(row).toContainText('Sales');
  await page.getByTestId('bulk-ideas').click(); await page.getByTestId('ideas-file').setInputFiles(path.join(process.cwd(),'tests','e2e','fixtures','ideas-invalid.csv')); await page.getByTestId('preview-ideas').click();
  await expect(page.getByTestId('preview-row')).toContainText('does not belong to Pillar'); await expect(page.getByTestId('import-ideas')).toHaveCount(0);
});
