<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Caveat:wght@600;700&display=swap" rel="stylesheet">
<style>
  /* ══════════════════════════════════════════════════════════════
     Login screen — laid out against a 1024 x 576 design canvas.
     --u is one design unit (1% of the canvas width). Every size is
     a multiple of --u, so the composition holds its proportions at
     any viewport instead of only at the design aspect ratio.
     ══════════════════════════════════════════════════════════════ */
  .authentication-wrapper {
    --u: min(1vw, 1.7778vh, 20px);
    --navy: #10243f;
    --ink: #13294a;
    --blue: #2b80f1;
    --blue-dark: #1566db;
    --muted: #7d8a9c;
    --line: #e6ecf4;

    position: relative;
    display: block !important;
    width: 100%;
    min-height: 100vh !important;
    min-height: 100dvh !important;
    padding: 0 !important;
    overflow: hidden;
    background: #fff;
    font-family: 'Inter', -apple-system, 'Segoe UI', sans-serif;
  }

  .sds-shell {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: stretch;
    min-height: 100vh;
    min-height: 100dvh;
  }

  /* ── pale blue shapes on the light half ── */
  .sds-blob {
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
    z-index: 0;
  }
  .sds-blob--a {
    top: calc(var(--u) * 6);
    left: calc(var(--u) * 27);
    width: calc(var(--u) * 17);
    height: calc(var(--u) * 17);
    background: radial-gradient(circle at 38% 34%, #e7f0fb 0%, #f2f7fd 62%, rgba(242, 247, 253, 0) 72%);
    animation: blobDrift 18s ease-in-out infinite alternate;
  }
  .sds-blob--b {
    bottom: calc(var(--u) * -8);
    left: calc(var(--u) * -4);
    width: calc(var(--u) * 26);
    height: calc(var(--u) * 26);
    background: radial-gradient(circle at 62% 40%, #eef5fd 0%, rgba(238, 245, 253, 0) 70%);
    animation: blobDrift 24s ease-in-out infinite alternate-reverse;
  }
  @keyframes blobDrift {
    0%   { transform: translate3d(0, 0, 0) scale(1); }
    100% { transform: translate3d(1.6vmin, 1.4vmin, 0) scale(1.05); }
  }

  /* ── dark navy panel + S-curved left edge ── */
  .sds-dark {
    position: absolute;
    inset: 0 0 0 auto;
    width: calc(41.4vw + 3px);
    background: linear-gradient(158deg, #16304f 0%, var(--navy) 46%, #0a1a2f 100%);
    z-index: 1;
  }
  .sds-dark-curve {
    position: absolute;
    top: 0;
    bottom: 0;
    right: calc(41.4vw - 2px);
    width: 12vw;
    z-index: 1;
    pointer-events: none;
  }
  .sds-dark-curve svg { display: block; width: 100%; height: 100%; }

  .sds-dark-circle {
    position: absolute;
    top: calc(var(--u) * -3.5);
    right: calc(var(--u) * -2);
    width: calc(var(--u) * 12);
    height: calc(var(--u) * 12);
    border-radius: 50%;
    background: linear-gradient(145deg, #3f92f8 0%, #2470d4 55%, rgba(36, 112, 212, 0) 100%);
    z-index: 3;
    animation: accentFloat 9s ease-in-out infinite;
  }
  @keyframes accentFloat {
    0%, 100% { transform: translateY(0) scale(1); }
    50%      { transform: translateY(calc(var(--u) * 0.5)) scale(1.03); }
  }
  .sds-dark-dots {
    position: absolute;
    top: calc(var(--u) * 2);
    right: calc(var(--u) * 2.3);
    width: calc(var(--u) * 4.6);
    height: calc(var(--u) * 4.6);
    z-index: 4;
    background-image: radial-gradient(rgba(255, 255, 255, 0.6) 12%, transparent 13%);
    background-size: calc(var(--u) * 1.15) calc(var(--u) * 1.15);
    animation: dotsFade 5s ease-in-out infinite;
  }
  @keyframes dotsFade {
    0%, 100% { opacity: 0.5; }
    50%      { opacity: 0.95; }
  }
  .sds-dark-glow {
    position: absolute;
    left: -14%;
    top: 30%;
    width: 65%;
    height: 45%;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(62, 143, 248, 0.26) 0%, rgba(62, 143, 248, 0) 70%);
    filter: blur(calc(var(--u) * 1.6));
    z-index: 2;
    animation: accentFloat 13s ease-in-out 1s infinite reverse;
  }

  /* ══════════ LEFT — marketing column ══════════ */
  .sds-left {
    position: relative;
    z-index: 4;
    flex: 1 1 58.6vw;
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: calc(var(--u) * 3.4) 0 calc(var(--u) * 2.2) calc(var(--u) * 4.5);
  }

  .sds-brand {
    display: inline-flex;
    align-items: center;
    gap: calc(var(--u) * 0.8);
    animation: fadeUp 0.7s cubic-bezier(.16,1,.3,1) both;
  }
  .sds-brand-mark {
    flex: 0 0 auto;
    width: calc(var(--u) * 3.9);
    height: calc(var(--u) * 3.9);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(145deg, #e9f2ff, #fff);
    box-shadow: 0 calc(var(--u) * 0.3) calc(var(--u) * 0.9) rgba(43, 128, 241, 0.16),
                inset 0 0 0 1px rgba(43, 128, 241, 0.1);
  }
  .sds-brand-mark img,
  .sds-brand-mark svg {
    max-width: calc(var(--u) * 2.5);
    max-height: calc(var(--u) * 2.5);
    height: auto;
  }
  .sds-brand-name {
    display: block;
    font-size: calc(var(--u) * 1.46);
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink);
    white-space: nowrap;
  }
  .sds-brand-sub {
    display: block;
    font-size: calc(var(--u) * 0.6);
    font-weight: 600;
    line-height: 1.3;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--muted);
    white-space: nowrap;
    margin-top: calc(var(--u) * 0.18);
  }

  .sds-left-body {
    flex: 1 1 auto;
    display: flex;
    align-items: center;
    min-height: 0;
  }

  .sds-copy {
    width: calc(var(--u) * 28);
    margin-left: calc(var(--u) * 1.75);
    animation: fadeUp 0.8s cubic-bezier(.16,1,.3,1) 0.08s both;
  }

  .sds-kicker {
    display: flex;
    align-items: center;
    gap: calc(var(--u) * 0.55);
    font-size: calc(var(--u) * 0.73);
    font-weight: 600;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: #9aa7b8;
    margin-bottom: calc(var(--u) * 1.7);
  }
  .sds-kicker::before {
    content: '';
    flex: 0 0 auto;
    width: calc(var(--u) * 2.1);
    height: calc(var(--u) * 0.19);
    border-radius: 2px;
    background: var(--blue);
  }

  .sds-headline {
    font-size: calc(var(--u) * 3.13);
    font-weight: 800;
    line-height: 1.16;
    letter-spacing: -0.03em;
    color: var(--ink);
    margin: 0 0 calc(var(--u) * 1.15);
  }
  .sds-headline .accent { color: var(--blue); }

  .sds-sub {
    width: calc(var(--u) * 22);
    font-size: calc(var(--u) * 1.03);
    line-height: 1.62;
    color: var(--muted);
    margin: 0 0 calc(var(--u) * 2.55);
  }

  .sds-features {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: calc(var(--u) * 1.6);
    row-gap: calc(var(--u) * 2.4);
    margin-bottom: calc(var(--u) * 3.3);
  }
  .sds-feature {
    display: flex;
    align-items: flex-start;
    gap: calc(var(--u) * 0.78);
    animation: fadeUp 0.6s cubic-bezier(.16,1,.3,1) both;
  }
  .sds-feature:nth-child(1) { animation-delay: 0.18s; }
  .sds-feature:nth-child(2) { animation-delay: 0.26s; }
  .sds-feature:nth-child(3) { animation-delay: 0.34s; }
  .sds-feature:nth-child(4) { animation-delay: 0.42s; }
  .sds-feature-icon {
    flex: 0 0 auto;
    width: calc(var(--u) * 2);
    height: calc(var(--u) * 2);
    border-radius: calc(var(--u) * 0.58);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: calc(var(--u) * 1);
    transition: transform 0.3s cubic-bezier(.16,1,.3,1);
  }
  .sds-feature:hover .sds-feature-icon { transform: translateY(-3px) scale(1.06); }
  .sds-feature-icon.is-blue   { background: #e9f2ff; color: #2b80f1; }
  .sds-feature-icon.is-green  { background: #e7f8ef; color: #17b26a; }
  .sds-feature-icon.is-purple { background: #efeaff; color: #7a5af8; }
  .sds-feature-icon.is-orange { background: #fff3e6; color: #f79009; }
  .sds-feature-title {
    font-size: calc(var(--u) * 0.86);
    font-weight: 700;
    line-height: 1.2;
    color: var(--ink);
    margin: 0 0 calc(var(--u) * 0.28);
  }
  .sds-feature-text {
    font-size: calc(var(--u) * 0.74);
    line-height: 1.45;
    color: #98a4b3;
    margin: 0;
  }

  .sds-script {
    display: inline-block;
    font-family: 'Caveat', cursive;
    font-size: calc(var(--u) * 1.72);
    font-weight: 700;
    line-height: 1.14;
    color: var(--ink);
    animation: fadeUp 0.7s cubic-bezier(.16,1,.3,1) 0.55s both;
  }
  .sds-script-underline {
    display: block;
    width: calc(var(--u) * 8.5);
    height: calc(var(--u) * 0.7);
    margin: calc(var(--u) * 0.25) 0 0 calc(var(--u) * 2.2);
    overflow: visible;
  }
  .sds-script-underline path {
    fill: none;
    stroke: var(--blue);
    stroke-width: 2.4;
    stroke-linecap: round;
    stroke-dasharray: 150;
    stroke-dashoffset: 150;
    animation: drawLine 1.3s ease 0.9s forwards;
  }
  @keyframes drawLine { to { stroke-dashoffset: 0; } }

  .sds-copyright {
    font-size: calc(var(--u) * 0.68);
    color: #a6b1c0;
    margin: 0;
    padding-top: calc(var(--u) * 1.1);
    animation: fadeUp 0.7s ease 0.7s both;
  }

  /* ══════════ HERO — plinth photo + CSS phone ══════════ */
  /* Sized and placed so the photo's white studio backdrop stays inside the
     light half; it would read as a hard rectangle over the navy panel. */
  .sds-hero {
    position: absolute;
    left: calc(var(--u) * 34.5);
    bottom: calc(var(--u) * 7.5);
    width: calc(var(--u) * 23.8);
    height: calc(var(--u) * 31);
    z-index: 3;
    animation: fadeIn 1s ease 0.2s both;
  }

  .sds-hero-scene {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: calc(var(--u) * 15.73);
    background: url('{{ \App\Helpers\Helpers::appWebBasePath() }}assets/img/pages/login-hero-plinth.jpg') center bottom / 100% 100% no-repeat;
    -webkit-mask-image: linear-gradient(to right, #000 94%, transparent 100%);
    mask-image: linear-gradient(to right, #000 94%, transparent 100%);
    z-index: 1;
  }

  .sds-phone {
    position: absolute;
    left: calc(var(--u) * 2.46);
    bottom: calc(var(--u) * 6.61);
    width: calc(var(--u) * 11.9);
    z-index: 2;
    animation: phoneFloat 6.5s ease-in-out infinite;
  }
  @keyframes phoneFloat {
    0%, 100% { transform: translateY(0); }
    50%      { transform: translateY(calc(var(--u) * -0.55)); }
  }
  .sds-phone-frame {
    position: relative;
    padding: calc(var(--u) * 0.42);
    border-radius: calc(var(--u) * 2);
    background: linear-gradient(155deg, #2e3c50 0%, #0f1a26 55%, #27333f 100%);
    box-shadow: 0 calc(var(--u) * 1.5) calc(var(--u) * 2.8) rgba(20, 38, 63, 0.3),
                inset 0 0 0 1px rgba(255, 255, 255, 0.08);
  }
  .sds-phone-frame::after {
    content: '';
    position: absolute;
    top: calc(var(--u) * 0.42);
    left: 50%;
    transform: translateX(-50%);
    width: 34%;
    height: calc(var(--u) * 0.75);
    background: #0f1a26;
    border-radius: 0 0 calc(var(--u) * 0.5) calc(var(--u) * 0.5);
    z-index: 3;
  }
  .sds-phone-screen {
    position: relative;
    aspect-ratio: 218 / 452;
    border-radius: calc(var(--u) * 1.62);
    overflow: hidden;
    background: #fbfcfe;
    display: flex;
    flex-direction: column;
  }

  .sds-app-status {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: calc(var(--u) * 0.42) calc(var(--u) * 0.7) 0;
    font-size: calc(var(--u) * 0.38);
    font-weight: 700;
    color: #2c3a4d;
  }
  .sds-app-status i { font-size: calc(var(--u) * 0.4); }

  .sds-app-stage {
    position: relative;
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
  }

  .sds-app-view {
    position: absolute;
    inset: 0;
    padding: calc(var(--u) * 0.28) calc(var(--u) * 0.62) calc(var(--u) * 0.2);
    display: flex;
    flex-direction: column;
    gap: calc(var(--u) * 0.42);
    opacity: 0;
    transform: translateX(14%);
    pointer-events: none;
    transition: opacity 0.42s ease, transform 0.42s cubic-bezier(.16,1,.3,1);
  }
  .sds-app-view.is-active {
    opacity: 1;
    transform: translateX(0);
    pointer-events: auto;
  }
  .sds-app-view.is-leave {
    opacity: 0;
    transform: translateX(-10%);
  }

  .sds-app-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: calc(var(--u) * 0.48);
    color: #9aa7b8;
  }
  .sds-app-hello {
    font-size: calc(var(--u) * 0.42);
    font-weight: 600;
    color: #8d9aab;
  }
  .sds-app-hello strong {
    display: block;
    font-size: calc(var(--u) * 0.58);
    font-weight: 800;
    color: var(--ink);
  }

  .sds-balance {
    position: relative;
    overflow: hidden;
    border-radius: calc(var(--u) * 0.72);
    padding: calc(var(--u) * 0.55) calc(var(--u) * 0.62);
    background: linear-gradient(135deg, #3f93f6 0%, #1c6fe0 55%, #1a5fc8 100%);
    box-shadow: 0 calc(var(--u) * 0.45) calc(var(--u) * 0.9) rgba(28, 111, 224, 0.28);
  }
  .sds-balance::after {
    content: '';
    position: absolute;
    top: 0;
    left: -60%;
    width: 45%;
    height: 100%;
    background: linear-gradient(100deg, transparent, rgba(255, 255, 255, 0.28), transparent);
    animation: cardSheen 5s ease-in-out infinite;
  }
  @keyframes cardSheen {
    0%        { left: -60%; }
    55%, 100% { left: 130%; }
  }
  .sds-balance-label {
    font-size: calc(var(--u) * 0.38);
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.8);
  }
  .sds-balance-amount {
    font-size: calc(var(--u) * 0.88);
    font-weight: 800;
    letter-spacing: -0.02em;
    color: #fff;
    margin-top: calc(var(--u) * 0.06);
  }
  .sds-balance-meta {
    display: flex;
    justify-content: space-between;
    margin-top: calc(var(--u) * 0.32);
    font-size: calc(var(--u) * 0.38);
    font-weight: 600;
    color: rgba(255, 255, 255, 0.88);
  }

  .sds-app-actions {
    display: flex;
    justify-content: space-between;
    gap: calc(var(--u) * 0.2);
  }
  .sds-app-action {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: calc(var(--u) * 0.14);
    font-size: calc(var(--u) * 0.34);
    font-weight: 600;
    color: #8d9aab;
  }
  .sds-app-action i {
    width: calc(var(--u) * 1.28);
    height: calc(var(--u) * 1.28);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: calc(var(--u) * 0.56);
    background: #eaf2ff;
    color: #2b80f1;
  }
  .sds-app-action.is-hit i {
    animation: tapPulse 0.55s ease;
  }
  @keyframes tapPulse {
    0%   { transform: scale(1); }
    40%  { transform: scale(0.86); }
    100% { transform: scale(1); }
  }

  .sds-app-section {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    font-size: calc(var(--u) * 0.44);
    font-weight: 700;
    color: var(--ink);
  }
  .sds-app-section span {
    font-size: calc(var(--u) * 0.36);
    font-weight: 500;
    color: #b3bdca;
  }

  .sds-activity {
    display: flex;
    flex-direction: column;
    gap: calc(var(--u) * 0.28);
  }
  .sds-activity-row {
    display: flex;
    align-items: center;
    gap: calc(var(--u) * 0.32);
    padding: calc(var(--u) * 0.3);
    background: #fff;
    border: 1px solid #eef2f7;
    border-radius: calc(var(--u) * 0.5);
  }
  .sds-activity-row i {
    flex: 0 0 auto;
    width: calc(var(--u) * 1.02);
    height: calc(var(--u) * 1.02);
    border-radius: calc(var(--u) * 0.32);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: calc(var(--u) * 0.48);
  }
  .sds-activity-row .t-green { background: #e7f8ef; color: #17b26a; }
  .sds-activity-row .t-blue  { background: #e9f2ff; color: #2b80f1; }
  .sds-activity-row .t-orange { background: #fff3e6; color: #f79009; }
  .sds-activity-row .t-red   { background: #ffeceb; color: #f04438; }
  .sds-activity-name {
    font-size: calc(var(--u) * 0.4);
    font-weight: 700;
    line-height: 1.25;
    color: var(--ink);
  }
  .sds-activity-date {
    font-size: calc(var(--u) * 0.32);
    color: #b3bdca;
  }
  .sds-activity-amt {
    margin-left: auto;
    font-size: calc(var(--u) * 0.4);
    font-weight: 700;
    white-space: nowrap;
  }
  .sds-activity-amt.up   { color: #17b26a; }
  .sds-activity-amt.down { color: #f04438; }
  .sds-activity-amt.wait { color: #f79009; }

  .sds-due-card {
    background: #fff;
    border: 1px solid #eef2f7;
    border-radius: calc(var(--u) * 0.7);
    padding: calc(var(--u) * 0.55);
    display: flex;
    flex-direction: column;
    gap: calc(var(--u) * 0.32);
  }
  .sds-due-kicker {
    font-size: calc(var(--u) * 0.34);
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #2b80f1;
  }
  .sds-due-title {
    font-size: calc(var(--u) * 0.52);
    font-weight: 800;
    color: var(--ink);
  }
  .sds-due-meta {
    font-size: calc(var(--u) * 0.36);
    color: #8d9aab;
  }
  .sds-due-amt {
    font-size: calc(var(--u) * 0.92);
    font-weight: 800;
    color: var(--ink);
  }
  .sds-due-progress {
    height: calc(var(--u) * 0.28);
    border-radius: 99px;
    background: #eef3f9;
    overflow: hidden;
  }
  .sds-due-progress span {
    display: block;
    height: 100%;
    width: 62%;
    border-radius: inherit;
    background: linear-gradient(90deg, #3f93f6, #1c6fe0);
  }
  .sds-app-view.is-active .sds-due-progress span {
    animation: dueGrow 1.1s ease both;
  }
  @keyframes dueGrow {
    from { width: 0; }
    to   { width: 62%; }
  }

  .sds-pay-btn {
    position: relative;
    overflow: hidden;
    margin-top: auto;
    display: flex;
    align-items: center;
    justify-content: center;
    height: calc(var(--u) * 1.7);
    border-radius: calc(var(--u) * 0.5);
    font-size: calc(var(--u) * 0.48);
    font-weight: 700;
    color: #fff;
    background: linear-gradient(95deg, #3f93f6 0%, #1c6fe0 100%);
  }
  .sds-pay-btn.is-paying {
    animation: tapPulse 0.4s ease;
  }
  .sds-pay-btn.is-paying::after {
    content: '';
    position: absolute;
    inset: 0 auto 0 0;
    width: 0;
    background: rgba(255, 255, 255, 0.28);
    animation: payFill 1.15s ease forwards;
  }
  @keyframes payFill {
    to { width: 100%; }
  }

  .sds-success {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    gap: calc(var(--u) * 0.28);
  }
  .sds-success-mark {
    width: calc(var(--u) * 2.4);
    height: calc(var(--u) * 2.4);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: calc(var(--u) * 1.2);
    color: #fff;
    background: #17b26a;
    box-shadow: 0 calc(var(--u) * 0.35) calc(var(--u) * 0.8) rgba(23, 178, 106, 0.28);
  }
  .sds-app-view.is-active .sds-success-mark {
    animation: popIn 0.5s cubic-bezier(.16,1,.3,1) both;
  }
  @keyframes popIn {
    from { transform: scale(0.4); opacity: 0; }
    to   { transform: scale(1); opacity: 1; }
  }
  .sds-success h4 {
    margin: calc(var(--u) * 0.2) 0 0;
    font-size: calc(var(--u) * 0.58);
    font-weight: 800;
    color: var(--ink);
  }
  .sds-success p {
    margin: 0;
    font-size: calc(var(--u) * 0.4);
    color: #8d9aab;
  }
  .sds-success strong {
    font-size: calc(var(--u) * 0.78);
    font-weight: 800;
    color: #17b26a;
  }

  .sds-app-tabs {
    flex: 0 0 auto;
    display: flex;
    justify-content: space-around;
    border-top: 1px solid #eef2f7;
    padding: calc(var(--u) * 0.32) 0 calc(var(--u) * 0.48);
    background: #fff;
  }
  .sds-app-tab {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: calc(var(--u) * 0.08);
    font-size: calc(var(--u) * 0.32);
    color: #c0cad6;
    transition: color 0.25s;
  }
  .sds-app-tab i { font-size: calc(var(--u) * 0.58); }
  .sds-app-tab.is-active { color: #2b80f1; }

  /* ══════════ RIGHT — login card ══════════ */
  .sds-right {
    position: relative;
    z-index: 5;
    flex: 0 0 41.4vw;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    padding: calc(var(--u) * 2) calc(var(--u) * 7.7) calc(var(--u) * 2) calc(var(--u) * 1.9);
  }

  .sds-card {
    width: min(calc(var(--u) * 31.7), 100%);
    background: #fff;
    border-radius: calc(var(--u) * 1.95);
    padding: calc(var(--u) * 2.15) calc(var(--u) * 3.6) calc(var(--u) * 2);
    box-shadow: 0 calc(var(--u) * 1.7) calc(var(--u) * 3.6) rgba(6, 21, 40, 0.3),
                0 calc(var(--u) * 0.3) calc(var(--u) * 0.9) rgba(6, 21, 40, 0.14);
    animation: cardIn 0.9s cubic-bezier(.16,1,.3,1) 0.15s both;
  }
  @keyframes cardIn {
    from { opacity: 0; transform: translateX(calc(var(--u) * 1.8)) scale(0.975); }
    to   { opacity: 1; transform: translateX(0) scale(1); }
  }

  .sds-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: calc(var(--u) * 0.6);
    margin-bottom: calc(var(--u) * 1.45);
  }
  .sds-card-brand {
    display: flex;
    align-items: center;
    gap: calc(var(--u) * 0.7);
    min-width: 0;
  }
  .sds-card-brand .sds-brand-mark {
    width: calc(var(--u) * 3.3);
    height: calc(var(--u) * 3.3);
  }
  .sds-card-brand .sds-brand-mark img,
  .sds-card-brand .sds-brand-mark svg {
    max-width: calc(var(--u) * 2.1);
    max-height: calc(var(--u) * 2.1);
  }
  .sds-card-brand .sds-brand-name { font-size: calc(var(--u) * 1.15); }
  .sds-card-brand .sds-brand-sub { font-size: calc(var(--u) * 0.55); }

  .sds-secure-pill {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    gap: calc(var(--u) * 0.22);
    padding: calc(var(--u) * 0.3) calc(var(--u) * 0.6);
    border-radius: 999px;
    font-size: calc(var(--u) * 0.6);
    font-weight: 600;
    white-space: nowrap;
    color: #5c6b7f;
    background: #f4f7fb;
    border: 1px solid var(--line);
  }
  .sds-secure-pill i {
    font-size: calc(var(--u) * 0.66);
    color: #17b26a;
  }

  .sds-welcome {
    font-size: calc(var(--u) * 1.66);
    font-weight: 800;
    letter-spacing: -0.02em;
    color: var(--ink);
    margin: 0 0 calc(var(--u) * 0.35);
  }
  .sds-wave {
    display: inline-block;
    transform-origin: 70% 70%;
    animation: wave 2.6s ease-in-out infinite;
  }
  @keyframes wave {
    0%, 60%, 100% { transform: rotate(0deg); }
    10% { transform: rotate(14deg); }
    20% { transform: rotate(-8deg); }
    30% { transform: rotate(14deg); }
    40% { transform: rotate(-4deg); }
  }
  .sds-welcome-sub {
    font-size: calc(var(--u) * 0.8);
    line-height: 1.5;
    color: var(--muted);
    margin: 0 0 calc(var(--u) * 1.5);
  }

  .sds-field { margin-bottom: calc(var(--u) * 1); }
  .sds-label {
    display: block;
    font-size: calc(var(--u) * 0.75);
    font-weight: 600;
    color: #45546a;
    margin-bottom: calc(var(--u) * 0.45);
  }
  .sds-input {
    display: flex;
    align-items: stretch;
    height: calc(var(--u) * 2.73);
    border-radius: calc(var(--u) * 0.62);
    background: #f7f9fc;
    border: 1px solid var(--line);
    transition: border-color 0.25s, box-shadow 0.25s, background 0.25s;
  }
  .sds-input:focus-within {
    background: #fff;
    border-color: rgba(43, 128, 241, 0.55);
    box-shadow: 0 0 0 calc(var(--u) * 0.2) rgba(43, 128, 241, 0.1);
  }
  .sds-input-icon {
    display: flex;
    align-items: center;
    padding-left: calc(var(--u) * 0.82);
    font-size: calc(var(--u) * 0.95);
    color: #9aa7b8;
  }
  .sds-input:focus-within .sds-input-icon { color: var(--blue); }
  .sds-input .form-control {
    height: 100% !important;
    min-height: 0 !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    border-radius: calc(var(--u) * 0.62) !important;
    padding: 0 calc(var(--u) * 0.7) !important;
    font-size: calc(var(--u) * 0.83) !important;
    line-height: 1.2 !important;
    color: #2c3a4d !important;
  }
  .sds-input .form-control::placeholder { color: #aab5c4 !important; }
  .sds-input .form-control:-webkit-autofill {
    -webkit-box-shadow: 0 0 0 1000px #f7f9fc inset !important;
    -webkit-text-fill-color: #2c3a4d !important;
  }
  .sds-eye {
    display: flex;
    align-items: center;
    padding: 0 calc(var(--u) * 0.8);
    font-size: calc(var(--u) * 0.9);
    color: #aab5c4;
    cursor: pointer;
    transition: color 0.2s;
  }
  .sds-eye:hover { color: var(--blue); }
  .sds-eye i { font-size: inherit !important; }

  .sds-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: calc(var(--u) * 0.6);
    margin: calc(var(--u) * 0.2) 0 calc(var(--u) * 1.15);
  }
  .sds-row .form-check {
    margin: 0;
    min-height: 0;
    padding-left: calc(var(--u) * 1.6);
    display: flex;
    align-items: center;
  }
  .sds-row .form-check-input {
    width: calc(var(--u) * 1.12);
    height: calc(var(--u) * 1.12);
    margin: 0 0 0 calc(var(--u) * -1.6);
    border: 1.5px solid #ccd6e2 !important;
    background-color: #fff !important;
  }
  .sds-row .form-check-input:checked {
    background-color: var(--blue) !important;
    border-color: var(--blue) !important;
  }
  .sds-row .form-check-label {
    font-size: calc(var(--u) * 0.76);
    color: #5c6b7f !important;
    padding-left: calc(var(--u) * 0.45);
  }
  .sds-link {
    font-size: calc(var(--u) * 0.76);
    font-weight: 600;
    color: var(--blue) !important;
    text-decoration: none;
    white-space: nowrap;
    transition: color 0.2s;
  }
  .sds-link:hover { color: var(--blue-dark) !important; text-decoration: underline !important; }

  .sds-btn-signin {
    position: relative;
    display: flex !important;
    align-items: center;
    justify-content: center;
    gap: calc(var(--u) * 0.35);
    width: 100%;
    height: calc(var(--u) * 3.03);
    overflow: hidden;
    border: none !important;
    border-radius: calc(var(--u) * 0.68) !important;
    padding: 0 !important;
    font-size: calc(var(--u) * 0.9) !important;
    font-weight: 600 !important;
    color: #fff !important;
    background: linear-gradient(95deg, #3f93f6 0%, #1c6fe0 55%, #1560cf 100%) !important;
    box-shadow: 0 calc(var(--u) * 0.5) calc(var(--u) * 1.1) rgba(28, 111, 224, 0.32) !important;
    transition: transform 0.22s, box-shadow 0.22s !important;
  }
  .sds-btn-signin::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 60%;
    height: 100%;
    background: linear-gradient(95deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    animation: btnShine 3.4s ease-in-out infinite;
  }
  @keyframes btnShine {
    0%        { left: -100%; }
    55%, 100% { left: 140%; }
  }
  .sds-btn-signin:hover {
    transform: translateY(-2px);
    box-shadow: 0 calc(var(--u) * 0.7) calc(var(--u) * 1.4) rgba(28, 111, 224, 0.42) !important;
  }
  .sds-btn-signin:active { transform: translateY(0); }
  .sds-btn-signin i,
  .sds-btn-signin span { position: relative; z-index: 1; }

  .sds-back {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: calc(var(--u) * 0.28);
    margin-top: calc(var(--u) * 1.15);
    font-size: calc(var(--u) * 0.78);
    font-weight: 600;
    color: var(--blue) !important;
    text-decoration: none;
    transition: color 0.2s, gap 0.2s;
  }
  .sds-back:hover {
    color: var(--blue-dark) !important;
    gap: calc(var(--u) * 0.45);
  }
  .sds-back i { font-size: calc(var(--u) * 1.05); }

  .sds-card-foot {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: calc(var(--u) * 0.25);
    margin: calc(var(--u) * 1.5) 0 0;
    font-size: calc(var(--u) * 0.7);
    color: #a6b1c0;
  }

  .sds-policy {
    position: absolute;
    right: calc(var(--u) * 4.8);
    bottom: calc(var(--u) * 2.2);
    z-index: 6;
    font-size: calc(var(--u) * 0.72);
    white-space: nowrap;
    animation: fadeUp 0.8s ease 0.7s both;
  }
  .sds-policy a {
    color: rgba(226, 236, 248, 0.7) !important;
    text-decoration: none;
    transition: color 0.2s;
  }
  .sds-policy a:hover { color: #fff !important; }
  .sds-policy .sep {
    margin: 0 calc(var(--u) * 0.45);
    color: rgba(226, 236, 248, 0.3);
  }

  .sds-alert {
    border-radius: calc(var(--u) * 0.62) !important;
    padding: calc(var(--u) * 0.6) calc(var(--u) * 0.8) !important;
    font-size: calc(var(--u) * 0.76) !important;
    margin-bottom: calc(var(--u) * 1) !important;
  }
  .sds-invalid {
    display: block;
    font-size: calc(var(--u) * 0.68);
    color: #f04438;
    margin-top: calc(var(--u) * 0.3);
  }

  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  @keyframes fadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
  }

  /* ══════════ Responsive ══════════ */

  /* Narrow desktops: drop the photographic hero, keep the split. */
  @media (max-width: 1199.98px) {
    .authentication-wrapper { --u: min(1.15vw, 1.7778vh, 20px); }
    .sds-hero { display: none; }
    .sds-left-body { align-items: center; }
  }

  /* Tablet / mobile: stack the marketing copy over a full-width dark base. */
  @media (max-width: 991.98px) {
    .authentication-wrapper { --u: min(2.6vw, 1.1vh, 20px); }
    .sds-shell { flex-direction: column; }
    .sds-dark {
      inset: auto 0 0 0;
      width: 100%;
      height: 60%;
      border-radius: calc(var(--u) * 3) calc(var(--u) * 3) 0 0;
    }
    .sds-dark-curve { display: none; }
    .sds-left {
      flex: 0 0 auto;
      padding: calc(var(--u) * 3) calc(var(--u) * 3) 0;
    }
    .sds-copy { width: 100%; margin-left: 0; }
    .sds-sub { width: 100%; }
    .sds-script, .sds-copyright { display: none; }
    .sds-features { margin-bottom: calc(var(--u) * 1.5); }
    .sds-right {
      flex: 1 1 auto;
      justify-content: center;
      padding: calc(var(--u) * 2) calc(var(--u) * 3) calc(var(--u) * 6);
    }
    .sds-card { width: min(calc(var(--u) * 46), 100%); }
    .sds-policy {
      left: 0;
      right: 0;
      bottom: calc(var(--u) * 1.6);
      text-align: center;
    }
  }

  @media (max-width: 575.98px) {
    .authentication-wrapper { --u: min(4.2vw, 1.05vh, 20px); }
    .sds-features { grid-template-columns: 1fr; row-gap: calc(var(--u) * 1.4); }
    .sds-feature:nth-child(n+3) { display: none; }
    .sds-card { width: 100%; padding: calc(var(--u) * 2.4) calc(var(--u) * 2.2); }
  }

  @media (prefers-reduced-motion: reduce) {
    .sds-blob, .sds-dark-circle, .sds-dark-dots, .sds-dark-glow,
    .sds-phone, .sds-balance::after, .sds-btn-signin::before,
    .sds-wave, .sds-script-underline path,
    .sds-app-view, .sds-success-mark, .sds-due-progress span,
    .sds-pay-btn, .sds-pay-btn.is-paying::after, .sds-app-action.is-hit i {
      animation: none !important;
      transition: none !important;
    }
    .sds-app-view { transform: none; }
    .sds-app-view:not(.is-active) { display: none; opacity: 0; }
  }

  .authentication-wrapper.sds-lock-overlay {
    position: fixed !important;
    inset: 0 !important;
    z-index: 2147483000 !important;
    width: 100% !important;
    height: 100% !important;
    min-height: 100vh !important;
    min-height: 100dvh !important;
    margin: 0 !important;
    padding: 0 !important;
    background: #fff !important;
    pointer-events: auto;
    transform: none !important;
    filter: none !important;
    isolation: isolate;
  }
  .authentication-wrapper.sds-lock-overlay[hidden] {
    display: none !important;
  }
  .sds-lock-overlay .sds-input .form-control[readonly] {
    background: transparent !important;
    color: #2c3a4d !important;
    cursor: default;
    opacity: 1;
  }
  html.sds-session-locked,
  html.sds-session-locked body {
    overflow: hidden !important;
  }
  html.sds-session-locked .layout-wrapper,
  html.sds-session-locked .layout-overlay,
  html.sds-session-locked .layout-menu,
  html.sds-session-locked .layout-navbar,
  html.sds-session-locked .layout-page,
  html.sds-session-locked .template-customizer,
  html.sds-session-locked .buy-now {
    visibility: hidden !important;
    pointer-events: none !important;
  }
</style>
