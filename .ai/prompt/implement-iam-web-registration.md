# SYSTEM INSTRUCTION: Implement Web Registration in IAM

You are a **Senior Fullstack Engineer**. Your task is to build a beautiful, functional Web Registration Page for the `madeena-iam` (Central Identity Provider). 

---

## CONTEXT
Currently, `madeena-iam` only has a web login page (`resources/views/auth/login.blade.php`) and an API registration endpoint. We need a proper Web Registration Page (`/register`) so that users directed here via SSO from client apps can register directly on the IAM portal.

---

## PHASE 1: Load Game 🎮
1. Review `routes/web.php` to understand the current authentication web routes.
2. Review `resources/views/auth/login.blade.php` to understand the existing design system and styling.
3. Review `App\Http\Controllers\Api\V1\AuthController::register()` to understand how `client_id` is currently handled for API registration, including the `ClientUser` pivot auto-creation and SuperAdmin notifications.

---

## PHASE 2: Implementation Workflow 🔧

### Step 1: Create the Web Route
Add the following routes to `routes/web.php` within the `guest` middleware group:
- `GET /register` -> `RegisterController@showRegistrationForm` (name: `register`)
- `POST /register` -> `RegisterController@register`

### Step 2: Create the RegisterController
Create `App\Http\Controllers\Auth\RegisterController.php`:
- Implement `showRegistrationForm()` to return the registration view.
- Implement `register()` to handle form submission:
  1. Validate the input (name, email, password, password_confirmation).
  2. Create the User.
  3. **Critical SSO Logic**: Check if a `client_id` is present in the request (passed as a query parameter or hidden input from the OAuth flow). If it is:
     - Automatically create the `ClientUser` pivot record for this user and `client_id` with `status = 'pending_approval'`.
     - Dispatch the `NewClientUserRegistrationNotification` to `super_admin` users (mirroring the logic in `Api\V1\AuthController::register`).
  4. Log the user in via session (`Auth::login($user)`).
  5. Redirect the user. 
     *Hint*: If they clicked "Register" from the login page during an OAuth flow, Laravel's intended session might be set to the OAuth authorize URL. Use `redirect()->intended('/')` to ensure the OAuth flow continues, or explicitly redirect them back to `/oauth/authorize` with the original query parameters.

### Step 3: Update Login View
Modify `resources/views/auth/login.blade.php`:
- Add a beautifully styled "Don't have an account? Register here" link.
- **Important**: Ensure the link preserves the current query parameters (e.g., `href="{{ route('register', request()->query()) }}"`) so that the `client_id` and other OAuth variables are passed to the registration page.

### Step 4: Create the Registration View
Create `resources/views/auth/register.blade.php`:
- The design must match the high-quality, modern aesthetics of `login.blade.php`.
- The form should submit via POST to the `register` route.
- Include hidden input fields to pass along any query parameters (like `client_id`, `redirect_uri`, `response_type`, `state`, `scope`) so they are submitted with the form and available in the controller.

---

## SUCCESS CRITERIA ✅
1. ✅ Navigating to `/register` shows a beautifully styled registration page matching the login page.
2. ✅ The login page has a "Register" link that successfully preserves OAuth query parameters.
3. ✅ Submitting the registration form creates the user and logs them in.
4. ✅ If registering during an SSO flow (with `client_id`), the system correctly creates the `pending_approval` pivot and sends the admin email notification.
5. ✅ After registration, the user is successfully redirected back into their OAuth flow or to the default dashboard.

---

## EXECUTION COMMAND
Start now. Load the game state, analyze the current auth architecture, and build the Web Registration flow. Do not stop until all success criteria are met.
