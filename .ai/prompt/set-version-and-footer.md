# Set Version & Footer

> Reusable prompt for AI agents to update the application version number and/or customise the global footer component.

---

## Context

Madeena IAM uses a **file-based versioning** system paired with a **global footer** that renders on every page (public login, Filament admin panel, and outgoing emails).

### Architecture Overview

```
VERSION                                  ← single source of truth (e.g. v1.2.0)
    ↓
AppServiceProvider::boot()               ← reads, trims "v", shares to all views
    ↓
┌──────────────────────────────────────┐
│  footer.blade.php  ($appVersion)     │ ← renders on public pages via <x-footer />
│                                      │   and on Filament via PanelsRenderHook::FOOTER
├──────────────────────────────────────┤
│  mail/html/message.blade.php         │ ← email HTML footer
│  mail/text/message.blade.php         │ ← email text footer
└──────────────────────────────────────┘
```

### Key Files

| File | Purpose |
|------|---------|
| `VERSION` (project root) | Contains the version string, e.g. `v1.2.0`. The leading `v` is stripped at runtime. |
| `app/Providers/AppServiceProvider.php` | Reads `VERSION`, shares `$appVersion` globally via `View::share()`, sets `config('app.version')`, registers Filament render hooks. |
| `resources/views/components/footer.blade.php` | The unified footer Blade component. Uses `$appVersion`. |
| `resources/views/auth/login.blade.php` | Public SSO login page — includes `<x-footer />`. |
| `resources/views/welcome.blade.php` | Landing page — includes `<x-footer />`. |
| `resources/views/vendor/mail/html/message.blade.php` | HTML email layout — footer slot uses `$appVersion`. |
| `resources/views/vendor/mail/text/message.blade.php` | Text email layout — footer uses `$appVersion`. |
| `tests/e2e/footer.spec.ts` | Playwright E2E test asserting footer visibility and version text. |
| `tests/Unit/VersionTest.php` | Unit test for version string resolution. |
| `tests/Feature/FooterTest.php` | Feature test for footer rendering on pages. |

---

## Task 1: Set Version

### Instructions

1. **Edit** the `VERSION` file at the project root with the new version string:
   ```
   v<MAJOR>.<MINOR>.<PATCH>
   ```
   Example: `v1.2.0`

2. **No other file changes are needed** — `AppServiceProvider` dynamically reads this file at boot. The leading `v` is stripped automatically via `ltrim($appVersion, 'vV')`.

3. **Clear caches** to ensure the new version takes effect immediately:
   ```bash
   php artisan view:clear
   php artisan config:clear
   php artisan cache:clear
   ```

4. **Verify** the version is displayed correctly:
   ```bash
   npx playwright test tests/e2e/footer.spec.ts
   ```

> [!IMPORTANT]
> The E2E test reads the `VERSION` file independently and asserts `v<version>` in the footer text. If you change the version, the test will automatically pick up the new value — no test code changes needed.

---

## Task 2: Customise the Footer

### Current Footer HTML

```blade
<div class="border-t border-gray-200 dark:border-gray-700 py-6 px-4"
     style="margin-top: auto; width: 100% !important; text-align: center !important; box-sizing: border-box;">
    <p class="text-gray-500 dark:text-gray-400"
       style="margin: 0 auto !important; text-align: center !important; font-size: 10px !important; line-height: 1.5; display: inline-block; width: 100%;">
        &copy; {{ date('Y') }} Madeena. All rights reserved.<br>
        v{{ $appVersion }}
    </p>
</div>
```

### How the Footer is Rendered

The footer appears in **three contexts**:

| Context | Mechanism |
|---------|-----------|
| **Filament Admin Panel** (login + dashboard) | `PanelsRenderHook::FOOTER` registered in `AppServiceProvider` renders `components.footer` view. |
| **Public Pages** (login, welcome) | `<x-footer />` Blade component tag included directly in the page template. |
| **Emails** | Separate templates in `resources/views/vendor/mail/` — edit those independently. |

### Layout Constraints

> [!WARNING]
> The footer must use `margin-top: auto` (not `position: fixed`) to push itself to the bottom of the viewport on short-content pages. This works because `AppServiceProvider` injects `.fi-main { flex-grow: 1 !important; }` via the `HEAD_END` render hook, making the main content area fill available height.

### Modification Steps

1. **Edit** `resources/views/components/footer.blade.php` with your desired HTML/CSS.
2. **Keep these constraints**:
   - Use `style="margin-top: auto;"` on the outer `<div>` (not `position: fixed` or `position: sticky`).
   - Ensure `text-align: center` is applied inline (Filament's Tailwind JIT may not compile custom classes like `text-[10px]` on production without a Vite build step).
   - The variable `$appVersion` is always available (shared globally by `AppServiceProvider`).
3. **Clear view cache**:
   ```bash
   php artisan view:clear
   ```
4. **Verify** with E2E tests:
   ```bash
   npx playwright test tests/e2e/footer.spec.ts
   ```

> [!TIP]
> If you change the footer text structure (e.g. removing the `v` prefix, changing copyright wording), also update the E2E test assertions in `tests/e2e/footer.spec.ts` to match.

---

## Verification Checklist

After making changes, run through this verification:

- [ ] `VERSION` file contains the correct version string (e.g. `v1.2.0`)
- [ ] `php artisan view:clear && php artisan config:clear && php artisan cache:clear`
- [ ] `npx playwright test tests/e2e/footer.spec.ts` — passes
- [ ] `php artisan test --filter VersionTest` — passes
- [ ] `php artisan test --filter FooterTest` — passes
- [ ] Visually confirm footer at bottom of `/admin/login` page
- [ ] Visually confirm footer at bottom of `/admin` dashboard after login
