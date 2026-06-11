# Integrate SSO Client with `madeena-iam`

Connect this company profile app to the central `madeena-iam` identity provider using OAuth2 (authorization code grant), replacing the local email/password login entirely.

## Design Decisions (Agreed)

| Decision | Choice |
|---|---|
| Login location | Filament admin panel only (`/admin/login`) |
| Auth strategy | **SSO-only** — no local password form |
| OAuth client | `socialiteproviders/laravelpassport` |
| User mapping | Find-or-create by email, store IAM `id` in `sso_id` column |
| Default role | `role = 'user'`, `is_admin = false` |
| Login UX | Auto-redirect to IAM (no intermediate button page) |
| Silent re-auth | Yes, try `prompt=none` first, fall back to full login |
| Logout | Local session only — IAM session stays alive |
| Registration | **Remove** — managed centrally in IAM |
| Token storage | **No** — don't persist OAuth tokens |
| Password column | Make **nullable** for SSO-created users |
| IAM Link API | **Call on login**, log errors but proceed |

---

## Proposed Changes

### Package Installation

#### Install `socialiteproviders/laravelpassport`

```bash
composer require socialiteproviders/laravelpassport
```

This pulls in `laravel/socialite` as a dependency automatically.

---

### Configuration

#### [MODIFY] [services.php](file:///var/www/madeena-company-profile/config/services.php)

Add the `laravelpassport` provider configuration block:

```php
'laravelpassport' => [
    'client_id' => env('MADEENA_IAM_CLIENT_ID'),
    'client_secret' => env('MADEENA_IAM_CLIENT_SECRET'),
    'redirect' => env('MADEENA_IAM_REDIRECT_URI'),
    'host' => env('MADEENA_IAM_URL'),
    'authorize_uri' => 'oauth/authorize',
    'token_uri' => 'oauth/token',
    'userinfo_uri' => 'api/v1/user',
],
```

#### [MODIFY] [.env.example](file:///var/www/madeena-company-profile/.env.example)

Add SSO environment variables:

```env
# ===================================================================
# MADEENA IAM SSO CONFIGURATION
# OAuth2 credentials from the central identity provider.
# ===================================================================
MADEENA_IAM_URL=https://sso.mhcsgo.cloud
MADEENA_IAM_CLIENT_ID=
MADEENA_IAM_CLIENT_SECRET=
MADEENA_IAM_REDIRECT_URI="${APP_URL}/sso/callback"
```

---

### Event Service Provider

#### [NEW] [SocialiteServiceProvider.php](file:///var/www/madeena-company-profile/app/Providers/SocialiteServiceProvider.php)

Register the `socialiteproviders/laravelpassport` event listener. The `SocialiteProviders` ecosystem uses Laravel's event system to extend Socialite:

```php
// Listen for SocialiteWasCalled event -> boot the LaravelPassport extender
```

> [!NOTE]
> This is the standard pattern required by all `socialiteproviders/*` packages.

---

### Database Migration

#### [NEW] `database/migrations/xxxx_add_sso_fields_to_users_table.php`

- Add `sso_id` column (string, nullable, unique) — stores the IAM user ID
- Make `password` column nullable — SSO users don't have local passwords

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('sso_id')->nullable()->unique()->after('id');
    $table->string('password')->nullable()->change();
});
```

---

### SSO Controller & Routes

#### [NEW] [SsoController.php](file:///var/www/madeena-company-profile/app/Http/Controllers/SsoController.php)

Three methods:

1. **`redirect()`** — Initiates the OAuth flow by redirecting to `madeena-iam`
2. **`silentRedirect()`** — Same as redirect but appends `prompt=none` for silent re-auth
3. **`callback()`** — Handles the OAuth callback:
   - If `error=login_required` (from `prompt=none` failure) → redirect to full login
   - If `error=access_denied` → show error page
   - Otherwise: retrieve user from IAM, find-or-create local user by email, set `sso_id`
   - Call the central IAM Link API (`PATCH /api/v1/client-user/link`) using the user's access token to establish bidirectional mapping. If this fails, log the error but proceed with login.
   - Log the user in locally, redirect to `/admin`

#### [MODIFY] [web.php](file:///var/www/madeena-company-profile/routes/web.php)

Add SSO routes (outside Filament middleware):

```php
Route::prefix('sso')->group(function () {
    Route::get('/redirect', [SsoController::class, 'redirect'])->name('sso.redirect');
    Route::get('/silent', [SsoController::class, 'silentRedirect'])->name('sso.silent');
    Route::get('/callback', [SsoController::class, 'callback'])->name('sso.callback');
});
```

---

### Filament Login Page Override

#### [NEW] [SsoLogin.php](file:///var/www/madeena-company-profile/app/Filament/Pages/Auth/SsoLogin.php)

A custom Filament login page that:
- On `mount()`: immediately redirects to `sso.silent` (tries `prompt=none` first)
- Has **no form** — the page is never actually rendered for the user (they get redirected instantly)
- If for some reason the page renders (JS disabled, etc.), shows a "Redirecting to SSO..." message with a manual link

#### [MODIFY] [AdminPanelProvider.php](file:///var/www/madeena-company-profile/app/Providers/Filament/AdminPanelProvider.php)

- Replace `->login()` with `->login(SsoLogin::class)`
- Remove `->registration(Register::class)` — registration is disabled

---

### User Model Update

#### [MODIFY] [User.php](file:///var/www/madeena-company-profile/app/Models/User.php)

- Add `sso_id` to `$fillable`
- Keep `password` in `$fillable` but it will be nullable now

---

## Verification Plan

### Manual Verification

1. **Run migration**: `php artisan migrate` — confirm `sso_id` column is added and `password` is nullable
2. **Visit `/admin`** — confirm unauthenticated user is redirected to `madeena-iam`
3. **Complete SSO login** — confirm callback creates/updates local user and redirects to `/admin` dashboard
4. **Visit `/admin/register`** — confirm it returns 404 or redirects
5. **Logout and revisit `/admin`** — confirm silent re-auth works if IAM session is still active
6. **Check `users` table** — confirm `sso_id` is populated for the logged-in user

### Code Quality

- Run `./vendor/bin/pint` to verify PSR-12 compliance
- Confirm no hardcoded URLs or secrets in the codebase
