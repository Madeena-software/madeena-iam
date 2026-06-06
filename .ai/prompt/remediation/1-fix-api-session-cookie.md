# Task: Fix API Session Cookie Persistence

## 1. Context
- **Affected Route**: `POST /api/v1/auth/login` in [api.php](file:///var/www/madeena-iam/routes/api.php)
- **Controller**: [AuthController.php](file:///var/www/madeena-iam/app/Http/Controllers/Api/V1/AuthController.php)
- **Active Rule**: [project-context.md](file:///var/www/madeena-iam/.ai/rules/project-context.md)

Currently, the custom API login endpoint `POST /api/v1/auth/login` uses `Auth::guard('web')->login($user)` to establish a central session. However, because routes defined in `routes/api.php` do not use session and cookie middleware, no session cookie is written to the response, causing the SSO session to be lost immediately.

---

## 2. Objective
Enable session and cookie persistence for the API login routes so that the central SSO session cookie is successfully written to the user's browser, enabling subsequent silent SSO checks.

---

## 3. Role
Senior Fullstack Engineer

---

## 4. Expectations & Requirements

### Implementation Steps
1. **Apply Session Middleware**: Ensure that session and cookie-related middleware are applied to the `POST /api/v1/auth/login` route. You can achieve this by:
   - Moving the authentication routes to `routes/web.php` (prefixed with `api/v1/`), OR
   - Manually adding the middleware group `web` or individual middlewares (`StartSession`, `EncryptCookies`, `AddQueuedCookiesToResponse`, `ShareErrorsFromSession`) to the API login route.
2. **Verify Session Persistence**: Ensure `sso_session_id` returned in the response matches a valid, persisted session ID.
3. **Automated Verification**:
   - Update or add tests in [ApiAuthenticationTest.php](file:///var/www/madeena-iam/tests/Feature/ApiAuthenticationTest.php) to assert that a session cookie (e.g. `laravel_session`) is returned in the response headers when calling `POST /api/v1/auth/login`.
   - Verify that all existing tests in `ApiAuthenticationTest.php` continue to pass.
4. **Code Quality**:
   - Follow strict typing (`declare(strict_types=1);`).
   - Run Laravel Pint (`./vendor/bin/pint`) to auto-format files.
