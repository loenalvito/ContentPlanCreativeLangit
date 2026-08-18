import { test, expect } from '@playwright/test';
import { login, logout, failOnPageErrors } from './helpers';

test('Revisi 1 critical workflow', async ({ page }) => {
  test.setTimeout(90_000);
  const noErrors = failOnPageErrors(page);
  const title = `Revision One ${Date.now()}`;
  const publish = new Date(); publish.setDate(publish.getDate() + 2);

  await login(page, 'sales@kolabo.id');
  await page.getByRole('link', { name: 'Ideas' }).click();
  await page.getByTestId('add-idea').click();
  await page.getByTestId('idea-editor').fill(`<b>${title}</b>`);
  await page.getByTestId('idea-pillar').selectOption({ label: 'Entertainment' });
  for (const label of ['Office Life','Meme','POV']) await expect(page.getByTestId('idea-series').locator('option', { hasText: label })).toHaveCount(1);
  await expect(page.getByTestId('idea-series').locator('option', { hasText: 'Kolabo Features' })).toHaveCount(0);
  await page.getByTestId('idea-series').selectOption({ label: 'POV' });
  await page.getByTestId('idea-format').selectOption({ label: 'Reels' });
  await page.getByTestId('submit-idea').click();
  let row = page.getByTestId('idea-row').filter({ hasText: title });
  await expect(row).toContainText('Andi Sales'); await expect(row).toContainText('Sales');
  await logout(page);

  await login(page, 'lead@kolabo.id');
  await page.getByRole('link', { name: 'Ideas' }).click();
  row = page.getByTestId('idea-row').filter({ hasText: title });
  await row.getByTestId('idea-status').selectOption('consider');
  row = page.getByTestId('idea-row').filter({ hasText: title }); await row.getByTestId('idea-status').selectOption('selected');
  row = page.getByTestId('idea-row').filter({ hasText: title }); await row.getByTestId('move-idea').click();
  await row.getByTestId('move-publish-date').fill(publish.toISOString().slice(0,10));
  await row.getByTestId('move-pic').selectOption({ label: 'Dina Lead' }); await row.getByTestId('move-submit').click();
  await page.getByRole('link', { name: title }).click(); await page.getByTestId('tab-source').click(); await expect(page.getByText('Andi Sales')).toBeVisible();
  await page.getByRole('link', { name: 'Ideas' }).click(); await expect(page.getByTestId('idea-row').filter({ hasText: title })).toContainText('Converted');

  await page.getByRole('link', { name: 'Content Plan' }).click(); row = page.getByTestId('content-row').filter({ hasText: title });
  page.once('dialog', dialog => dialog.accept()); await row.getByTestId('content-status').selectOption('in_production'); await page.waitForTimeout(300);
  await page.getByRole('link', { name: 'Production' }).click(); await expect(page.locator('section[data-status="in_production"]').getByText(title)).toBeVisible();
  const card = page.locator('a[draggable="true"]').filter({ hasText: title });
  const cardId = await card.getAttribute('data-id');
  expect(await page.evaluate(async id => (await fetch(`/production/${id}`, {method:'PATCH',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')!.content},body:JSON.stringify({status:'review'})})).ok, cardId)).toBe(true);
  await page.reload(); await expect(page.locator('section[data-status="review"]').getByText(title)).toBeVisible();
  await page.goto(`/content/${cardId}`); await page.getByTestId('tab-revision').click(); await page.getByTestId('revision-comment').fill('Playwright revision comment'); await page.getByTestId('request-revision').click();
  await page.getByTestId('tab-comments').click(); await expect(page.getByTestId('comment')).toContainText('Playwright revision comment'); await expect(page.getByTestId('comment')).toContainText('Dina Lead');
  const id = page.url().split('/').pop();
  const update = async (status:string) => page.evaluate(async ({id,status}) => (await fetch(`/production/${id}`, {method:'PATCH',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')!.content},body:JSON.stringify({status})})).ok, {id,status});
  for (const status of ['review','approved','scheduled','published']) expect(await update(status)).toBe(true);
  await page.getByRole('link', { name: 'Published' }).click(); await expect(page.getByText(title, { exact: true })).toBeVisible();
  await page.getByRole('link', { name: 'Calendar' }).click(); await expect(page.locator('.status-published').filter({ hasText: title })).toBeVisible();
  noErrors();
});
