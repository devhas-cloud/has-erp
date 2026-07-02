<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ERP System')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-w: 264px;
            --sidebar-collapsed-w: 72px;
            --topbar-h: 60px;
            --accent: #10b981;
            --accent-hover: #059669;
            --accent-soft: rgba(16,185,129,0.08);
            --accent-glow: rgba(16,185,129,0.25);
            --sidebar-bg: #0c1222;
            --sidebar-surface: #111a2e;
            --sidebar-hover: #162036;
            --sidebar-border: rgba(255,255,255,0.06);
            --sidebar-text: #8896ab;
            --sidebar-text-active: #f1f5f9;
            --bg: #f0f4f8;
            --bg-dot: rgba(148,163,184,0.1);
            --card: #ffffff;
            --card-border: #e8ecf1;
            --card-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.02);
            --card-shadow-hover: 0 8px 24px rgba(0,0,0,0.06), 0 2px 6px rgba(0,0,0,0.03);
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --danger: #ef4444;
            --danger-soft: rgba(239,68,68,0.08);
            --success: #10b981;
            --success-soft: rgba(16,185,129,0.08);
            --warning: #f59e0b;
            --warning-soft: rgba(245,158,11,0.08);
            --info: #3b82f6;
            --info-soft: rgba(59,130,246,0.08);
            --radius: 10px;
            --radius-sm: 7px;
            --radius-lg: 14px;
            --ease: cubic-bezier(0.4,0,0.2,1);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            margin: 0;
            min-height: 100vh;
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: radial-gradient(var(--bg-dot) 1px, transparent 1px);
            background-size: 22px 22px;
            pointer-events: none;
            z-index: 0;
        }

        /* ========== SIDEBAR ========== */
        .sidebar {
            position: fixed;
            top: 0; left: 0; bottom: 0;
            width: var(--sidebar-w);
            background: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            z-index: 1050;
            transition: transform 0.35s var(--ease);
            overflow: hidden;
        }

        .sidebar::after {
            content: '';
            position: absolute;
            top: 0; right: 0; bottom: 0;
            width: 1px;
            background: linear-gradient(to bottom, transparent 10%, var(--accent-glow) 50%, transparent 90%);
            opacity: 0.4;
        }

        .sidebar-scroll {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.06) transparent;
        }
        .sidebar-scroll::-webkit-scrollbar { width: 3px; }
        .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 3px; }

        .sidebar-header {
            padding: 20px 18px;
            border-bottom: 1px solid var(--sidebar-border);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .sidebar-logo {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, var(--accent), #34d399);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: #fff;
            font-size: 16px;
            box-shadow: 0 4px 14px var(--accent-glow);
            flex-shrink: 0;
        }

        .sidebar-brand { display: flex; flex-direction: column; }
        .sidebar-brand-name {
            font-size: 16px; font-weight: 700;
            color: #f1f5f9; line-height: 1.2;
            letter-spacing: -0.3px;
        }
        .sidebar-brand-sub {
            font-size: 10px; color: var(--sidebar-text);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 2px;
        }

        .sidebar-nav { padding: 8px 0 16px; }

        .sidebar-group {
            padding: 20px 18px 7px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #3e4f6a;
            font-weight: 700;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 9px 16px;
            margin: 2px 10px;
            color: var(--sidebar-text);
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.2s var(--ease);
            position: relative;
        }

        .sidebar-link::before {
            content: '';
            position: absolute;
            left: 0; top: 50%;
            transform: translateY(-50%) scaleY(0);
            width: 3px; height: 18px;
            background: var(--accent);
            border-radius: 0 3px 3px 0;
            transition: transform 0.2s var(--ease);
        }

        .sidebar-link:hover {
            background: var(--sidebar-hover);
            color: #cbd5e1;
        }

        .sidebar-link.active {
            background: var(--accent-soft);
            color: var(--sidebar-text-active);
        }
        .sidebar-link.active::before { transform: translateY(-50%) scaleY(1); }
        .sidebar-link.active .sidebar-icon { color: var(--accent); }

        .sidebar-icon {
            width: 20px; text-align: center;
            font-size: 14px;
            transition: color 0.2s var(--ease);
            flex-shrink: 0;
        }

        .sidebar-footer {
            padding: 14px 14px;
            border-top: 1px solid var(--sidebar-border);
            flex-shrink: 0;
        }
        .sidebar-footer-info {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 10px;
            border-radius: var(--radius-sm);
            background: var(--sidebar-surface);
        }
        .sidebar-avatar {
            width: 32px; height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--accent), #34d399);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 11px; font-weight: 700;
            flex-shrink: 0;
            letter-spacing: 0.5px;
        }
        .sidebar-footer-text { line-height: 1.3; }
        .sidebar-footer-name { font-size: 12px; font-weight: 600; color: #e2e8f0; }
        .sidebar-footer-role { font-size: 10px; color: #4e5f78; font-weight: 500; }

        /* ========== SIDEBAR COLLAPSED STATE ========== */
        .sidebar-desktop-toggle {
            display: none;
            width: 36px; height: 36px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--card-border);
            background: var(--card);
            color: var(--text-muted);
            cursor: pointer;
            font-size: 13px;
            align-items: center; justify-content: center;
            transition: all 0.2s var(--ease);
            flex-shrink: 0;
        }
        .sidebar-desktop-toggle:hover {
            background: var(--accent-soft);
            color: var(--accent);
            border-color: var(--accent);
        }

        /* Sidebar collapsed: width only, text hidden */
        .sidebar.collapsed {
            width: var(--sidebar-collapsed-w);
            transition: width 0.3s var(--ease);
        }
        .sidebar { transition: width 0.3s var(--ease), transform 0.35s var(--ease); }

        .topbar { transition: left 0.3s var(--ease); }
        .topbar.collapsed { left: var(--sidebar-collapsed-w); }

        .main-content { transition: margin-left 0.3s var(--ease); }
        .main-content.collapsed { margin-left: var(--sidebar-collapsed-w); }

        /* Center icons, hide text */
        .sidebar.collapsed .sidebar-link {
            justify-content: center;
            padding: 12px 0;
            margin: 4px 14px;
            border-radius: var(--radius-sm);
        }
        .sidebar.collapsed .sidebar-link span { display: none; }
        .sidebar.collapsed .sidebar-link::before { display: none; }
        .sidebar.collapsed .sidebar-icon { font-size: 18px; width: auto; }

        /* Hide brand, center logo */
        .sidebar.collapsed .sidebar-brand { display: none; }
        .sidebar.collapsed .sidebar-header {
            justify-content: center;
            padding: 18px 0;
        }
        .sidebar.collapsed .sidebar-logo {
            width: 42px; height: 42px;
            border-radius: 12px;
            font-size: 18px;
        }

        /* Hide group labels, show divider line */
        .sidebar.collapsed .sidebar-group {
            display: flex;
            justify-content: center;
            padding: 14px 0 4px;
            font-size: 0;
            color: transparent;
            position: relative;
        }
        .sidebar.collapsed .sidebar-group::before {
            content: '';
            display: block;
            width: 20px;
            height: 1px;
            background: rgba(255,255,255,0.08);
        }

        /* Hide footer text, center avatar */
        .sidebar.collapsed .sidebar-footer-text { display: none; }
        .sidebar.collapsed .sidebar-footer-info { justify-content: center; padding: 9px; }
        .sidebar.collapsed .sidebar-footer { padding: 14px 10px; }

        /* Hover tooltip on collapsed sidebar */
        .sidebar.collapsed .sidebar-link { position: relative; }
        .sidebar.collapsed .sidebar-link:hover::after {
            content: attr(data-title);
            position: absolute;
            left: calc(100% + 8px);
            top: 50%;
            transform: translateY(-50%);
            padding: 6px 12px;
            background: #1e293b;
            color: #f1f5f9;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            border-radius: 6px;
            z-index: 1055;
            pointer-events: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .sidebar.collapsed .sidebar-group { position: relative; }
        .sidebar.collapsed .sidebar-group:hover::after {
            content: attr(data-title);
            position: absolute;
            left: calc(100% + 8px);
            top: 50%;
            transform: translateY(-50%);
            padding: 5px 10px;
            background: #334155;
            color: #94a3b8;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
            border-radius: 6px;
            z-index: 1055;
            letter-spacing: 1px;
            text-transform: uppercase;
            pointer-events: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        /* Responsive: desktop toggle button visibility */
        @media (min-width: 769px) {
            .sidebar-desktop-toggle { display: flex; }
        }

        /* Sidebar overlay (mobile) */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(8,12,24,0.55);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            z-index: 1045;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .sidebar-overlay.show { display: block; opacity: 1; }

        /* ========== TOPBAR ========== */
        .topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-w);
            right: 0;
            height: var(--topbar-h);
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(14px) saturate(1.3);
            -webkit-backdrop-filter: blur(14px) saturate(1.3);
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 26px;
            z-index: 1040;
            transition: left 0.35s var(--ease);
        }

        .topbar-left { display: flex; align-items: center; gap: 14px; }

        .sidebar-toggle {
            background: none; border: none;
            font-size: 17px; cursor: pointer;
            color: var(--text-secondary);
            display: none;
            width: 36px; height: 36px;
            border-radius: var(--radius-sm);
            transition: all 0.2s var(--ease);
            align-items: center; justify-content: center;
        }
        .sidebar-toggle:hover { background: var(--accent-soft); color: var(--accent); }

        .topbar-page-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.3px;
        }

        .topbar-right { display: flex; align-items: center; gap: 6px; }

        .topbar-btn {
            width: 36px; height: 36px;
            border-radius: var(--radius-sm);
            border: 1px solid transparent;
            background: transparent;
            color: var(--text-muted);
            font-size: 15px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s var(--ease);
            position: relative;
        }
        .topbar-btn:hover { background: var(--accent-soft); color: var(--accent); border-color: rgba(16,185,129,0.12); }
        .topbar-badge {
            position: absolute; top: 6px; right: 6px;
            width: 7px; height: 7px;
            background: var(--danger);
            border-radius: 50%;
            border: 1.5px solid #fff;
        }

        .topbar-divider {
            width: 1px; height: 26px;
            background: var(--card-border);
            margin: 0 8px;
        }

        .topbar-user {
            display: flex; align-items: center; gap: 9px;
            padding: 5px 12px 5px 5px;
            border-radius: var(--radius);
            cursor: default;
            transition: background 0.2s var(--ease);
        }

        .topbar-user-avatar {
            width: 33px; height: 33px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--accent), #34d399);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 12px; font-weight: 700;
            letter-spacing: 0.3px;
        }

        .topbar-user-info { line-height: 1.2; }
        .topbar-user-name { font-size: 13px; font-weight: 600; color: var(--text-primary); }
        .topbar-user-role {
            font-size: 10px; color: var(--text-muted);
            font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .topbar-logout {
            padding: 6px 14px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--card-border);
            background: #fff;
            color: var(--text-muted);
            font-size: 12.5px;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s var(--ease);
            display: flex; align-items: center; gap: 6px;
        }
        .topbar-logout:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: var(--danger-soft);
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            margin-left: var(--sidebar-w);
            margin-top: var(--topbar-h);
            padding: 26px;
            min-height: calc(100vh - var(--topbar-h));
            position: relative;
            z-index: 1;
            transition: margin-left 0.35s var(--ease);
        }

        /* ========== BREADCRUMB ========== */
        .breadcrumb-custom {
            font-size: 12px;
            margin-bottom: 18px;
            color: var(--text-muted);
            display: flex; align-items: center; gap: 6px;
            font-weight: 500;
        }
        .breadcrumb-custom a { color: var(--text-muted); text-decoration: none; transition: color 0.2s; }
        .breadcrumb-custom a:hover { color: var(--accent); }
        .breadcrumb-custom .bc-sep { font-size: 9px; opacity: 0.4; }
        .breadcrumb-custom .bc-current { color: var(--text-primary); font-weight: 600; }

        /* ========== CARDS ========== */
        .card-custom {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--card-shadow);
            transition: box-shadow 0.3s ease;
            overflow: hidden;
        }
        .card-custom:hover { box-shadow: var(--card-shadow-hover); }

        .card-header-custom {
            padding: 15px 22px;
            border-bottom: 1px solid var(--card-border);
            font-weight: 700;
            font-size: 14.5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: var(--text-primary);
            letter-spacing: -0.2px;
            background: rgba(248,250,252,0.4);
        }
        .card-body-custom { padding: 22px; }

        /* ========== STAT CARDS ========== */
        .stat-card {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: -20px; right: -20px;
            width: 90px; height: 90px;
            border-radius: 50%;
            opacity: 0.06;
            transition: opacity 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--card-shadow-hover); }
        .stat-card:hover::after { opacity: 0.12; }
        .stat-card.accent-green::after { background: var(--success); }
        .stat-card.accent-blue::after { background: var(--info); }
        .stat-card.accent-amber::after { background: var(--warning); }
        .stat-card.accent-red::after { background: var(--danger); }

        .stat-icon {
            width: 42px; height: 42px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 17px; margin-bottom: 14px;
        }
        .stat-icon.green { background: var(--success-soft); color: var(--success); }
        .stat-icon.blue { background: var(--info-soft); color: var(--info); }
        .stat-icon.amber { background: var(--warning-soft); color: var(--warning); }
        .stat-icon.red { background: var(--danger-soft); color: var(--danger); }

        .stat-value {
            font-size: 26px; font-weight: 800;
            color: var(--text-primary);
            line-height: 1; letter-spacing: -0.5px;
        }
        .stat-label {
            font-size: 12px; color: var(--text-muted);
            margin-top: 5px; font-weight: 500;
        }

        /* ========== TABLE ========== */
        .table-custom { font-size: 13.5px; margin: 0; }
        .table-custom thead th {
            background: #f8fafc;
            color: var(--text-muted);
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 1px solid var(--card-border);
            padding: 11px 16px;
            white-space: nowrap;
        }
        .table-custom tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text-secondary);
            vertical-align: middle;
        }
        .table-custom tbody tr { transition: background 0.15s; }
        .table-custom tbody tr:hover { background: rgba(16,185,129,0.02); }
        .table-custom tbody tr:last-child td { border-bottom: none; }

        /* ========== STATUS BADGES ========== */
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 10px; border-radius: 20px;
            font-size: 11.5px; font-weight: 600; letter-spacing: 0.2px;
        }
        .status-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
        .status-active { background: var(--success-soft); color: #065f46; }
        .status-active::before { background: var(--success); }
        .status-inactive { background: var(--danger-soft); color: #7f1d1d; }
        .status-inactive::before { background: var(--danger); }
        .status-pending { background: var(--warning-soft); color: #78350f; }
        .status-pending::before { background: var(--warning); }

        /* Legacy text-based status (backward compat) */
        .status-active-text { color: #059669; font-weight: 600; }
        .status-inactive-text { color: #dc2626; font-weight: 600; }

        /* ========== BUTTONS ========== */
        .btn-accent {
            background: var(--accent); color: #fff; border: none;
            padding: 8px 18px; border-radius: var(--radius-sm);
            font-size: 13px; font-weight: 600; font-family: inherit;
            cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.2s var(--ease);
            box-shadow: 0 2px 8px var(--accent-glow);
        }
        .btn-accent:hover {
            background: var(--accent-hover);
            box-shadow: 0 4px 14px var(--accent-glow);
            transform: translateY(-1px);
        }

        .btn-ghost {
            background: transparent; color: var(--text-secondary);
            border: 1px solid var(--card-border);
            padding: 7px 14px; border-radius: var(--radius-sm);
            font-size: 13px; font-weight: 600; font-family: inherit;
            cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.2s var(--ease);
        }
        .btn-ghost:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }

        .btn-icon {
            width: 32px; height: 32px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--card-border);
            background: #fff; color: var(--text-muted);
            font-size: 13px; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
            transition: all 0.2s var(--ease);
        }
        .btn-icon:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }
        .btn-icon.danger:hover { border-color: var(--danger); color: var(--danger); background: var(--danger-soft); }

        /* Legacy btn-action compat */
        .btn-action { padding: 4px 10px; font-size: 13px; }

        /* ========== PAGINATION ========== */
        .pagination { justify-content: center; }
        .page-link {
            border-radius: var(--radius-sm);
            border: 1px solid var(--card-border);
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            margin: 0 2px;
            transition: all 0.2s var(--ease);
        }
        .page-link:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: var(--accent-soft);
        }
        .page-item.active .page-link {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
            box-shadow: 0 2px 8px var(--accent-glow);
        }

        /* ========== FILTER BAR ========== */
        .filter-bar {
            display: flex; align-items: center; gap: 10px;
            padding: 14px 18px;
            background: #f8fafc;
            border-radius: var(--radius);
            border: 1px solid var(--card-border);
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .filter-bar .form-control,
        .filter-bar .form-select { padding: 7px 12px; font-size: 12.5px; min-width: 160px; }

        /* ========== PAGE HEADER ========== */
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 22px; flex-wrap: wrap; gap: 12px;
        }
        .page-header-title {
            font-size: 22px; font-weight: 800;
            color: var(--text-primary); letter-spacing: -0.5px;
        }
        .page-header-sub { font-size: 13px; color: var(--text-muted); margin-top: 2px; font-weight: 500; }
        .page-header-actions { display: flex; gap: 8px; align-items: center; }

        /* ========== FORM ========== */
        .form-control, .form-select {
            border: 1px solid var(--card-border);
            border-radius: var(--radius-sm);
            padding: 9px 14px;
            font-size: 13.5px;
            font-family: inherit;
            color: var(--text-primary);
            transition: all 0.2s var(--ease);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }
        .form-label {
            font-size: 12px; font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ========== EMPTY STATE ========== */
        .empty-state { text-align: center; padding: 48px 20px; color: var(--text-muted); }
        .empty-state i { font-size: 40px; opacity: 0.25; margin-bottom: 14px; display: block; }
        .empty-state p { font-size: 14px; font-weight: 500; }

        /* ========== AVATAR ========== */
        .avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #34d399);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            flex-shrink: 0;
            letter-spacing: 0.3px;
            object-fit: cover;
        }

        /* ========== MODAL ========== */
        .modal-content { border: none; border-radius: var(--radius-lg); box-shadow: 0 24px 48px rgba(0,0,0,0.12); }
        .modal-header { border-bottom: 1px solid var(--card-border); padding: 18px 24px; }
        .modal-title { font-size: 16px; font-weight: 700; letter-spacing: -0.3px; }
        .modal-body { padding: 24px; }
        .modal-footer { border-top: 1px solid var(--card-border); padding: 14px 24px; }

        /* ========== TOASTR OVERRIDES ========== */
        .toast-top-right { top: 72px !important; right: 20px !important; }
        .toast { border-radius: var(--radius) !important; box-shadow: 0 8px 24px rgba(0,0,0,0.1) !important; font-family: 'Plus Jakarta Sans', sans-serif !important; }
        .toast-success { background: #f0fdf4 !important; border: 1px solid #bbf7d0 !important; color: #065f46 !important; }
        .toast-success .toast-close-button { color: #059669 !important; }
        .toast-error { background: #fef2f2 !important; border: 1px solid #fecaca !important; color: #7f1d1d !important; }
        .toast-error .toast-close-button { color: #dc2626 !important; }
        .toast-warning { background: #fffbeb !important; border: 1px solid #fde68a !important; color: #78350f !important; }
        .toast-warning .toast-close-button { color: #d97706 !important; }
        .toast-info { background: #eff6ff !important; border: 1px solid #bfdbfe !important; color: #1e40af !important; }
        .toast-info .toast-close-button { color: #2563eb !important; }
        #toast-container > .toast { background-image: none !important; padding: 14px 16px !important; }
        #toast-container > .toast:before { display: none !important; }

        /* ========== SWEETALERT2 OVERRIDES ========== */
        .swal2-popup { font-family: 'Plus Jakarta Sans', sans-serif !important; border-radius: var(--radius-lg) !important; }
        .swal2-title { font-weight: 700 !important; letter-spacing: -0.3px !important; }
        .swal2-html-container { font-size: 14px !important; color: var(--text-secondary) !important; }
        .swal2-confirm { font-weight: 600 !important; font-family: 'Plus Jakarta Sans', sans-serif !important; border-radius: var(--radius-sm) !important; }
        .swal2-cancel { font-weight: 600 !important; font-family: 'Plus Jakarta Sans', sans-serif !important; border-radius: var(--radius-sm) !important; }

        /* ========== ANIMATIONS ========== */
        .fade-in { animation: fadeIn 0.4s ease both; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .stagger-1 { animation-delay: 0.05s; }
        .stagger-2 { animation-delay: 0.1s; }
        .stagger-3 { animation-delay: 0.15s; }
        .stagger-4 { animation-delay: 0.2s; }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .topbar-user-info { display: none; }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .sidebar-toggle { display: flex; }
            .topbar { left: 0; padding: 0 16px; }
            .main-content { margin-left: 0; padding: 20px 16px; }
            .topbar-logout span { display: none; }
            .topbar-divider:last-of-type { display: none; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }

        @media (max-width: 480px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar .form-control, .filter-bar .form-select { min-width: 100%; }
        }
    </style>
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    @yield('styles')
</head>
<body>

    <!-- Sidebar Overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fa-solid fa-cubes"></i>
            </div>
            <div class="sidebar-brand">
                <span class="sidebar-brand-name">ERP System</span>
                <span class="sidebar-brand-sub">Enterprise</span>
            </div>
        </div>

        <div class="sidebar-scroll">
            <nav class="sidebar-nav">
                @php
                    $user = Auth::user();
                    $accessibleModuleIds = \App\Models\UserAccessControl::where('user_id', $user->id)
                        ->where('can_read', true)
                        ->pluck('module_id')
                        ->toArray();
                    $modules = \App\Models\Module::orderBy('group')->orderBy('module_name')->get()
                        ->filter(fn($m) => in_array($m->id, $accessibleModuleIds) || $user->role === 'Admin');
                @endphp

                @php $currentGroup = ''; @endphp
                @foreach ($modules as $module)
                    @if ($module->group !== $currentGroup)
                        @php $currentGroup = $module->group; @endphp
                        <div class="sidebar-group" data-title="{{ $currentGroup }}">{{ $currentGroup }}</div>
                    @endif
                    <a href="{{ $module->route_name ? route($module->route_name . '.index') : '#' }}"
                       class="sidebar-link {{ str_starts_with(request()->route()->getName(), $module->route_name) ? 'active' : '' }}"
                       data-title="{{ $module->module_name }}">
                        <i class="sidebar-icon {{ $module->icon ?? 'fa-solid fa-circle' }}"></i>
                        <span>{{ $module->module_name }}</span>
                    </a>
                @endforeach
            </nav>
        </div>

        <div class="sidebar-footer">
            <div class="sidebar-footer-info">
                <div class="sidebar-avatar">
                    {{ strtoupper(substr(Auth::user()->username, 0, 2)) }}
                </div>
                <div class="sidebar-footer-text">
                    <div class="sidebar-footer-name">{{ Auth::user()->username }}</div>
                    <div class="sidebar-footer-role">{{ Auth::user()->role }}</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Topbar -->
    <header class="topbar" id="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <button class="sidebar-desktop-toggle" id="sidebarDesktopToggle" title="Collapse sidebar">
                <i class="fa-solid fa-angles-left"></i>
            </button>
            <span class="topbar-page-title">@yield('page-title', 'Dashboard')</span>
        </div>
        <div class="topbar-right">
            <button class="topbar-btn" title="Notifikasi" aria-label="Notifications">
                <i class="fa-regular fa-bell"></i>
                <span class="topbar-badge"></span>
            </button>
            <button class="topbar-btn" title="Pengaturan" aria-label="Settings">
                <i class="fa-solid fa-gear"></i>
            </button>
            <div class="topbar-divider"></div>
            <div class="topbar-user">
                <div class="topbar-user-avatar">
                    {{ strtoupper(substr(Auth::user()->username, 0, 2)) }}
                </div>
                <div class="topbar-user-info">
                    <div class="topbar-user-name">{{ Auth::user()->username }}</div>
                    <div class="topbar-user-role">{{ Auth::user()->role }}</div>
                </div>
            </div>
            <div class="topbar-divider"></div>
            <form method="POST" action="{{ route('logout') }}" class="d-inline" id="logout-form">
                @csrf
                <button type="button" class="topbar-logout" id="btn-logout" title="Keluar">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content" id="main-content">
        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // --- Sidebar toggle ---
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const toggle  = document.getElementById('sidebarToggle');

        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }

        toggle.addEventListener('click', function() {
            sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
        });
        overlay.addEventListener('click', closeSidebar);
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
        });

        // --- Desktop sidebar collapse ---
        const DKEY = 'erp_sidebar_collapsed';
        const topbar = document.getElementById('topbar');
        const mainContent = document.getElementById('main-content');
        const desktopBtn = document.getElementById('sidebarDesktopToggle');

        function desktopSidebarIcon(collapsed) {
            if (!desktopBtn) return;
            desktopBtn.innerHTML = collapsed
                ? '<i class="fa-solid fa-angles-right"></i>'
                : '<i class="fa-solid fa-angles-left"></i>';
        }

        function applyCollapsed(state) {
            if (state) {
                sidebar.classList.add('collapsed');
                if (topbar) topbar.classList.add('collapsed');
                if (mainContent) mainContent.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
                if (topbar) topbar.classList.remove('collapsed');
                if (mainContent) mainContent.classList.remove('collapsed');
            }
            desktopSidebarIcon(state);
        }

        function toggleDesktop() {
            var collapsed = !sidebar.classList.contains('collapsed');
            applyCollapsed(collapsed);
            try { localStorage.setItem(DKEY, collapsed); } catch(e) {}
        }

        if (desktopBtn) {
            desktopBtn.addEventListener('click', toggleDesktop);
        }

        function handleResize() {
            var isDesktop = window.matchMedia('(min-width: 769px)').matches;
            if (isDesktop) {
                var saved = localStorage.getItem(DKEY) === 'true';
                applyCollapsed(saved);
            } else {
                applyCollapsed(false);
            }
        }

        handleResize();
        window.addEventListener('resize', function() {
            clearTimeout(window._resizeTimer);
            window._resizeTimer = setTimeout(handleResize, 150);
        });

        // --- Toastr config ---
        toastr.options = {
            positionClass: 'toast-top-right',
            progressBar: true,
            timeOut: 3000,
            closeButton: true,
            preventDuplicates: true,
            showDuration: 200,
            hideDuration: 200,
        };

        @if(session('success'))
            toastr.success('{{ session("success") }}');
        @endif
        @if(session('error'))
            toastr.error('{{ session("error") }}');
        @endif

        // --- Logout confirmation ---
        $('#btn-logout').on('click', function() {
            Swal.fire({
                title: 'Logout?',
                text: 'Anda akan keluar dari sistem.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, logout',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
            }).then((result) => {
                if (result.isConfirmed) $('#logout-form').submit();
            });
        });

        // --- Delete confirmation ---
        $(document).on('click', '.btn-delete', function() {
            const form = $(this).closest('form');
            Swal.fire({
                title: 'Yakin?',
                text: 'Data yang dihapus tidak dapat dikembalikan!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                reverseButtons: true,
            }).then((result) => {
                if (result.isConfirmed) form.submit();
            });
        });
    </script>
    @yield('scripts')
    @stack('modals')

</body>
</html>
