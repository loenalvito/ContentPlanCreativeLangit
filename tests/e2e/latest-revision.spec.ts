import { expect, test } from '@playwright/test';
import { failOnPageErrors, login, logout } from './helpers';

test('Request Dashboard rename keeps the request workflow available', async ({ page }) => {
  const noErrors = failOnPageErrors(page);
  await login(page, 'sales@kolabo.id');
  await expect(page).toHaveURL(/sales-dashboard/);
  await expect(page.getByRole('heading', { name: 'Request Dashboard' })).toBeVisible();
  await expect(page.locator('aside nav').getByRole('link', { name: 'Request Dashboard' })).toBeVisible();
  await expect(page.locator('main')).not.toContainText('Sales Dashboard');
  await page.getByTestId('request-content').click();
  await expect(page.getByTestId('request-modal')).toBeVisible();
  noErrors();
});

test('Content Plan date modes and Ideas database filters work through the browser', async ({ page }) => {
  test.setTimeout(90_000);
  await login(page, 'lead@kolabo.id');

  const expectedTitle = 'Kolabo Daily Use — Finance';
  for (const scenario of [
    { mode: 'specific', field: 'filter-specific-date', value: '2026-08-18' },
    { mode: 'month', field: 'filter-month', value: '2026-08' },
    { mode: 'year', field: 'filter-year', value: '2026' },
    { mode: 'range', field: 'filter-start-date', value: '2026-08-18', end: '2026-08-18' },
  ]) {
    await page.goto('/content');
    await page.getByTestId('content-search').fill(expectedTitle);
    await page.getByTestId('date-filter-toggle').click();
    await page.getByTestId('date-mode').selectOption(scenario.mode);
    await page.getByTestId(scenario.field).fill(scenario.value);
    if (scenario.end) await page.getByTestId('filter-end-date').fill(scenario.end);
    await page.getByTestId('content-filter-submit').click();
    await expect(page.getByTestId('content-row').filter({ hasText: expectedTitle })).toBeVisible();
  }
  await page.getByTestId('content-filter-reset').click();
  await expect(page).toHaveURL(/\/content$/);

  const ideaTitle = `Latest Filter Idea ${Date.now()}`;
  await page.goto('/ideas');
  await page.getByTestId('add-idea').click();
  await page.getByTestId('idea-editor').fill(ideaTitle);
  await page.getByTestId('idea-platform-instagram').check();
  await page.getByTestId('idea-pillar').selectOption({ label: 'Product' });
  await page.getByTestId('idea-series').selectOption({ label: 'Kolabo Features' });
  await page.getByTestId('submit-idea').click();
  let row = page.getByTestId('idea-row').filter({ hasText: ideaTitle });
  await expect(row.getByTestId('idea-status').locator('option')).toContainText(['New', 'Consider', 'Archived']);
  await row.getByTestId('idea-status').selectOption('consider');
  await page.getByTestId('ideas-filter-status').selectOption('consider');
  await page.getByTestId('ideas-filter-submitter').selectOption({ label: 'Dina Lead' });
  await page.getByTestId('ideas-filter-submit').click();
  row = page.getByTestId('idea-row').filter({ hasText: ideaTitle });
  await expect(row).toBeVisible();
  await row.getByTestId('idea-status').selectOption('archived');
  await page.getByTestId('ideas-filter-status').selectOption('archived');
  await page.getByTestId('ideas-filter-submit').click();
  await expect(page.getByTestId('idea-row').filter({ hasText: ideaTitle }).getByTestId('idea-status')).toHaveValue('archived');
});

test('Content Detail edit policy and Published information flow persist end to end', async ({ page }) => {
  test.setTimeout(120_000);
  const unique = Date.now();
  const originalTitle = `Latest Published ${unique}`;
  const editedTitle = `${originalTitle} Edited`;
  const viewerEmail = `latest-viewer-${unique}@kolabo.test`;
  const publishedUrl = `https://instagram.com/latest-${unique}`;

  await login(page, 'admin@kolabo.id');
  await page.goto('/admin/users');
  await page.getByTestId('add-user').click();
  await page.getByTestId('user-name').fill(`Latest Viewer ${unique}`);
  await page.getByTestId('user-email').fill(viewerEmail);
  await page.getByTestId('user-password').fill('password');
  await page.getByTestId('user-department').selectOption({ label: 'Sales' });
  await page.getByTestId('user-role').selectOption({ label: 'Viewer' });
  await page.getByTestId('user-submit').click();
  await expect(page.getByTestId('user-row').filter({ hasText: viewerEmail })).toBeVisible();
  await logout(page);

  await login(page, 'lead@kolabo.id');
  await page.goto('/content');
  await page.getByTestId('add-content').click();
  await page.getByTestId('content-title').fill(originalTitle);
  await page.getByTestId('content-date').fill('2026-09-15');
  await page.getByTestId('content-pillar').selectOption({ label: 'Product' });
  await page.getByTestId('content-series').selectOption({ label: 'Kolabo Features' });
  await page.getByTestId('content-format').selectOption({ label: 'Reels' });
  await page.locator('[data-platform-toggle]').first().check();
  await page.getByTestId('content-account-instagram').selectOption({ index: 1 });
  await page.getByTestId('content-submit').click();
  await expect(page.getByRole('heading', { name: originalTitle })).toBeVisible();
  const contentId = page.url().split('/').pop()!;

  await page.getByTestId('edit-content').click();
  await expect(page.getByTestId('edit-content-modal')).toBeVisible();
  await page.getByTestId('edit-title').fill(editedTitle);
  await page.getByTestId('save-content-edit').click();
  await expect(page.getByRole('heading', { name: editedTitle })).toBeVisible();

  await page.getByTestId('publish-content').click();
  await expect(page.getByTestId('content-detail-status')).toHaveText('Published');
  await expect(page.getByTestId('published-link-modal')).toBeVisible();
  await page.getByTestId('published-modal-close').click();
  await page.reload();
  await expect(page.getByTestId('content-detail-status')).toHaveText('Published');
  await page.getByTestId('edit-published-information').click();
  await page.getByTestId('published-url').fill(publishedUrl);
  await page.getByTestId('save-published-url').click();
  await expect(page.getByTestId('published-information')).toContainText(publishedUrl);
  await page.goto('/published');
  const publishedCard = page.locator('article').filter({ hasText: editedTitle });
  await expect(publishedCard.getByRole('link', { name: 'Open Post' })).toHaveAttribute('href', publishedUrl);

  await page.goto(`/content/${contentId}`);
  await page.getByTestId('edit-published-information').click();
  await page.getByTestId('not-for-public').click();
  await expect(page.getByTestId('published-information')).toContainText('Not for Public');
  await page.goto('/published');
  await expect(page.locator('article').filter({ hasText: editedTitle })).toContainText('Not for Public');

  await logout(page);
  await login(page, viewerEmail);
  await page.goto(`/content/${contentId}`);
  await expect(page.getByTestId('edit-content')).toHaveCount(0);
  const unauthorized = await page.evaluate(async id => {
    const response = await fetch(`/content/${id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')!.content,
      },
      body: JSON.stringify({ title: 'Unauthorized mutation' }),
    });
    return response.status;
  }, contentId);
  expect(unauthorized).toBe(403);
});
