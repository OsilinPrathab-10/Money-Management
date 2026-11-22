<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', $brand['name'] ?? 'Star Finance')</title>
  <meta name="description"
    content="@yield('meta_description', $brand['tagline'] ?? 'Empowering your financial journey')">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  @if(!empty($brand['favicon']))
    <link rel="icon" type="image/png" href="{{ $brand['favicon'] }}">
    <link rel="shortcut icon" type="image/png" href="{{ $brand['favicon'] }}">
  @else
    <link rel="icon" type="image/svg+xml"
      href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%230e2b5c'%3E%3Cpath d='M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z'/%3E%3C/svg%3E">
  @endif
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Caveat:wght@600;700&display=swap"
    rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">
  <style>
    :root {
      --sf-ink: #0b1220;
      --sf-navy: #122033;
      --sf-navy2: #1a2f4a;
      --sf-blue: #1a5cff;
      --sf-blue2: #3b7bff;
      --sf-sky: #eef4ff;
      --sf-gold: #e8a317;
      --sf-gold2: #c9850d;
      --sf-green: #0f9f6e;
      --sf-muted: #5b6b7c;
      --sf-line: #e6edf5;
      --sf-paper: #f4f7fb;
      --sf-white: #ffffff;
      --sf-radius: 1rem;
      --sf-radius-sm: .7rem;
      --sf-shadow: 0 1px 2px rgba(11, 18, 32, .04), 0 12px 32px rgba(11, 18, 32, .06);
      --sf-shadow-lg: 0 20px 50px rgba(11, 18, 32, .12);
      --sf-font: 'Plus Jakarta Sans', ui-sans-serif, sans-serif;
    }

    * {
      box-sizing: border-box;
    }

    body {
      font-family: var(--sf-font);
      color: var(--sf-ink);
      background:
        radial-gradient(1200px 500px at 10% -10%, rgba(26, 92, 255, .08), transparent 55%),
        radial-gradient(900px 420px at 100% 0%, rgba(232, 163, 23, .07), transparent 50%),
        var(--sf-paper);
      font-size: .95rem;
      line-height: 1.65;
      -webkit-font-smoothing: antialiased;
    }

    a {
      text-underline-offset: 2px;
    }

    /* ── Top Bar ── */
    .sf-topbar {
      background: #ffffff;
      border-bottom: 1px solid rgba(226, 232, 240, 0.8);
      font-size: .815rem;
      font-weight: 600;
      color: #475569;
    }

    .sf-topbar-link {
      color: #475569;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      transition: color .15s ease;
    }

    .sf-topbar-link:hover {
      color: var(--sf-blue);
    }

    main.sf-main {
      min-height: 55vh;
    }

    /* ── Brand mark ── */
    .sf-mark {
      width: 38px;
      height: 38px;
      border-radius: .75rem;
      background: linear-gradient(145deg, #0e2b5c, #1a5cff);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 6px 16px rgba(14, 43, 92, .25);
      flex-shrink: 0;
    }

    .sf-mark i {
      color: #fff;
      font-size: 1.15rem;
    }

    .sf-logo-img {
      max-height: 40px;
      width: auto;
      object-fit: contain;
    }

    .sf-mark i {
      color: #fff;
      font-size: 1.05rem;
    }

    /* ── Navbar ── */
    .sf-nav {
      background: rgba(255, 255, 255, .96);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border-bottom: 1px solid rgba(226, 232, 240, .9);
      padding: .65rem 0;
    }

    .sf-nav .navbar-brand {
      font-weight: 800;
      letter-spacing: -.03em;
      font-size: 1.22rem;
      color: #0e2b5c !important;
    }

    .sf-nav .nav-link {
      color: #334155;
      font-weight: 600;
      font-size: .88rem;
      padding: .45rem .85rem !important;
      border-radius: 999px;
      transition: color .15s, background .15s;
    }

    .sf-nav .nav-link:hover {
      color: #1a5cff;
      background: rgba(238, 244, 255, .8);
    }

    .sf-nav .nav-link.active-link {
      color: #1a5cff;
      background: #eef4ff;
    }

    .sf-nav .dropdown-menu {
      background: #fff;
      border: 1px solid var(--sf-line);
      border-radius: 1rem;
      box-shadow: 0 16px 40px rgba(14, 43, 92, .1);
      padding: .5rem;
      margin-top: .5rem !important;
    }

    .sf-nav .dropdown-item {
      color: #334155;
      font-size: .875rem;
      font-weight: 600;
      padding: .55rem .9rem;
      border-radius: .55rem;
    }

    .sf-nav .dropdown-item:hover {
      background: var(--sf-sky);
      color: var(--sf-blue);
    }

    .sf-nav .dropdown-divider {
      border-color: var(--sf-line);
      opacity: 1;
      margin: .35rem 0;
    }

    .btn-sf-login {
      background: transparent;
      border: 1px solid #cbd5e1;
      color: #0e2b5c;
      font-weight: 700;
      font-size: .86rem;
      border-radius: 999px;
      padding: .45rem 1.15rem;
      transition: .15s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
    }

    .btn-sf-login:hover {
      border-color: #0e2b5c;
      background: #f8fafc;
      color: #0e2b5c;
    }

    .btn-sf-login:hover {
      border-color: #c9d6e5;
      background: #f8fafc;
      color: var(--sf-ink);
    }

    .btn-sf-cta,
    .btn-sf-apply-pill {
      background: #0e2b5c;
      border: 1px solid #0e2b5c;
      color: #fff !important;
      font-weight: 700;
      font-size: .88rem;
      border-radius: 999px;
      padding: .5rem 1.35rem;
      transition: all .2s cubic-bezier(0.16, 1, 0.3, 1);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      box-shadow: 0 6px 18px rgba(14, 43, 92, .22);
    }

    .btn-sf-cta:hover,
    .btn-sf-apply-pill:hover {
      background: #184ca1;
      border-color: #184ca1;
      color: #fff !important;
      transform: translateY(-2px);
      box-shadow: 0 10px 24px rgba(14, 43, 92, .32);
    }

    .navbar-toggler {
      border: 0;
      padding: 0;
    }

    .navbar-toggler i {
      color: var(--sf-ink);
    }

    /* ── Shared section helpers ── */
    .sf-section-label {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      background: #fff;
      color: var(--sf-blue);
      font-size: .72rem;
      font-weight: 800;
      letter-spacing: .08em;
      text-transform: uppercase;
      padding: .35rem .8rem;
      border-radius: 999px;
      margin-bottom: .75rem;
      border: 1px solid var(--sf-line);
    }

    .sf-section-title {
      font-weight: 800;
      color: var(--sf-ink);
      line-height: 1.2;
      letter-spacing: -.03em;
    }

    .sf-section-sub {
      color: var(--sf-muted);
      font-size: 1.02rem;
      max-width: 560px;
      line-height: 1.7;
    }

    .sf-page-hero {
      position: relative;
      overflow: hidden;
      color: #fff;
      padding: 3.25rem 0 3rem;
      background:
        radial-gradient(ellipse 70% 80% at 90% 10%, rgba(232, 163, 23, .18), transparent 55%),
        linear-gradient(145deg, #0b1220 0%, #12284a 48%, #1a5cff 100%);
    }

    .sf-page-hero::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(180deg, transparent 60%, rgba(11, 18, 32, .18));
      pointer-events: none;
    }

    .sf-page-hero .container {
      position: relative;
      z-index: 1;
    }

    .sf-page-hero h1 {
      font-weight: 800;
      letter-spacing: -.035em;
      line-height: 1.15;
    }

    .sf-page-hero .breadcrumb {
      margin-bottom: 0;
    }

    .sf-page-hero .breadcrumb-item+.breadcrumb-item::before {
      color: rgba(255, 255, 255, .45);
    }

    .sf-surface {
      background: var(--sf-white);
      border: 1px solid var(--sf-line);
      border-radius: var(--sf-radius);
      box-shadow: var(--sf-shadow);
    }

    .form-control,
    .form-select {
      border-radius: var(--sf-radius-sm);
      border-color: var(--sf-line);
      padding: .7rem .9rem;
      font-size: .92rem;
      background: #fff;
    }

    .form-control:focus,
    .form-select:focus {
      border-color: #93b4ff;
      box-shadow: 0 0 0 4px rgba(26, 92, 255, .12);
    }

    .btn-primary {
      background: var(--sf-blue);
      border-color: var(--sf-blue);
      font-weight: 700;
      border-radius: .65rem;
      padding: .55rem 1.15rem;
    }

    .btn-primary:hover,
    .btn-primary:focus {
      background: #1248d6;
      border-color: #1248d6;
    }

    .btn-outline-primary {
      border-color: #b8ccff;
      color: var(--sf-blue);
      font-weight: 700;
      border-radius: .65rem;
      background: #fff;
    }

    .btn-outline-primary:hover {
      background: var(--sf-sky);
      border-color: var(--sf-blue);
      color: #1248d6;
    }

    .sf-card {
      border: 1px solid var(--sf-line);
      box-shadow: var(--sf-shadow);
      border-radius: var(--sf-radius);
      transition: border-color .2s, box-shadow .2s, transform .2s;
      background: #fff;
    }

    .sf-card:hover {
      border-color: #c7d7f5;
      box-shadow: var(--sf-shadow-lg);
      transform: translateY(-2px);
    }

    .loan-card-icon {
      width: 54px;
      height: 54px;
      border-radius: .85rem;
      background: var(--sf-sky);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.45rem;
      color: var(--sf-blue);
    }

    .loan-rate-badge {
      background: #e8faf3;
      color: #0b6b4a;
      font-size: .78rem;
      font-weight: 700;
      padding: .25rem .65rem;
      border-radius: 999px;
    }

    .sf-stat-num {
      font-size: 2rem;
      font-weight: 800;
      color: var(--sf-blue);
      line-height: 1;
      letter-spacing: -.03em;
    }

    .sf-stat-label {
      font-size: .85rem;
      color: var(--sf-muted);
      font-weight: 500;
      margin-top: .3rem;
    }

    .step-circle {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: linear-gradient(145deg, var(--sf-blue2), var(--sf-blue));
      color: #fff;
      font-size: 1.25rem;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 10px 24px rgba(26, 92, 255, .28);
      flex-shrink: 0;
    }

    .why-icon {
      width: 60px;
      height: 60px;
      border-radius: 1rem;
      background: var(--sf-sky);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      color: var(--sf-blue);
      margin: 0 auto .75rem;
    }

    .testi-card {
      border-left: 4px solid var(--sf-gold);
      border-radius: var(--sf-radius);
    }

    .testi-stars {
      color: var(--sf-gold);
      letter-spacing: .08em;
    }

    .sf-cta-band {
      background:
        radial-gradient(ellipse 50% 80% at 100% 0%, rgba(232, 163, 23, .2), transparent 55%),
        linear-gradient(135deg, #0b1220 0%, #153a7a 55%, #1a5cff 100%);
      border-radius: 1.5rem;
      color: #fff;
    }

    /* ── Footer ── */
    .sf-footer {
      background:
        radial-gradient(700px 280px at 0% 0%, rgba(26, 92, 255, .18), transparent 60%),
        #0b1220;
      color: rgba(255, 255, 255, .68);
      margin-top: 0;
    }

    .sf-footer h5,
    .sf-footer h6 {
      color: #fff;
      font-weight: 800;
      letter-spacing: -.02em;
    }

    .sf-footer a {
      color: rgba(255, 255, 255, .58);
      text-decoration: none;
      font-size: .88rem;
      font-weight: 500;
      transition: color .15s;
    }

    .sf-footer a:hover {
      color: #fff;
    }

    .sf-footer-divider {
      border-color: rgba(255, 255, 255, .1);
    }

    .sf-social-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      height: 38px;
      border-radius: .7rem;
      background: rgba(255, 255, 255, .08);
      border: 1px solid rgba(255, 255, 255, .08);
      color: rgba(255, 255, 255, .75);
      text-decoration: none;
      transition: .15s ease;
    }

    .sf-social-btn:hover {
      background: var(--sf-blue);
      border-color: var(--sf-blue);
      color: #fff;
    }

    .sf-alert-top {
      border-radius: 0;
      border: none;
      border-bottom: 3px solid var(--sf-green);
    }

    /* ── Scroll progress bar ── */
    .sf-scroll-progress {
      position: fixed;
      top: 0;
      left: 0;
      height: 3px;
      width: 0%;
      background: linear-gradient(90deg, var(--sf-blue), #38bdf8, var(--sf-gold));
      z-index: 9999;
      transition: width .1s ease-out;
      box-shadow: 0 0 10px rgba(26, 92, 255, .6);
    }

    /* ── Navbar scrolled enhancement ── */
    .sf-nav {
      transition: background .25s ease, padding .25s ease, box-shadow .25s ease;
    }

    .sf-nav.scrolled {
      background: rgba(255, 255, 255, .94) !important;
      padding: .45rem 0 !important;
      box-shadow: 0 10px 30px rgba(11, 18, 32, .08);
      border-bottom-color: rgba(230, 237, 245, .8);
    }

    /* ── Micro-interactions & animations ── */
    @keyframes floatSlow {

      0%,
      100% {
        transform: translateY(0);
      }

      50% {
        transform: translateY(-8px);
      }
    }

    @keyframes glowPulse {

      0%,
      100% {
        opacity: .6;
        transform: scale(1);
      }

      50% {
        opacity: 1;
        transform: scale(1.05);
      }
    }

    @keyframes livePulse {
      0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
      }

      70% {
        transform: scale(1.1);
        box-shadow: 0 0 0 8px rgba(16, 185, 129, 0);
      }

      100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
      }
    }

    @keyframes shimmerSweep {
      0% {
        left: -100%;
      }

      100% {
        left: 200%;
      }
    }

    @keyframes rotateSlow {
      from {
        transform: rotate(0deg);
      }

      to {
        transform: rotate(360deg);
      }
    }

    .badge-live-pulse {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
    }

    .badge-live-pulse .pulse-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--sf-green);
      box-shadow: 0 0 8px var(--sf-green);
      animation: livePulse 1.8s infinite;
      display: inline-block;
    }

    .hover-lift {
      transition: transform .28s cubic-bezier(0.16, 1, 0.3, 1), box-shadow .28s cubic-bezier(0.16, 1, 0.3, 1), border-color .28s ease !important;
    }

    .hover-lift:hover {
      transform: translateY(-6px) !important;
      box-shadow: 0 20px 45px rgba(11, 18, 32, .12) !important;
    }

    .hover-glow-blue:hover {
      box-shadow: 0 16px 40px rgba(26, 92, 255, .18) !important;
      border-color: #b8ccff !important;
    }

    .hover-glow-gold:hover {
      box-shadow: 0 16px 40px rgba(232, 163, 23, .2) !important;
      border-color: #fce1a9 !important;
    }

    .btn-shimmer {
      position: relative;
      overflow: hidden;
    }

    .btn-shimmer::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 60%;
      height: 100%;
      background: linear-gradient(120deg, transparent, rgba(255, 255, 255, .32), transparent);
      animation: shimmerSweep 3.5s infinite;
      pointer-events: none;
    }

    /* ── Floating Contact & Back to Top Hub ── */
    .sf-floating-hub {
      position: fixed;
      right: 22px;
      bottom: 24px;
      z-index: 1040;
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: .65rem;
      pointer-events: none;
    }

    .sf-floating-btn {
      pointer-events: auto;
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      border-radius: 999px;
      padding: .65rem 1rem;
      font-weight: 700;
      font-size: .84rem;
      text-decoration: none;
      box-shadow: 0 10px 28px rgba(0, 0, 0, .18);
      transition: all .25s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .sf-floating-whatsapp {
      background: #25d366;
      color: #fff;
    }

    .sf-floating-whatsapp:hover {
      background: #20ba59;
      color: #fff;
      transform: translateY(-3px) scale(1.03);
      box-shadow: 0 14px 34px rgba(37, 211, 102, .4);
    }

    .sf-floating-backtop {
      width: 44px;
      height: 44px;
      padding: 0;
      justify-content: center;
      background: #0b1220;
      color: #fff;
      opacity: 0;
      transform: translateY(12px);
      transition: opacity .25s ease, transform .25s ease, background .2s ease;
      cursor: pointer;
      border: 1px solid rgba(255, 255, 255, .15);
    }

    .sf-floating-backtop.visible {
      opacity: 1;
      transform: translateY(0);
    }

    .sf-floating-backtop:hover {
      background: var(--sf-blue);
      color: #fff;
      transform: translateY(-3px);
    }

    /* ── Entrance Animations ── */
    @media (prefers-reduced-motion: no-preference) {

      .fade-up,
      .anim-fade-up {
        opacity: 0;
        transform: translateY(26px);
        transition: opacity .65s cubic-bezier(0.16, 1, 0.3, 1), transform .65s cubic-bezier(0.16, 1, 0.3, 1);
        will-change: opacity, transform;
      }

      .fade-up.visible,
      .anim-fade-up.visible {
        opacity: 1;
        transform: translateY(0);
      }

      .anim-fade-in {
        opacity: 0;
        transition: opacity .7s ease;
      }

      .anim-fade-in.visible {
        opacity: 1;
      }

      .anim-zoom {
        opacity: 0;
        transform: scale(0.92);
        transition: opacity .65s ease, transform .65s cubic-bezier(0.16, 1, 0.3, 1);
      }

      .anim-zoom.visible {
        opacity: 1;
        transform: scale(1);
      }

      .delay-1 {
        transition-delay: .1s !important;
      }

      .delay-2 {
        transition-delay: .2s !important;
      }

      .delay-3 {
        transition-delay: .3s !important;
      }

      .delay-4 {
        transition-delay: .4s !important;
      }

      .delay-5 {
        transition-delay: .5s !important;
      }

      .sf-hero-copy {
        animation: sfRise .7s cubic-bezier(0.16, 1, 0.3, 1) both;
      }

      .sf-hero-panel {
        animation: sfRise .75s .15s cubic-bezier(0.16, 1, 0.3, 1) both;
      }

      @keyframes sfRise {
        from {
          opacity: 0;
          transform: translateY(24px);
        }

        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
    }

    @media (max-width: 991.98px) {
      .sf-nav .navbar-nav {
        padding-top: .75rem;
        gap: .15rem;
      }

      .sf-nav .d-flex.align-items-center.gap-2 {
        width: 100%;
      }

      .btn-sf-login,
      .btn-sf-cta {
        justify-content: center;
        flex: 1;
      }

      .sf-floating-whatsapp span {
        display: none;
      }

      .sf-floating-whatsapp {
        width: 44px;
        height: 44px;
        padding: 0;
        justify-content: center;
        font-size: 1.25rem;
      }
    }
  </style>
  @stack('styles')
</head>

<body>
  <div id="sfScrollProgress" class="sf-scroll-progress"></div>

  {{-- ── TOP INFO BAR ── --}}
  <div class="sf-topbar d-none d-md-block">
    <div class="container d-flex justify-content-end align-items-center gap-4 py-2">
      @if(!empty($brand['phone']))
        <a href="tel:{{ $brand['phone'] }}" class="sf-topbar-link">
          <i class="ri-phone-fill text-primary"></i><span>{{ $brand['phone'] }}</span>
        </a>
      @endif
      @if(!empty($brand['email']))
        <a href="mailto:{{ $brand['email'] }}" class="sf-topbar-link">
          <i class="ri-mail-fill text-primary"></i><span>{{ $brand['email'] }}</span>
        </a>
      @endif
    </div>
  </div>

  <nav class="navbar navbar-expand-lg sf-nav sticky-top">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('website.home') }}">
        @if(!empty($brand['logo']))
          <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] ?? 'Star Finance' }}" class="sf-logo-img">
        @else
          <span class="sf-mark"><i class="ri-shield-flash-fill"></i></span>
        @endif
        <span class="fw-bold sf-brand-text"
          style="color:#0e2b5c;font-size:1.22rem;letter-spacing:-.03em">{{ $brand['name'] ?? 'Star Finance' }}</span>
      </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sfNav"
        aria-label="Toggle navigation">
        <i class="ri-menu-3-line fs-4"></i>
      </button>

      <div class="collapse navbar-collapse" id="sfNav">
        <ul class="navbar-nav mx-auto align-items-lg-center">
          <li class="nav-item"><a class="nav-link {{ request()->routeIs('website.home') ? 'active-link' : '' }}"
              href="{{ route('website.home') }}">Home</a></li>
          <li class="nav-item"><a class="nav-link {{ request()->routeIs('website.about') ? 'active-link' : '' }}"
              href="{{ route('website.about') }}">About Us</a></li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle {{ request()->routeIs('website.loans.*') ? 'active-link' : '' }}"
              href="#" data-bs-toggle="dropdown">Loan Products</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="{{ route('website.loans.index') }}"><i
                    class="ri-layout-grid-line me-2"></i>All Loan Products</a></li>
              <li>
                <hr class="dropdown-divider">
              </li>
              @foreach(($categories ?? collect())->take(8) as $cat)
                <li><a class="dropdown-item"
                    href="{{ route('website.loans.category', app(\App\Services\WebsiteService::class)->categorySlug($cat)) }}">{{ $cat->name }}</a>
                </li>
              @endforeach
            </ul>
          </li>
          <li class="nav-item"><a class="nav-link {{ request()->routeIs('website.services') ? 'active-link' : '' }}"
              href="{{ route('website.services') }}">Services</a></li>
          <li class="nav-item"><a class="nav-link {{ request()->routeIs('website.emi') ? 'active-link' : '' }}"
              href="{{ route('website.emi') }}">EMI Calculator</a></li>
          <li class="nav-item"><a class="nav-link {{ request()->routeIs('website.eligibility') ? 'active-link' : '' }}"
              href="{{ route('website.eligibility') }}">Eligibility</a></li>
          <li class="nav-item"><a class="nav-link {{ request()->routeIs('website.contact') ? 'active-link' : '' }}"
              href="{{ route('website.contact') }}">Contact Us</a></li>
        </ul>
        <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
          <a href="{{ route('login') }}" class="btn-sf-login">
            <i class="ri-user-line me-1"></i>Login
          </a>
          <a href="{{ route('website.apply') }}" class="btn-sf-cta">
            Apply Now <i class="ri-arrow-right-line ms-1"></i>
          </a>
        </div>
      </div>
  </nav>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible sf-alert-top mb-0" role="alert">
      <div class="container"><i class="ri-checkbox-circle-line me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible sf-alert-top mb-0" role="alert">
      <div class="container"><i class="ri-error-warning-line me-1"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    </div>
  @endif

  <main class="sf-main">
    @yield('content')
  </main>

  <footer class="sf-footer pt-5 pb-4 mt-5">
    <div class="container">
      <div class="row g-5">
        <div class="col-lg-4 col-md-6">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="sf-mark"><i class="ri-star-fill"></i></span>
            <span class="text-white fw-bold fs-5"
              style="letter-spacing:-.02em">{{ $brand['name'] ?? 'Star Finance' }}</span>
          </div>
          <p class="small mb-3" style="max-width:300px;line-height:1.75;">
            {{ $brand['tagline'] ?? 'Transparent lending with clear rates, digital verification, and support you can reach.' }}
          </p>
          <div class="d-flex gap-2">
            <a href="#" class="sf-social-btn" aria-label="Facebook"><i class="ri-facebook-fill"></i></a>
            <a href="#" class="sf-social-btn" aria-label="Instagram"><i class="ri-instagram-line"></i></a>
            <a href="#" class="sf-social-btn" aria-label="X"><i class="ri-twitter-x-line"></i></a>
            <a href="#" class="sf-social-btn" aria-label="LinkedIn"><i class="ri-linkedin-fill"></i></a>
            <a href="#" class="sf-social-btn" aria-label="WhatsApp"><i class="ri-whatsapp-line"></i></a>
          </div>
        </div>

        <div class="col-lg-2 col-md-3 col-6">
          <h6 class="mb-3">Quick Links</h6>
          <ul class="list-unstyled d-flex flex-column gap-2">
            <li><a href="{{ route('website.home') }}">Home</a></li>
            <li><a href="{{ route('website.about') }}">About Us</a></li>
            <li><a href="{{ route('website.loans.index') }}">Loan Products</a></li>
            <li><a href="{{ route('website.eligibility') }}">Eligibility</a></li>
            <li><a href="{{ route('website.emi') }}">EMI Calculator</a></li>
            <li><a href="{{ route('website.contact') }}">Contact Us</a></li>
          </ul>
        </div>

        <div class="col-lg-2 col-md-3 col-6">
          <h6 class="mb-3">Services</h6>
          <ul class="list-unstyled d-flex flex-column gap-2">
            <li><a href="{{ route('website.apply') }}">Apply Online</a></li>
            <li><a href="{{ route('website.application.track.form') }}">Track Application</a></li>
            <li><a href="{{ route('login') }}">Login</a></li>
            <li><a href="{{ route('website.faq') }}">FAQ</a></li>
            @php
              $legalPages = $policyPages ?? \App\Models\PageConfiguration::query()->active()->ordered()->get();
            @endphp
            @foreach($legalPages as $policy)
              @php
                $legalUrl = match ($policy->slug) {
                  'privacy-policy' => route('public.privacy-policy'),
                  'terms-and-conditions' => route('public.terms-and-conditions'),
                  default => route('public.page', $policy->slug),
                };
              @endphp
              <li><a href="{{ $legalUrl }}">{{ $policy->name }}</a></li>
            @endforeach
          </ul>
        </div>

        <div class="col-lg-4 col-md-6">
          <h6 class="mb-3">Get In Touch</h6>
          <ul class="list-unstyled d-flex flex-column gap-2">
            @if(!empty($brand['phone']))
              <li class="d-flex align-items-start gap-2">
                <i class="ri-phone-line mt-1" style="color:var(--sf-gold);flex-shrink:0"></i>
                <span>{{ $brand['phone'] }}</span>
              </li>
            @endif
            @if(!empty($brand['email']))
              <li class="d-flex align-items-start gap-2">
                <i class="ri-mail-line mt-1" style="color:var(--sf-gold);flex-shrink:0"></i>
                <span>{{ $brand['email'] }}</span>
              </li>
            @endif
            @if(!empty($brand['address']))
              <li class="d-flex align-items-start gap-2">
                <i class="ri-map-pin-line mt-1" style="color:var(--sf-gold);flex-shrink:0"></i>
                <span>{{ $brand['address'] }}</span>
              </li>
            @endif
            @if(empty($brand['phone']) && empty($brand['email']))
              <li class="small" style="opacity:.7">Configure contact info in admin → Company Settings.</li>
            @endif
          </ul>
        </div>
      </div>

      <hr class="sf-footer-divider my-4">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
        <p class="small mb-0" style="opacity:.7">© {{ date('Y') }} {{ $brand['name'] ?? 'Star Finance' }}. All rights
          reserved.</p>
        <p class="small mb-0" style="opacity:.7">Developed with 💕</p>
      </div>
    </div>
  </footer>

  {{-- Floating Hub: WhatsApp Quick Chat & Back to Top --}}
  <div class="sf-floating-hub" id="sfFloatingHub">
    @php $cleanPhone = preg_replace('/\D/', '', $brand['phone'] ?? '919876543210'); @endphp
    <a href="https://wa.me/{{ $cleanPhone }}?text=Hello%20Star%20Finance,%20I%20would%20like%20to%20inquire%20about%20a%20loan."
      target="_blank" rel="noopener" class="sf-floating-btn sf-floating-whatsapp" aria-label="Chat on WhatsApp">
      <i class="ri-whatsapp-fill fs-5"></i>
      <span>Chat on WhatsApp</span>
    </a>
    <button type="button" class="sf-floating-btn sf-floating-backtop" id="sfBackTop" aria-label="Back to Top">
      <i class="ri-arrow-up-line fs-5"></i>
    </button>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      // 1. Reading progress & Navbar transformation on scroll
      const progressBar = document.getElementById('sfScrollProgress');
      const nav = document.querySelector('.sf-nav');
      const backTop = document.getElementById('sfBackTop');

      window.addEventListener('scroll', function () {
        const scrollTop = window.scrollY || document.documentElement.scrollTop;
        const docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        if (progressBar && docHeight > 0) {
          const scrollPct = (scrollTop / docHeight) * 100;
          progressBar.style.width = scrollPct + '%';
        }

        if (nav) {
          if (scrollTop > 24) {
            nav.classList.add('scrolled');
          } else {
            nav.classList.remove('scrolled');
          }
        }

        if (backTop) {
          if (scrollTop > 350) {
            backTop.classList.add('visible');
          } else {
            backTop.classList.remove('visible');
          }
        }
      }, { passive: true });

      if (backTop) {
        backTop.addEventListener('click', function () {
          window.scrollTo({ top: 0, behavior: 'smooth' });
        });
      }

      // 2. Entrance Animation Observer (fade-up, anim-fade-in, anim-zoom, etc.)
      const animElements = document.querySelectorAll('.fade-up, .anim-fade-up, .anim-fade-in, .anim-zoom');
      if ('IntersectionObserver' in window) {
        const obs = new IntersectionObserver((entries, observer) => {
          entries.forEach(e => {
            if (e.isIntersecting) {
              e.target.classList.add('visible');
              observer.unobserve(e.target);
            }
          });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        animElements.forEach(el => obs.observe(el));
      } else {
        animElements.forEach(el => el.classList.add('visible'));
      }

      // 3. Number Counter Animation Observer
      const counterElements = document.querySelectorAll('[data-counter]');
      if ('IntersectionObserver' in window) {
        const counterObserver = new IntersectionObserver((entries, observer) => {
          entries.forEach(e => {
            if (e.isIntersecting) {
              const el = e.target;
              animateCounter(el);
              observer.unobserve(el);
            }
          });
        }, { threshold: 0.2 });
        counterElements.forEach(el => counterObserver.observe(el));
      } else {
        counterElements.forEach(el => {
          const target = el.getAttribute('data-counter');
          const prefix = el.getAttribute('data-prefix') || '';
          const suffix = el.getAttribute('data-suffix') || '';
          el.textContent = prefix + target + suffix;
        });
      }

      function animateCounter(el) {
        const target = parseFloat(el.getAttribute('data-counter')) || 0;
        const prefix = el.getAttribute('data-prefix') || '';
        const suffix = el.getAttribute('data-suffix') || '';
        const decimals = parseInt(el.getAttribute('data-decimals')) || 0;
        const duration = parseInt(el.getAttribute('data-duration')) || 1600;
        const startTime = performance.now();

        function updateCount(currentTime) {
          const elapsed = currentTime - startTime;
          const progress = Math.min(elapsed / duration, 1);
          // Ease out cubic
          const ease = 1 - Math.pow(1 - progress, 3);
          const current = target * ease;

          const formattedNum = decimals > 0
            ? current.toFixed(decimals)
            : Math.round(current).toLocaleString('en-IN');

          el.textContent = prefix + formattedNum + suffix;

          if (progress < 1) {
            requestAnimationFrame(updateCount);
          } else {
            const finalNum = decimals > 0
              ? target.toFixed(decimals)
              : Math.round(target).toLocaleString('en-IN');
            el.textContent = prefix + finalNum + suffix;
          }
        }

        requestAnimationFrame(updateCount);
      }
    });
  </script>
  @stack('scripts')
</body>

</html>