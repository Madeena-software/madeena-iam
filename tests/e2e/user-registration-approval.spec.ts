import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import * as fs from 'fs';
import * as path from 'path';

test.describe('User Registration and Admin Approval E2E Flow', () => {
  const clientId = '99999999-9999-9999-9999-999999999999';
  const clientSecret = 'super-secret-e2e-key';

  test.beforeAll(async () => {
    // Programmatically create the oauth client in database
    const phpCommand = `php -r "require 'vendor/autoload.php'; \\$app = require_once 'bootstrap/app.php'; \\$app->make('Illuminate\\\\Contracts\\\\Console\\\\Kernel')->bootstrap(); App\\\\Models\\\\OauthClient::updateOrCreate(['id' => '${clientId}'], ['name' => 'E2E Registration App', 'secret' => Illuminate\\\\Support\\\\Facades\\\\Hash::make('${clientSecret}'), 'redirect_uris' => ['http://localhost:8080/callback'], 'grant_types' => ['authorization_code'], 'revoked' => false, 'is_active' => true]);"`;
    execSync(phpCommand);
  });

  test.afterAll(async () => {
    // Clean up client
    const phpCommand = `php -r "require 'vendor/autoload.php'; \\$app = require_once 'bootstrap/app.php'; \\$app->make('Illuminate\\\\Contracts\\\\Console\\\\Kernel')->bootstrap(); App\\\\Models\\\\OauthClient::where('id', '${clientId}')->forceDelete();"`;
    try {
      execSync(phpCommand);
    } catch (e) {
      console.error('Failed to clean up oauth client', e);
    }
  });

  test('register user, receive admin email, approve user, receive onboarding email, set password', async ({ page, context, request }) => {
    const logPath = path.resolve('storage/logs/laravel.log');

    // 1. Clear/truncate laravel.log
    if (fs.existsSync(logPath)) {
      fs.writeFileSync(logPath, '');
    }

    // 2. Register a new user via API call
    const email = `e2e-user-${Date.now()}@example.com`;
    const name = `E2E User ${Date.now()}`;
    const registerResponse = await request.post('/api/v1/auth/register', {
      data: {
        name: name,
        email: email,
        password: 'password123',
        client_id: clientId,
        client_secret: clientSecret
      }
    });

    expect(registerResponse.status()).toBe(201);
    const registerData = await registerResponse.json();
    expect(registerData.status).toBe('pending_approval');

    // 3. Wait/poll for the admin email to be logged
    let logContent = '';
    let adminUrl = '';
    for (let i = 0; i < 20; i++) {
      if (fs.existsSync(logPath)) {
        logContent = fs.readFileSync(logPath, 'utf8');
        const cleanContent = logContent.replace(/=\r?\n/g, '').replace(/=3D/g, '=');
        if (cleanContent.includes('E2E_MAIL_SENT:') && cleanContent.includes('New User Registration')) {
          // Parse CTA link
          const match = cleanContent.match(/http:\/\/localhost:8000\/admin\/users\/[a-f0-9-]+\/edit/i);
          if (match) {
            adminUrl = match[0];
            break;
          }
        }
      }
      await page.waitForTimeout(500);
    }

    expect(adminUrl).not.toBe('');

    // 4. Log in as admin via UI
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');
    await page.fill('input[type="email"]', 'admin@madeena.local');
    await page.fill('input[type="password"]', 'admin');
    await page.keyboard.press('Enter');
    await page.waitForURL(url => url.pathname.startsWith('/admin') && !url.pathname.includes('/login'));
    await page.waitForLoadState('networkidle');

    // 5. Navigate directly to the user's edit page
    await page.goto(adminUrl);
    await page.waitForLoadState('networkidle');

    // 6. Click on the "Clients" relation manager tab
    await page.getByRole('tab', { name: 'Clients' }).click();
    await page.waitForLoadState('networkidle');

    // 7. Locate the client row and click Edit
    const clientRow = page.locator('table tbody tr', { hasText: 'E2E Registration App' }).first();
    await expect(clientRow).toBeVisible();

    const relationEditButton = clientRow.getByRole('button', { name: 'Edit' });
    await expect(relationEditButton).toBeVisible();

    // Before clicking Edit, we want to clear the logs so we only capture the new onboarding mail
    if (fs.existsSync(logPath)) {
      fs.writeFileSync(logPath, '');
    }

    await relationEditButton.click();
    
    // 8. In the modal, change the status to Approved and Save
    const editModal = page.getByRole('dialog').filter({ hasText: 'Edit E2E Registration App' });
    await expect(editModal.getByRole('heading', { name: 'Edit E2E Registration App' })).toBeVisible();

    await editModal.locator('select[id*="status"]').selectOption('approved');
    
    // Save changes
    await editModal.getByRole('button', { name: /save/i }).click();
    await page.waitForLoadState('networkidle');

    // 9. Wait/poll for the onboarding email to be logged to the newly registered user
    let onboardingUrl = '';
    for (let i = 0; i < 20; i++) {
      if (fs.existsSync(logPath)) {
        logContent = fs.readFileSync(logPath, 'utf8');
        const cleanContent = logContent.replace(/=\r?\n/g, '').replace(/=3D/g, '=');
        if (cleanContent.includes('E2E_MAIL_SENT:') && cleanContent.includes('Welcome to Madeena SSO - Set Up Your Account')) {
          // Parse password reset URL
          const match = cleanContent.match(/http:\/\/localhost:8000\/password-reset\/[^\s"'<>]+/i);
          if (match) {
            onboardingUrl = match[0];
            break;
          }
        }
      }
      await page.waitForTimeout(500);
    }

    expect(onboardingUrl).not.toBe('');

    // 10. Log out first to ensure we are guest when resetting password
    await context.clearCookies();

    // 11. Navigate to the password reset URL
    await page.goto(onboardingUrl);
    await page.waitForLoadState('networkidle');

    // Verify password reset page loads correctly
    await expect(page).toHaveTitle('set password - madeena');
    await expect(page.locator('h1.info-title')).toContainText('Secure Credentials Setup');
    await expect(page.locator('input[type="email"]')).toHaveValue(email);
  });
});
