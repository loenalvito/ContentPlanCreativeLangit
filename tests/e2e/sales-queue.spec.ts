import { expect, Page, test } from '@playwright/test';
import { failOnPageErrors, login, logout } from './helpers';

test.describe.configure({ retries: 0 });

async function requestContent(page: Page, title: string, urgent = false, neededAt?: string) {
  await page.goto('/sales-dashboard');
  await page.getByTestId('request-content').click();
  await page.getByTestId('request-title').fill(title);
  await page.getByTestId('request-platform-instagram').check();
  await page.locator('[data-testid="request-modal"] select[name="pillar_id"]').selectOption({ label: 'Product' });
  await page.locator('[data-testid="request-modal"] select[name="series_id"]').selectOption({ label: 'Kolabo Features' });
  await page.locator('[data-testid="request-modal"] select[name="format_id"]').selectOption({ label: 'Reels' });
  if (urgent) {
    await page.getByTestId('urgent-toggle').check();
    await page.locator('[data-testid="request-modal"] input[name="needed_at"]').fill(neededAt!);
    await page.locator('[data-testid="request-modal"] textarea[name="urgent_purpose"]').fill('Sales campaign deadline');
  }
  await page.getByTestId('request-submit').click();
  await expect(page.getByRole('heading', { name: title })).toBeVisible();
}

async function convertRequests(page: Page, items: Array<{ title: string; publishDate: string }>) {
  await page.goto('/ideas');
  for (const item of items) await page.getByTestId('idea-row').filter({ hasText: item.title }).getByTestId('idea-select').check();
  await page.getByTestId('bulk-move-button').click();
  for (const item of items) {
    const section = page.getByTestId('move-modal').locator('section').filter({ hasText: item.title });
    await section.getByTestId('move-publish-date').fill(item.publishDate);
    await section.getByTestId('move-pic').selectOption({ label: 'Dina Lead' });
    await section.getByTestId('move-account-instagram').selectOption({ index: 1 });
  }
  await page.getByTestId('move-now').click();
  await expect(page).toHaveURL(/\/content$/);
}

async function changeStatus(page: Page, title: string, status: string) {
  await page.goto(`/content?search=${encodeURIComponent(title)}`);
  const row = page.getByTestId('content-row').filter({ hasText: title });
  const dialog = page.waitForEvent('dialog').then(async item => item.accept());
  await row.getByTestId('content-status').selectOption(status);
  await dialog;
  await expect(row.getByTestId('content-status')).toHaveValue(status);
}

test('Sales queue presents backend order, lifecycle, ownership, and read-only state', async ({ page }) => {
  test.setTimeout(180_000);
  const noPageErrors = failOnPageErrors(page);
  const unique = Date.now();
  const a = `Queue A ${unique}`;
  const b = `Queue B ${unique}`;
  const c = `Queue C ${unique}`;
  const pending = `Pending Urgent ${unique}`;

  await login(page, 'sales@kolabo.id');
  await requestContent(page, a);
  await requestContent(page, b, true, '2026-08-26T10:00');
  await requestContent(page, c, true, '2026-08-20T10:00');

  await logout(page);
  await login(page, 'lead@kolabo.id');
  await convertRequests(page, [
    { title: a, publishDate: '2026-08-25' },
    { title: b, publishDate: '2026-08-26' },
    { title: c, publishDate: '2026-08-20' },
  ]);

  await logout(page);
  await login(page, 'sales@kolabo.id');
  await expect(page.getByTestId('sales-workload')).toBeVisible();
  await expect(page.getByTestId('pending-review')).toBeVisible();
  await expect(page.getByTestId('content-queue')).toBeVisible();
  await expect(page.getByTestId('my-requests')).toBeVisible();
  await expect(page.getByTestId('request-content')).toBeVisible();

  const queue = page.getByTestId('queue-item');
  await expect(queue.nth(0)).toContainText(c);
  await expect(queue.nth(0).getByTestId('queue-position')).toHaveText('#1');
  await expect(queue.nth(1)).toContainText(b);
  await expect(queue.nth(1).getByTestId('queue-position')).toHaveText('#2');
  await expect(queue.nth(2)).toContainText(a);
  await expect(queue.nth(2).getByTestId('queue-position')).toHaveText('#3');
  await expect(queue.nth(0)).toContainText('URGENT REQUEST');
  await expect(queue.nth(0)).toContainText('Dina Lead');
  await expect(queue.nth(0)).toContainText('Tomorrow · 10:00');
  await expect(queue.nth(2).getByTestId('queue-status')).toHaveText('Queued');
  await expect(page.getByTestId('workload-card').filter({ hasText: 'Dina Lead' })).toContainText('1');
  await expect(page.getByTestId('content-queue').locator('select, [draggable="true"], button')).toHaveCount(0);

  await logout(page);
  await login(page, 'lead@kolabo.id');
  await changeStatus(page, a, 'in_production');
  await logout(page);
  await login(page, 'sales@kolabo.id');
  await expect(page.getByTestId('queue-item').filter({ hasText: a }).getByTestId('queue-status')).toHaveText('Working');

  await logout(page);
  await login(page, 'lead@kolabo.id');
  await changeStatus(page, a, 'published');
  await logout(page);
  await login(page, 'sales@kolabo.id');
  await expect(page.getByTestId('queue-item').filter({ hasText: a })).toHaveCount(0);
  await expect(page.getByTestId('my-request-item').filter({ hasText: a })).toContainText('Done');

  await logout(page);
  await login(page, 'lead@kolabo.id');
  await changeStatus(page, a, 'in_production');
  await logout(page);
  await login(page, 'sales@kolabo.id');
  await expect(page.getByTestId('queue-item').filter({ hasText: a })).toContainText('Working');

  await requestContent(page, pending, true, '2026-08-21T10:00');
  await page.goto('/sales-dashboard');
  await expect(page.getByTestId('pending-card').filter({ hasText: pending })).toContainText('URGENT REQUEST');
  await logout(page);
  await login(page, 'lead@kolabo.id');
  await convertRequests(page, [{ title: pending, publishDate: '2026-08-21' }]);
  await logout(page);
  await login(page, 'sales@kolabo.id');
  await expect(page.getByTestId('pending-card').filter({ hasText: pending })).toHaveCount(0);
  await expect(page.getByTestId('queue-item').filter({ hasText: pending })).toContainText('URGENT REQUEST');

  await logout(page);
  await login(page, 'admin@kolabo.id');
  const secondEmail = `queue-sales-${unique}@kolabo.test`;
  await page.goto('/admin/users');
  await page.getByTestId('add-user').click();
  await page.getByTestId('user-name').fill(`Queue Sales ${unique}`);
  await page.getByTestId('user-email').fill(secondEmail);
  await page.getByTestId('user-password').fill('password123');
  await page.getByTestId('user-department').selectOption({ label: 'Sales' });
  await page.getByTestId('user-role').selectOption({ label: 'Sales Contributor' });
  await page.getByTestId('user-submit').click();
  await logout(page);
  await page.locator('input[name="email"]').fill(secondEmail);
  await page.locator('input[name="password"]').fill('password123');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).toHaveURL(/sales-dashboard/);
  await expect(page.getByTestId('my-request-item').filter({ hasText: a })).toHaveCount(0);
  await expect(page.getByTestId('queue-item').filter({ hasText: a })).toBeVisible();

  noPageErrors();
});

test('Sales queue cards fit the mobile viewport', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page, 'sales@kolabo.id');
  await expect(page.getByTestId('sales-workload')).toBeVisible();
  await expect(page.getByTestId('pending-review')).toBeVisible();
  await expect(page.getByTestId('content-queue')).toBeVisible();
  await expect(page.getByTestId('my-requests')).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
});
