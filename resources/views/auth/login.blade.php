<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: #10b981;
            --accent-hover: #059669;
            --accent-soft: rgba(16,185,129,0.08);
            --accent-glow: rgba(16,185,129,0.25);
            --sidebar-bg: #0c1222;
            --sidebar-surface: #111a2e;
            --sidebar-border: rgba(255,255,255,0.06);
            --sidebar-text: #8896ab;
            --card: #ffffff;
            --card-border: #e8ecf1;
            --card-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.02);
            --card-shadow-hover: 0 8px 24px rgba(0,0,0,0.06), 0 2px 6px rgba(0,0,0,0.03);
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --danger: #ef4444;
            --radius: 10px;
            --radius-sm: 7px;
            --radius-lg: 14px;
            --ease: cubic-bezier(0.4,0,0.2,1);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--sidebar-bg);
            -webkit-font-smoothing: antialiased;
            overflow: hidden;
            position: relative;
        }

        /* Dot pattern — sama seperti template utama */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: radial-gradient(rgba(148,163,184,0.06) 1px, transparent 1px);
            background-size: 22px 22px;
            pointer-events: none;
            z-index: 0;
        }

        /* Ambient glow orbs */
        body::after {
            content: '';
            position: fixed;
            width: 500px; height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, var(--accent-glow) 0%, transparent 70%);
            top: -120px; right: -100px;
            pointer-events: none;
            z-index: 0;
            animation: orbFloat 8s ease-in-out infinite alternate;
        }

        .orb-bottom {
            position: fixed;
            width: 400px; height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(59,130,246,0.12) 0%, transparent 70%);
            bottom: -100px; left: -80px;
            pointer-events: none;
            z-index: 0;
            animation: orbFloat 10s ease-in-out 2s infinite alternate-reverse;
        }

        @keyframes orbFloat {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, 20px) scale(1.1); }
        }

        /* ========== LOGIN WRAPPER ========== */
        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            padding: 20px;
            animation: loginFadeIn 0.6s var(--ease) both;
        }

        @keyframes loginFadeIn {
            from { opacity: 0; transform: translateY(24px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ========== LOGIN CARD ========== */
        .login-card {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            box-shadow: 0 24px 64px rgba(0,0,0,0.3), 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        /* Header */
        .login-header {
            background: var(--sidebar-bg);
            padding: 32px 28px 28px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(to right, transparent 10%, var(--accent-glow) 50%, transparent 90%);
            opacity: 0.6;
        }

        .login-header::before {
            content: '';
            position: absolute;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: radial-gradient(circle, var(--accent-glow) 0%, transparent 70%);
            top: -80px; right: -40px;
            pointer-events: none;
            opacity: 0.3;
        }

        .login-logo {
            width: 52px; height: 52px;
            background: linear-gradient(135deg, var(--accent), #34d399);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 22px;
            box-shadow: 0 6px 20px var(--accent-glow);
            margin-bottom: 16px;
            position: relative;
            z-index: 1;
        }

        .login-brand-name {
            font-size: 20px;
            font-weight: 800;
            color: #f1f5f9;
            letter-spacing: -0.4px;
            margin-bottom: 4px;
            position: relative;
            z-index: 1;
        }

        .login-brand-sub {
            font-size: 11px;
            color: var(--sidebar-text);
            letter-spacing: 2px;
            text-transform: uppercase;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        /* Body */
        .login-body {
            padding: 28px;
        }

        /* ========== FORM — sesuai template utama ========== */
        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            border: 1px solid var(--card-border);
            border-radius: var(--radius-sm);
            padding: 9px 14px;
            font-size: 13.5px;
            font-family: inherit;
            color: var(--text-primary);
            transition: all 0.2s var(--ease);
            background: #fff;
        }
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
            outline: none;
        }
        .form-control::placeholder {
            color: var(--text-muted);
            font-weight: 400;
        }

        .input-group-text {
            background: #f8fafc;
            border: 1px solid var(--card-border);
            border-right: none;
            color: var(--text-muted);
            font-size: 14px;
            border-radius: var(--radius-sm) 0 0 var(--radius-sm);
            transition: all 0.2s var(--ease);
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
        }

        .input-group:focus-within .input-group-text {
            border-color: var(--accent);
            color: var(--accent);
            background: var(--accent-soft);
        }
        .input-group:focus-within .form-control {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }

        /* Password toggle */
        .password-wrapper {
            position: relative;
        }

        .password-wrapper .form-control {
            padding-right: 44px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-muted);
            z-index: 10;
            font-size: 14px;
            padding: 4px;
            transition: color 0.2s var(--ease);
            background: none;
            border: none;
        }
        .toggle-password:hover { color: var(--accent); }

        /* Remember me */
        .form-check-input {
            border-color: var(--card-border);
            transition: all 0.2s var(--ease);
        }
        .form-check-input:checked {
            background-color: var(--accent);
            border-color: var(--accent);
        }
        .form-check-input:focus {
            box-shadow: 0 0 0 3px var(--accent-soft);
            border-color: var(--accent);
        }
        .form-check-label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* ========== BUTTON — sesuai template ========== */
        .btn-accent {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s var(--ease);
            box-shadow: 0 2px 8px var(--accent-glow);
            width: 100%;
            letter-spacing: -0.2px;
        }
        .btn-accent:hover {
            background: var(--accent-hover);
            box-shadow: 0 4px 18px var(--accent-glow);
            transform: translateY(-1px);
            color: #fff;
        }
        .btn-accent:active {
            transform: translateY(0);
            box-shadow: 0 1px 4px var(--accent-glow);
        }

        .btn-accent .spinner-border {
            width: 16px; height: 16px;
            border-width: 2px;
            border-color: rgba(255,255,255,0.3);
            border-top-color: #fff;
        }

        /* ========== TOASTR OVERRIDES — sesuai template ========== */
        .toast-top-right { top: 20px !important; right: 20px !important; }
        .toast {
            border-radius: var(--radius) !important;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15) !important;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }
        .toast-success {
            background: #f0fdf4 !important;
            border: 1px solid #bbf7d0 !important;
            color: #065f46 !important;
        }
        .toast-success .toast-close-button { color: #059669 !important; }
        .toast-error {
            background: #fef2f2 !important;
            border: 1px solid #fecaca !important;
            color: #7f1d1d !important;
        }
        .toast-error .toast-close-button { color: #dc2626 !important; }
        .toast-warning {
            background: #fffbeb !important;
            border: 1px solid #fde68a !important;
            color: #78350f !important;
        }
        .toast-warning .toast-close-button { color: #d97706 !important; }
        .toast-info {
            background: #eff6ff !important;
            border: 1px solid #bfdbfe !important;
            color: #1e40af !important;
        }
        .toast-info .toast-close-button { color: #2563eb !important; }
        #toast-container > .toast { background-image: none !important; padding: 14px 16px !important; }
        #toast-container > .toast:before { display: none !important; }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 480px) {
            .login-wrapper { padding: 16px; }
            .login-header { padding: 24px 20px 22px; }
            .login-body { padding: 22px 20px; }
            .login-brand-name { font-size: 18px; }
        }
    </style>
</head>
<body>

    <!-- Ambient orb -->
    <div class="orb-bottom"></div>

    <div class="login-wrapper">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <div class="login-logo">
                    <i class="fa-solid fa-cubes"></i>
                </div>
                <div class="login-brand-name">ERP System</div>
                <div class="login-brand-sub">Enterprise</div>
            </div>

            <!-- Body -->
            <div class="login-body">
                <form method="POST" action="{{ route('login') }}" id="login-form">
                    @csrf

                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                            <input type="text"
                                   class="form-control"
                                   id="username"
                                   name="username"
                                   value="{{ old('username') }}"
                                   placeholder="Masukkan username"
                                   required
                                   autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group password-wrapper">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password"
                                   class="form-control"
                                   id="password"
                                   name="password"
                                   placeholder="Masukkan password"
                                   required>
                            <button type="button" class="toggle-password" onclick="togglePassword()" aria-label="Toggle password visibility">
                                <i class="fa-solid fa-eye" id="toggle-icon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Ingat saya</label>
                    </div>

                    <button type="submit" class="btn-accent" id="btn-login">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                        <span>Login</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
    <script>
        // --- Toastr config — sama persis dengan template utama ---
        toastr.options = {
            positionClass: 'toast-top-right',
            progressBar: true,
            timeOut: 3000,
            closeButton: true,
            preventDuplicates: true,
            showDuration: 200,
            hideDuration: 200,
        };

        @if ($errors->any())
            @foreach ($errors->all() as $error)
                toastr.error('{{ $error }}');
            @endforeach
        @endif

        // --- Toggle password ---
        function togglePassword() {
            const pw = document.getElementById('password');
            const icon = document.getElementById('toggle-icon');
            if (pw.type === 'password') {
                pw.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                pw.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // --- Loading state pada tombol login ---
        document.getElementById('login-form').addEventListener('submit', function() {
            const btn = document.getElementById('btn-login');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span><span>Memproses...</span>';
        });
    </script>
</body>
</html>
