<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Madeena IAM</title>
    
    <!-- Google Fonts: Outfit & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: hsl(222, 47%, 7%);
            --card-bg: rgba(17, 24, 39, 0.65);
            --card-border: rgba(255, 255, 255, 0.08);
            --text-primary: hsl(0, 0%, 100%);
            --text-secondary: hsl(215, 20%, 65%);
            --primary-color: hsl(250, 89%, 65%);
            --primary-hover: hsl(250, 89%, 70%);
            --error-color: hsl(0, 84%, 60%);
            --error-bg: rgba(239, 68, 68, 0.1);
            --error-border: rgba(239, 68, 68, 0.2);
            --focus-ring: rgba(99, 102, 241, 0.35);
            --input-bg: rgba(255, 255, 255, 0.03);
            --input-border: rgba(255, 255, 255, 0.12);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            position: relative;
        }

        /* Ambient Glowing Background Elements */
        .ambient-glow {
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, rgba(99, 102, 241, 0) 70%);
            top: -150px;
            left: -150px;
            z-index: -1;
            filter: blur(60px);
            animation: float 20s ease-in-out infinite alternate;
        }

        .ambient-glow-2 {
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(168, 85, 247, 0.1) 0%, rgba(168, 85, 247, 0) 70%);
            bottom: -100px;
            right: -100px;
            z-index: -1;
            filter: blur(60px);
            animation: float 25s ease-in-out infinite alternate-reverse;
        }

        @keyframes float {
            0% {
                transform: translate(0, 0) scale(1);
            }
            100% {
                transform: translate(80px, 50px) scale(1.1);
            }
        }

        /* Login Layout Container */
        .login-container {
            width: 100%;
            max-width: 440px;
            padding: 24px;
            z-index: 10;
        }

        /* Premium Brand Header */
        .brand-header {
            text-align: center;
            margin-bottom: 32px;
            animation: fadeIn 0.8s ease-out;
        }

        .logo-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, hsl(250, 89%, 65%) 0%, hsl(280, 89%, 60%) 100%);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
            margin-bottom: 20px;
        }

        .logo-mark svg {
            width: 26px;
            height: 26px;
            fill: none;
            stroke: #fff;
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .brand-title {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
            background: linear-gradient(to right, #ffffff, hsl(215, 20%, 85%));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .brand-subtitle {
            font-size: 15px;
            color: var(--text-secondary);
        }

        /* Glassmorphic Form Card */
        .login-card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Form Group Layout */
        .form-group {
            margin-bottom: 24px;
            position: relative;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: hsl(215, 20%, 80%);
            margin-bottom: 8px;
            letter-spacing: 0.2px;
        }

        /* Modern Input Styling */
        .input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            font-size: 15px;
            font-family: inherit;
            color: var(--text-primary);
            background-color: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            outline: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-input::placeholder {
            color: transparent; /* Ensures we obey "no placeholder text" standard visually while maintaining semantic fallback */
        }

        .form-input:focus {
            border-color: var(--primary-color);
            background-color: rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 0 4px var(--focus-ring);
        }

        /* Remember Me & Custom Checkbox styling */
        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .remember-checkbox-label {
            display: flex;
            align-items: center;
            font-size: 13px;
            color: var(--text-secondary);
            cursor: pointer;
            user-select: none;
        }

        .remember-checkbox {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }

        .custom-checkbox {
            height: 18px;
            width: 18px;
            background-color: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 5px;
            margin-right: 10px;
            position: relative;
            transition: all 0.15s ease;
        }

        .remember-checkbox-label:hover .custom-checkbox {
            border-color: var(--primary-color);
        }

        .remember-checkbox:checked ~ .custom-checkbox {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .remember-checkbox:checked ~ .custom-checkbox::after {
            content: "";
            position: absolute;
            left: 5px;
            top: 2px;
            width: 4px;
            height: 8px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        /* Button Premium Styling & Micro-animations */
        .submit-btn {
            width: 100%;
            padding: 14px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            color: #ffffff;
            background: linear-gradient(135deg, hsl(250, 89%, 65%) 0%, hsl(270, 89%, 60%) 100%);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
            position: relative;
            overflow: hidden;
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.35);
            background: linear-gradient(135deg, hsl(250, 89%, 67%) 0%, hsl(270, 89%, 62%) 100%);
        }

        .submit-btn:active {
            transform: translateY(1px);
            box-shadow: 0 2px 10px rgba(99, 102, 241, 0.2);
        }

        /* Errors alert styling */
        .error-alert {
            background-color: var(--error-bg);
            border: 1px solid var(--error-border);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 24px;
            font-size: 13px;
            color: var(--error-color);
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            animation: fadeIn 0.4s ease-out;
        }

        .error-alert svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* Focus Ring Style for accessibility */
        .submit-btn:focus-visible {
            outline: none;
            box-shadow: 0 0 0 4px var(--focus-ring);
        }
    </style>
</head>
<body>

    <div class="ambient-glow"></div>
    <div class="ambient-glow-2"></div>

    <div class="login-container">
        <header class="brand-header">
            <div class="logo-mark">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>
            <h1 class="brand-title">Madeena IAM</h1>
            <p class="brand-subtitle">Central Identity Access Management</p>
        </header>

        <main class="login-card">
            @if ($errors->any())
                <div class="error-alert" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div>
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            <form action="{{ url('/login') }}" method="POST" id="login-form">
                @csrf

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-wrapper">
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
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            name="password" 
                            id="password" 
                            class="form-input" 
                            required 
                            autocomplete="current-password"
                        >
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-checkbox-label" for="remember">
                        <input 
                            type="checkbox" 
                            name="remember" 
                            id="remember" 
                            class="remember-checkbox"
                            {{ old('remember') ? 'checked' : '' }}
                        >
                        <span class="custom-checkbox"></span>
                        Keep me signed in
                    </label>
                </div>

                <button type="submit" class="submit-btn" id="submit-login">
                    Sign In
                </button>
            </form>
        </main>
    </div>

</body>
</html>
