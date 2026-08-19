import { test, expect, Page } from '@playwright/test';
import { login, logout } from './helpers';

async function dragStatus(page: Page, id: string, title: string, from: string, to: string, physical = false) {
  const source = page.locator(`section[data-status="${from}"] [data-id="${id}"]`);
  const target = page.locator(`section[data-status="${to}"] .cards`);
  await expect(source).toContainText(title);
  const responsePromise = page.waitForResponse(response => response.url().endsWith(`/production/${id}`) && response.request().method() === 'PATCH', { timeout: 15_000 });
  if (physical) {
    const targetBox = await target.boundingBox();
    expect(targetBox).not.toBeNull();
    await source.hover();
    await page.mouse.down();
    await target.hover({ position: { x: targetBox!.width / 2, y: 80 }, force: true });
    await page.mouse.up();
  } else await page.evaluate(({ id, to }) => {
    const item = document.querySelector<HTMLElement>(`[data-id="${id}"]`)!;
    const from = item.parentElement!;
    const destination = document.querySelector<HTMLElement>(`section[data-status="${to}"] .cards`)!;
    const oldIndex = Array.from(from.children).indexOf(item);
    destination.appendChild(item);
    const sortable = (window as any).Sortable.get(from);
    sortable.option('onEnd')({ item, to: destination, from, oldIndex });
  }, { id, to });
  const response = await responsePromise;
  expect(response.ok()).toBeTruthy();
  expect(await response.json()).toMatchObject({ status: to });
  await expect(page.locator(`section[data-status="${to}"] [data-id="${id}"]`)).toContainText(title);
  await page.reload();
  await expect(page.locator(`section[data-status="${to}"] [data-id="${id}"]`)).toContainText(title);
}

test('Production supports arbitrary status movement and cross-module synchronization', async ({ page }) => {
  test.setTimeout(120_000);
  await page.setViewportSize({ width: 1920, height: 1080 });
  await login(page, 'lead@kolabo.id');
  const createdTitle = `Flexible Kanban ${Date.now()}`;
  await page.goto('/content');
  await page.getByTestId('add-content').click();
  await page.getByTestId('content-title').fill(createdTitle);
  await page.getByTestId('content-date').fill('2026-08-26');
  await page.getByTestId('content-pillar').selectOption({ label: 'Product' });
  await page.getByTestId('content-series').selectOption({ label: 'Kolabo Features' });
  await page.getByTestId('content-format').selectOption({ label: 'Reels' });
  await page.getByTestId('content-submit').click();
  await expect(page.getByRole('heading', { name: createdTitle })).toBeVisible();
  await page.goto('/production');
  const initial = page.locator('section[data-status="planned"] [data-testid="kanban-card"]').filter({ hasText: createdTitle });
  const id = (await initial.getAttribute('data-id'))!;
  const title = (await initial.locator('b').innerText()).trim();

  let current = 'planned';
  for (const target of ['scheduled', 'in_production', 'published', 'review', 'planned']) {
    await dragStatus(page, id, title, current, target, current === 'planned' && target === 'scheduled');
    current = target;
  }

  await dragStatus(page, id, title, 'planned', 'published');
  await page.goto('/dashboard');
  const publishedBeforeRollback = Number(await page.locator('main').getByText('Published', { exact: true }).first().locator('..').locator('div.mt-1').innerText());
  await page.goto('/production');
  await dragStatus(page, id, title, 'published', 'in_production');

  await page.goto('/content');
  const contentRow = page.getByTestId('content-row').filter({ hasText: title });
  await expect(contentRow.getByTestId('content-status')).toHaveValue('in_production');

  await page.goto('/calendar');
  await expect(page.locator('.status-in_production').filter({ hasText: title })).toBeVisible();
  await expect(page.locator('.status-published').filter({ hasText: title })).toHaveCount(0);

  await page.goto('/published');
  await expect(page.getByText(title, { exact: true })).toHaveCount(0);

  await page.goto(`/content/${id}`);
  await expect(page.getByText('In Production', { exact: true }).first()).toBeVisible();
  await page.getByTestId('tab-activity').click();
  await expect(page.getByText(/changed status from Published to In Production/)).toBeVisible();

  await page.goto('/dashboard');
  const publishedAfterRollback = Number(await page.locator('main').getByText('Published', { exact: true }).first().locator('..').locator('div.mt-1').innerText());
  expect(publishedAfterRollback).toBe(publishedBeforeRollback - 1);

  await page.goto('/production');
  await dragStatus(page, id, title, 'in_production', 'published');
  await page.goto('/published');
  await expect(page.getByText(title, { exact: true })).toBeVisible();
  await page.goto('/calendar');
  await expect(page.locator('.status-published').filter({ hasText: title })).toBeVisible();
  await page.goto(`/content/${id}`);
  await expect(page.getByText('Published', { exact: true }).first()).toBeVisible();

  await logout(page);
  await login(page, 'sales@kolabo.id');
  const result = await page.evaluate(async contentId => {
    const response = await fetch(`/production/${contentId}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')!.content,
      },
      body: JSON.stringify({ status: 'published' }),
    });
    return response.status;
  }, id);
  expect(result).toBe(403);
});

test('Super Admin can create Pillar and Series used by dependent Idea fields', async ({ page }) => {
  const suffix = Date.now();
  const pillarName = `Playwright Pillar ${suffix}`;
  const seriesName = `Playwright Series ${suffix}`;
  await login(page, 'admin@kolabo.id');
  await page.goto('/admin/masters');
  await expect(page.getByText('Pillars, Series & Formats')).toBeVisible();

  await page.locator('section').filter({ has: page.getByRole('heading', { name: 'Pillars' }) }).getByRole('button', { name: '+ Add' }).click();
  await page.getByTestId('pillar-name').fill(pillarName);
  await page.getByTestId('pillar-submit').click();
  await expect(page.locator('section').filter({ has: page.getByRole('heading', { name: 'Pillars' }) }).locator('summary span').filter({ hasText: pillarName }).first()).toBeVisible();

  await page.locator('section').filter({ has: page.getByRole('heading', { name: 'Series' }) }).getByRole('button', { name: '+ Add' }).click();
  await page.getByTestId('series-name').fill(seriesName);
  await page.getByTestId('series-pillar').selectOption({ label: pillarName });
  await page.getByTestId('series-submit').click();
  await expect(page.locator('section').filter({ has: page.getByRole('heading', { name: 'Series' }) }).locator('summary').filter({ hasText: seriesName }).first()).toBeVisible();
  await page.reload();
  await expect(page.locator('section').filter({ has: page.getByRole('heading', { name: 'Pillars' }) }).locator('summary span').filter({ hasText: pillarName }).first()).toBeVisible();
  await expect(page.locator('section').filter({ has: page.getByRole('heading', { name: 'Series' }) }).locator('summary').filter({ hasText: seriesName }).first()).toBeVisible();

  const pillarDetails = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Pillars' }) }).locator('details').filter({ hasText: pillarName });
  await pillarDetails.locator('summary').click();
  await pillarDetails.locator('input[name="name"]').fill(`${pillarName} Edited`);
  await pillarDetails.getByRole('button', { name: 'Save' }).click();
  await expect(page.getByText(`${pillarName} Edited`, { exact: true }).first()).toBeVisible();
  const editedPillar = `${pillarName} Edited`;

  const seriesDetails = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Series' }) }).locator('details').filter({ hasText: seriesName });
  await seriesDetails.locator('summary').click();
  await seriesDetails.locator('input[name="name"]').fill(`${seriesName} Edited`);
  await seriesDetails.getByRole('button', { name: 'Save' }).click();
  const editedSeries = `${seriesName} Edited`;
  await expect(page.getByText(editedSeries, { exact: false }).first()).toBeVisible();

  const activeSeries = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Series' }) }).locator('details').filter({ hasText: editedSeries });
  await activeSeries.locator('summary').click();
  await activeSeries.getByRole('button', { name: 'Deactivate' }).click();
  const inactiveSeries = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Series' }) }).locator('details').filter({ hasText: editedSeries });
  await inactiveSeries.locator('summary').click();
  await inactiveSeries.getByRole('button', { name: 'Activate' }).click();

  await page.goto('/ideas');
  await page.getByTestId('add-idea').click();
  await page.getByTestId('idea-pillar').selectOption({ label: editedPillar });
  await expect(page.getByTestId('idea-series').locator('option', { hasText: editedSeries })).toHaveCount(1);

  await page.goto('/admin/masters');
  let editedPillarDetails = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Pillars' }) }).locator('details').filter({ hasText: editedPillar });
  await editedPillarDetails.locator('summary').click();
  await editedPillarDetails.getByRole('button', { name: 'Toggle status' }).click();
  await expect(page.locator('section').filter({ has: page.getByRole('heading', { name: 'Pillars' }) }).locator('details').filter({ hasText: editedPillar })).toContainText('Inactive');
  editedPillarDetails = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Pillars' }) }).locator('details').filter({ hasText: editedPillar });
  await editedPillarDetails.locator('summary').click();
  await editedPillarDetails.getByRole('button', { name: 'Toggle status' }).click();
  await expect(page.locator('section').filter({ has: page.getByRole('heading', { name: 'Pillars' }) }).locator('details').filter({ hasText: editedPillar })).toContainText('Active');
});
