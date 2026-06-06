# Project Context: Madeena IAM

## 1. Project Overview & Purpose

**Madeena IAM** (Identity and Access Management) is the central authentication, authorization, and audit log engine for the Madeena enterprise suite (including `simama` portal, `madeena-workspace`, `madeena-erp`, and `madeena-it`). Powered by Laravel 13, Filament v5, and Laravel Passport, it serves as the single source of truth for user credentials, profiles, sessions, and access permissions across the ecosystem.

### Database Isolation Rule
To maintain absolute security, security audits, and architectural independence, `madeena-iam` **MUST** run on a completely separate database server instance, sharing zero tables or credentials with client applications.

---

## 2. Key Features & Flows

- **Centralized Single Sign-On (SSO)**: Users log in once and gain seamless access to authorized applications.
- **Granular Access Control**: Direct assignment or revocation of application access for users from a central control panel.
- **SSO Silent Session Sync (`prompt=none`)**: Supports standard OAuth2/OIDC `prompt=none` redirect logic so client apps can silently verify if a user has an active session cookie on `sso.mhcsgo.cloud` and sign them in automatically.
- **API-Driven Credentials Verification**:
  - `POST /api/v1/auth/login`: Direct server-to-server login check (validates credentials, client permissions, status = `approved`).
  - `POST /api/v1/auth/register`: Adds user in `pending_approval` status, requiring admin validation.
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

## 6. Proposed Database Schema Design

### `users`
* `id` (UUID)
* `name` (string)
* `email` (string, unique)
* `password` (string)
* `remember_token` (string, nullable)
* `created_at` / `updated_at`

### `oauth_clients` (Managed by Laravel Passport / Custom attributes)
* `id` (UUID)
* `name` (string)
* `secret` (string)
* `redirect` (text)
* `app_logo_path` (string, nullable)
* `description` (string, nullable)
* `is_active` (boolean, default true)

### `client_user` (Pivot Table)
Defines user access rules to apps.
* `id` (bigint, primary key)
* `user_id` (foreign key to `users`)
* `client_id` (foreign key to `oauth_clients`)
* `client_app_user_id` (string, nullable)
* `status` (string, default: `'pending_approval'` — options: `'pending_approval'`, `'approved'`, `'suspended'`)
* `approved_at` (timestamp, nullable)
* `approved_by` (UUID, nullable, reference to admin user)
* `is_blocked` (boolean, default false)
* `created_at` / `updated_at`

### `login_activities` (Audit trail logs)
* `id` (bigint, primary key)
* `user_id` (foreign key to `users`)
* `client_id` (foreign key to `oauth_clients`, nullable for portal login)
* `ip_address` (string)
* `user_agent` (text)
* `status` (string: e.g., `'success'`, `'failed_password'`, `'blocked_app'`)
* `created_at` (timestamp)

---

## 7. General Coding Conventions

- **PHP Standards**: Adhere to PSR-12 guidelines. Code must be auto-formatted using Pint (`./vendor/bin/pint`) before finishing a task.
- **Strict Typing**: Declare `declare(strict_types=1);` on all newly created classes/controllers.
- **Models**: Explicitly declare types for relations and database properties/attributes. Use mass assignment protections (`$fillable` or `$guarded`).
- **Security**: Never expose auto-increment database IDs in URLs; use UUIDs or slugs. Utilize Laravel's query binding mechanisms to protect against SQL injections.
- **Thin Controllers**: Delegate business logic to services, actions, or Eloquent models. Keep Http controllers/invokables clean.
