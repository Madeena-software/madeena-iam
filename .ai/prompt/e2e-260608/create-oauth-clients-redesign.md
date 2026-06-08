# Task Prompt: Create OAuth Client Resource & Form Redesign

Use this prompt to execute the refactoring and redesign of the OAuth Client creation and management form in the Filament Admin Panel.

---

## 1. Context & Setup
- **Active State**: [.ai/memory/state.md](file:///var/www/madeena-iam/.ai/memory/state.md)
- **Target File**: [OauthClientForm.php](file:///var/www/madeena-iam/app/Filament/Resources/OauthClients/Schemas/OauthClientForm.php)
- **Related Files**:
  - Model: [OauthClient.php](file:///var/www/madeena-iam/app/Models/OauthClient.php)
  - Creation Page: [CreateOauthClient.php](file:///var/www/madeena-iam/app/Filament/Resources/OauthClients/Pages/CreateOauthClient.php)
  - Verification: [FilamentResourceTest.php](file:///var/www/madeena-iam/tests/Feature/FilamentResourceTest.php)

---

## 2. Objective
Redesign the **Create OAuth Client** page in the Filament Admin Panel to simplify user inputs, automate credential generation, enable S3 logo uploads, and hide system, credential, and audit fields from the creation view.

---

## 3. Detailed Refactoring Specifications

> [!IMPORTANT]
> Because `grant_types` is cast to an array in `OauthClient.php`, switching the form component to a `CheckboxList` means it will submit/store an array of values. Ensure that existing tests in `tests/Feature/FilamentResourceTest.php` that pass `grant_types` as a comma-separated string are updated to pass an array cast instead.

> [!WARNING]
> The `s3` storage disk configured in `filesystems.php` requires the AWS S3 Flysystem driver. Since it is not present in composer dependencies, you must install it via `composer require league/flysystem-aws-s3-v3` as part of Step 4.

### Step 1: Hide Client Credentials on Create Page
#### [MODIFY] [OauthClientForm.php](file:///var/www/madeena-iam/app/Filament/Resources/OauthClients/Schemas/OauthClientForm.php)
1. Modify the `id` (Client ID) component:
   - Hide it entirely on the **Create Oauth Client** page (e.g. using `hiddenOn('create')`).
2. Modify the `secret` (Client Secret) component:
   - Hide it entirely on the **Create Oauth Client** page (e.g. using `hiddenOn('create')`).

### Step 2: Remove Ownership & Provider Fields
#### [MODIFY] [OauthClientForm.php](file:///var/www/madeena-iam/app/Filament/Resources/OauthClients/Schemas/OauthClientForm.php)
1. Remove the following form components entirely:
   - `owner_type`
   - `owner_id`
   - `provider`

#### [MODIFY] [OauthClient.php](file:///var/www/madeena-iam/app/Models/OauthClient.php)
2. Ensure the `provider` column automatically defaults to `'users'` upon model creation if not specified:
   ```php
   static::creating(function ($model) {
       $model->created_by = auth()->id();
       if (empty($model->provider)) {
           $model->provider = 'users';
       }
   });
   ```

### Step 3: Refactor Grant Types Input
#### [MODIFY] [OauthClientForm.php](file:///var/www/madeena-iam/app/Filament/Resources/OauthClients/Schemas/OauthClientForm.php)
1. Replace the raw textarea for `grant_types` with a checkbox group/list (`CheckboxList::make('grant_types')`).
2. Allow the admin to check multiple options.
3. Configure the checkboxes to save as an array cast.
4. Add the following descriptions and helper text directly to the interface options:
   - **Authorization Code**: Web apps redirecting users via standard browser login.
     - *Example*: Redirects user to the central login screen, then sends them back after authentication.
     - *Use Case*: Integrated standard web applications like *Simama*, *Madeena ERP*, or *Madeena Workspace*.
   - **Refresh Token**: Allowing apps to retrieve new access tokens silently without prompting users again.
     - *Example*: Automatically refreshing session credentials in the background before they expire.
     - *Use Case*: Any client application requiring persistent login/session continuity.
   - **Client Credentials**: Machine-to-machine sync scripts (no user authentication).
     - *Example*: Server-to-server daemon using Client ID and Secret directly to get a token.
     - *Use Case*: Background cron jobs syncing databases between *Madeena IAM* and external IT tools.
   - **Password**: Trusted native or mobile applications (direct username/password exchange).
     - *Example*: User typing credentials directly into native mobile inputs rather than a web redirect.
     - *Use Case*: First-party iOS/Android native mobile applications.

### Step 4: Convert App Logo Path to File Upload
#### [MODIFY] [OauthClientForm.php](file:///var/www/madeena-iam/app/Filament/Resources/OauthClients/Schemas/OauthClientForm.php)
1. Replace the plain `TextInput` for `app_logo_path` with a Filament `FileUpload` component.
2. Configure the component to store files using the configured S3 gateway/storage disk.
3. Add a fallback or dynamic config option to fallback to `public` disk if local S3 connectivity is unavailable.

### Step 5: Remove Audit Logs from Form
#### [MODIFY] [OauthClientForm.php](file:///var/www/madeena-iam/app/Filament/Resources/OauthClients/Schemas/OauthClientForm.php)
1. Remove these fields from the form components list:
   - `created_by`
   - `updated_by`
   - `deleted_by`

---

## 4. Verification & Validation

All verification must follow the automated **Test Pyramid** strategy defined in [.ai/rules/testing-pyramid.md](file:///var/www/madeena-iam/.ai/rules/testing-pyramid.md). Manual visual testing is prohibited.

### A. Static Analysis & Build
- Ensure the code parses correctly and has no syntax or PHP compilation errors.
- Run Laravel Pint to check formatting.

### B. Automated Middle-Layer: Feature / Integration Tests (PHPUnit)
#### [MODIFY] [FilamentResourceTest.php](file:///var/www/madeena-iam/tests/Feature/FilamentResourceTest.php)
- Update PHPUnit feature tests to assert:
  - The `OauthClientForm` form schema configuration does not display `id`, `secret`, `owner_type`, `owner_id`, `provider`, and audit logs on the create view.
  - The form correctly renders and handles validation rules for `name`, `redirect_uris`, and the array cast for `grant_types`.
  - Creating a client via the controller/livewire component correctly triggers observers to populate `created_by`, `id`, and `secret` in the database.
  - Assert that `grant_types` is set and resolved as an array payload.

### C. Automated Top-Layer: E2E Tests (Playwright)
#### [NEW] [oauth-clients.spec.ts](file:///var/www/madeena-iam/tests/e2e/oauth-clients.spec.ts)
- Set up Playwright E2E automation in the project:
  - Install Playwright dependency: `npm install -D @playwright/test` and install browser binaries: `npx playwright install`.
  - Initialize/update a standard `playwright.config.ts` configuration.
  - Create a new E2E test script at `tests/e2e/oauth-clients.spec.ts`.
- The E2E test script must automate the following steps:
  1. Boot the test application or use the local dev server at `http://localhost:8000`.
  2. Ensure the database contains a seeded super admin user (`admin@madeena.local` / `admin`).
  3. Navigate to the Admin login page (`/admin/login`) and authenticate.
  4. Navigate to the Create OAuth Client page (`/admin/oauth-clients/create`).
  5. Assert that the form elements for `id`, `secret`, `owner_type`, `owner_id`, `provider`, and audit controls are hidden.
  6. Assert that the form elements for `name`, `redirect_uris`, `grant_types` (checkbox group), and `app_logo_path` (file upload) are visible.
  7. Fill in the form: Name `E2E Test Client`, Redirect URIs `http://localhost:8080/callback`, check standard grant type options.
  8. Automate file upload for the app logo by targeting the hidden file input tag:
     ```typescript
     await page.setInputFiles('input[type="file"]', 'tests/fixtures/logo.png');
     ```
  9. Click Create/Submit, verify that the system issues a successful credential notification, and assert that the generated ID and secret are visible in the notification body.
- Execute the test suite using `npx playwright test` and ensure all assertions pass.
