<?php

// ==========================================================================
// Helper Functions for Dev Dashboard & Client Server Down Screen
// ==========================================================================

if (!function_exists('renderDevControlDashboard')) {
function renderDevControlDashboard(array $status) {
    $isLive = ($status['mode'] === 'live');
    $updatedAt = $status['updated_at'] ?? 'Never (Default Live)';
    $hasCookie = (isset($_COOKIE['dev_bypass']) && $_COOKIE['dev_bypass'] === 'active');
    $savedNotice = isset($_GET['saved']);
    $clearedNotice = isset($_GET['cookie_cleared']);
    $activatedNotice = isset($_GET['bypass_activated']);
    $basePath = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Control Switch | devosilinprathab</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --live-color: #10b981;
            --live-glow: rgba(16, 185, 129, 0.35);
            --down-color: #ef4444;
            --down-glow: rgba(239, 68, 68, 0.4);
            --primary-accent: <?= $isLive ? 'var(--live-color)' : 'var(--down-color)' ?>;
            --primary-glow: <?= $isLive ? 'var(--live-glow)' : 'var(--down-glow)' ?>;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #030712;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient Cyber Grid & Floating Orbs */
        .bg-grid {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: 
                radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.08) 1px, transparent 1px),
                linear-gradient(to right, rgba(255, 255, 255, 0.02) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
            background-size: 32px 32px;
            pointer-events: none;
            z-index: 0;
        }
        .orb-glow {
            position: fixed;
            width: 450px;
            height: 450px;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.22;
            pointer-events: none;
            z-index: 0;
            animation: orbFloat 10s ease-in-out infinite alternate;
        }
        .orb-1 {
            top: -100px;
            left: -100px;
            background: <?= $isLive ? '#059669' : '#dc2626' ?>;
        }
        .orb-2 {
            bottom: -100px;
            right: -100px;
            background: #4f46e5;
            animation-duration: 14s;
        }
        @keyframes orbFloat {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(50px, 40px) scale(1.15); }
        }

        /* Main Glass Console Card */
        .console-panel {
            position: relative;
            z-index: 1;
            max-width: 660px;
            width: 100%;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 28px;
            padding: 36px 32px;
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.7), 0 0 40px -10px var(--primary-glow);
            animation: panelEntrance 0.7s cubic-bezier(0.16, 1, 0.3, 1);
            transition: box-shadow 0.4s ease;
        }
        @keyframes panelEntrance {
            from { opacity: 0; transform: translateY(30px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Console Top Bar */
        .console-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
            padding-bottom: 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            flex-wrap: wrap;
            gap: 12px;
        }
        .brand-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .brand-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, #6366f1, #3b82f6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }
        .brand-icon svg { width: 22px; height: 22px; }
        .title-text h1 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.01em;
        }
        .title-text p {
            font-size: 0.8rem;
            color: #94a3b8;
        }
        .ist-clock-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 9999px;
            padding: 6px 14px;
            font-size: 0.78rem;
            color: #38bdf8;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 600;
        }
        .clock-dot {
            width: 7px;
            height: 7px;
            background: #38bdf8;
            border-radius: 50%;
            animation: blink 1s infinite;
        }
        @keyframes blink {
            50% { opacity: 0.3; }
        }

        /* Notifications */
        .toast-notice {
            padding: 12px 18px;
            border-radius: 14px;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.4s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .toast-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.35);
            color: #6ee7b7;
        }
        .toast-info {
            background: rgba(56, 189, 248, 0.15);
            border: 1px solid rgba(56, 189, 248, 0.35);
            color: #7dd3fc;
        }

        /* Hero Status Banner with Pulse Waves */
        .status-hero {
            position: relative;
            background: <?= $isLive ? 'linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(6, 78, 59, 0.2))' : 'linear-gradient(135deg, rgba(239, 68, 68, 0.12), rgba(127, 29, 29, 0.22))' ?>;
            border: 1px solid <?= $isLive ? 'rgba(16, 185, 129, 0.3)' : 'rgba(239, 68, 68, 0.35)' ?>;
            border-radius: 22px;
            padding: 28px 24px;
            margin-bottom: 24px;
            text-align: center;
            overflow: hidden;
        }
        .radar-pulse-wrap {
            position: relative;
            width: 76px;
            height: 76px;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .radar-ring {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 2px solid var(--primary-accent);
            opacity: 0.6;
            animation: pulseWave 2.2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
        }
        .radar-ring:nth-child(2) {
            animation-delay: 0.7s;
        }
        .radar-ring:nth-child(3) {
            animation-delay: 1.4s;
        }
        @keyframes pulseWave {
            0% { transform: scale(0.6); opacity: 0.8; }
            100% { transform: scale(1.6); opacity: 0; }
        }
        .radar-center-core {
            position: relative;
            z-index: 2;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: <?= $isLive ? 'linear-gradient(135deg, #10b981, #047857)' : 'linear-gradient(135deg, #ef4444, #b91c1c)' ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            box-shadow: 0 0 20px var(--primary-accent);
            animation: coreBounce 3s ease-in-out infinite alternate;
        }
        @keyframes coreBounce {
            0% { transform: scale(1); }
            100% { transform: scale(1.08); }
        }
        .radar-center-core svg { width: 28px; height: 28px; }

        .status-hero h2 {
            font-size: 1.4rem;
            font-weight: 800;
            color: <?= $isLive ? '#34d399' : '#f87171' ?>;
            margin-bottom: 8px;
            letter-spacing: 0.02em;
        }
        .status-hero p {
            font-size: 0.92rem;
            color: #cbd5e1;
            max-width: 480px;
            margin: 0 auto;
            line-height: 1.55;
        }

        /* Master Toggle Controller Box */
        .toggle-box {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .toggle-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .toggle-label-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #e2e8f0;
        }
        /* Interactive Cyber Toggle Pill */
        .current-pill-toggle {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 7px 16px;
            border-radius: 9999px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
            background: <?= $isLive ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)' ?>;
            color: <?= $isLive ? '#6ee7b7' : '#fca5a5' ?>;
            border: 1px solid <?= $isLive ? 'rgba(16, 185, 129, 0.45)' : 'rgba(239, 68, 68, 0.45)' ?>;
            box-shadow: 0 0 16px <?= $isLive ? 'rgba(16, 185, 129, 0.25)' : 'rgba(239, 68, 68, 0.25)' ?>;
        }
        .current-pill-toggle:hover {
            transform: translateY(-2px) scale(1.03);
            background: <?= $isLive ? 'rgba(16, 185, 129, 0.32)' : 'rgba(239, 68, 68, 0.32)' ?>;
            color: #ffffff;
            box-shadow: 0 4px 22px <?= $isLive ? 'rgba(16, 185, 129, 0.45)' : 'rgba(239, 68, 68, 0.45)' ?>;
        }
        .switch-track {
            position: relative;
            width: 36px;
            height: 20px;
            background: <?= $isLive ? '#10b981' : '#334155' ?>;
            border-radius: 9999px;
            transition: background 0.3s;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.4);
            display: inline-block;
        }
        .switch-thumb {
            position: absolute;
            top: 2px;
            left: <?= $isLive ? '18px' : '2px' ?>;
            width: 16px;
            height: 16px;
            background: #ffffff;
            border-radius: 50%;
            transition: left 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 2px 5px rgba(0,0,0,0.5);
            display: block;
        }

        /* Large Cyber Switch Button */
        .btn-master-switch {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 18px 24px;
            border-radius: 16px;
            font-size: 1.05rem;
            font-weight: 800;
            cursor: pointer;
            border: none;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            letter-spacing: 0.02em;
        }
        .btn-to-maintenance {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 50%, #b91c1c 100%);
            color: #ffffff;
            box-shadow: 0 10px 25px rgba(220, 38, 38, 0.45);
        }
        .btn-to-maintenance:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(220, 38, 38, 0.6);
            background: linear-gradient(135deg, #f87171 0%, #ef4444 50%, #dc2626 100%);
        }
        .btn-to-live {
            background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);
            color: #ffffff;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.45);
        }
        .btn-to-live:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(16, 185, 129, 0.6);
            background: linear-gradient(135deg, #34d399 0%, #10b981 50%, #059669 100%);
        }
        .btn-master-switch::after {
            content: '';
            position: absolute;
            top: 0; left: -100%; width: 50%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.25), transparent);
            transform: skewX(-20deg);
            animation: shine 4s infinite;
        }
        @keyframes shine {
            100% { left: 150%; }
        }

        /* Bypass Explainer & Control Card */
        .bypass-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .bypass-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .bypass-title {
            font-size: 0.92rem;
            font-weight: 700;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .badge-bypass {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 9999px;
            background: <?= $hasCookie ? 'rgba(16, 185, 129, 0.2)' : 'rgba(148, 163, 184, 0.2)' ?>;
            color: <?= $hasCookie ? '#6ee7b7' : '#94a3b8' ?>;
            border: 1px solid <?= $hasCookie ? 'rgba(16, 185, 129, 0.4)' : 'rgba(148, 163, 184, 0.3)' ?>;
        }
        .bypass-desc {
            font-size: 0.82rem;
            color: #94a3b8;
            line-height: 1.5;
            margin-bottom: 14px;
        }
        .bypass-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-bypass-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-clear-bypass {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .btn-clear-bypass:hover {
            background: rgba(239, 68, 68, 0.25);
            color: #fff;
        }
        .btn-activate-bypass {
            background: rgba(16, 185, 129, 0.15);
            color: #86efac;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .btn-activate-bypass:hover {
            background: rgba(16, 185, 129, 0.25);
            color: #fff;
        }

        /* Information Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 24px;
        }
        .info-cell {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 14px;
            padding: 14px;
            transition: border-color 0.2s;
        }
        .info-cell:hover {
            border-color: rgba(255, 255, 255, 0.15);
        }
        .cell-label {
            font-size: 0.72rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
            font-weight: 600;
        }
        .cell-value {
            font-size: 0.92rem;
            font-weight: 700;
            color: #f8fafc;
            word-break: break-all;
        }

        /* Action Buttons Row */
        .btn-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-console-link {
            flex: 1;
            min-width: 140px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 18px;
            border-radius: 12px;
            font-size: 0.88rem;
            font-weight: 600;
            color: #cbd5e1;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-console-link:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            transform: translateY(-1px);
        }
        .btn-preview {
            border-color: rgba(56, 189, 248, 0.3);
            color: #38bdf8;
        }
        .btn-preview:hover {
            background: rgba(56, 189, 248, 0.15);
            color: #7dd3fc;
        }

        /* Footer */
        .console-footer {
            text-align: center;
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 22px;
        }

        @media (max-width: 540px) {
            .console-panel { padding: 26px 20px; }
            .info-grid { grid-template-columns: 1fr; }
            .btn-row { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="orb-glow orb-1"></div>
    <div class="orb-glow orb-2"></div>

    <div class="console-panel">
        <!-- Top Bar -->
        <div class="console-header">
            <div class="brand-title">
                <div class="brand-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                </div>
                <div class="title-text">
                    <h1>Server Status Switch</h1>
                    <p>Exclusive Private URL: <code>/devosilinprathab</code></p>
                </div>
            </div>
            <div class="ist-clock-badge">
                <span class="clock-dot"></span>
                <span id="liveClockIST">IST Loading...</span>
            </div>
        </div>

        <!-- Toast Notifications -->
        <?php if ($savedNotice): ?>
            <div class="toast-notice toast-success">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Mode updated to <strong><?= strtoupper(htmlspecialchars($status['mode'])) ?></strong> (IST recorded)
            </div>
        <?php endif; ?>

        <?php if ($clearedNotice): ?>
            <div class="toast-notice toast-info">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Admin bypass cleared. You are now testing as a regular public visitor.
            </div>
        <?php endif; ?>

        <?php if ($activatedNotice): ?>
            <div class="toast-notice toast-success">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Admin bypass active! You can now access the live site during maintenance.
            </div>
        <?php endif; ?>

        <!-- Hero Status Card -->
        <div class="status-hero">
            <div class="radar-pulse-wrap">
                <div class="radar-ring"></div>
                <div class="radar-ring"></div>
                <div class="radar-ring"></div>
                <div class="radar-center-core">
                    <?php if ($isLive): ?>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <?php else: ?>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <?php endif; ?>
                </div>
            </div>
            <h2><?= $isLive ? 'SYSTEM STATUS: LIVE & OPERATIONAL' : 'SYSTEM STATUS: SERVER DOWN (503)' ?></h2>
            <?php if ($isLive): ?>
                <p>Production application is active. All customers, chit fund participants, and loan operations proceed normally.</p>
            <?php else: ?>
                    <div style="margin-top: 14px;">
                    <div style="font-size: 0.8rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; margin-bottom: 8px;">Emergency Contact Helpline</div>
                    <div style="display: flex; align-items: center; justify-content: center;">
                        <a href="tel:+919384820625" style="font-size: 1.75rem; font-weight: 900; color: #34d399; text-decoration: none; padding: 8px 24px; background: rgba(52, 211, 153, 0.1); border: 1px solid rgba(52, 211, 153, 0.3); border-radius: 12px; display: inline-flex; align-items: center; gap: 8px;">
                            📞 +91 93848 20625
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Master Switch Form -->
        <div class="toggle-box">
            <div class="toggle-header">
                <div class="toggle-label-title">One-Click System State Toggle</div>
                <a href="?set=<?= $isLive ? 'maintenance' : 'live' ?>" class="current-pill-toggle" title="Click to Toggle Server State">
                    <span class="switch-track">
                        <span class="switch-thumb"></span>
                    </span>
                    <span>Active: <?= $isLive ? 'LIVE' : 'DOWN' ?> &bull; Click to <?= $isLive ? 'TURN DOWN' : 'RESTORE LIVE' ?></span>
                </a>
            </div>

            <form method="POST" action="?set=<?= $isLive ? 'maintenance' : 'live' ?>">
                <?php if (function_exists('csrf_token')): ?>
                    <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                <?php endif; ?>
                <?php if ($isLive): ?>
                    <input type="hidden" name="target_mode" value="maintenance">
                    <button type="submit" class="btn-master-switch btn-to-maintenance">
                        <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-2.83m-1.414 5.658a9 9 0 01-2.167-9.238m7.824-2.167a1 1 0 111.414 1.414m-1.414-1.414L3 3m8.293 8.293l1.414 1.414"/></svg>
                        🚨 TURN ON SERVER DOWN MODE
                    </button>
                <?php else: ?>
                    <input type="hidden" name="target_mode" value="live">
                    <button type="submit" class="btn-master-switch btn-to-live">
                        <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        ✅ RESTORE APPLICATION TO LIVE MODE
                    </button>
                <?php endif; ?>
            </form>
            <div style="text-align: center; margin-top: 12px;">
                <a href="?set=<?= $isLive ? 'maintenance' : 'live' ?>" style="color: #64748b; font-size: 0.8rem; text-decoration: underline;">
                    Instant Switch via Direct Link (No Form / No CSRF)
                </a>
            </div>
        </div>

        <!-- Meta Grid -->
        <div class="info-grid">
            <div class="info-cell">
                <div class="cell-label">Emergency Helpline</div>
                <div class="cell-value" style="color: #34d399; font-size: 1.25rem; font-weight: 800;">
                    <a href="tel:+919384820625" style="color: #34d399; text-decoration: none;">+91 93848 20625</a>
                </div>
            </div>
            <div class="info-cell">
                <div class="cell-label">Last Modified (IST)</div>
                <div class="cell-value"><?= htmlspecialchars($updatedAt) ?></div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="btn-row">
            <?php if ($hasCookie): ?>
                <a href="?clear_bypass=1" class="btn-console-link" style="color: #f87171; border-color: rgba(248, 113, 113, 0.35);">
                    ❌ Clear Bypass (Test Server Down)
                </a>
            <?php else: ?>
                <a href="?activate_bypass=1" class="btn-console-link" style="color: #34d399; border-color: rgba(52, 211, 153, 0.35);">
                    🛡️ Activate Admin Bypass
                </a>
            <?php endif; ?>
            <a href="?preview=1" target="_blank" class="btn-console-link btn-preview">
                👁️ Preview Server Down Screen
            </a>
            <a href="<?= htmlspecialchars(dirname($basePath) === '/' ? '/' : dirname($basePath)) ?>" class="btn-console-link">
                🌐 Open Live Web App
            </a>
        </div>

        <div class="console-footer">
            Antigravity Fintronix System Guard &bull; Authorized Operator Access Only
        </div>
    </div>

    <!-- Live IST Clock Script -->
    <script>
        function updateISTClock() {
            const now = new Date();
            const istOptions = {
                timeZone: 'Asia/Kolkata',
                hour12: true,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            const clockEl = document.getElementById('liveClockIST');
            if (clockEl) {
                clockEl.textContent = now.toLocaleTimeString('en-IN', istOptions) + ' IST';
            }
        }
        setInterval(updateISTClock, 1000);
        updateISTClock();
    </script>
</body>
</html>
<?php
}
}

if (!function_exists('renderServerDownScreen')) {
function renderServerDownScreen(bool $isPreview = false) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Down | Maintenance in Progress</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #050814;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: #f8fafc;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient Dynamic Background & Glowing Orbs */
        .ambient-mesh {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: 
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(220, 38, 38, 0.18), transparent),
                radial-gradient(ellipse 60% 50% at 80% 90%, rgba(99, 102, 241, 0.12), transparent),
                radial-gradient(ellipse 50% 50% at 10% 80%, rgba(245, 158, 11, 0.1), transparent);
            pointer-events: none;
            z-index: 0;
        }
        .bg-stars {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: radial-gradient(rgba(255, 255, 255, 0.12) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
            z-index: 0;
        }
        .floating-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
            opacity: 0.25;
            z-index: 0;
            animation: orbFloat 12s ease-in-out infinite alternate;
        }
        .orb-red {
            width: 380px; height: 380px;
            background: #dc2626;
            top: 10%; right: 15%;
        }
        .orb-indigo {
            width: 320px; height: 320px;
            background: #4f46e5;
            bottom: 10%; left: 10%;
            animation-duration: 16s;
        }
        @keyframes orbFloat {
            0% { transform: translateY(0) scale(1); }
            100% { transform: translateY(50px) scale(1.15); }
        }

        /* Glassmorphism Card */
        .card-container {
            position: relative;
            z-index: 1;
            max-width: 620px;
            width: 100%;
            background: rgba(15, 23, 42, 0.78);
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 32px;
            padding: 44px 36px;
            text-align: center;
            box-shadow: 0 35px 70px -15px rgba(0, 0, 0, 0.7), 0 0 50px -10px rgba(239, 68, 68, 0.25);
            animation: cardEntrance 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes cardEntrance {
            from { opacity: 0; transform: translateY(40px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Preview Banner */
        .preview-banner {
            background: rgba(234, 179, 8, 0.18);
            border: 1px solid rgba(234, 179, 8, 0.4);
            color: #fef08a;
            padding: 8px 18px;
            border-radius: 12px;
            font-size: 0.82rem;
            margin-bottom: 24px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.02em;
        }

        /* Status Badge with Live Pulsing Radar */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(239, 68, 68, 0.14);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #fca5a5;
            padding: 8px 20px;
            border-radius: 9999px;
            font-size: 0.82rem;
            font-weight: 700;
            margin-bottom: 28px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.2);
        }
        .pulse-beacon {
            position: relative;
            width: 10px;
            height: 10px;
        }
        .pulse-core {
            width: 10px;
            height: 10px;
            background: #ef4444;
            border-radius: 50%;
        }
        .pulse-wave {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            border-radius: 50%;
            border: 2px solid #ef4444;
            animation: beaconWave 1.8s infinite;
        }
        @keyframes beaconWave {
            0% { transform: scale(1); opacity: 0.9; }
            100% { transform: scale(3.5); opacity: 0; }
        }

        /* Animated High-Tech Server Visual */
        .server-visual {
            position: relative;
            width: 110px;
            height: 110px;
            margin: 0 auto 28px;
            animation: serverFloat 4s ease-in-out infinite;
        }
        @keyframes serverFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        .server-glow-bg {
            position: absolute;
            inset: -10px;
            border-radius: 28px;
            background: radial-gradient(circle, rgba(239, 68, 68, 0.35), transparent 70%);
            filter: blur(14px);
            z-index: 0;
        }
        .server-chassis {
            position: relative;
            z-index: 1;
            width: 100%;
            height: 100%;
            background: linear-gradient(145deg, #1e293b, #0f172a);
            border: 2px solid rgba(239, 68, 68, 0.4);
            border-radius: 24px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 9px;
            padding: 14px;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }
        .server-blade {
            width: 82%;
            height: 14px;
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 8px;
        }
        .led-group {
            display: flex;
            gap: 4px;
        }
        .led-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
        }
        .led-red { background: #ef4444; box-shadow: 0 0 6px #ef4444; animation: blinkFast 1.2s infinite; }
        .led-amber { background: #f59e0b; box-shadow: 0 0 6px #f59e0b; animation: blinkFast 2s infinite 0.4s; }
        .led-blue { background: #38bdf8; box-shadow: 0 0 6px #38bdf8; animation: blinkFast 1.6s infinite 0.8s; }
        @keyframes blinkFast {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.25; }
        }
        .server-slot {
            width: 24px;
            height: 3px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 2px;
        }

        /* Typography */
        h1 {
            font-size: 2.1rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 12px;
            line-height: 1.25;
            letter-spacing: -0.02em;
        }
        p.desc {
            font-size: 1.02rem;
            color: #94a3b8;
            line-height: 1.65;
            margin-bottom: 30px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Emergency Support Card */
        .helpline-box {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.8) 0%, rgba(30, 41, 59, 0.6) 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 22px;
            padding: 26px 22px;
            margin-bottom: 24px;
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }
        .helpline-box::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 2px;
            background: linear-gradient(90deg, transparent, #38bdf8, transparent);
        }
        .helpline-label {
            font-size: 0.82rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
            font-weight: 700;
        }
        .phone-numbers-group {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }
        .phone-link {
            font-size: 2.35rem;
            font-weight: 900;
            color: #38bdf8;
            text-decoration: none;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            background: rgba(56, 189, 248, 0.08);
            border: 1px solid rgba(56, 189, 248, 0.28);
            border-radius: 18px;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            text-shadow: 0 0 25px rgba(56, 189, 248, 0.45);
        }
        .phone-link:hover {
            color: #7dd3fc;
            background: rgba(56, 189, 248, 0.16);
            border-color: rgba(56, 189, 248, 0.55);
            transform: translateY(-3px) scale(1.02);
            text-shadow: 0 0 35px rgba(56, 189, 248, 0.75);
            box-shadow: 0 10px 25px rgba(56, 189, 248, 0.25);
        }
        .phone-link.secondary-phone {
            color: #34d399;
            background: rgba(52, 211, 153, 0.08);
            border: 1px solid rgba(52, 211, 153, 0.28);
            text-shadow: 0 0 25px rgba(52, 211, 153, 0.45);
        }
        .phone-link.secondary-phone:hover {
            color: #6ee7b7;
            background: rgba(52, 211, 153, 0.16);
            border-color: rgba(52, 211, 153, 0.55);
            text-shadow: 0 0 35px rgba(52, 211, 153, 0.75);
            box-shadow: 0 10px 25px rgba(52, 211, 153, 0.25);
        }
        .phone-divider {
            display: none;
        }

        /* Action Buttons */
        .btn-grid {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 24px;
            border-radius: 14px;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            border: none;
            position: relative;
            overflow: hidden;
        }
        .btn-call {
            background: linear-gradient(135deg, #0284c7 0%, #2563eb 100%);
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
        }
        .btn-call:hover {
            background: linear-gradient(135deg, #0369a1 0%, #1d4ed8 100%);
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(37, 99, 235, 0.55);
        }
        .btn-call-alt {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(5, 150, 105, 0.4);
        }
        .btn-call-alt:hover {
            background: linear-gradient(135deg, #047857 0%, #065f46 100%);
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(5, 150, 105, 0.55);
        }
        .btn-whatsapp {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(22, 163, 74, 0.4);
        }
        .btn-whatsapp:hover {
            background: linear-gradient(135deg, #15803d 0%, #166534 100%);
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(22, 163, 74, 0.55);
        }
        .btn-reload {
            background: rgba(255, 255, 255, 0.08);
            color: #cbd5e1;
            border: 1px solid rgba(255, 255, 255, 0.14);
        }
        .btn-reload:hover {
            background: rgba(255, 255, 255, 0.16);
            color: #ffffff;
            transform: translateY(-2px);
        }
        .btn-reload svg {
            transition: transform 0.4s ease;
        }
        .btn-reload:hover svg {
            transform: rotate(180deg);
        }

        /* Auto-Reconnect Counter */
        .reconnect-counter {
            font-size: 0.82rem;
            color: #64748b;
            margin-top: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .counter-pill {
            background: rgba(255, 255, 255, 0.06);
            padding: 2px 8px;
            border-radius: 6px;
            color: #94a3b8;
            font-weight: 600;
        }

        @media (max-width: 520px) {
            .card-container { padding: 32px 20px; }
            h1 { font-size: 1.7rem; }
            .phone-link { font-size: 1.65rem; width: 100%; justify-content: center; padding: 14px 16px; }
            .btn-action { width: 100%; }
            .btn-grid { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="ambient-mesh"></div>
    <div class="bg-stars"></div>
    <div class="floating-orb orb-red"></div>
    <div class="floating-orb orb-indigo"></div>

    <div class="card-container">
        <?php if ($isPreview): ?>
            <div class="preview-banner">
                ⚠️ Administrator Preview Mode (Not Active Live Error)
            </div>
        <?php endif; ?>

        <!-- Status Badge -->
        <div class="status-badge">
            <div class="pulse-beacon">
                <div class="pulse-core"></div>
                <div class="pulse-wave"></div>
            </div>
            <span>Server Down &bull; Maintenance Active</span>
        </div>

        <!-- High-Tech Animated Server Blade Unit -->
        <div class="server-visual">
            <div class="server-glow-bg"></div>
            <div class="server-chassis">
                <div class="server-blade">
                    <div class="led-group">
                        <div class="led-dot led-red"></div>
                        <div class="led-dot led-amber"></div>
                    </div>
                    <div class="server-slot"></div>
                </div>
                <div class="server-blade">
                    <div class="led-group">
                        <div class="led-dot led-blue"></div>
                        <div class="led-dot led-red"></div>
                    </div>
                    <div class="server-slot"></div>
                </div>
                <div class="server-blade">
                    <div class="led-group">
                        <div class="led-dot led-amber"></div>
                        <div class="led-dot led-blue"></div>
                    </div>
                    <div class="server-slot"></div>
                </div>
            </div>
        </div>

        <!-- Main Title & Desc -->
        <h1>Server Temporarily Down</h1>
        <p class="desc">
            We are performing essential system maintenance and server infrastructure upgrades.
            All client services will resume shortly. We apologize for any temporary inconvenience.
        </p>

        <!-- Emergency Helpline Box -->
        <div class="helpline-box">
            <div class="helpline-label">Emergency Helpline &amp; Immediate Assistance</div>
            <div class="phone-numbers-group">
                <a href="tel:+919384820625" class="phone-link" title="Call +91 93848 20625">
                    <svg width="26" height="26" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    +91 93848 20625
                </a>
            </div>
            
            <div class="btn-grid">
                <a href="tel:+919384820625" class="btn-action btn-call" title="Call +91 93848 20625">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    Call: 93848 20625
                </a>
                <a href="https://wa.me/919384820625?text=Hello%2C%20inquiring%20about%20the%20server%20status." target="_blank" rel="noopener noreferrer" class="btn-action btn-whatsapp" title="WhatsApp +91 93848 20625">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91C2.13 13.66 2.59 15.36 3.45 16.86L2.05 22L7.3 20.62C8.75 21.41 10.38 21.83 12.04 21.83C17.5 21.83 21.95 17.38 21.95 11.92C21.95 9.27 20.92 6.78 19.05 4.91C17.18 3.03 14.69 2 12.04 2ZM12.05 20.15C10.57 20.15 9.12 19.76 7.85 19L7.55 18.82L4.43 19.64L5.26 16.59L5.06 16.27C4.22 14.94 3.79 13.41 3.79 11.91C3.79 7.37 7.49 3.67 12.04 3.67C14.25 3.67 16.31 4.53 17.87 6.09C19.42 7.65 20.28 9.72 20.28 11.92C20.28 16.46 16.58 20.15 12.05 20.15Z"/></svg>
                    WhatsApp Support
                </a>
                <button type="button" onclick="window.location.reload();" class="btn-action btn-reload">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Check Status
                </button>
            </div>
        </div>

        <!-- Automatic Retry Countdown -->
        <div class="reconnect-counter">
            <span>Automatic status check in</span>
            <span class="counter-pill" id="retryCounter">30s</span>
        </div>
    </div>

    <!-- Countdown Timer Script -->
    <script>
        let timeLeft = 30;
        const counterEl = document.getElementById('retryCounter');
        setInterval(() => {
            timeLeft--;
            if (timeLeft <= 0) {
                window.location.reload();
            } else if (counterEl) {
                counterEl.textContent = timeLeft + 's';
            }
        }, 1000);
    </script>
</body>
</html>
<?php
}
}

// ==========================================================================
// Main Guard Logic & Route Interception
// ==========================================================================

// --------------------------------------------------------------------------
// Maintenance & Server Down Control System
// Accessible and controlled exclusively via: baseurl/devosilinprathab
// --------------------------------------------------------------------------
$statusFile = __DIR__ . '/../storage/framework/server_status.json';

// Detect whether current request is accessing the private dev controller
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$parsedPath = parse_url($requestUri, PHP_URL_PATH) ?? '';
$isDevControl = (stripos($parsedPath, 'devosilinprathab') !== false) || isset($_GET['devosilinprathab']);

// Read current server status (defaults to 'live' if unconfigured)
$serverStatus = [
    'mode' => 'live',
    'updated_at' => null,
    'updated_by' => null,
];
if (file_exists($statusFile)) {
    $decoded = json_decode(@file_get_contents($statusFile), true);
    if (is_array($decoded) && !empty($decoded['mode'])) {
        $serverStatus = array_merge($serverStatus, $decoded);
    }
}

// --------------------------------------------------------------------------
// 1. Secret Dev Switch (/devosilinprathab)
// --------------------------------------------------------------------------
if ($isDevControl) {
    $currentPath = strtok($requestUri, '?');

    // Helper to get current IST timestamp
    $getIstTime = function() {
        $dt = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        return $dt->format('d M Y, h:i:s A \I\S\T');
    };

    // Handle cookie bypass clearing (lets admin test as a regular client)
    if (isset($_GET['clear_bypass'])) {
        setcookie('dev_bypass', '', time() - 3600, '/');
        header('Location: ' . $currentPath . '?cookie_cleared=1');
        exit;
    }

    // Handle cookie bypass reactivation
    if (isset($_GET['activate_bypass'])) {
        setcookie('dev_bypass', 'active', time() + 86400 * 30, '/');
        header('Location: ' . $currentPath . '?bypass_activated=1');
        exit;
    }

    // Handle toggle via GET or POST
    if (isset($_GET['set'])) {
        $newMode = ($_GET['set'] === 'maintenance') ? 'maintenance' : 'live';
        $serverStatus['mode'] = $newMode;
        $serverStatus['updated_at'] = $getIstTime();
        $serverStatus['updated_by'] = 'devosilinprathab';
        @file_put_contents($statusFile, json_encode($serverStatus, JSON_PRETTY_PRINT));
        
        // Clear bypass cookie by default so admin can immediately test and see the 503 Server Down page
        setcookie('dev_bypass', '', time() - 3600, '/');
        
        header('Location: ' . $currentPath . '?saved=1');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['target_mode'])) {
        $newMode = ($_POST['target_mode'] === 'maintenance') ? 'maintenance' : 'live';
        $serverStatus['mode'] = $newMode;
        $serverStatus['updated_at'] = $getIstTime();
        $serverStatus['updated_by'] = 'devosilinprathab';
        @file_put_contents($statusFile, json_encode($serverStatus, JSON_PRETTY_PRINT));
        
        // Clear bypass cookie by default so admin can immediately test and see the 503 Server Down page
        setcookie('dev_bypass', '', time() - 3600, '/');
        
        header('Location: ' . $currentPath . '?saved=1');
        exit;
    }

    // Preview server down page as viewed by regular clients
    if (isset($_GET['preview'])) {
        renderServerDownScreen(true);
        exit;
    }

    // Render the animated Dev Control Dashboard
    renderDevControlDashboard($serverStatus);
    exit;
}

// --------------------------------------------------------------------------
// 2. Public Traffic Check (Maintenance Mode Interception)
// --------------------------------------------------------------------------
if ($serverStatus['mode'] === 'maintenance' && php_sapi_name() !== 'cli') {
    $hasBypass = (isset($_COOKIE['dev_bypass']) && $_COOKIE['dev_bypass'] === 'active')
              || isset($_GET['bypass'])
              || isset($_GET['live']);

    if (!$hasBypass) {
        http_response_code(503);
        header('Retry-After: 300');
        renderServerDownScreen(false);
        exit;
    }
}
