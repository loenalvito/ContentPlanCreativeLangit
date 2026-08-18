import { test, expect } from '@playwright/test';
import { login, logout } from './helpers';

test('valid login and logout', async ({ page }) => {
  await login(page, 'admin@kolabo.id');
  await expect(page).toHaveURL(/dashboard/);
  await expect(page.getByText('Rayhan Admin').first()).toBeVisible();
  await expect(page.getByRole('link', { name: 'Content Plan' })).toBeVisible();
  await logout(page);
});

test('invalid login remains on login with error', async ({ page }) => {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill('admin@kolabo.id');
  await page.locator('input[name="password"]').fill('wrong-password');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).toHaveURL(/login/);
  await expect(page.getByText('Email atau password tidak valid.')).toBeVisible();
});
