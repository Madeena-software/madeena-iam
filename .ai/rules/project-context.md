# Project Context: Madeena IAM

## 1. Project Overview & Purpose

**Madeena IAM** (Identity and Access Management) is the central authentication, authorization, and audit log engine for the Madeena enterprise suite (including `simama` portal, `madeena-workspace`, `madeena-erp`, and `madeena-it`). Powered by Laravel 13, Filament v5, and Laravel Passport, it serves as the single source of truth for user credentials, profiles, sessions, and access permissions across the ecosystem.

### Database Isolation Rule
To maintain absolute security, security audits, and architectural independence, `madeena-iam` **MUST** run on a completely separate database server instance, sharing zero tables or credentials with client applications.

---

## 2. Key Features & Flows

- **Centralized Single Sign-On (SSO - Primary)**: Redirect-based OAuth2/OIDC Authorization Code Grant (`/oauth/authorize` and `/oauth/token`). Users log in once and gain seamless access.
- **SSO Silent Session Sync (`prompt=none`)**: Supports standard `prompt=none` redirect logic so client apps can silently verify if a user has an active session cookie on `sso.mhcsgo.cloud` and sign them in.
- **Alternative Direct API Login (Secondary)**:
  - `POST /api/v1/auth/login`: Direct server-to-server credentials verification (validates credentials, client permissions, status = `approved`) returning a Personal Access Token.
  - `POST /api/v1/auth/register`: Adds user in `pending_approval` status, requiring admin validation, and queues a "New User Registration" email notification to all super_admin users.
- **Granular Access Control**: Direct assignment or revocation of application access for users from a central control panel.
- **Admin Configuration**: App registry configuration and permission mappings (via Filament Admin Panel on `sso.mhcsgo.cloud`).
- **Audit Trails**: Logs all logins, logouts, IPs, user agents, accessed clients, and status (`success`, `failed_password`, `blocked_app`).
- **Remote Session Revocation**: Allows users and admins to remotely view and terminate active device sessions.

---

## 3. Setup Instructions (Local Hybrid Environment)

Madeena IAM uses a **hybrid development architecture** to maximize WSL host performance:
- PHP, Composer, and NPM packages run directly on the host system.
- Infrastructure (MySQL database) runs isolated inside Docker.

### Running Setup
```bash
# Provision application, copy environment variables, build assets, and run migrations
./deploy-local.sh

# To reset database and reseed from scratch
./deploy-local.sh --fresh

# To setup without starting the development server
./deploy-local.sh --no-start
```

### Dev Commands
- **Start Dev Server**: `composer dev` (starts PHP server, Vite, queue listener, and Laravel Pail concurrently).
- **Run Tests**: `composer test`.
- **Formatting**: `./vendor/bin/pint`.
- **Stop Database**: `docker compose -f docker-compose.local.yml down`.

---

## 4. Environment Variables & Configuration

Below are the primary environment variables configured in `.env.example` and `.env.local`:

| Variable | Description |
|---|---|
| `APP_NAME` | Name of the application (Default: `"Madeena IAM"`) |
| `APP_ENV` | Application environment (Default: `local` / `production`) |
| `APP_KEY` | Laravel application encryption key |
| `APP_DEBUG` | Enable debug logs / details (Default: `false` in local env.local for production parity simulation) |
| `APP_URL` | Base application URL (Default: `http://localhost:8000`) |
| `DB_CONNECTION` | Database driver (Default: `mysql`) |
| `DB_HOST` | Database host (Default: `127.0.0.1`) |
| `DB_PORT` | Database port (Default: `3310` for local MySQL container) |
| `DB_DATABASE` | Database name (Default: `madeena-iam`) |
| `DB_USERNAME` | Database user (Default: `madeena-iam`) |
| `DB_PASSWORD` | Database password (Default: `secret`) |
| `QUEUE_CONNECTION` | Queue connection driver (Default: `database`) |
| `SESSION_DRIVER` | Session storage driver (Default: `database`) |
| `CACHE_STORE` | Cache driver (Default: `database`) |
| `DATA_DRIVE_PATH` | Path for custom enterprise file storage (Default: `storage/enterprise_data_local`) |
| `SUPER_ADMIN_EMAIL` | Default administrator account email |
| `SUPER_ADMIN_PASSWORD` | Default administrator account password |

---

## 5. Repository Structure Mapping

```
├── app/
│   ├── Enums/                 # Domain-specific enumerations
│   ├── Filament/              # Filament Admin resources and page configurations
│   │   └── Resources/
│   │       ├── Activities/          # Spatie Activitylog UI Resources
│   │       ├── AuthenticationLogs/  # User Authentication Logs UI
│   │       ├── OauthClients/        # Passport OAuth Client UI
│   │       └── Users/               # User administration
│   ├── Http/                  # HTTP Controllers, Requests, Middleware
│   ├── Listeners/             # Event Listeners (e.g., LogSuccessfulLogout)
│   ├── Models/                # Eloquent Database Models
│   ├── Policies/              # Authorization/Permission Policies
│   └── Providers/             # Laravel Service Providers
├── bootstrap/                 # Framework bootstrapping and cache
├── config/                    # Framework configuration files
├── database/                  # Migrations, Seeders, Factories
├── docker-compose.local.yml   # Local MySQL 8.4 service definition
├── deploy-local.sh            # Setup and orchestration utility script
├── public/                    # Built web assets and entrypoints
├── resources/                 # Blade views, raw CSS, JS source code
├── routes/                    # Web and API routing files
├── tests/                     # Automated testing suite
│   ├── Feature/               # Endpoint and integration tests
│   └── Unit/                  # Isolated logic/unit tests
└── vite.config.js             # Asset compiler config (Vite 8)
```

---

## 6. Database Schema Design

### `users`
* `id` (UUID, Primary Key)
* `name` (string)
* `email` (string, unique)
* `password` (string)
* `remember_token` (string, nullable)
* `created_by` / `updated_by` / `deleted_by` (UUID, nullable)
* `created_at` / `updated_at` / `deleted_at` (timestamp, nullable)

### `oauth_clients` (Managed by Laravel Passport / Custom attributes)
* `id` (UUID, Primary Key)
* `name` (string)
* `secret` (string, nullable)
* `provider` (string, nullable)
* `redirect_uris` (text)
* `grant_types` (text)
* `revoked` (boolean)
* `app_logo_path` (string, nullable)
* `description` (string, nullable)
* `is_active` (boolean, default true)
* `owner_type` / `owner_id` (string/bigint, nullable morphs)
* `created_by` / `updated_by` / `deleted_by` (UUID, nullable)
* `created_at` / `updated_at` / `deleted_at` (timestamp, nullable)

### `client_user` (Pivot Table)
Defines user access rules to apps.
* `id` (bigint, Primary Key)
* `user_id` (foreignUuid to `users`)
* `client_id` (foreignUuid to `oauth_clients`)
* `client_app_user_id` (string, nullable)
* `status` (enum: `'pending_approval'`, `'approved'`, `'suspended'`, default: `'pending_approval'`)
* `approved_at` (timestamp, nullable)
* `approved_by` (foreignUuid to `users`, nullable)
* `is_blocked` (boolean, default false)
* `created_by` / `updated_by` / `deleted_by` (UUID, nullable)
* `created_at` / `updated_at` / `deleted_at` (timestamp, nullable)

### `authentication_logs` (Audit trail logs)
* `id` (bigint, Primary Key)
* `authenticatable_id` (UUID, nullable polymorphic relation to `users`)
* `authenticatable_type` (string, nullable polymorphic relation to `users`)
* `client_id` (foreignUuid to `oauth_clients`, nullable)
* `ip_address` (string, 45, nullable)
* `user_agent` (text, nullable)
* `login_at` (timestamp, nullable)
* `logout_at` (timestamp, nullable)
* `login_successful` (boolean, default false)
* `cleared_by_user` (boolean, default false)
* `location` (json, nullable)
* `status` (string, nullable: e.g. `'success'`, `'failed_password'`, `'blocked_app'`, `'invalid_client'`)
* `auth_type` (string, nullable: e.g. `'password'`, `'google'`)

---

## 7. General Coding Conventions

- **PHP Standards**: Adhere to PSR-12 guidelines. Code must be auto-formatted using Pint (`./vendor/bin/pint`) before finishing a task.
- **Strict Typing**: Declare `declare(strict_types=1);` on all newly created classes/controllers.
- **Models**: Explicitly declare types for relations and database properties/attributes. Use mass assignment protections (`$fillable` or `$guarded`).
- **Security**: Never expose auto-increment database IDs in URLs; use UUIDs or slugs. Utilize Laravel's query binding mechanisms to protect against SQL injections.
- **Thin Controllers**: Delegate business logic to services, actions, or Eloquent models. Keep Http controllers/invokables clean.
