import { test, expect } from '@playwright/test';

test('create oauth client redesign form flow', async ({ page }) => {
  // 1. Navigate to login and authenticate
  await page.goto('/admin/login');
  
  // Fill credentials
  await page.fill('input[type="email"]', 'admin@madeena.local');
  await page.fill('input[type="password"]', 'admin');
  
  // Submit login
  await page.click('button[type="submit"]');
  
  // Wait for redirect to admin panel and let page settle
  await page.waitForURL('**/admin**');
  await page.waitForLoadState('networkidle');

  // 2. Navigate to Create OAuth Client page
  await page.goto('/admin/oauth-clients/create');
  await page.waitForLoadState('networkidle');

  // 3. Assert fields are hidden (do not exist in DOM)
  await expect(page.locator('[id="form.id"]')).toHaveCount(0);
  await expect(page.locator('[id="form.secret"]')).toHaveCount(0);
  await expect(page.locator('[id="form.provider"]')).toHaveCount(0);
  await expect(page.locator('[id="form.created_by"]')).toHaveCount(0);
  await expect(page.locator('[id="form.updated_by"]')).toHaveCount(0);
  await expect(page.locator('[id="form.deleted_by"]')).toHaveCount(0);

  // Assert owner fields exist and are visible
  const ownerTypeLabel = page.locator('label:has-text("Owner Type")');
  const ownerNameLabel = page.locator('label:has-text("Owner Name")');
  await expect(ownerTypeLabel).toBeVisible();
  await expect(ownerNameLabel).toBeVisible();

  // 4. Assert visible form elements
  const nameField = page.locator('[id="form.name"]');
  const redirectUrisField = page.locator('[id="form.redirect_uris"]');
  const logoField = page.locator('input[type="file"]');

  await expect(nameField).toBeVisible();
  await expect(redirectUrisField).toBeVisible();

  // 5. Fill out form
  await nameField.fill('E2E Test Client');
  await redirectUrisField.fill('http://localhost:8080/callback');

  // Select grant types (CheckboxList)
  await page.check('input[value="authorization_code"]');
  await page.check('input[value="refresh_token"]');

  // 6. Handle file upload for app logo path
  await page.setInputFiles('input[type="file"]', 'tests/fixtures/logo.png');

  // Wait for file upload component to complete upload
  await page.waitForTimeout(2000);

  // 7. Click Create/Submit
  await page.getByRole('button', { name: 'Create', exact: true }).click();

  // 8. Assert successful creation and notification credentials
  const heading = page.getByRole('heading', { name: 'OAuth Client Credentials Created' });
  await expect(heading).toBeVisible({ timeout: 15000 });

  const notificationBody = page.locator('.fi-no-notification-body');
  await expect(notificationBody).toBeVisible();
  await expect(notificationBody).toContainText('Client ID');
  await expect(notificationBody).toContainText('Client Secret');
});
