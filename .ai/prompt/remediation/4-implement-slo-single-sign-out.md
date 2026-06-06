# Task: Implement Single Sign-Out (SLO)

## 1. Context
- **Active Rules**: [project-context.md](file:///var/www/madeena-iam/.ai/rules/project-context.md)
- **PRD Reference**: [madeena_iam_prd.md](file:///var/www/madeena-iam/docs/madeena_iam_prd.md#L176)

When a user logs out of any client application, the active central SSO session on the central IAM server must be destroyed. Currently, there is no SLO route, controller, or token revocation endpoint.

---

## 2. Objective
Implement Single Sign-Out (SLO) routes and logic that invalidate active OAuth tokens and destroy the central browser-session cookie.

---

## 3. Role
Senior Fullstack Engineer

---

## 4. Expectations & Requirements

### Implementation Steps
1. **Define Logout Endpoints**:
   - Register a POST `/api/v1/auth/logout` endpoint in `routes/api.php`.
   - Optionally register a GET/POST `/logout` web endpoint for browser-driven logout.
2. **Implement Revocation Logic**:
   - Revoke/invalidate the user's active Passport access tokens and refresh tokens.
   - Destroy the central browser session via the `web` auth guard (`Auth::guard('web')->logout()` and invalidate/regenerate session).
   - Log the logout event in the audit trail (updating `logout_at` on the matching `AuthenticationLog`).
3. **Automated Verification**:
   - Create `tests/Feature/SingleSignOutTest.php`.
   - Write tests checking:
     - The logout endpoint destroys the central session (assert user is guest).
     - The user's Passport tokens are revoked.
     - The logout action logs a `logout_at` timestamp in the database logs.
4. **Code Quality**:
   - Follow strict typing (`declare(strict_types=1);`).
   - Run Laravel Pint (`./vendor/bin/pint`) to auto-format files.
