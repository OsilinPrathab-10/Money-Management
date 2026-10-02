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
