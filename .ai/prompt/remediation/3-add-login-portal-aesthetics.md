# Task: Create Named Login Route & Premium Login Portal

## 1. Context
- **Affected Routes**: `GET /login` (named route `login`) and `POST /login`
- **Active Rules**: [project-context.md](file:///var/www/madeena-iam/.ai/rules/project-context.md)
- **Design Standard**: Premium Stripe-like or Apple ID-like modern aesthetics

Passport and Laravel's auth guards redirect unauthenticated users to the named route `login`. Currently, this route does not exist. Additionally, a premium interactive user login interface is required.

---

## 2. Objective
Create a named `login` route with GET and POST methods, and build a beautiful, modern web login screen that authenticates users into the central `web` guard and handles intended redirect paths (e.g. Passport's authorization redirect URL).

---

## 3. Role
Senior Fullstack Engineer / UI-UX Specialist

---

## 4. Expectations & Requirements

### Implementation Steps
1. **Define Routes & Controller**:
   - Register GET `/login` and POST `/login` in [web.php](file:///var/www/madeena-iam/routes/web.php). Ensure the GET route is named `login`.
   - Implement controller logic to validate user credentials, log them into the `web` guard, and redirect to the `intended()` redirect URL.
2. **Build Web UI (Blade & Vanilla CSS)**:
   - Create a modern, visually stunning login Blade view using standard HTML and Vanilla CSS. Do NOT use TailwindCSS.
   - Employ premium design patterns:
     - Curated dark mode theme with sleek HSL-tailored colors.
     - Smooth gradients (e.g. subtle moving radial gradients).
     - Modern Google Fonts (e.g. Outfit, Inter).
     - Responsive design that fits mobile and desktop beautifully.
     - Subtle micro-animations (e.g., input field focus glow, button scale transitions).
     - Do not use any placeholder text.
3. **Automated Verification**:
   - Create a feature test file `tests/Feature/WebAuthenticationTest.php`.
   - Assert that:
     - The `/login` route renders successfully.
     - Submitting valid credentials signs the user in and redirects to the intended page.
     - Submitting invalid credentials displays validation errors.
4. **Code Quality**:
   - Follow strict typing (`declare(strict_types=1);`).
   - Run Laravel Pint (`./vendor/bin/pint`) to auto-format files.
