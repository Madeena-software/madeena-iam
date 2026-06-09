import { test, expect } from '@playwright/test';

test('authentication logs read-only UI, details modal, and logout tracking flow', async ({ page }) => {
  // 1. Login as admin (Session 1)
  await page.goto('/admin/login');
  await page.waitForLoadState('networkidle');
  await page.fill('input[type="email"]', 'admin@madeena.local');
  await page.fill('input[type="password"]', 'admin');
  await page.keyboard.press('Enter');
  await page.waitForURL(url => url.pathname.startsWith('/admin') && !url.pathname.includes('/login'));
  await page.waitForLoadState('networkidle');

  // Go to Authentication Logs list via sidebar
  await page.getByRole('link', { name: 'Authentication Logs' }).click();
  await page.waitForLoadState('networkidle');

  // ==========================================
  // Perform logout to trigger logout tracking
  // ==========================================
  // Click the Dashboard sidebar link to get back to dashboard
  await page.getByRole('link', { name: 'Dashboard' }).click();
  await page.waitForLoadState('networkidle');

  // Click the "Sign out" button in the Welcome dashboard widget card
  const signOutButton = page.getByRole('button', { name: 'Sign out' }).first();
  await expect(signOutButton).toBeVisible();
  await signOutButton.click();

  // Wait for redirection back to login page
  await page.waitForURL('**/admin/login');
  await page.waitForLoadState('networkidle');

  // ==========================================
  // Login again (Session 2) to verify Session 1's logout log
  // ==========================================
  await page.fill('input[type="email"]', 'admin@madeena.local');
  await page.fill('input[type="password"]', 'admin');
  await page.keyboard.press('Enter');
  await page.waitForURL(url => url.pathname.startsWith('/admin') && !url.pathname.includes('/login'));
  await page.waitForLoadState('networkidle');

  // Go to Authentication Logs list via sidebar
  await page.getByRole('link', { name: 'Authentication Logs' }).click();
  await page.waitForLoadState('networkidle');

  // The second row corresponds to Session 1 (which has logged out)
  const rows = page.locator('table tbody tr');
  await expect(rows.nth(1)).toBeVisible({ timeout: 10000 }); // There should be at least two sessions logged

  // Verify that the second row has a logout timestamp
  // We check the "Logout at" column in the second row.
  // In our table, columns are: User, Authenticatable type, Ip address, Login at, Login successful, Logout at, Cleared by user.
  // Logout at is column index 5 (0-indexed).
  const secondRowLogoutCell = rows.nth(1).locator('td').nth(5);
  await expect(secondRowLogoutCell).not.toBeEmpty();

  // Open view modal for the logged-out session (second row)
  const viewButton = rows.nth(1).getByRole('button', { name: 'View' });
  await expect(viewButton).toBeVisible();
  await viewButton.click();

  // Verify details modal is visible and contains logout time
  await expect(page.getByRole('heading', { name: 'View authentication log' })).toBeVisible();
  const dialog = page.getByRole('dialog').filter({ hasText: 'View authentication log' });

  const logoutField = dialog.getByLabel('Logout at');
  await expect(logoutField).toBeVisible();
  const logoutValue = await logoutField.inputValue();
  expect(logoutValue).not.toBeNull();
  expect(logoutValue).not.toBe('');

  // Close the modal
  await page.keyboard.press('Escape');
  await page.waitForTimeout(500);
});
