<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>sso madeena</title>
    
    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: hsl(250, 89%, 60%);
            --primary-hover: hsl(250, 89%, 65%);
            --header-bg: #0f172a;
            --page-bg: #f8fafc;
            --card-bg: #ffffff;
            --card-header-bg: #f8fafc;
            --card-border: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #334155;
            --text-muted: #64748b;
            --input-border: #cbd5e1;
            --input-focus: hsl(250, 89%, 65%);
            --input-focus-ring: rgba(99, 102, 241, 0.2);
            --error-bg: #fef2f2;
            --error-border: #fca5a5;
            --error-text: #991b1b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--page-bg);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header Bar */
        .header-bar {
            background-color: var(--header-bg);
            padding: 16px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #1e293b;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-logo {
            height: 36px;
            width: auto;
            display: block;
        }

        .header-title {
            font-family: 'Outfit', sans-serif;
            color: #ffffff;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.5px;
            text-transform: lowercase;
        }

        .header-right {
            display: flex;
            gap: 28px;
        }

        .header-link {
            color: #94a3b8;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: color 0.2s ease;
        }

        .header-link:hover {
            color: #ffffff;
        }

        /* Main Container */
        .main-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 60px 24px;
        }

        .grid-container {
            max-width: 1080px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr;
            gap: 60px;
            align-items: center;
        }

        @media (min-width: 768px) {
            .grid-container {
                grid-template-columns: 1.1fr 1fr;
            }
        }

        /* Login Card */
        .login-card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .card-header {
            background-color: var(--card-header-bg);
            border-bottom: 1px solid var(--card-border);
            padding: 20px 24px;
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-secondary);
            text-align: center;
            letter-spacing: -0.2px;
            text-transform: lowercase;
        }

        .card-body {
            padding: 32px;
        }

        /* Errors Alert */
        .error-alert {
            background-color: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
            padding: 14px 18px;
            margin-bottom: 24px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
        }

        .error-alert ul {
            margin: 0;
            padding-left: 20px;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 12px 14px;
            font-size: 14px;
            font-family: inherit;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            background-color: #ffffff;
            box-sizing: border-box;
            color: var(--text-primary);
            transition: all 0.2s ease;
        }

        .form-input:focus {
            border-color: var(--input-focus);
            outline: 0;
            box-shadow: 0 0 0 4px var(--input-focus-ring);
        }

        /* Remember Me & Checkbox */
        .checkbox-group {
            margin-bottom: 24px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            font-size: 13px;
            color: var(--text-muted);
            cursor: pointer;
            user-select: none;
        }

        .checkbox-input {
            margin-right: 10px;
            cursor: pointer;
            width: 16px;
            height: 16px;
            accent-color: var(--primary-color);
        }

        /* Actions Row */
        .actions-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            gap: 16px;
        }

        .btn-submit {
            background-color: var(--primary-color);
            border: 1px solid var(--primary-color);
            color: #ffffff;
            padding: 12px 28px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.1);
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 10px -1px rgba(99, 102, 241, 0.15);
        }

        .btn-submit:active {
            transform: translateY(1px);
        }

        .forgot-link {
            font-size: 13px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.15s ease;
        }

        .forgot-link:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        /* Register Divider */
        .divider {
            text-align: center;
            margin: 24px 0 16px 0;
            font-size: 13px;
            color: var(--text-muted);
            position: relative;
        }

        .divider::before {
            content: "";
            display: block;
            border-top: 1px solid var(--card-border);
            position: absolute;
            top: 50%;
            width: 100%;
            z-index: 1;
        }

        .divider-text {
            background: #ffffff;
            padding: 0 12px;
            position: relative;
            z-index: 2;
        }

        .btn-request-access {
            display: block;
            width: 100%;
            text-align: center;
            background-color: #ffffff;
            border: 1px solid var(--input-border);
            color: var(--text-secondary);
            padding: 12px 20px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            box-sizing: border-box;
            transition: all 0.15s ease;
        }

        .btn-request-access:hover {
            background-color: var(--page-bg);
            border-color: var(--text-muted);
            color: var(--text-primary);
        }

        /* Right Column (Branding & Security Info) */
        .info-column {
            color: var(--text-secondary);
        }

        .info-title {
            font-family: 'Outfit', sans-serif;
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 16px;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }

        .info-desc {
            font-size: 15px;
            line-height: 1.6;
            color: var(--text-muted);
            margin-bottom: 28px;
        }

        .warning-card {
            background-color: #fff;
            border-left: 4px solid #eab308;
            padding: 16px 20px;
            border-radius: 0 8px 8px 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }

        .warning-title {
            font-size: 13px;
            font-weight: 700;
            color: #854d0e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .warning-text {
            font-size: 13px;
            line-height: 1.5;
            color: #713f12;
        }

        @media (max-width: 767px) {
            .info-column {
                text-align: center;
                margin-top: 20px;
            }
            .warning-card {
                text-align: left;
                border-left: 4px solid #eab308;
            }
        }

        /* Footer */
        .footer-bar {
            text-align: center;
            padding: 24px;
            font-size: 12px;
            color: var(--text-muted);
            border-top: 1px solid var(--card-border);
            background-color: #ffffff;
        }
    </style>
</head>
<body>

    <!-- Header bar -->
    <header class="header-bar">
        <div class="header-left">
            <img src="{{ asset('images/madeena-logo-current.png') }}" class="header-logo" alt="Madeena Logo">
            <span class="header-title">madeena</span>
        </div>
    </header>

    <!-- Main Content Wrapper -->
    <main class="main-wrapper">
        <div class="grid-container">
            
            <!-- Left Column: Login Card -->
            <div class="login-card">
                <div class="card-header">
                    sign in
                </div>
                <div class="card-body">
                    
                    @if (session('status'))
                        <div class="success-alert" role="alert" style="background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 14px 18px; margin-bottom: 24px; border-radius: 8px; font-size: 13px; line-height: 1.5;">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="error-alert" role="alert">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ url('/login') }}" method="POST" id="login-form">
                        @csrf

                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input 
                                type="email" 
                                name="email" 
                                id="email" 
                                class="form-input" 
                                value="{{ old('email') }}" 
                                required 
                                autofocus 
                                autocomplete="email"
                            >
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label">Password</label>
                            <input 
                                type="password" 
                                name="password" 
                                id="password" 
                                class="form-input" 
                                required 
                                autocomplete="current-password"
                            >
                        </div>

                        <div class="checkbox-group">
                            <label class="checkbox-label" for="remember">
                                <input 
                                    type="checkbox" 
                                    name="remember" 
                                    id="remember" 
                                    class="checkbox-input"
                                    {{ old('remember') ? 'checked' : '' }}
                                >
                                <span class="checkbox-text">Keep me signed in</span>
                            </label>
                        </div>

                        <div class="actions-row">
                            <button type="submit" class="btn-submit" id="submit-login">
                                Sign In
                            </button>
                            <a href="#" class="forgot-link">Forgot password?</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Column: Branding & Security Info -->
            <div class="info-column">
                <h1 class="info-title">Secure Single Sign-On</h1>
                <p class="info-desc">
                    Madeena SSO is the central identity provider for your Madeena application services. Sign in with your registered corporate credentials to access all permitted Madeena services.
                </p>
                <div class="warning-card">
                    <div class="warning-title">Security Recommendation</div>
                    <div class="warning-text">
                        For security reasons, please log out and close all browser windows when you are finished accessing protected services, especially on shared or public devices.
                    </div>
                </div>
            </div>

        </div>
    </main>

    <x-footer />
</body>
</html>
