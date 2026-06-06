# Task: Audit Log Alignment & Compliance

## 1. Context
- **Migration**: [create_authentication_logs_table.php](file:///var/www/madeena-iam/database/migrations/2026_06_06_102435_create_authentication_logs_table.php)
- **Model**: [AuthenticationLog.php](file:///var/www/madeena-iam/app/Models/AuthenticationLog.php)
- **Listener**: [LogSuccessfulLogin.php](file:///var/www/madeena-iam/app/Listeners/LogSuccessfulLogin.php)
- **PRD Reference**: [madeena_iam_prd.md](file:///var/www/madeena-iam/docs/madeena_iam_prd.md#L193)

The PRD calls for a `login_activities` table recording user logins and failures including `client_id`, `status` (success, failed_password, blocked_app), `auth_type`, and location. The current `authentication_logs` polymorphic logging implementation does not log client IDs, failed logins, or authentication types.

---

## 2. Objective
Extend the polymorphic `AuthenticationLog` table and create the necessary listeners and hooks to log all login successes and failures, including client ID, status, and authentication type.

---

## 3. Role
Senior Fullstack Engineer

---

## 4. Expectations & Requirements

### Implementation Steps
1. **Database Schema Update**:
   - Create a migration to add `client_id` (foreignUuid, nullable, constrained to `oauth_clients`), `status` (string/enum), and `auth_type` (string/enum) columns to the `authentication_logs` table.
2. **Log Successful Logins with Client Context**:
   - Update [LogSuccessfulLogin.php](file:///var/www/madeena-iam/app/Listeners/LogSuccessfulLogin.php) to capture the `client_id` from the request when authenticating via `POST /api/v1/auth/login` or standard OIDC authorize redirection.
   - Record the `status` as `success` and `auth_type` as `password` (or other auth types when used).
3. **Log Failed Login Attempts**:
   - Register an event listener for `Illuminate\Auth\Events\Failed`.
   - Manually log failed requests in the API `AuthController::login` endpoint (e.g. invalid password, unauthorized client, blocked user status).
   - Log the matching user agent, IP address, status (`failed_password`, `blocked_app`, `invalid_client`), and the targeted `client_id`.
4. **GeoIP / Location**:
   - Scaffolding or configuration for location tracking. You may mock a helper that inspects the IP and resolves it to a placeholder/mock location payload in tests and dev environments.
5. **Automated Verification**:
   - Create a feature test file `tests/Feature/AuditLogTest.php`.
   - Verify that:
     - A successful login creates an `AuthenticationLog` record with the correct user ID, client ID, and `success` status.
     - A failed password login creates a log record with the `failed_password` status.
     - Accessing a blocked application creates a log record with the `blocked_app` status.
6. **Code Quality**:
   - Follow strict typing (`declare(strict_types=1);`).
   - Run Laravel Pint (`./vendor/bin/pint`) to auto-format files.
