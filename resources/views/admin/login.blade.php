<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login | TavsScore</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        body {
            font-family:'Inter',system-ui,sans-serif; font-size:14px;
            background:#080d1a; color:#e2e8f0; min-height:100vh;
            display:flex; align-items:center; justify-content:center;
            -webkit-font-smoothing:antialiased;
            background-image: radial-gradient(ellipse 80% 50% at 50% -10%, rgba(16,185,129,.1), transparent);
        }

        .login-box {
            width:100%; max-width:400px; padding:1.5rem;
        }

        .login-brand {
            display:flex; align-items:center; gap:.55rem;
            justify-content:center; margin-bottom:2rem;
            text-decoration:none; color:#fff; font-weight:800; font-size:1.05rem;
        }

        .login-brand-icon {
            width:36px; height:36px; border-radius:9px;
            background:linear-gradient(135deg,#10b981,#059669);
            display:flex; align-items:center; justify-content:center; font-size:18px;
        }

        .login-card {
            background:#0e1525; border:1px solid rgba(255,255,255,.07);
            border-radius:14px; padding:1.75rem;
        }

        .login-title { font-size:1.15rem; font-weight:800; color:#fff; margin-bottom:.3rem; }
        .login-sub   { font-size:.78rem; color:#64748b; margin-bottom:1.5rem; }

        .form-group { margin-bottom:1rem; }
        .form-label { display:block; font-size:.78rem; font-weight:600; color:#e2e8f0; margin-bottom:.4rem; }

        .form-input {
            width:100%; background:#131d30; border:1px solid rgba(255,255,255,.07);
            border-radius:7px; color:#e2e8f0; padding:.6rem .8rem;
            font-size:.85rem; font-family:inherit; outline:none;
            transition:border-color 160ms;
        }
        .form-input:focus { border-color:rgba(16,185,129,.4); box-shadow:0 0 0 3px rgba(16,185,129,.08); }

        .form-error { font-size:.72rem; color:#fca5a5; margin-top:.35rem; }

        .remember-row {
            display:flex; align-items:center; gap:.5rem;
            margin-bottom:1.25rem;
        }
        .remember-row input { accent-color:#10b981; width:14px; height:14px; }
        .remember-row label { font-size:.78rem; color:#64748b; cursor:pointer; }

        .btn-login {
            width:100%; padding:.65rem; border-radius:8px;
            background:linear-gradient(135deg,#10b981,#059669);
            color:#fff; font-size:.88rem; font-weight:700;
            border:none; cursor:pointer; font-family:inherit;
            transition:opacity 160ms, transform 160ms;
        }
        .btn-login:hover { opacity:.9; transform:translateY(-1px); }

        .back-link { display:block; text-align:center; margin-top:1rem; font-size:.75rem; color:#64748b; text-decoration:none; }
        .back-link:hover { color:#e2e8f0; }

        .alert-red { background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.25); border-radius:7px; padding:.65rem .875rem; color:#fca5a5; font-size:.78rem; font-weight:600; margin-bottom:1rem; }
    </style>
</head>
<body>
<div class="login-box">
    <a href="{{ route('home.index') }}" class="login-brand">
        <span class="login-brand-icon">⚽</span>
        TavsScore
    </a>

    <div class="login-card">
        <h1 class="login-title">Admin Login</h1>
        <p class="login-sub">Sign in to manage TavsScore</p>

        @if($errors->any())
            <div class="alert-red">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.login.post') }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="email">Email address</label>
                <input id="email" type="email" name="email" class="form-input"
                       value="{{ old('email') }}" placeholder="admin@tavsscore.com"
                       required autocomplete="email">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input id="password" type="password" name="password" class="form-input"
                       placeholder="••••••••" required autocomplete="current-password">
            </div>

            <div class="remember-row">
                <input id="remember" type="checkbox" name="remember">
                <label for="remember">Keep me signed in</label>
            </div>

            <button type="submit" class="btn-login">Sign in →</button>
        </form>

        <a href="{{ route('home.index') }}" class="back-link">← Back to TavsScore</a>
    </div>
</div>
</body>
</html>
