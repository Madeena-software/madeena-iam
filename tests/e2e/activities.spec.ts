import { test, expect } from '@playwright/test';

test('activities read-only UI and logging flows', async ({ page }) => {
  // 1. Login once
  await page.goto('/admin/login');
  await page.fill('input[type="email"]', 'admin@madeena.local');
  await page.fill('input[type="password"]', 'admin');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin**');
  await page.waitForLoadState('networkidle');

  // ==========================================
  // Flow A: Verify Activities UI is Read-Only
  // ==========================================
  await page.goto('/admin/activities');
  await page.waitForLoadState('networkidle');

  // Assert "New activity" button does not exist
  await expect(page.getByRole('button', { name: 'New activity' })).toHaveCount(0);
  await expect(page.locator('a', { hasText: 'New activity' })).toHaveCount(0);

  // Assert that Edit Action does not exist in table
  await expect(page.locator('a', { hasText: 'Edit' })).toHaveCount(0);
  await expect(page.locator('button', { hasText: 'Edit' })).toHaveCount(0);

  // Assert that View Action exists and can be clicked to open modal
  const viewAction = page.locator('a', { hasText: 'View' }).first();
  if (await viewAction.count() > 0) {
    await viewAction.click();
    await expect(page.locator('.fi-modal')).toBeVisible();
    await expect(page.locator('.fi-modal button[type="submit"]')).toHaveCount(0);
    // Close the modal
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);
  }

  // ==========================================
  // Flow B: Create OAuth Client and verify log
  // ==========================================
  await page.goto('/admin/oauth-clients/create');
  await page.waitForLoadState('networkidle');

  const clientName = 'Activities Logging Test Client ' + Date.now();
  await page.fill('[id="form.name"]', clientName);
  await page.fill('[id="form.redirect_uris"]', 'http://localhost:8080/callback');
  await page.check('input[value="authorization_code"]');
  await page.check('input[value="refresh_token"]');

  // Upload logo
  await page.setInputFiles('input[type="file"]', 'tests/fixtures/logo.png');
  await page.waitForTimeout(2000); // Wait for upload

  // Submit
  await page.getByRole('button', { name: 'Create', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'OAuth Client Credentials Created' })).toBeVisible({ timeout: 15000 });

  // Close credentials notification modal if it is open
  await page.keyboard.press('Escape');
  await page.waitForTimeout(500);

  // Verify created log in Activities table
  await page.goto('/admin/activities');
  await page.waitForLoadState('networkidle');
  await expect(page.locator('td', { hasText: 'App\\Models\\OauthClient' }).first()).toBeVisible();
  await expect(page.locator('td', { hasText: 'created' }).first()).toBeVisible();
});
