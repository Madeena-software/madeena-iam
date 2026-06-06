# Task: Filament Resources & User Onboarding Enhancements

## 1. Context
- **Filament Resources**: [UserResource.php](file:///var/www/madeena-iam/app/Filament/Resources/Users/UserResource.php) and [OauthClientResource.php](file:///var/www/madeena-iam/app/Filament/Resources/OauthClients/OauthClientResource.php)
- **Active Rules**: [laravel-filament.md](file:///var/www/madeena-iam/.ai/rules/laravel-filament.md)
- **PRD Reference**: [madeena_iam_prd.md](file:///var/www/madeena-iam/docs/madeena_iam_prd.md#L185)

Admin flows require registering OAuth clients and direct user registration in the admin dashboard. The current Filament pages have input flaws (such as requiring a password on user edit) and lack auto-generation of secure client secrets and UUIDs, as well as onboarding/approval emails.

---

## 2. Objective
Enhance Filament CRUD schemas, add client ID and secret auto-generation on creation, allow editing users without forcing password re-entry, and support automatic onboarding/approval email dispatching.

---

## 3. Role
Senior Fullstack Engineer

---

## 4. Expectations & Requirements

### Implementation Steps
1. **OAuth Client CRUD Upgrades**:
   - Update `CreateOauthClient` page or the `OauthClientForm` schema.
   - If not provided, auto-generate a UUID for the client `id` and a random 40-character secure string for the client `secret`.
   - Store the hashed client `secret` in the database, but display the raw unhashed secret to the administrator **only once** upon creation (e.g. using a custom notification, modal, or custom page redirect).
2. **User Management Form Updates**:
   - Modify the `password` field in [UserForm.php](file:///var/www/madeena-iam/app/Filament/Resources/Users/Schemas/UserForm.php) to be optional on edit (use `dehydrated(fn ($state) => filled($state))` or equivalent).
3. **Onboarding & Approval Mail**:
   - Create a Mailable class for account onboarding and approvals.
   - When a user is directly created in Filament (or when an admin updates a client pivot status to `approved`), dispatch an onboarding notification/email to let the user set their password.
4. **Automated Verification**:
   - Create a test `tests/Feature/FilamentResourceTest.php` to verify:
     - Creating an OAuth client auto-generates credentials and properly hashes the secret.
     - Updating a user without a password field leaves the old password intact.
     - Onboarding email is queued/dispatched on creation or status approval.
5. **Code Quality**:
   - Follow strict typing (`declare(strict_types=1);`).
   - Run Laravel Pint (`./vendor/bin/pint`) to auto-format files.
