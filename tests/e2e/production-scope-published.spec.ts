import { expect, Page, test } from '@playwright/test';
import { login, logout } from './helpers';

type ContentInput = {
  title: string;
  date?: string;
  pic: string;
  platform?: string;
};

async function createContent(page: Page, input: ContentInput) {
  await page.goto('/content');
  await page.getByTestId('add-content').click();
  await page.getByTestId('content-title').fill(input.title);
  if (input.date) await page.getByTestId('content-date').fill(input.date);
  await page.getByTestId('content-pillar').selectOption({ label: 'Product' });
  await page.getByTestId('content-series').selectOption({ label: 'Kolabo Features' });
  await page.getByTestId('content-format').selectOption({ label: 'Reels' });
  await page.getByTestId('content-pic').selectOption({ label: input.pic });
  if (input.platform) {
    await page.getByLabel(input.platform, { exact: true }).check();
    const account = page.locator(`[data-account-panel]:visible select`);
    await expect(account).toBeVisible();
    await account.selectOption({ index: 1 });
  }
  await page.getByTestId('content-submit').click();
  await expect(page.getByRole('heading', { name: input.title })).toBeVisible();
  return page.url().split('/').pop()!;
}

async function moveCard(page: Page, id: string, to: string) {
  await page.waitForFunction(contentId => {
    const item = document.querySelector<HTMLElement>(`[data-id="${contentId}"]`);
    return Boolean(item?.parentElement && (window as any).Sortable?.get(item.parentElement));
  }, id);
  const responsePromise = page.waitForResponse(response =>
    response.url().endsWith(`/production/${id}`) && response.request().method() === 'PATCH');
  await page.evaluate(({ id, to }) => {
    const item = document.querySelector<HTMLElement>(`[data-id="${id}"]`)!;
    const from = item.parentElement!;
    const destination = document.querySelector<HTMLElement>(`section[data-status="${to}"] .cards`)!;
    const oldIndex = Array.from(from.children).indexOf(item);
    destination.appendChild(item);
    (window as any).Sortable.get(from).option('onEnd')({ item, from, to: destination, oldIndex });
  }, { id, to });
  const response = await responsePromise;
  expect(response.ok()).toBeTruthy();
  expect(await response.json()).toMatchObject({ status: to });
}

async function cancelPublishing(page: Page, id: string, origin: string, action: 'cancel' | 'close' | 'escape' | 'overlay') {
  const rollback = page.waitForResponse(response =>
    response.url().endsWith(`/production/${id}`) && response.request().method() === 'PATCH' && response.request().postData()?.includes('cancelled_publishing'));
  if (action === 'cancel') await page.getByTestId('kanban-published-cancel').click();
  if (action === 'close') await page.getByTestId('kanban-published-close').click();
  if (action === 'escape') await page.keyboard.press('Escape');
  if (action === 'overlay') await page.getByTestId('kanban-published-modal').click({ position: { x: 5, y: 5 } });
  const response = await rollback;
  expect(response.ok()).toBeTruthy();
  expect(await response.json()).toMatchObject({ status: origin });
  await expect(page.getByTestId('kanban-published-modal')).toBeHidden();
  await expect(page.locator(`section[data-status="${origin}"] [data-id="${id}"]`)).toBeVisible();
  await page.reload();
  await expect(page.locator(`section[data-status="${origin}"] [data-id="${id}"]`)).toBeVisible();
}

test('Production scopes/filter reset and Published link prompt work through the browser', async ({ page }) => {
  test.setTimeout(180_000);
  const unique = Date.now();
  const ownWeek = `Scope Own Week ${unique}`;
  const ownNext = `Scope Own Next ${unique}`;
  const otherWeek = `Scope Other Week ${unique}`;
  const otherNext = `Scope Other Next ${unique}`;
  const publicTitle = `Published URL ${unique}`;
  const privateTitle = `Published Private ${unique}`;
  const closeTitle = `Published Close ${unique}`;
  const reviewTitle = `Published Review Cancel ${unique}`;
  const escapeTitle = `Published Escape ${unique}`;
  const failureTitle = `Published Failed Rollback ${unique}`;

  await login(page, 'lead@kolabo.id');
  await createContent(page, { title: ownWeek, date: '2026-08-20', pic: 'Fadly Creative', platform: 'Instagram' });
  await createContent(page, { title: ownNext, date: '2026-08-27', pic: 'Fadly Creative' });
  await createContent(page, { title: otherWeek, date: '2026-08-20', pic: 'Nabila Creative' });
  await createContent(page, { title: otherNext, date: '2026-08-27', pic: 'Nabila Creative' });
  const publicId = await createContent(page, { title: publicTitle, date: '2026-08-20', pic: 'Dina Lead' });
  const privateId = await createContent(page, { title: privateTitle, date: '2026-08-20', pic: 'Dina Lead' });
  const closeId = await createContent(page, { title: closeTitle, date: '2026-08-20', pic: 'Dina Lead' });
  const reviewId = await createContent(page, { title: reviewTitle, date: '2026-08-20', pic: 'Dina Lead' });
  const escapeId = await createContent(page, { title: escapeTitle, date: '2026-08-20', pic: 'Dina Lead' });
  const failureId = await createContent(page, { title: failureTitle, date: '2026-08-20', pic: 'Dina Lead' });

  await page.goto('/production');
  await expect(page.getByTestId('production-context')).toHaveText('All Tasks · All Dates');
  for (const title of [ownWeek, ownNext, otherWeek, otherNext]) {
    await expect(page.getByTestId('kanban-card').filter({ hasText: title })).toBeVisible();
  }

  await page.getByTestId('production-filter-pic').selectOption({ label: 'Fadly Creative' });
  await page.getByTestId('production-filter-period').selectOption('this_week');
  await page.getByTestId('production-filter-platform').selectOption({ label: 'Instagram' });
  await expect(page.getByTestId('production-filter-account').locator('option')).toHaveCount(3);
  await page.getByTestId('production-filter-account').selectOption({ index: 1 });
  await page.getByTestId('production-filter-pillar').selectOption({ label: 'Product' });
  await page.getByTestId('production-filter-series').selectOption({ label: 'Kolabo Features' });
  await page.getByTestId('production-filter-format').selectOption({ label: 'Reels' });
  await page.getByTestId('production-filter-apply').click();
  await expect(page.getByTestId('kanban-card').filter({ hasText: ownWeek })).toBeVisible();
  for (const title of [ownNext, otherWeek, otherNext]) {
    await expect(page.getByTestId('kanban-card').filter({ hasText: title })).toHaveCount(0);
  }
  await expect(page).toHaveURL(/pic_user_id=.*period=this_week.*platform_id=.*account_id=.*pillar_id=.*series_id=.*format_id=/);
  await page.getByTestId('production-filter-reset').click();
  await expect(page.getByTestId('production-context')).toHaveText('All Tasks · All Dates');

  await moveCard(page, publicId, 'approved');
  await moveCard(page, publicId, 'published');
  await expect(page.getByTestId('kanban-published-modal')).toBeVisible();
  const postingUrl = `https://instagram.com/p/${unique}`;
  await page.getByTestId('kanban-published-url').fill(postingUrl);
  const savePublic = page.waitForResponse(response => response.url().includes('/published-information') && response.request().method() === 'PUT');
  await page.getByTestId('kanban-save-published-url').click();
  expect((await savePublic).ok()).toBeTruthy();
  await expect(page.getByTestId('kanban-published-modal')).toBeHidden();
  await page.goto(`/content/${publicId}`);
  await expect(page.getByTestId('published-information')).toContainText(postingUrl);

  await page.goto('/production');
  await moveCard(page, privateId, 'scheduled');
  await moveCard(page, privateId, 'published');
  await expect(page.getByTestId('kanban-published-modal')).toBeVisible();
  const savePrivate = page.waitForResponse(response => response.url().includes('/published-information') && response.request().method() === 'PUT');
  await page.getByTestId('kanban-not-for-public').click();
  expect((await savePrivate).ok()).toBeTruthy();
  await page.reload();
  await expect(page.locator(`section[data-status="published"] [data-id="${privateId}"]`)).toBeVisible();
  await page.goto(`/content/${privateId}`);
  await expect(page.getByTestId('published-information')).toContainText('Not for Public');

  await page.goto('/production');
  await moveCard(page, closeId, 'published');
  await expect(page.getByTestId('kanban-published-modal')).toBeVisible();
  await cancelPublishing(page, closeId, 'planned', 'close');

  await page.getByTestId('production-filter-pic').selectOption({ label: 'My Tasks' });
  await page.getByTestId('production-filter-period').selectOption('this_week');
  await page.getByTestId('production-filter-pillar').selectOption({ label: 'Product' });
  await page.getByTestId('production-filter-apply').click();
  await moveCard(page, reviewId, 'review');
  await moveCard(page, reviewId, 'published');
  await expect(page.getByTestId('kanban-published-modal')).toBeVisible();
  await cancelPublishing(page, reviewId, 'review', 'cancel');
  await expect(page.getByTestId('production-filter-pic')).toHaveValue(/.+/);
  await expect(page.getByTestId('production-filter-period')).toHaveValue('this_week');
  await expect(page.getByTestId('production-filter-pillar')).not.toHaveValue('');
  await expect(page).toHaveURL(/pic_user_id=.*period=this_week.*pillar_id=/);

  await page.getByTestId('production-filter-reset').click();
  await moveCard(page, escapeId, 'in_production');
  await moveCard(page, escapeId, 'published');
  await expect(page.getByTestId('kanban-published-modal')).toBeVisible();
  await cancelPublishing(page, escapeId, 'in_production', 'escape');

  await moveCard(page, failureId, 'approved');
  await moveCard(page, failureId, 'published');
  await page.route(`**/production/${failureId}`, async route => {
    if (route.request().method() === 'PATCH') await route.fulfill({ status: 500, contentType: 'application/json', body: '{"message":"forced failure"}' });
    else await route.continue();
  });
  await page.getByTestId('kanban-published-cancel').click();
  await expect(page.getByTestId('kanban-published-error')).toContainText('Failed to restore previous status. Please try again.');
  await expect(page.getByTestId('kanban-published-modal')).toBeVisible();
  await expect(page.locator(`section[data-status="published"] [data-id="${failureId}"]`)).toBeVisible();
  await page.unroute(`**/production/${failureId}`);
  await cancelPublishing(page, failureId, 'approved', 'cancel');

  await logout(page);
  await login(page, 'fadly@kolabo.id');
  await page.goto('/production');
  await expect(page.getByTestId('production-context')).toHaveText('My Tasks · This Week');
  await expect(page.getByTestId('production-filter-pic-locked')).toHaveText('My Tasks');
  await expect(page.getByTestId('kanban-card').filter({ hasText: ownWeek })).toBeVisible();
  await expect(page.getByTestId('kanban-card').filter({ hasText: ownNext })).toHaveCount(0);
  await expect(page.getByTestId('kanban-card').filter({ hasText: otherWeek })).toHaveCount(0);
  await page.goto('/production?pic_user_id=999999&period=all');
  await expect(page.getByTestId('kanban-card').filter({ hasText: ownWeek })).toBeVisible();
  await expect(page.getByTestId('kanban-card').filter({ hasText: otherWeek })).toHaveCount(0);
  await page.getByTestId('production-filter-period').selectOption('next_week');
  await page.getByTestId('production-filter-apply').click();
  await expect(page.getByTestId('kanban-card').filter({ hasText: ownNext })).toBeVisible();
  await expect(page.getByTestId('kanban-card').filter({ hasText: ownWeek })).toHaveCount(0);
});

test('production.view_all_tasks can be assigned and removed in the role editor', async ({ page }) => {
  test.setTimeout(90_000);
  await login(page, 'admin@kolabo.id');
  await page.goto('/admin/roles');
  await page.getByTestId('role-selector').selectOption({ label: 'Creative Member' });
  const editor = page.locator('section:visible');
  const permission = editor.getByTestId('permission-production.view_all_tasks');
  await expect(permission).not.toBeChecked();
  await permission.check();
  await editor.getByTestId('role-save').click();
  await page.getByTestId('role-selector').selectOption({ label: 'Creative Member' });
  await expect(page.locator('section:visible').getByTestId('permission-production.view_all_tasks')).toBeChecked();
  await logout(page);

  await login(page, 'fadly@kolabo.id');
  await page.goto('/production');
  await expect(page.getByTestId('production-context')).toHaveText('All Tasks · All Dates');
  await logout(page);

  await login(page, 'admin@kolabo.id');
  await page.goto('/admin/roles');
  await page.getByTestId('role-selector').selectOption({ label: 'Creative Member' });
  const restoredEditor = page.locator('section:visible');
  await restoredEditor.getByTestId('permission-production.view_all_tasks').uncheck();
  await restoredEditor.getByTestId('role-save').click();
  await page.getByTestId('role-selector').selectOption({ label: 'Creative Member' });
  await expect(page.locator('section:visible').getByTestId('permission-production.view_all_tasks')).not.toBeChecked();
});
