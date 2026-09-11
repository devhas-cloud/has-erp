<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP - Tidak Ada Akses</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: #10b981;
            --accent-hover: #059669;
            --accent-glow: rgba(16,185,129,0.25);
            --sidebar-bg: #0c1222;
            --card: #ffffff;
            --card-border: #e8ecf1;
            --card-shadow: 0 8px 24px rgba(0,0,0,0.06), 0 2px 6px rgba(0,0,0,0.03);
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --radius: 10px;
            --radius-sm: 7px;
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
            background-image: radial-gradient(rgba(148,163,184,0.08) 1px, transparent 1px);
            background-size: 22px 22px;
        }
        .card {
            width: 100%;
            max-width: 420px;
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--card-shadow);
            padding: 32px 28px;
            text-align: center;
        }
        .icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #fef3c7;
            color: #92400e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin: 0 auto 16px;
        }
        h1 {
            font-size: 17px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 8px;
        }
        p {
            font-size: 13.5px;
            color: var(--text-secondary);
            margin: 0 0 22px;
            line-height: 1.55;
        }
        .btn-accent {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 10px 22px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s var(--ease);
            box-shadow: 0 2px 8px var(--accent-glow);
        }
        .btn-accent:hover { background: var(--accent-hover); }
        .user-line {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 18px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h1>Belum Ada Akses Modul</h1>
        <p>
            Akun <strong>{{ auth()->user()->username ?? '' }}</strong> belum memiliki akses baca (can_read)
            ke modul manapun. Hubungi administrator untuk mengaktifkan akses modul pada akun ini.
        </p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-accent">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span>Keluar</span>
            </button>
        </form>
    </div>
</body>
</html>
