# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: critical-flow.spec.ts >> Revisi 1 critical workflow
- Location: tests\e2e\critical-flow.spec.ts:4:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByText('Revision One 1787043303488', { exact: true })
Expected: visible
Timeout: 10000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" with timeout 10000ms
  - waiting for getByText('Revision One 1787043303488', { exact: true })

```

```yaml
- complementary:
  - text: ◈ kolabo OVERVIEW
  - link "□ Dashboard":
    - /url: http://127.0.0.1:8000/dashboard
  - text: CONTENT
  - link "□ Ideas":
    - /url: http://127.0.0.1:8000/ideas
  - link "□ Content Plan":
    - /url: http://127.0.0.1:8000/content
  - link "□ Production":
    - /url: http://127.0.0.1:8000/production
  - link "□ Calendar":
    - /url: http://127.0.0.1:8000/calendar
  - text: LIBRARY
  - link "□ Published":
    - /url: http://127.0.0.1:8000/published
  - link "□ Assets":
    - /url: http://127.0.0.1:8000/assets
  - text: TEAM
  - link "□ My Tasks":
    - /url: http://127.0.0.1:8000/my-tasks
  - link "□ Team":
    - /url: http://127.0.0.1:8000/team
  - text: ADMINISTRATION
  - link "□ Users":
    - /url: http://127.0.0.1:8000/admin/users
  - text: Dina Lead Creative Lead
  - button "Logout"
- main:
  - text: Kolabo Creative Workspace Dina Lead
  - heading "Published Library" [level=1]
  - paragraph: Arsip semua konten yang sudah dipublish
  - article:
    - text: ▶
    - link "Team BTS — Shooting Day":
      - /url: http://127.0.0.1:8000/content/10
    - paragraph: 25 Aug 2026 · Brand
    - paragraph: TikTok · Nabila Creative
    - link "Open Post ↗":
      - /url: https://instagram.com/kolabo.id
  - article:
    - text: ▶
    - link "Inside Kolabo — Office Culture":
      - /url: http://127.0.0.1:8000/content/9
    - paragraph: 24 Aug 2026 · Brand
    - paragraph: YouTube · Fadly Creative
    - link "Open Post ↗":
      - /url: https://instagram.com/kolabo.id
  - article:
    - text: ▶
    - link "&lt;b&gt;Revision One 1787043303488&lt;/b&gt;":
      - /url: http://127.0.0.1:8000/content/11
    - paragraph: 20 Aug 2026 · Entertainment
    - paragraph: Instagram · Dina Lead
```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test';
  2  | import { login, logout, failOnPageErrors } from './helpers';
  3  | 
  4  | test('Revisi 1 critical workflow', async ({ page }) => {
  5  |   test.setTimeout(90_000);
  6  |   const noErrors = failOnPageErrors(page);
  7  |   const title = `Revision One ${Date.now()}`;
  8  |   const publish = new Date(); publish.setDate(publish.getDate() + 2);
  9  | 
  10 |   await login(page, 'sales@kolabo.id');
  11 |   await page.getByRole('link', { name: 'Ideas' }).click();
  12 |   await page.getByTestId('add-idea').click();
  13 |   await page.getByTestId('idea-editor').fill(`<b>${title}</b>`);
  14 |   await page.getByTestId('idea-pillar').selectOption({ label: 'Entertainment' });
  15 |   for (const label of ['Office Life','Meme','POV']) await expect(page.getByTestId('idea-series').locator('option', { hasText: label })).toHaveCount(1);
  16 |   await expect(page.getByTestId('idea-series').locator('option', { hasText: 'Kolabo Features' })).toHaveCount(0);
  17 |   await page.getByTestId('idea-series').selectOption({ label: 'POV' });
  18 |   await page.getByTestId('idea-format').selectOption({ label: 'Reels' });
  19 |   await page.getByTestId('submit-idea').click();
  20 |   let row = page.getByTestId('idea-row').filter({ hasText: title });
  21 |   await expect(row).toContainText('Andi Sales'); await expect(row).toContainText('Sales');
  22 |   await logout(page);
  23 | 
  24 |   await login(page, 'lead@kolabo.id');
  25 |   await page.getByRole('link', { name: 'Ideas' }).click();
  26 |   row = page.getByTestId('idea-row').filter({ hasText: title });
  27 |   await row.getByTestId('idea-status').selectOption('consider');
  28 |   row = page.getByTestId('idea-row').filter({ hasText: title }); await row.getByTestId('idea-status').selectOption('selected');
  29 |   row = page.getByTestId('idea-row').filter({ hasText: title }); await row.getByTestId('move-idea').click();
  30 |   await row.getByTestId('move-publish-date').fill(publish.toISOString().slice(0,10));
  31 |   await row.getByTestId('move-pic').selectOption({ label: 'Dina Lead' }); await row.getByTestId('move-submit').click();
  32 |   await page.getByRole('link', { name: title }).click(); await page.getByTestId('tab-source').click(); await expect(page.getByText('Andi Sales')).toBeVisible();
  33 |   await page.getByRole('link', { name: 'Ideas' }).click(); await expect(page.getByTestId('idea-row').filter({ hasText: title })).toContainText('Converted');
  34 | 
  35 |   await page.getByRole('link', { name: 'Content Plan' }).click(); row = page.getByTestId('content-row').filter({ hasText: title });
  36 |   page.once('dialog', dialog => dialog.accept()); await row.getByTestId('content-status').selectOption('in_production'); await page.waitForTimeout(300);
  37 |   await page.getByRole('link', { name: 'Production' }).click(); await expect(page.locator('section[data-status="in_production"]').getByText(title)).toBeVisible();
  38 |   const card = page.locator('a[draggable="true"]').filter({ hasText: title });
  39 |   const cardId = await card.getAttribute('data-id');
  40 |   expect(await page.evaluate(async id => (await fetch(`/production/${id}`, {method:'PATCH',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')!.content},body:JSON.stringify({status:'review'})})).ok, cardId)).toBe(true);
  41 |   await page.reload(); await expect(page.locator('section[data-status="review"]').getByText(title)).toBeVisible();
  42 |   await page.goto(`/content/${cardId}`); await page.getByTestId('tab-revision').click(); await page.getByTestId('revision-comment').fill('Playwright revision comment'); await page.getByTestId('request-revision').click();
  43 |   await page.getByTestId('tab-comments').click(); await expect(page.getByTestId('comment')).toContainText('Playwright revision comment'); await expect(page.getByTestId('comment')).toContainText('Dina Lead');
  44 |   const id = page.url().split('/').pop();
  45 |   const update = async (status:string) => page.evaluate(async ({id,status}) => (await fetch(`/production/${id}`, {method:'PATCH',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')!.content},body:JSON.stringify({status})})).ok, {id,status});
  46 |   for (const status of ['review','approved','scheduled','published']) expect(await update(status)).toBe(true);
> 47 |   await page.getByRole('link', { name: 'Published' }).click(); await expect(page.getByText(title, { exact: true })).toBeVisible();
     |                                                                                                                     ^ Error: expect(locator).toBeVisible() failed
  48 |   await page.getByRole('link', { name: 'Calendar' }).click(); await expect(page.locator('.status-published').filter({ hasText: title })).toBeVisible();
  49 |   noErrors();
  50 | });
  51 | 
```