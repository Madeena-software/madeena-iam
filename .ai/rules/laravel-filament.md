# Laravel 13 + Filament v5 — Stack Rules & Best Practices

> Mandatory coding rules, architectural constraints, and security recommendations.

---

## 1. Laravel Architecture Rules

### Route Definitions
- Define all web routes in `routes/web.php`. Use named routes exclusively.
- Use **implicit model route binding** (`{model:slug}`) — never pass raw IDs in public URLs.
- Group related routes with `Route::prefix()` and `->name()` chains.
- API routes (if added) go in `routes/api.php` with `api` middleware.

### Controllers
- Keep controllers **thin** — delegate business logic to Models, Services, or Actions.
- Use **single-action invokable controllers** for simple endpoints (e.g., `PublicStorageController`).
- Group feature-specific controllers in subdirectories (e.g., `Inabuyer2026/FeedbackController`).

### Models (Eloquent)
- Always define `$fillable` or `$guarded` — never leave mass-assignment unprotected.
- Use typed properties and casts via `$casts` array (Laravel 13 attribute casting).
- Define relationships explicitly as methods with return type declarations.
- Use query scopes for reusable query constraints.
- Never expose auto-increment IDs in URLs — use slugs or UUIDs.

### Service Providers
- Register bindings in `AppServiceProvider::register()`.
- Boot logic (event listeners, observers, macros) goes in `AppServiceProvider::boot()`.
- Create dedicated providers only for complex subsystems (e.g., `WebDavFilesystemServiceProvider`).

### Migrations & Database
- Never modify an existing migration that has been committed. Create a new migration instead.
- Always include `->down()` method for reversibility.
- Use `constrained()->cascadeOnDelete()` for foreign keys unless soft-delete behavior is needed.
- Database-level defaults should match model `$attributes` defaults.
- Migration Cleanliness: When adding fields, do not generate a brand-new migration file if the feature branch is still local and unmerged. Modify the existing local migration instead to avoid migration bloat. If merged to main, a new migration is mandatory.

### Configuration & Environment
- Never hardcode credentials, API keys, or secrets. Always use `env()` helper.
- Cache configuration in production: `php artisan config:cache`.
- Use `.env.example` as the source of truth for required environment variables.

---

## 2. Filament v5 Rules

### Resources
- Place all resources in `app/Filament/Resources/` with corresponding `Pages/` subdirectory.
- Use `TextInput`, `RichEditor`, `FileUpload`, `Select`, `Toggle` — Filament's form builder components.
- Always define both `form()` and `table()` methods in resources.
- Use `->searchable()`, `->sortable()`, and `->toggleable()` on table columns for good UX.

### Pages
- Custom pages go in `app/Filament/Pages/`.
- Settings pages should use Filament's `ManagePage` pattern (see `ManageSettings.php`).

### Authorization
- Use Laravel Policies for resource authorization (see `PostPolicy.php`).
- Register policies in the model or `AuthServiceProvider`.
- Filament auto-discovers policies — ensure naming convention matches.

### File Uploads
- Use `FileUpload::make('field')->disk('public')` to store on the correct disk.
- The `public` disk resolves to MinIO S3 (`mmcp-storage` bucket) in both dev and prod.
- Always validate file types and sizes: `->acceptedFileTypes([...])`, `->maxSize(...)`.

---

## 3. Frontend Rules (Blade + Tailwind + Alpine.js)

### Blade Templates
- Use the master layout: `@extends('layouts.app')` or `<x-layouts.app>`.
- Use `@section` / `@yield` for content blocks.
- Use `{{ }}` (escaped) for all user-generated content — never use `{!! !!}` unless rendering trusted, sanitized HTML.
- Break large views into Blade components or `@include` partials.

### Tailwind CSS
- Use the project's custom color palette (`madeena-blue`, `madeena-teal`, `madeena-light`).
- Avoid inline styles — use Tailwind utility classes exclusively.
- Extend themes in `tailwind.config.js` — never override core.
- Use `@tailwindcss/forms` for styled form elements and `@tailwindcss/typography` for prose content.

### Alpine.js
- Use `x-data`, `x-show`, `x-bind`, `x-on` for reactive UI behavior.
- Keep Alpine.js logic small and co-located with the Blade template.
- For complex stateful UIs, prefer Livewire components over Alpine.

### Vite
- Entry points: `resources/css/app.css` and `resources/js/app.js`.
- Use `@vite()` directive in Blade layouts — never manually link compiled assets.
- Run `npm run dev` during development for HMR.

---

## 4. Security Rules

### Authentication & Authorization
- Admin panel is protected by Filament's built-in auth (`/admin`).
- Use CSRF tokens on all forms: `@csrf` in Blade, or fetch via API endpoint.
- Validate all request input with Form Requests or inline `$request->validate()`.

### Input Validation
- Always validate on the server side — never trust client-side validation alone.
- Use Laravel's validation rules: `required`, `string`, `max:255`, `email`, `unique`, etc.
- Sanitize file uploads: validate MIME type, extension, and size.

### SQL Injection Prevention
- Use Eloquent ORM or Query Builder — never use raw SQL with user input.
- If raw queries are unavoidable, use parameterized bindings: `DB::select('... WHERE id = ?', [$id])`.

### XSS Prevention
- Always use `{{ }}` (escaped output) in Blade — never `{!! !!}` for user content.
- Sanitize any HTML content stored in the database before rendering.

### CORS & Headers
- Set proper CORS headers if API endpoints are added.
- Use `Content-Security-Policy` headers in production Nginx config.

### File Storage Security
- Never serve files directly from `storage/app/private`.
- Public files go through the `PublicStorageController` with proper access control.
- Validate all uploaded file types and sizes.

### Secrets Management
- All secrets live in `.env` files — never commit them.
- Production secrets are managed via GitHub Secrets and injected via CI/CD.
- The agent must never request or handle raw SSH keys, passwords, or tokens.

---

## 5. Performance Rules

### Caching
- Cache database queries for settings and infrequently changing data.
- Use `Cache::remember()` with sensible TTLs.
- Invalidate caches explicitly when data changes (in Filament resource hooks).

### Eager Loading
- Always eager-load relationships: `Model::with('relation')->get()`.
- Never allow N+1 queries — use `->preventLazyLoading()` in development.

### Asset Optimization
- Vite handles CSS/JS minification in production builds.
- Use `php artisan optimize` in production to cache config, routes, and views.
- Leverage browser caching via Nginx `expires` directives.

### Octane Considerations (Dev)
- Be aware of in-memory state persistence across requests.
- Avoid storing request-specific data in static properties or singletons.
- Flush per-request data in Octane request lifecycle hooks.

---

## 6. Docker & Deployment Rules

### Dockerfile
- Multi-stage build: `composer-deps` → `node-builder` → `base` → `app`.
- Never install dev dependencies in the production image.
- Run `php artisan package:discover` and `php artisan filament:assets` during build.

### Docker Compose (Production)
- Services: `db` (MySQL 8.4), `app` (PHP-FPM), `queue` (worker), `nginx` (reverse proxy).
- All services run on the `madeena_cp_network` overlay network.
- Health checks are configured on every service.
- Resource limits and restart policies are defined.

### GitHub Actions Workflows
- `tests.yml` — Runs PHPUnit on push/PR.
- `deploy-swarm.yml` — Builds Docker image and deploys via Swarm (includes MinIO S3 preflight checks).
- `server-setup-*.yml` — Server provisioning (DB, deploy environment).
- `update-changelog.yml` — Auto-updates CHANGELOG.md.
