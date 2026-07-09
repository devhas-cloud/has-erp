<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ERP System')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --sidebar-w: 264px;
            --sidebar-collapsed-w: 72px;
            --topbar-h: 64px;
            --accent: #10b981;
            --accent-hover: #059669;
            --accent-soft: rgba(16, 185, 129, 0.08);
            --accent-glow: rgba(16, 185, 129, 0.3);
            --accent-subtle: rgba(16, 185, 129, 0.04);

            /* ===== SIDEBAR COLOR SYSTEM ===== */
            --sidebar-bg: #070b14;
            --sidebar-bg-mid: #0a1020;
            --sidebar-bg-top: #0e1528;
            --sidebar-surface: rgba(255, 255, 255, 0.025);
            --sidebar-surface-hover: rgba(255, 255, 255, 0.045);
            --sidebar-hover: rgba(255, 255, 255, 0.035);
            --sidebar-border: rgba(255, 255, 255, 0.04);
            --sidebar-text: #4f6280;
            --sidebar-text-hover: #8da0bc;
            --sidebar-text-active: #eaf0f9;
            --sidebar-active-start: rgba(16, 185, 129, 0.14);
            --sidebar-active-end: rgba(16, 185, 129, 0.02);
            --sidebar-indicator-top: #34d399;
            --sidebar-indicator-bot: #10b981;
            --sidebar-glow-accent: rgba(16, 185, 129, 0.08);
            --sidebar-glow-indigo: rgba(99, 102, 241, 0.06);
            --sidebar-group-color: #1e2d44;
            --sidebar-divider: rgba(255, 255, 255, 0.04);
            --sidebar-footer-bg: rgba(255, 255, 255, 0.02);
            --sidebar-footer-border: rgba(255, 255, 255, 0.05);

            /* ===== REST ===== */
            --bg: #f4f6fb;
            --bg-dot: rgba(148, 163, 184, 0.08);
            --card: #ffffff;
            --card-border: rgba(0, 0, 0, 0.06);
            --card-shadow: 0 1px 3px rgba(0, 0, 0, 0.03), 0 4px 12px rgba(0, 0, 0, 0.03);
            --card-shadow-hover: 0 8px 30px rgba(0, 0, 0, 0.07), 0 2px 8px rgba(0, 0, 0, 0.04);
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --danger: #ef4444;
            --danger-soft: rgba(239, 68, 68, 0.07);
            --success: #10b981;
            --success-soft: rgba(16, 185, 129, 0.07);
            --warning: #f59e0b;
            --warning-soft: rgba(245, 158, 11, 0.07);
            --info: #6366f1;
            --info-soft: rgba(99, 102, 241, 0.07);
            --radius: 14px;
            --radius-sm: 10px;
            --radius-lg: 18px;
            --radius-xl: 22px;
            --ease: cubic-bezier(0.4, 0, 0.2, 1);
            --ease-bounce: cubic-bezier(0.34, 1.56, 0.64, 1);
            --glass-bg: rgba(255, 255, 255, 0.65);
            --glass-border: rgba(255, 255, 255, 0.5);
            --glass-blur: blur(20px) saturate(1.4);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            margin: 0;
            min-height: 100vh;
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: radial-gradient(var(--bg-dot) 1px, transparent 1px);
            background-size: 24px 24px;
            pointer-events: none;
            z-index: 0;
        }

        body::after {
            content: '';
            position: fixed;
            top: -40%;
            right: -20%;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.06) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            animation: floatBlob 20s ease-in-out infinite alternate;
        }

        @keyframes floatBlob {
            0% {
                transform: translate(0, 0) scale(1);
            }

            33% {
                transform: translate(-60px, 40px) scale(1.1);
            }

            66% {
                transform: translate(30px, -30px) scale(0.95);
            }

            100% {
                transform: translate(-20px, 20px) scale(1.05);
            }
        }

        .bg-blob-secondary {
            position: fixed;
            bottom: -30%;
            left: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            animation: floatBlob2 25s ease-in-out infinite alternate;
        }

        @keyframes floatBlob2 {
            0% {
                transform: translate(0, 0) scale(1);
            }

            50% {
                transform: translate(50px, -40px) scale(1.15);
            }

            100% {
                transform: translate(-30px, 30px) scale(0.9);
            }
        }

        /* ================================================================
       SIDEBAR — COMPLETE COLOR REDESIGN
       ================================================================ */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-w);
            /* Multi-stop gradient: lightest at top → darkest at bottom */
            background: linear-gradient(180deg,
                    var(--sidebar-bg-top) 0%,
                    var(--sidebar-bg-mid) 25%,
                    var(--sidebar-bg) 60%,
                    #050810 100%);
            display: flex;
            flex-direction: column;
            z-index: 1050;
            transition: width 0.35s var(--ease), transform 0.35s var(--ease);
            overflow: hidden;
        }

        /* Ambient radial glow — emerald spotlight from top center */
        .sidebar::before {
            content: '';
            position: absolute;
            top: -40px;
            left: 50%;
            transform: translateX(-50%);
            width: 280px;
            height: 220px;
            background: radial-gradient(ellipse at center,
                    rgba(16, 185, 129, 0.10) 0%,
                    rgba(16, 185, 129, 0.04) 40%,
                    transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* Right-edge glow: emerald → indigo → emerald gradient line */
        .sidebar::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 1px;
            background: linear-gradient(to bottom,
                    transparent 0%,
                    rgba(52, 211, 153, 0.35) 15%,
                    rgba(16, 185, 129, 0.45) 30%,
                    rgba(99, 102, 241, 0.20) 55%,
                    rgba(16, 185, 129, 0.30) 75%,
                    rgba(52, 211, 153, 0.20) 90%,
                    transparent 100%);
            opacity: 0.6;
            transition: opacity 0.4s ease;
            z-index: 1;
        }

        .sidebar:hover::after {
            opacity: 1;
        }

        /* Subtle bottom ambient glow — indigo warmth */
        .sidebar-bottom-glow {
            position: absolute;
            bottom: -60px;
            left: 50%;
            transform: translateX(-50%);
            width: 240px;
            height: 180px;
            background: radial-gradient(ellipse at center,
                    rgba(99, 102, 241, 0.06) 0%,
                    transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .sidebar-scroll {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.04) transparent;
            position: relative;
            z-index: 1;
        }

        .sidebar-scroll::-webkit-scrollbar {
            width: 3px;
        }

        .sidebar-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(16, 185, 129, 0.08);
            border-radius: 3px;
        }

        .sidebar-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(16, 185, 129, 0.15);
        }

        /* ===== SIDEBAR HEADER ===== */
        .sidebar-header {
            padding: 22px 18px;
            border-bottom: 1px solid var(--sidebar-divider);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }

        .sidebar-logo {
            width: 40px;
            height: 40px;
            /* Triple-stop gradient for richer depth */
            background: linear-gradient(135deg,
                    #059669 0%,
                    #10b981 40%,
                    #34d399 70%,
                    #6ee7b7 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 16px;
            box-shadow:
                0 4px 18px rgba(16, 185, 129, 0.35),
                0 0 0 1px rgba(52, 211, 153, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.15);
            flex-shrink: 0;
            transition: transform 0.3s var(--ease-bounce), box-shadow 0.3s ease;
        }

        .sidebar-logo:hover {
            transform: scale(1.08) rotate(-2deg);
            box-shadow:
                0 6px 28px rgba(16, 185, 129, 0.45),
                0 0 0 1px rgba(52, 211, 153, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .sidebar-brand {
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand-name {
            font-size: 16px;
            font-weight: 800;
            color: #eaf0f9;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }

        .sidebar-brand-sub {
            font-size: 9.5px;
            color: var(--sidebar-text);
            letter-spacing: 2px;
            text-transform: uppercase;
            font-weight: 700;
            margin-top: 3px;
        }

        /* ===== SIDEBAR NAV ===== */
        .sidebar-nav {
            padding: 8px 0 16px;
        }

        .sidebar-group {
            padding: 22px 18px 7px;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 2.5px;
            /* Slightly tinted with accent for freshness */
            color: #1c2c46;
            font-weight: 800;
            position: relative;
        }

        /* ===== SIDEBAR LINKS ===== */
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            margin: 2px 10px;
            color: var(--sidebar-text);
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.25s var(--ease);
            position: relative;
            overflow: hidden;
        }

        /* Left indicator bar — gradient emerald */
        .sidebar-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%) scaleY(0);
            width: 3px;
            height: 22px;
            background: linear-gradient(to bottom,
                    var(--sidebar-indicator-top),
                    var(--sidebar-indicator-bot));
            border-radius: 0 4px 4px 0;
            transition: transform 0.3s var(--ease-bounce);
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);
            z-index: 2;
        }

        /* Active/hover gradient wash — left to right fade */
        .sidebar-link::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg,
                    var(--sidebar-active-start),
                    var(--sidebar-active-end));
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: inherit;
        }

        /* Hover state — subtle luminous lift */
        .sidebar-link:hover {
            color: var(--sidebar-text-hover);
            background: var(--sidebar-hover);
        }

        .sidebar-link:hover .sidebar-icon {
            color: var(--sidebar-text-hover);
            transform: translateX(1px);
        }

        /* Active state — fully lit */
        .sidebar-link.active {
            color: var(--sidebar-text-active);
        }

        .sidebar-link.active::before {
            transform: translateY(-50%) scaleY(1);
        }

        .sidebar-link.active::after {
            opacity: 1;
        }

        .sidebar-link.active .sidebar-icon {
            color: #34d399;
            filter: drop-shadow(0 0 8px rgba(52, 211, 153, 0.6));
        }

        .sidebar-icon {
            width: 20px;
            text-align: center;
            font-size: 15px;
            transition: all 0.25s var(--ease);
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }

        .sidebar-link span {
            position: relative;
            z-index: 1;
        }

        /* ===== SIDEBAR FOOTER ===== */
        .sidebar-footer {
            padding: 14px 14px;
            border-top: 1px solid var(--sidebar-footer-border);
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }

        .sidebar-footer-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            /* Frosted glass within dark context */
            background: var(--sidebar-footer-bg);
            border: 1px solid var(--sidebar-divider);
            transition: all 0.3s ease;
        }

        .sidebar-footer-info:hover {
            border-color: rgba(16, 185, 129, 0.12);
            background: rgba(16, 185, 129, 0.03);
        }

        .sidebar-avatar {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: linear-gradient(135deg,
                    #059669 0%,
                    #10b981 50%,
                    #34d399 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            flex-shrink: 0;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 12px rgba(16, 185, 129, 0.35);
        }

        .sidebar-footer-text {
            line-height: 1.3;
        }

        .sidebar-footer-name {
            font-size: 12.5px;
            font-weight: 700;
            color: #d8e2f0;
        }

        .sidebar-footer-role {
            font-size: 10px;
            color: #3a4e6a;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        /* ========== SIDEBAR COLLAPSED ========== */
        .sidebar-desktop-toggle {
            display: none;
            width: 38px;
            height: 38px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--card-border);
            background: var(--card);
            color: var(--text-muted);
            cursor: pointer;
            font-size: 13px;
            align-items: center;
            justify-content: center;
            transition: all 0.25s var(--ease);
            flex-shrink: 0;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
        }

        .sidebar-desktop-toggle:hover {
            background: var(--accent-soft);
            color: var(--accent);
            border-color: rgba(16, 185, 129, 0.2);
            box-shadow: 0 2px 10px rgba(16, 185, 129, 0.1);
            transform: scale(1.05);
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-w);
        }

        .sidebar.collapsed .sidebar-link {
            justify-content: center;
            padding: 12px 0;
            margin: 4px 14px;
        }

        .sidebar.collapsed .sidebar-link span {
            display: none;
        }

        .sidebar.collapsed .sidebar-link::before {
            display: none;
        }

        .sidebar.collapsed .sidebar-link::after {
            display: none;
        }

        .sidebar.collapsed .sidebar-icon {
            font-size: 18px;
            width: auto;
        }

        .sidebar.collapsed .sidebar-brand {
            display: none;
        }

        .sidebar.collapsed .sidebar-header {
            justify-content: center;
            padding: 20px 0;
        }

        .sidebar.collapsed .sidebar-logo {
            width: 44px;
            height: 44px;
            border-radius: 13px;
            font-size: 18px;
        }

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
            background: rgba(16, 185, 129, 0.08);
        }

        .sidebar.collapsed .sidebar-footer-text {
            display: none;
        }

        .sidebar.collapsed .sidebar-footer-info {
            justify-content: center;
            padding: 10px;
        }

        .sidebar.collapsed .sidebar-footer {
            padding: 14px 10px;
        }

        .sidebar.collapsed .sidebar-link:hover::after {
            content: attr(data-title);
            position: absolute;
            left: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            padding: 7px 14px;
            background: linear-gradient(135deg, #141c30, #1a2540);
            color: #e8edf5;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            border-radius: 8px;
            z-index: 1055;
            pointer-events: none;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.06);
            animation: tooltipIn 0.2s var(--ease-bounce) both;
        }

        @keyframes tooltipIn {
            from {
                opacity: 0;
                transform: translateY(-50%) translateX(-4px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(-50%) translateX(0) scale(1);
            }
        }

        .sidebar.collapsed .sidebar-group:hover::after {
            content: attr(data-title);
            position: absolute;
            left: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            padding: 5px 10px;
            background: #1a2540;
            color: #5a7090;
            font-size: 9.5px;
            font-weight: 800;
            white-space: nowrap;
            border-radius: 6px;
            z-index: 1055;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            pointer-events: none;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
            animation: tooltipIn 0.2s var(--ease-bounce) both;
        }

        @media (min-width: 769px) {
            .sidebar-desktop-toggle {
                display: flex;
            }
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(5, 8, 16, 0.7);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 1045;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }

        /* ========== TOPBAR ========== */
        .topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-w);
            right: 0;
            height: var(--topbar-h);
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            z-index: 1040;
            transition: left 0.35s var(--ease);
        }

        .topbar.collapsed {
            left: var(--sidebar-collapsed-w);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            font-size: 17px;
            cursor: pointer;
            color: var(--text-secondary);
            display: none;
            width: 38px;
            height: 38px;
            border-radius: var(--radius-sm);
            transition: all 0.25s var(--ease);
            align-items: center;
            justify-content: center;
        }

        .sidebar-toggle:hover {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .topbar-page-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.4px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .topbar-btn {
            width: 38px;
            height: 38px;
            border-radius: var(--radius-sm);
            border: 1px solid transparent;
            background: transparent;
            color: var(--text-muted);
            font-size: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.25s var(--ease);
            position: relative;
        }

        .topbar-btn:hover {
            background: var(--accent-soft);
            color: var(--accent);
            border-color: rgba(16, 185, 129, 0.1);
            transform: translateY(-1px);
        }

        .topbar-badge {
            position: absolute;
            top: 7px;
            right: 7px;
            width: 7px;
            height: 7px;
            background: var(--danger);
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.9);
            animation: badgePulse 2s ease-in-out infinite;
        }

        @keyframes badgePulse {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
            }

            50% {
                box-shadow: 0 0 0 4px rgba(239, 68, 68, 0);
            }
        }

        .topbar-divider {
            width: 1px;
            height: 28px;
            background: linear-gradient(to bottom, transparent, var(--card-border), transparent);
            margin: 0 10px;
        }

        .topbar-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 14px 5px 5px;
            border-radius: var(--radius);
            cursor: default;
            transition: all 0.25s var(--ease);
            border: 1px solid transparent;
        }

        .topbar-user:hover {
            background: rgba(16, 185, 129, 0.03);
            border-color: rgba(16, 185, 129, 0.08);
        }

        .topbar-user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), #34d399);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.3px;
            box-shadow: 0 2px 8px var(--accent-glow);
        }

        .topbar-user-info {
            line-height: 1.25;
        }

        .topbar-user-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .topbar-user-role {
            font-size: 10px;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .topbar-logout {
            padding: 7px 16px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--card-border);
            background: var(--card);
            color: var(--text-muted);
            font-size: 12.5px;
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s var(--ease);
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .topbar-logout:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: var(--danger-soft);
            box-shadow: 0 2px 10px rgba(239, 68, 68, 0.12);
            transform: translateY(-1px);
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            margin-left: var(--sidebar-w);
            margin-top: var(--topbar-h);
            padding: 28px;
            min-height: calc(100vh - var(--topbar-h));
            position: relative;
            z-index: 1;
            transition: margin-left 0.35s var(--ease);
        }

        .main-content.collapsed {
            margin-left: var(--sidebar-collapsed-w);
        }

        /* ========== BREADCRUMB ========== */
        .breadcrumb-custom {
            font-size: 12px;
            margin-bottom: 18px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .breadcrumb-custom a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
            padding: 2px 0;
            border-bottom: 1px solid transparent;
        }

        .breadcrumb-custom a:hover {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }

        .breadcrumb-custom .bc-sep {
            font-size: 8px;
            opacity: 0.35;
        }

        .breadcrumb-custom .bc-current {
            color: var(--text-primary);
            font-weight: 700;
        }

        /* ========== CARDS ========== */
        .card-custom {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--card-shadow);
            transition: all 0.35s var(--ease);
            overflow: hidden;
            position: relative;
        }

        .card-custom::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.2), transparent);
            opacity: 0;
            transition: opacity 0.35s ease;
        }

        .card-custom:hover {
            box-shadow: var(--card-shadow-hover);
            transform: translateY(-2px);
        }

        .card-custom:hover::before {
            opacity: 1;
        }

        .card-header-custom {
            padding: 16px 24px;
            border-bottom: 1px solid var(--card-border);
            font-weight: 800;
            font-size: 14.5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: var(--text-primary);
            letter-spacing: -0.3px;
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.6) 0%, rgba(248, 250, 252, 0.2) 100%);
        }

        .card-body-custom {
            padding: 24px;
        }

        /* ========== STAT CARDS ========== */
        .stat-card {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            padding: 22px;
            box-shadow: var(--card-shadow);
            transition: all 0.35s var(--ease);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            opacity: 0;
            transition: opacity 0.35s ease;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            opacity: 0.04;
            transition: all 0.4s var(--ease);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.08), 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card:hover::after {
            opacity: 0.1;
            transform: scale(1.2);
        }

        .stat-card.accent-green::before {
            background: linear-gradient(90deg, #10b981, #34d399);
        }

        .stat-card.accent-green::after {
            background: var(--success);
        }

        .stat-card.accent-blue::before {
            background: linear-gradient(90deg, #6366f1, #818cf8);
        }

        .stat-card.accent-blue::after {
            background: var(--info);
        }

        .stat-card.accent-amber::before {
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
        }

        .stat-card.accent-amber::after {
            background: var(--warning);
        }

        .stat-card.accent-red::before {
            background: linear-gradient(90deg, #ef4444, #f87171);
        }

        .stat-card.accent-red::after {
            background: var(--danger);
        }

        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 16px;
            transition: transform 0.3s var(--ease-bounce);
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.08) rotate(-3deg);
        }

        .stat-icon.green {
            background: var(--success-soft);
            color: var(--success);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
        }

        .stat-icon.blue {
            background: var(--info-soft);
            color: var(--info);
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.1);
        }

        .stat-icon.amber {
            background: var(--warning-soft);
            color: var(--warning);
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.1);
        }

        .stat-icon.red {
            background: var(--danger-soft);
            color: var(--danger);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.1);
        }

        .stat-value {
            font-size: 28px;
            font-weight: 900;
            color: var(--text-primary);
            line-height: 1;
            letter-spacing: -1px;
        }

        .stat-label {
            font-size: 12.5px;
            color: var(--text-muted);
            margin-top: 6px;
            font-weight: 600;
        }

        /* ========== TABLE ========== */
        .table-custom {
            font-size: 13.5px;
            margin: 0;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-custom thead th {
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            color: var(--text-muted);
            font-weight: 800;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--card-border);
            padding: 12px 18px;
            white-space: nowrap;
            position: sticky;
            top: 0;
        }

        .table-custom thead th:first-child {
            border-radius: var(--radius) 0 0 0;
        }

        .table-custom thead th:last-child {
            border-radius: 0 var(--radius) 0 0;
        }

        .table-custom tbody td {
            padding: 14px 18px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.03);
            color: var(--text-secondary);
            vertical-align: middle;
            transition: background 0.2s ease;
        }

        .table-custom tbody tr {
            transition: all 0.2s ease;
        }

        .table-custom tbody tr:hover {
            background: linear-gradient(90deg, var(--accent-subtle), rgba(99, 102, 241, 0.02));
        }

        .table-custom tbody tr:hover td {
            color: var(--text-primary);
        }

        .table-custom tbody tr:last-child td {
            border-bottom: none;
        }

        /* ========== STATUS BADGES ========== */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
            transition: transform 0.2s var(--ease-bounce);
        }

        .status-badge:hover {
            transform: scale(1.05);
        }

        .status-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .status-active {
            background: var(--success-soft);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.12);
        }

        .status-active::before {
            background: var(--success);
            box-shadow: 0 0 6px rgba(16, 185, 129, 0.5);
            animation: dotPulse 2s ease-in-out infinite;
        }

        @keyframes dotPulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .status-inactive {
            background: var(--danger-soft);
            color: #7f1d1d;
            border: 1px solid rgba(239, 68, 68, 0.1);
        }

        .status-inactive::before {
            background: var(--danger);
        }

        .status-pending {
            background: var(--warning-soft);
            color: #78350f;
            border: 1px solid rgba(245, 158, 11, 0.1);
        }

        .status-pending::before {
            background: var(--warning);
            animation: dotPulse 2s ease-in-out infinite;
        }

        .status-active-text {
            color: #059669;
            font-weight: 700;
        }

        .status-inactive-text {
            color: #dc2626;
            font-weight: 700;
        }

        /* ========== BUTTONS ========== */
        .btn-accent {
            background: linear-gradient(135deg, var(--accent) 0%, #059669 100%);
            color: #fff;
            border: none;
            padding: 9px 20px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all 0.3s var(--ease);
            box-shadow: 0 2px 10px var(--accent-glow), 0 0 0 0 var(--accent-glow);
            position: relative;
            overflow: hidden;
        }

        .btn-accent::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.15) 50%, transparent 100%);
            transform: translateX(-100%);
            transition: transform 0.5s ease;
        }

        .btn-accent:hover::before {
            transform: translateX(100%);
        }

        .btn-accent:hover {
            box-shadow: 0 4px 18px var(--accent-glow), 0 0 0 4px rgba(16, 185, 129, 0.1);
            transform: translateY(-2px);
        }

        .btn-accent:active {
            transform: translateY(0);
            box-shadow: 0 1px 4px var(--accent-glow);
        }

        .btn-ghost {
            background: var(--card);
            color: var(--text-secondary);
            border: 1px solid var(--card-border);
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all 0.25s var(--ease);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }

        .btn-ghost:hover {
            border-color: rgba(16, 185, 129, 0.25);
            color: var(--accent);
            background: var(--accent-soft);
            box-shadow: 0 2px 10px rgba(16, 185, 129, 0.08);
            transform: translateY(-1px);
        }

        .btn-icon {
            width: 34px;
            height: 34px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--card-border);
            background: var(--card);
            color: var(--text-muted);
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.25s var(--ease);
        }

        .btn-icon:hover {
            border-color: rgba(16, 185, 129, 0.25);
            color: var(--accent);
            background: var(--accent-soft);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
            transform: translateY(-1px) scale(1.05);
        }

        .btn-icon.danger:hover {
            border-color: rgba(239, 68, 68, 0.25);
            color: var(--danger);
            background: var(--danger-soft);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.1);
        }

        .btn-action {
            padding: 5px 12px;
            font-size: 13px;
        }

        /* ========== PAGINATION ========== */
        .pagination {
            justify-content: center;
        }

        .page-link {
            border-radius: var(--radius-sm);
            border: 1px solid var(--card-border);
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 700;
            font-family: inherit;
            margin: 0 3px;
            transition: all 0.25s var(--ease);
            background: var(--card);
            min-width: 36px;
            text-align: center;
        }

        .page-link:hover {
            border-color: rgba(16, 185, 129, 0.25);
            color: var(--accent);
            background: var(--accent-soft);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, var(--accent), #059669);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 3px 12px var(--accent-glow);
            transform: translateY(-1px);
        }

        /* ========== FILTER BAR ========== */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: var(--radius);
            border: 1px solid var(--card-border);
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .filter-bar .form-control,
        .filter-bar .form-select {
            padding: 8px 14px;
            font-size: 12.5px;
            min-width: 160px;
        }

        /* ========== PAGE HEADER ========== */
        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 14px;
        }

        .page-header-title {
            font-size: 24px;
            font-weight: 900;
            color: var(--text-primary);
            letter-spacing: -0.7px;
            line-height: 1.1;
        }

        .page-header-sub {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
            font-weight: 500;
        }

        .page-header-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        /* ========== FORM ========== */
        .form-control,
        .form-select {
            border: 1.5px solid var(--card-border);
            border-radius: var(--radius-sm);
            padding: 10px 14px;
            font-size: 13.5px;
            font-family: inherit;
            color: var(--text-primary);
            transition: all 0.25s var(--ease);
            background: var(--card);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px var(--accent-soft), 0 2px 8px rgba(16, 185, 129, 0.08);
            outline: none;
        }

        .form-control::placeholder {
            color: var(--text-muted);
            font-weight: 400;
        }

        .form-label {
            font-size: 11px;
            font-weight: 800;
            color: var(--text-secondary);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 56px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 44px;
            opacity: 0.15;
            margin-bottom: 16px;
            display: block;
            animation: emptyFloat 3s ease-in-out infinite;
        }

        @keyframes emptyFloat {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        .empty-state p {
            font-size: 14px;
            font-weight: 500;
        }

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
            font-weight: 800;
            flex-shrink: 0;
            letter-spacing: 0.3px;
            object-fit: cover;
            box-shadow: 0 2px 8px var(--accent-glow);
        }

        /* ========== MODAL ========== */
        .modal-content {
            border: none;
            border-radius: var(--radius-xl);
            box-shadow: 0 32px 64px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .modal-header {
            border-bottom: 1px solid var(--card-border);
            padding: 20px 28px;
            background: linear-gradient(180deg, #f8fafc 0%, transparent 100%);
        }

        .modal-title {
            font-size: 17px;
            font-weight: 800;
            letter-spacing: -0.4px;
        }

        .modal-body {
            padding: 28px;
        }

        .modal-footer {
            border-top: 1px solid var(--card-border);
            padding: 16px 28px;
            background: rgba(248, 250, 252, 0.3);
        }

        /* ========== TOASTR ========== */
        .toast-top-right {
            top: 76px !important;
            right: 20px !important;
        }

        .toast {
            border-radius: var(--radius) !important;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12) !important;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
        }

        .toast-success {
            background: rgba(240, 253, 244, 0.92) !important;
            border: 1px solid #bbf7d0 !important;
            color: #065f46 !important;
        }

        .toast-success .toast-close-button {
            color: #059669 !important;
        }

        .toast-error {
            background: rgba(254, 242, 242, 0.92) !important;
            border: 1px solid #fecaca !important;
            color: #7f1d1d !important;
        }

        .toast-error .toast-close-button {
            color: #dc2626 !important;
        }

        .toast-warning {
            background: rgba(255, 251, 235, 0.92) !important;
            border: 1px solid #fde68a !important;
            color: #78350f !important;
        }

        .toast-warning .toast-close-button {
            color: #d97706 !important;
        }

        .toast-info {
            background: rgba(238, 242, 255, 0.92) !important;
            border: 1px solid #c7d2fe !important;
            color: #3730a3 !important;
        }

        .toast-info .toast-close-button {
            color: #4f46e5 !important;
        }

        #toast-container>.toast {
            background-image: none !important;
            padding: 14px 18px !important;
        }

        #toast-container>.toast:before {
            display: none !important;
        }

        /* ========== SWEETALERT2 ========== */
        .swal2-popup {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            border-radius: var(--radius-xl) !important;
            box-shadow: 0 32px 64px rgba(0, 0, 0, 0.15) !important;
        }

        .swal2-title {
            font-weight: 800 !important;
            letter-spacing: -0.4px !important;
        }

        .swal2-html-container {
            font-size: 14px !important;
            color: var(--text-secondary) !important;
        }

        .swal2-confirm {
            font-weight: 700 !important;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            border-radius: var(--radius-sm) !important;
            background: linear-gradient(135deg, var(--accent), #059669) !important;
            box-shadow: 0 2px 10px var(--accent-glow) !important;
        }

        .swal2-cancel {
            font-weight: 700 !important;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            border-radius: var(--radius-sm) !important;
        }

        /* ========== ANIMATIONS ========== */
        .fade-in {
            animation: fadeIn 0.5s var(--ease) both;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(14px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s var(--ease) both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .stagger-1 {
            animation-delay: 0.05s;
        }

        .stagger-2 {
            animation-delay: 0.1s;
        }

        .stagger-3 {
            animation-delay: 0.15s;
        }

        .stagger-4 {
            animation-delay: 0.2s;
        }

        /* ========== NOTIFICATION DROPDOWN ========== */
        .notif-dropdown {
            display: none;
            position: absolute;
            top: 52px;
            right: 0;
            width: 400px;
            max-height: 500px;
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 48px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(0, 0, 0, 0.03);
            z-index: 1060;
            overflow: hidden;
            flex-direction: column;
            animation: dropdownIn 0.25s var(--ease-bounce) both;
        }

        @keyframes dropdownIn {
            from {
                opacity: 0;
                transform: translateY(-8px) scale(0.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .notif-dropdown.show {
            display: flex;
        }

        .notif-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--card-border);
            font-size: 14px;
            font-weight: 800;
            flex-shrink: 0;
            background: linear-gradient(180deg, #f8fafc, transparent);
            letter-spacing: -0.2px;
        }

        .notif-header .mark-all {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--accent);
            cursor: pointer;
            background: var(--accent-soft);
            border: 1px solid rgba(16, 185, 129, 0.12);
            padding: 4px 10px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .notif-header .mark-all:hover {
            background: rgba(16, 185, 129, 0.15);
            border-color: rgba(16, 185, 129, 0.2);
        }

        .notif-list {
            overflow-y: auto;
            flex: 1;
        }

        .notif-item {
            display: flex;
            gap: 12px;
            padding: 14px 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.03);
            cursor: pointer;
            transition: all 0.2s ease;
            align-items: flex-start;
        }

        .notif-item:hover {
            background: linear-gradient(90deg, var(--accent-subtle), transparent);
        }

        .notif-item.unread {
            background: linear-gradient(90deg, var(--accent-soft), transparent);
        }

        .notif-item.unread:hover {
            background: rgba(16, 185, 129, 0.1);
        }

        .notif-item .notif-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent);
            margin-top: 6px;
            flex-shrink: 0;
            box-shadow: 0 0 8px var(--accent-glow);
            animation: dotPulse 2s ease-in-out infinite;
        }

        .notif-item.read .notif-dot {
            background: transparent;
            box-shadow: none;
            animation: none;
        }

        .notif-item .notif-content {
            flex: 1;
            min-width: 0;
        }

        .notif-item .notif-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .notif-item .notif-body {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
            line-height: 1.4;
        }

        .notif-item .notif-time {
            font-size: 10.5px;
            color: var(--text-muted);
            margin-top: 5px;
            font-weight: 600;
        }

        .notif-group {
            border-bottom: 1px solid rgba(0, 0, 0, 0.03);
        }
        .notif-group-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            cursor: pointer;
            gap: 10px;
            background: #f8fafc;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .notif-group-header:hover {
            background: #f1f5f9;
        }
        .notif-group-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 0;
        }
        .notif-group-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 13px;
            flex-shrink: 0;
        }
        .notif-group-info {
            flex: 1;
            min-width: 0;
        }
        .notif-group-title {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .notif-group-meta {
            font-size: 10.5px;
            color: var(--text-muted);
        }
        .notif-group-badge {
            background: var(--accent);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
            flex-shrink: 0;
        }
        .notif-group-chevron {
            font-size: 11px;
            color: var(--text-muted);
            transition: transform 0.2s;
            flex-shrink: 0;
        }
        .notif-group-chevron.open {
            transform: rotate(180deg);
        }
        .notif-group-body {
            background: #fff;
        }
        .notif-group-body .notif-item {
            padding: 10px 20px 10px 52px;
            border-bottom: none;
        }
        .notif-group-body .notif-item:not(:last-child) {
            border-bottom: 1px solid rgba(0, 0, 0, 0.02);
        }
        .notif-group-body .notif-item .notif-dot {
            width: 6px;
            height: 6px;
            margin-top: 5px;
        }

        .notif-empty {
            padding: 48px 20px;
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
        }

        .notif-footer {
            border-top: 1px solid var(--card-border);
            padding: 12px 20px;
            text-align: center;
            flex-shrink: 0;
            background: linear-gradient(180deg, transparent, rgba(248,250,252,0.5));
        }

        .notif-footer a {
            color: var(--accent);
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .notif-footer a:hover {
            color: var(--accent-hover);
            gap: 8px;
        }

        .notif-bell-wrapper {
            position: relative;
        }

        .notif-close-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--card-border);
            background: var(--card);
            color: var(--text-muted);
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .notif-close-btn:hover {
            background: var(--accent-soft);
            color: var(--accent);
            border-color: rgba(16, 185, 129, 0.2);
        }

        .topbar-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            min-width: 17px;
            height: 17px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            border-radius: 9px;
            padding: 0 5px;
            display: none;
            align-items: center;
            justify-content: center;
            line-height: 1;
            border: 2px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.4);
        }

        /* ========== SCROLL PROGRESS ========== */
        .scroll-progress {
            position: fixed;
            top: 0;
            left: var(--sidebar-w);
            right: 0;
            height: 2px;
            z-index: 1060;
            background: transparent;
            pointer-events: none;
            transition: left 0.35s var(--ease);
        }

        .scroll-progress.collapsed {
            left: var(--sidebar-collapsed-w);
        }

        .scroll-progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--accent), #34d399, #6ee7b7);
            border-radius: 0 2px 2px 0;
            transition: width 0.1s linear;
            box-shadow: 0 0 8px var(--accent-glow);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .topbar-user-info {
                display: none;
            }
        }

        @keyframes notifMobileIn {
            from {
                transform: translateY(100%);
            }
            to {
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-toggle {
                display: flex;
            }

            .topbar {
                left: 0;
                padding: 0 16px;
            }

            .main-content {
                margin-left: 0;
                padding: 20px 16px;
            }

            .topbar-logout span {
                display: none;
            }

            .topbar-divider:last-of-type {
                display: none;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .notif-close-btn {
                display: flex;
            }

            .notif-dropdown.show {
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                width: 100%;
                height: 100dvh;
                max-height: none;
                border-radius: 0;
                z-index: 1070;
                animation: notifMobileIn 0.3s var(--ease-bounce) both;
            }

            .notif-list {
                min-height: 0;
                -webkit-overflow-scrolling: touch;
            }

            .scroll-progress {
                left: 0;
            }
        }

        @media (max-width: 480px) {
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-bar .form-control,
            .filter-bar .form-select {
                min-width: 100%;
            }

            .page-header-title {
                font-size: 20px;
            }

            .stat-value {
                font-size: 24px;
            }
        }
    </style>


    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />

    @yield('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
                    $modules = \App\Models\Module::orderBy('group')
                        ->orderBy('module_name')
                        ->get()
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
            <div class="notif-bell-wrapper">
                <button class="topbar-btn" id="notif-bell" title="Notifikasi" aria-label="Notifications">
                    <i class="fa-regular fa-bell"></i>
                </button>
                <span class="topbar-badge" id="notif-badge"></span>
                <div class="notif-dropdown" id="notif-dropdown">
                    <div class="notif-header">
                        <span>Notifikasi</span>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <button class="mark-all" onclick="event.stopPropagation();markAllRead()">Tandai semua dibaca</button>
                            <button class="notif-close-btn" id="notifCloseBtn" aria-label="Tutup">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                    <div class="notif-list" id="notif-list">
                        <div class="notif-empty">Memuat...</div>
                    </div>
                    <div class="notif-footer">
                        <a href="{{ route('notifications.all') }}">
                            <i class="fa-regular fa-eye"></i>
                            Lihat Semua Notifikasi
                        </a>
                    </div>
                </div>
            </div>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.30.1/moment.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        // --- Sidebar toggle ---
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const toggle = document.getElementById('sidebarToggle');

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
            desktopBtn.innerHTML = collapsed ?
                '<i class="fa-solid fa-angles-right"></i>' :
                '<i class="fa-solid fa-angles-left"></i>';
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
            try {
                localStorage.setItem(DKEY, collapsed);
            } catch (e) {}
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

        // --- Notification bell ---
        var notifPollTimer;

        function pollNotificationCount() {
            $.get('{{ route('notifications.count') }}', function(res) {
                var $badge = $('#notif-badge');
                if (res.count > 0) {
                    $badge.text(res.count > 99 ? '99+' : res.count).css('display', 'flex');
                } else {
                    $badge.hide();
                }
            });
        }

        function loadNotifications() {
            $.get('{{ route('notifications.index') }}', function(res) {
                var $list = $('#notif-list');
                if (!res.data || res.data.length === 0) {
                    $list.html(
                        '<div class="notif-empty"><i class="fa fa-bell-slash" style="font-size:24px;display:block;margin-bottom:8px;opacity:0.3"></i>Tidak ada notifikasi</div>'
                    );
                    return;
                }

                var html = '';
                res.data.forEach(function(group) {
                    var hasUnread = group.unread_count > 0;

                    html += '<div class="notif-group">';
                    html += '<div class="notif-group-header" onclick="toggleGroup(this)">';
                    html += '<div class="notif-group-left">';
                    html += '<div class="notif-group-icon"><i class="fa ' + group.group_icon + '"></i></div>';
                    html += '<div class="notif-group-info">';
                    html += '<div class="notif-group-title">' + escapeHtml(group.group_title) + '</div>';
                    html += '<div class="notif-group-meta">' + group.notifications.length + ' notifikasi · ' + group.latest_time + '</div>';
                    html += '</div></div>';
                    if (hasUnread) {
                        html += '<span class="notif-group-badge">' + group.unread_count + '</span>';
                    }
                    html += '<i class="fa fa-chevron-down notif-group-chevron"></i>';
                    html += '</div>';
                    html += '<div class="notif-group-body">';

                    group.notifications.forEach(function(n) {
                        var cls = n.read ? 'read' : 'unread';
                        var dataAttrs = 'data-id="' + n.id + '" data-type="' + (n.type || 'default') + '"';
                        if (n.task_id) dataAttrs += ' data-task-id="' + n.task_id + '"';
                        if (n.lead_id) dataAttrs += ' data-lead-id="' + n.lead_id + '"';
                        if (n.activity_id) dataAttrs += ' data-activity-id="' + n.activity_id + '"';

                        html += '<div class="notif-item ' + cls + '" ' + dataAttrs + ' onclick="openNotif(this)" style="cursor:pointer;">';
                        html += '<span class="notif-dot"></span>';
                        html += '<div class="notif-content">';
                        html += '<div class="notif-title">' + escapeHtml(n.title) + '</div>';
                        if (n.body) html += '<div class="notif-body">' + escapeHtml(n.body) + '</div>';
                        html += '<div class="notif-time">' + (n.time || 'Baru saja') + '</div>';
                        html += '</div></div>';
                    });

                    html += '</div></div>';
                });
                $list.html(html);
            }).fail(function(err) {
                console.error('Error loading notifications:', err);
                var $list = $('#notif-list');
                $list.html('<div class="notif-empty">Error memuat notifikasi</div>');
            });
        }

        function toggleGroup(headerEl) {
            var $header = $(headerEl);
            var $body = $header.next('.notif-group-body');
            $body.slideToggle(150);
            $header.find('.notif-group-chevron').toggleClass('open');
        }

        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function openNotif(el) {
            var $el = $(el);
            var notifId = parseInt($el.data('id'));
            var type = $el.data('type') || 'default';
            var taskId = $el.data('task-id') || null;
            var leadId = $el.data('lead-id') || null;
            var activityId = $el.data('activity-id') || null;

            $.post('{{ url('/notifications') }}/' + notifId + '/read', {
                _token: '{{ csrf_token() }}'
            }, function(res) {
                $('#notif-dropdown').removeClass('show');
                pollNotificationCount();

                var targetUrl = null;
                if (type === 'mention' && leadId) {
                    targetUrl = '{{ url('leads-management') }}/' + leadId;
                } else if (taskId) {
                    targetUrl = '{{ url('task-planner') }}/' + taskId;
                }

                if (targetUrl && activityId) {
                    targetUrl += '#activity-' + activityId;
                }

                if (targetUrl) {
                    window.location.href = targetUrl;
                }
            }).fail(function(err) {
                console.error('Error marking notification as read:', err);
                toastr.error('Error membaca notifikasi');
            });
        }

        function markAllRead() {
            $.post('{{ route('notifications.read-all') }}', {
                _token: '{{ csrf_token() }}'
            }, function() {
                $('.notif-group-body .notif-item').removeClass('unread').addClass('read').find('.notif-dot').addClass('read');
                $('.notif-group-badge').hide();
                pollNotificationCount();
            });
        }

        $('#notif-bell').on('click', function(e) {
            e.stopPropagation();
            var $dd = $('#notif-dropdown');
            if ($dd.hasClass('show')) {
                $dd.removeClass('show');
                return;
            }
            loadNotifications();
            $dd.addClass('show');
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.notif-bell-wrapper, #notif-dropdown').length) {
                $('#notif-dropdown').removeClass('show');
            }
        });

        $('#notifCloseBtn').on('click', function(e) {
            e.stopPropagation();
            $('#notif-dropdown').removeClass('show');
        });

        pollNotificationCount();
        notifPollTimer = setInterval(pollNotificationCount, 30000);

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

        @if (session('success'))
            toastr.success('{{ session('success') }}');
        @endif
        @if (session('error'))
            toastr.error('{{ session('error') }}');
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
