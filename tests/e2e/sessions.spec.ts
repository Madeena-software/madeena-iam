import { test, expect } from '@playwright/test';

test('active sessions management: standalone list, relation manager, and self-termination warning flow', async ({ page }) => {
  // 1. Log in as admin
  await page.goto('/admin/login');
  await page.waitForLoadState('networkidle');
  await page.fill('input[type="email"]', 'admin@madeena.local');
  await page.fill('input[type="password"]', 'admin');
  await page.keyboard.press('Enter');
  await page.waitForURL(url => url.pathname.startsWith('/admin') && !url.pathname.includes('/login'));
  await page.waitForLoadState('networkidle');

  // 2. Navigate to standalone Sessions resource via sidebar
  await page.getByRole('link', { name: 'Sessions' }).click();
  await page.waitForURL('**/admin/sessions');
  await page.waitForLoadState('networkidle');

  // Verify list page loads and includes active session row
  await expect(page.getByRole('heading', { name: 'Sessions' })).toBeVisible();
  const sessionRows = page.locator('table tbody tr');
  await expect(sessionRows.first()).toBeVisible();

  // Assert that session details are visible (e.g. Super Admin user name, IP address)
  await expect(page.locator('table tbody')).toContainText('Super Admin');

  // 3. Navigate to Users edit page to check Relation Manager
  await page.getByRole('link', { name: 'Users' }).click();
  await page.waitForURL('**/admin/users');
  await page.waitForLoadState('networkidle');

  // Search for the admin user to ensure it is visible regardless of sorting or pagination
  const searchInput = page.getByRole('searchbox', { name: 'Search', exact: true });
  await searchInput.fill('admin@madeena.local');
  await page.keyboard.press('Enter');
  await page.waitForLoadState('networkidle');

  const adminRow = page.locator('table tbody tr', { hasText: 'admin@madeena.local' }).first();
  const editButton = adminRow.getByRole('link', { name: 'Edit' });
  await expect(editButton).toBeVisible();
  await editButton.click();
  await page.waitForURL('**/admin/users/*/edit');
  await page.waitForLoadState('networkidle');

  // Click on the "Sessions" tab to make the relation manager visible
  await page.getByRole('tab', { name: 'Sessions' }).click();
  await page.waitForLoadState('networkidle');

  // Locate the Sessions relation manager tab panel (which is the only visible tabpanel)
  const relationManagerSection = page.getByRole('tabpanel').filter({ visible: true });
  await expect(relationManagerSection).toBeVisible();
  await expect(relationManagerSection.locator('table')).toBeVisible();

  // 4. Test session termination flow
  // Wait for table rows to be visible before counting
  await expect(relationManagerSection.locator('table tbody tr').first()).toBeVisible();

  // Find the first session row in the relation manager table
  const firstSessionRow = relationManagerSection.locator('table tbody tr').first();
  const checkbox = firstSessionRow.getByRole('checkbox');
  const checkboxLabel = await checkbox.getAttribute('aria-label') ?? '';

  const relationDeleteButton = firstSessionRow.getByRole('button', { name: 'Delete' });
  await expect(relationDeleteButton).toBeVisible();
  await relationDeleteButton.click();

  // Assert the confirmation modal is shown by checking its heading
  const dialog = page.getByRole('dialog').filter({ hasText: 'Terminate Session' });
  await expect(dialog.getByRole('heading', { name: 'Terminate Session' })).toBeVisible();
  await expect(dialog).toContainText('Are you sure you want to terminate this active device session?');

  // Click the danger action button inside the modal to terminate
  const deleteConfirmButton = dialog.getByRole('button', { name: 'Delete', exact: true });
  await expect(deleteConfirmButton).toBeVisible();
  await deleteConfirmButton.click();

  // Wait for the modal to close
  await expect(page.getByRole('heading', { name: 'Terminate Session' })).toBeHidden();
  await page.waitForLoadState('networkidle');

  // 5. Verify the session is removed from the table (specific checkbox no longer exists)
  if (checkboxLabel) {
    await expect(relationManagerSection.getByRole('checkbox', { name: checkboxLabel })).toHaveCount(0);
  }
});
