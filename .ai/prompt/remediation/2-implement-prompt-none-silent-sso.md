# Task: Implement prompt=none OIDC Silent SSO Sync

## 1. Context
- **Affected Route**: Laravel Passport's default authorization code grant endpoint `GET /oauth/authorize`
- **Active Rules**: [project-context.md](file:///var/www/madeena-iam/.ai/rules/project-context.md)
- **PRD Reference**: [madeena_iam_prd.md](file:///var/www/madeena-iam/docs/madeena_iam_prd.md#L95)

Standard OIDC silent sync uses a browser redirect to `/oauth/authorize` with `prompt=none`. The default Laravel Passport configuration does not handle `prompt=none` and redirects unauthenticated requests to a named `/login` route, which is currently undefined.

---

## 2. Objective
Implement the standard OIDC `prompt=none` redirect logic to silently authorize logged-in users, redirecting unauthenticated users back to the client application with `error=login_required` instead of showing a login form or throwing an exception.

---

## 3. Role
Senior Fullstack Engineer

---

## 4. Expectations & Requirements

### Implementation Steps
1. **Intercept Authorization Request**: Write a middleware or override Passport's standard `AuthorizationController` at the `oauth/authorize` endpoint.
2. **Handle `prompt=none` Query Parameter**:
   - Check if `prompt=none` is present in the request query.
   - If present:
     - **Active SSO Session Found & Access Permitted**:
       - Verify the user is authenticated in the central `web` guard.
       - Verify the user is explicitly allowed to access the requesting `client_id` (via the `client_user` pivot table status = `approved` and `is_blocked = false`).
       - If both checks pass, bypass the consent screen, generate an authorization code, and redirect back to the client's `redirect_uri` with the `code` and the client's `state`.
     - **Active SSO Session NOT Found**:
       - Immediately redirect back to the client's `redirect_uri` with query parameters `error=login_required` and `state` (forwarded from request).
     - **Access Denied / Not Approved**:
       - If authenticated but not permitted for the client app (status not approved or blocked), redirect back to the client's `redirect_uri` with `error=access_denied` and `state`.
3. **Automated Verification**:
   - Create a feature test file `tests/Feature/SsoSilentSyncTest.php`.
   - Write tests validating all three flows:
     1. Successful silent login code issuance for an authenticated, permitted user.
     2. Immediate redirect with `error=login_required` for an unauthenticated user.
     3. Immediate redirect with `error=access_denied` for an authenticated user without client permission.
4. **Code Quality**:
   - Follow strict typing (`declare(strict_types=1);`).
   - Run Laravel Pint (`./vendor/bin/pint`) to auto-format files.
