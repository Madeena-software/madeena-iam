# Task Prompt: Browser End-to-End Testing

Use this prompt to execute a comprehensive, browser-based end-to-end (E2E) verification of the Madeena IAM central authentication system and its Filament Admin Panel flows.

---

## 1. Context & Setup
- **Active State**: [.ai/memory/state.md](file:///var/www/madeena-iam/.ai/memory/state.md)
- **Project Context**: [.ai/rules/project-context.md](file:///var/www/madeena-iam/.ai/rules/project-context.md)
- **PRD**: [docs/madeena_iam_prd.md](file:///var/www/madeena-iam/docs/madeena_iam_prd.md)
- **Admin Panel Access**: `http://localhost:8000/admin`
- **Default Admin Credentials**:
  - Email: `admin@madeena.local` (or value of `SUPER_ADMIN_EMAIL`)
  - Password: `admin` (or value of `SUPER_ADMIN_PASSWORD`)

---

## 2. Objective
Validate key user and admin journeys using the `browser_subagent` to ensure that:
1. The Filament Admin Panel is fully operational and has no rendering or interaction errors.
2. Admins can successfully register new OAuth applications (clients) and view credentials.
3. Admins can register new users, configure their roles, and provision access permissions via the Client-User pivot.
4. Users can authenticate through the standard web login interface.
5. System interactions are correctly captured in the audit logs (Authentication Logs).

---

## 3. Step-by-Step Instructions

### Step 1: Pre-flight Environment Verification
Before invoking the browser agent, verify that the local hybrid application stack is active and healthy:
1. Ensure the MySQL Docker database container is running:
   ```bash
   docker compose -f docker-compose.local.yml up -d
   ```
2. Check if the local development server is running and listening on `http://localhost:8000`. If not, start it in the background:
   ```bash
   php artisan serve --port=8000
   ```
3. Ensure assets are compiled:
   ```bash
   npm run build
   ```

### Step 2: Initialize Browser Subagent
Invoke the `browser_subagent` tool to perform the E2E verification. Specify `RecordingName` (e.g. `iam_e2e_flow_verification`) to capture a WebP video of the entire run.

### Step 3: Admin Authentication & Dashboard Verification
Instruct the subagent to:
1. Navigate to the Admin login page: `http://localhost:8000/admin/login`.
2. Fill in the Super Admin credentials (`admin@madeena.local` / `admin`).
3. Click "Sign in".
4. Assert that the dashboard loads successfully, showing the "Account Widget" and basic admin interface elements without errors.

### Step 4: OAuth Client Registration
Instruct the subagent to:
1. Locate and click on the "OAuth Clients" resource link in the navigation sidebar (resolves to `/admin/oauth-clients`).
2. Click the "Create" button.
3. Fill in the form components:
   - **Name**: `E2E Integration App`
   - **Redirect URIs**: `http://localhost:8080/callback`
   - **Grant Types**: Select or input `password,authorization_code`
   - **Is Active**: Toggle to `Yes`
4. Click the "Create" action button.
5. Verify the success notification appears.
6. Verify that the system generated a Client ID (UUID) and Secret, and that these are displayed correctly in the interface. Note down the Client ID.

### Step 5: User Management & Access Provisioning
Instruct the subagent to:
1. Locate and click on the "Users" resource link in the navigation sidebar (resolves to `/admin/users`).
2. Click the "Create" button.
3. Fill in the form components:
   - **Name**: `E2E Test User`
   - **Email address**: `e2e.user@madeena.local`
   - **Password**: `E2EUserSecurePassword123`
4. Click the "Create" action button to save the user.
5. Once created, navigate to the user's Edit page (or continue from the create success page if it redirects to Edit).
6. Locate the **Clients** relation manager/section at the bottom of the page.
7. Click the **Attach** button to assign application access:
   - Select the newly created `E2E Integration App` client.
   - Set the pivot status field to `Approved` (`approved`).
   - Ensure "Is Blocked" is toggled to `No`.
8. Click "Attach" or the submit button to save the relationship.
9. Verify that the client is listed under the user's clients with status badge "Approved".

### Step 6: End-User Authentication Verification
Instruct the subagent to:
1. Sign out of the admin panel (or open a clean/incognito session).
2. Navigate to the user login portal: `http://localhost:8000/login`.
3. Input the newly created user's credentials: `e2e.user@madeena.local` / `E2EUserSecurePassword123`.
4. Click the "Login" button.
5. Verify that authentication succeeds. (Note: Accessing `/` directly might result in a 403 abort, but verify that no credentials error is displayed and the browser session is established).

### Step 7: Audit Log Verification
Instruct the subagent to:
1. Re-authenticate as the Super Admin at `http://localhost:8000/admin/login`.
2. Navigate to the "Authentication Logs" resource in the navigation sidebar.
3. Verify that the table contains records for the recent login activities:
   - An entry for `e2e.user@madeena.local` with status `success`.
   - Appropriate user agent and IP address mapping.

---

## 4. Deliverable Format
Produce an E2E verification report as a Markdown artifact (`e2e_browser_test_results.md` in the artifacts folder) containing:
1. **Execution Status**: Narrative summary of whether all steps completed successfully.
2. **Recorded Session Details**: Path to the generated WebP browser recording animation in the artifacts directory.
3. **Validation Summary Matrix**: A table mapping each step (Setup, Admin Login, Client Registration, User Access Provisioning, User Authentication, Audit Logging) to its result (`Passed` / `Failed`).
4. **Observed Anomalies**: Any visual bugs, console errors, or performance lags caught during browser interaction.
