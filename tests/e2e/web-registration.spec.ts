import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import * as fs from 'fs';
import * as path from 'path';

test.describe('Web Registration E2E Flow', () => {
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

  test('standalone web registration from login link', async ({ page, context }) => {
    // 1. Visit Login page
    await page.goto('/login');
    await page.waitForLoadState('networkidle');

    // 2. Assert "Register here" link exists
    const registerLink = page.locator('#register-link');
    await expect(registerLink).toBeVisible();
    await expect(registerLink).toHaveText('Register here');

    // 3. Click the link and assert navigation to /register
    await registerLink.click();
    await page.waitForURL('**/register');

    // 4. Fill registration form
    const email = `web-e2e-${Date.now()}@example.com`;
    await page.fill('#name', 'Web E2E User');
    await page.fill('#email', email);
    await page.fill('#password', 'password123');
    await page.fill('#password_confirmation', 'password123');

    // 5. Submit form
    await page.click('#submit-register');

    // 6. Assert redirection to home page '/' since there is no client_id
    // But since root is / which redirects to 403 or aborts (in routes/web.php, / aborts 403), we expect a 403 or redirect
    // Let's assert the pathname becomes '/'
    await page.waitForURL(url => url.pathname === '/');
    
    // Clean cookies/auth
    await context.clearCookies();
  });

  test('SSO web registration preserves query parameters and redirects to authorize', async ({ page, context }) => {
    const queryParams = `client_id=${clientId}&redirect_uri=http%3A%2F%2Flocalhost%3A8080%2Fcallback&response_type=code&state=randomstatee2e`;
    
    // 1. Visit Login page with SSO query parameters
    await page.goto(`/login?${queryParams}`);
    await page.waitForLoadState('networkidle');

    // 2. Click "Register here" link
    const registerLink = page.locator('#register-link');
    await expect(registerLink).toBeVisible();
    
    // Assert the href includes the query parameters
    const href = await registerLink.getAttribute('href');
    expect(href).toContain(`client_id=${clientId}`);
    expect(href).toContain('randomstatee2e');

    await registerLink.click();
    await page.waitForURL(url => url.pathname === '/register');
    
    // Assert the register URL has preserved the query parameters
    expect(page.url()).toContain(`client_id=${clientId}`);

    // 3. Fill and submit registration form
    const email = `web-sso-e2e-${Date.now()}@example.com`;
    await page.fill('#name', 'Web SSO E2E User');
    await page.fill('#email', email);
    await page.fill('#password', 'password123');
    await page.fill('#password_confirmation', 'password123');

    await page.click('#submit-register');

    // 4. Assert it redirects to /oauth/authorize with parameters
    // Because they are logged in but not yet approved for the client app,
    // the AuthorizationController/CheckClientAccess middleware will throw a 403 or redirect
    // Let's wait for the URL to change to /oauth/authorize
    await page.waitForURL(url => url.pathname === '/oauth/authorize');
    expect(page.url()).toContain(`client_id=${clientId}`);
    
    // Since CheckClientAccess will show 403 "Your account is not approved or is suspended for this application."
    await expect(page.locator('body')).toContainText('not approved or is suspended');

    // Clean up
    await context.clearCookies();
  });
});
