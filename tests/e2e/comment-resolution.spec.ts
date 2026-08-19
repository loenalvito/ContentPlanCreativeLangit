import { test, expect } from '@playwright/test';
import { login } from './helpers';

test('Comment counts persist and open badges decrement when discussions are resolved', async ({ page }) => {
  const unique = Date.now();
  const title = `Comment Lifecycle ${unique}`;
  const first = `Resolve discussion A ${unique}`;
  const second = `Resolve discussion B ${unique}`;

  await login(page, 'lead@kolabo.id');
  await page.goto('/content');
  await page.getByTestId('add-content').click();
  await page.getByTestId('content-title').fill(title);
  await page.getByTestId('content-date').fill('2026-08-29');
  await page.getByTestId('content-pillar').selectOption({ label: 'Entertainment' });
  await page.getByTestId('content-series').selectOption({ label: 'POV' });
  await page.getByTestId('content-format').selectOption({ label: 'Reels' });
  await page.getByTestId('content-submit').click();
  await expect(page.getByRole('heading', { name: title })).toBeVisible();

  for (const body of [first, second]) {
    await page.getByTestId('tab-comments').click();
    await page.getByTestId('comment-body').fill(body);
    await page.getByTestId('comment-submit').click();
  }

  await page.getByTestId('tab-comments').click();
  await expect(page.getByTestId('tab-comments').getByTestId('comment-total')).toHaveText('2');
  await expect(page.getByTestId('tab-comments').getByTestId('open-comment-count')).toHaveText('2');

  await page.getByTestId('comment').filter({ hasText: first }).getByTestId('resolve-comment').click();
  await page.getByTestId('tab-comments').click();
  await expect(page.getByTestId('tab-comments').getByTestId('comment-total')).toHaveText('2');
  await expect(page.getByTestId('tab-comments').getByTestId('open-comment-count')).toHaveText('1');
  await expect(page.getByTestId('comment').filter({ hasText: first }).getByTestId('resolved-meta')).toContainText('Dina Lead');

  await page.getByTestId('comment').filter({ hasText: second }).getByTestId('resolve-comment').click();
  await page.getByTestId('tab-comments').click();
  await expect(page.getByTestId('tab-comments').getByTestId('comment-total')).toHaveText('2');
  await expect(page.getByTestId('tab-comments').getByTestId('open-comment-count')).toHaveCount(0);
  await page.reload();
  await expect(page.getByTestId('tab-comments').getByTestId('comment-total')).toHaveText('2');

  await page.goto(`/content?search=${encodeURIComponent(title)}`);
  const planRow = page.getByTestId('content-row').filter({ hasText: title });
  await expect(planRow.getByTestId('comment-total')).toHaveText('2');
  await expect(planRow.getByTestId('open-comment-count')).toHaveCount(0);

  await page.goto('/production');
  const card = page.getByTestId('kanban-card').filter({ hasText: title });
  await expect(card.getByTestId('comment-total')).toHaveText('2');
  await expect(card.getByTestId('open-comment-count')).toHaveCount(0);
});

test('User without comments.resolve cannot call resolve endpoint', async ({ page }) => {
  await login(page, 'sales@kolabo.id');
  const response = await page.evaluate(async () => {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    return fetch('/content/1/comments/1/resolve', {
      method: 'PATCH',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
    }).then(result => result.status);
  });
  expect(response).toBe(403);
});
