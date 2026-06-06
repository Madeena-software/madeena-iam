# Task: Device Session Tracking & Remote Revocation

## 1. Context
- **Database Table**: `sessions` (defined in [create_users_table.php](file:///var/www/madeena-iam/database/migrations/0001_01_01_000000_create_users_table.php#L34))
- **Active Rules**: [project-context.md](file:///var/www/madeena-iam/.ai/rules/project-context.md)
- **PRD Reference**: [madeena_iam_prd.md](file:///var/www/madeena-iam/docs/madeena_iam_prd.md#L191)

The database table `sessions` is present in the migrations. However, there are no endpoints, models, or UIs to list a user's active device sessions or allow the user or admin to remotely revoke them.

---

## 2. Objective
Implement active session tracking and session revocation endpoints (or dashboard views) so that users and admins can view active logged-in devices/browsers and remotely terminate them.

---

## 3. Role
Senior Fullstack Engineer

---

## 4. Expectations & Requirements

### Implementation Steps
1. **List Device Sessions**:
   - Create an API endpoint (e.g., `GET /api/v1/sessions`) that returns a list of active sessions for the authenticated user.
   - Parse the `user_agent` column in the session database records to format human-readable device and browser descriptions (e.g. using a simple regex-based parser or installing a package like `jenssegers/agent`).
   - Identify the current session in the list.
2. **Revoke Sessions**:
   - Create an API endpoint (e.g., `DELETE /api/v1/sessions/{id}`) to terminate a specific session.
   - Ensure a user can only revoke their own sessions.
   - Deleting a session record in the database must invalidate that browser's session, logging them out.
3. **Automated Verification**:
   - Create a feature test file `tests/Feature/DeviceSessionTest.php`.
   - Write tests verifying:
     - Listing active sessions correctly parses details.
     - A user cannot list or delete another user's sessions.
     - Deleting a session successfully signs out the targeted device.
4. **Code Quality**:
   - Follow strict typing (`declare(strict_types=1);`).
   - Run Laravel Pint (`./vendor/bin/pint`) to auto-format files.
