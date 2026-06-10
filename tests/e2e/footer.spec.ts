import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

test('global sticky footer visibility and correct version info', async ({ page }) => {
  // Read expected version from VERSION file
  const versionPath = path.resolve(process.cwd(), 'VERSION');
  let rawVersion = '1.0.0';
  if (fs.existsSync(versionPath)) {
    rawVersion = fs.readFileSync(versionPath, 'utf8').trim().replace(/^[vV]/, '');
  }

  // 1. Visit Login Page and check footer using explicit IP to avoid IPv6 localhost resolution issues
  await page.goto('http://127.0.0.1:8000/login');
  await page.waitForLoadState('networkidle');

  // Verify the footer is visible and has dynamic version
  const loginFooter = page.getByText('Madeena. All rights reserved.').first();
  await expect(loginFooter).toBeVisible();
  await expect(loginFooter).toContainText(`v${rawVersion}`);

  // 2. Login as admin and check footer in Filament Admin panel
  await page.goto('http://127.0.0.1:8000/admin/login');
  await page.waitForLoadState('networkidle');
  await page.fill('input[type="email"]', 'admin@madeena.local');
  await page.fill('input[type="password"]', 'admin');
  await page.keyboard.press('Enter');
  await page.waitForURL(url => url.pathname.startsWith('/admin') && !url.pathname.includes('/login'));
  await page.waitForLoadState('networkidle');

  // Verify Filament page has the sticky footer
  const filamentFooter = page.getByText('Madeena. All rights reserved.').first();
  await expect(filamentFooter).toBeVisible();
  await expect(filamentFooter).toContainText(`v${rawVersion}`);
});
