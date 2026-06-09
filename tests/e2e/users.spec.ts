import { test, expect } from '@playwright/test';

test('users resource full CRUD and restore flow', async ({ page }) => {
  // 1. Log in as admin
  await page.goto('/admin/login');
  await page.waitForLoadState('networkidle');
  await page.fill('input[type="email"]', 'admin@madeena.local');
  await page.fill('input[type="password"]', 'admin');
  await page.keyboard.press('Enter');
  await page.waitForURL(url => url.pathname.startsWith('/admin') && !url.pathname.includes('/login'));
  await page.waitForLoadState('networkidle');

  // 2. Navigate to Users resource
  await page.getByRole('link', { name: 'Users' }).click();
  await page.waitForURL('**/admin/users');
  await page.waitForLoadState('networkidle');

  // Verify list page loads
  await expect(page.getByRole('heading', { name: 'Users' })).toBeVisible();

  // 3. Create a new user
  await page.getByRole('link', { name: 'New user' }).click();
  await page.waitForURL('**/admin/users/create');
  await page.waitForLoadState('networkidle');

  const randomEmail = `user-${Date.now()}@example.com`;
  await page.fill('[id="form.name"]', 'E2E Test User');
  await page.fill('[id="form.email"]', randomEmail);
  await page.fill('[id="form.password"]', 'password123');

  // Click Create
  await page.getByRole('button', { name: 'Create', exact: true }).click();
  
  // Wait for redirect to Edit page
  await page.waitForURL('**/admin/users/*/edit');
  await page.waitForLoadState('networkidle');

  // Verify success notification or content
  await expect(page.getByRole('heading', { name: 'Edit User' })).toBeVisible();
  await expect(page.locator('[id="form.email"]')).toHaveValue(randomEmail);

  // Assert audit fields display correctly as text entries (Infolist style)
  await expect(page.locator('text=Created By')).toBeVisible();
  await expect(page.locator('#form').getByText('Super Admin')).toBeVisible();

  // 4. Go back to list, search and edit user
  await page.goto('/admin/users');
  await page.waitForLoadState('networkidle');

  // Search for the user using the table search input
  const searchInput = page.getByRole('searchbox', { name: 'Search', exact: true });
  await searchInput.fill(randomEmail);
  await page.keyboard.press('Enter');
  await page.waitForLoadState('networkidle');

  // Verify only our created user is shown in the table
  const userRow = page.locator('table tbody tr', { hasText: randomEmail }).first();
  await expect(userRow).toBeVisible();

  // Click Edit action on that row
  const editButton = userRow.getByRole('link', { name: 'Edit' });
  await editButton.click();
  await page.waitForURL('**/admin/users/*/edit');
  await page.waitForLoadState('networkidle');

  // Edit name
  await page.fill('[id="form.name"]', 'E2E Test User Updated');
  // Click Save changes (standard submit button on Edit page)
  await page.getByRole('button', { name: 'Save changes', exact: true }).click();
  await page.waitForLoadState('networkidle');

  // Verify the changes saved successfully
  await expect(page.locator('[id="form.name"]')).toHaveValue('E2E Test User Updated');

  // 5. Delete (soft-delete) the user
  const deleteButton = page.getByRole('button', { name: 'Delete', exact: true });
  await expect(deleteButton).toBeVisible();
  await deleteButton.click();

  // Confirm deletion in the dialog modal
  const deleteDialog = page.getByRole('dialog').filter({ hasText: 'Delete User' });
  await expect(deleteDialog.getByRole('heading', { name: 'Delete User' })).toBeVisible();
  const confirmDeleteButton = deleteDialog.getByRole('button', { name: 'Delete', exact: true });
  await confirmDeleteButton.click();

  // Wait for redirect back to list page
  await page.waitForURL('**/admin/users');
  await page.waitForLoadState('networkidle');

  // 6. Search for deleted user (should NOT find it since it's soft-deleted)
  const searchInputAfterDelete = page.getByRole('searchbox', { name: 'Search', exact: true });
  await searchInputAfterDelete.fill(randomEmail);
  await page.keyboard.press('Enter');
  await page.waitForLoadState('networkidle');
  await expect(page.locator('table tbody tr', { hasText: randomEmail })).toHaveCount(0);

  // 7. Enable Trashed records filter to show soft-deleted user
  // Click filter button
  const filterButton = page.locator('button.fi-ta-filter-trigger, button[title="Filter"]').first();
  await filterButton.click();
  await page.waitForLoadState('networkidle');

  // Select "With trashed" or "Only trashed" from the Trashed select filter
  const trashedFilter = page.getByLabel('Deleted records');
  await trashedFilter.selectOption({ label: 'With deleted records' });
  
  // Click Apply filters button to submit the filter form
  await page.getByRole('button', { name: 'Apply filters' }).click();
  await page.waitForLoadState('networkidle');

  // The table should update automatically. Verify the soft-deleted user is now visible
  const trashedUserRow = page.locator('table tbody tr', { hasText: randomEmail }).first();
  await expect(trashedUserRow).toBeVisible();

  // Click edit on the soft-deleted user
  const editTrashedButton = trashedUserRow.getByRole('link', { name: 'Edit' });
  await editTrashedButton.click();
  await page.waitForURL('**/admin/users/*/edit');
  await page.waitForLoadState('networkidle');

  // 8. Restore the user
  const restoreButton = page.getByRole('button', { name: 'Restore', exact: true });
  await expect(restoreButton).toBeVisible();
  await restoreButton.click();

  // Confirm restore in the dialog modal
  const restoreDialog = page.getByRole('dialog').filter({ hasText: 'Restore User' });
  await expect(restoreDialog.getByRole('heading', { name: 'Restore User' })).toBeVisible();
  const confirmRestoreButton = restoreDialog.getByRole('button', { name: 'Restore', exact: true });
  await confirmRestoreButton.click();
  await page.waitForLoadState('networkidle');

  // Verify restore button is no longer visible (since it's restored, delete should be back)
  await expect(page.getByRole('button', { name: 'Restore', exact: true })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Delete', exact: true })).toBeVisible();
});
