# Task Prompt: Create OAuth Client Resource & Form Redesign

Use this prompt to execute the refactoring and redesign of the OAuth Client creation and management form in the Filament Admin Panel.

---

## 1. Context & Setup
- **Active State**: [.ai/memory/state.md](file:///var/www/madeena-iam/.ai/memory/state.md)
- **Target File**: [OauthClientForm.php](file:///var/www/madeena-iam/app/Filament/Resources/OauthClients/Schemas/OauthClientForm.php)
- **Related Files**:
  - Model: [OauthClient.php](file:///var/www/madeena-iam/app/Models/OauthClient.php)
  - Creation Page: [CreateOauthClient.php](file:///var/www/madeena-iam/app/Filament/Resources/OauthClients/Pages/CreateOauthClient.php)

---

## 2. Objective
Redesign the **Create OAuth Client** page in the Filament Admin Panel to simplify user inputs, automate credential generation, enable S3 logo uploads, and hide system, credential, and audit fields from the creation view.

---

## 3. Detailed Refactoring Specifications

### Step 1: Hide Client Credentials on Create Page
1. Modify the `id` (Client ID) component:
   - Hide it entirely on the **Create Oauth Client** page (e.g. using `hiddenOn('create')`).
2. Modify the `secret` (Client Secret) component:
   - Hide it entirely on the **Create Oauth Client** page (e.g. using `hiddenOn('create')`).

### Step 2: Remove Ownership & Provider Fields
1. Remove the following form components entirely:
   - `owner_type`
   - `owner_id`
   - `provider`
2. Ensure they are either set to default values in database migrations or handled on model creation (the provider column should automatically default to `users` on saving).

### Step 3: Refactor Grant Types Input
1. Replace the raw textarea for `grant_types` with a checkbox group/list.
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
1. Replace the plain `TextInput` for `app_logo_path` with a Filament `FileUpload` component.
2. Configure the component to store files using the configured S3 gateway/storage disk.

### Step 5: Remove Audit Logs from Form
1. Remove these fields from the form components list:
   - `created_by`
   - `updated_by`
   - `deleted_by`
2. Confirm these remain automatically populated in background hooks (`OauthClient::booted()`).

---

## 4. Verification & Validation

All verification must follow the automated **Test Pyramid** strategy defined in [.ai/rules/testing-pyramid.md](file:///var/www/madeena-iam/.ai/rules/testing-pyramid.md). Manual visual testing is prohibited.

### A. Static Analysis & Build
- Ensure the code parses correctly and has no syntax or PHP compilation errors.
- Run Laravel Pint to check formatting.

### B. Automated Middle-Layer: Feature / Integration Tests (PHPUnit)
- Write or update feature tests (e.g. `tests/Feature/FilamentResourceTest.php` or a new `tests/Feature/OauthClientsFormTest.php`) using PHPUnit to assert:
  - The `OauthClientForm` form schema configuration does not display `id`, `secret`, `owner_type`, `owner_id`, `provider`, and audit logs on the create view.
  - The form correctly renders and handles validation rules for `name`, `redirect_uris`, and the array cast for `grant_types`.
  - Creating a client via the controller/livewire component correctly triggers observers to populate `created_by`, `id`, and `secret` in the database.

### C. Automated Top-Layer: E2E Tests (Playwright)
- Set up Playwright E2E automation in the project:
  - Install Playwright dependency: `npm install -D @playwright/test` and install browser binaries: `npx playwright install`.
  - Initialize/update a standard `playwright.config.ts` configuration.
  - Create a new E2E test script at `tests/e2e/oauth-clients.spec.ts`.
- The E2E test script must automate the following steps:
  1. Boot the test application or use the local dev server at `http://localhost:8000`.
  2. Navigate to the Admin login page (`/admin/login`) and authenticate using super admin credentials.
  3. Navigate to the Create OAuth Client page (`/admin/oauth-clients/create`).
  4. Assert that the form elements for `id` (Client ID), `secret` (Client Secret), `owner_type`, `owner_id`, `provider`, and audit controls (`created_by`, etc.) are hidden.
  5. Assert that the form elements for `name`, `redirect_uris`, `grant_types` (checkbox group with descriptions), and `app_logo_path` (file upload) are visible.
  6. Fill in the form: Name `E2E Test Client`, Redirect URIs `http://localhost:8080/callback`, check standard grant type options, and upload a test image to the app logo component.
  7. Click Create/Submit, verify that the system issues a successful credential notification, and assert that the generated ID and secret are visible in the notification body.
- Execute the test suite using `npx playwright test` and ensure all assertions pass.

