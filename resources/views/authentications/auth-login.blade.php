@php
  use App\Helpers\SettingsHelper;
  use App\Services\WebsiteService;

  $pageConfigs = ['myLayout' => 'blank', 'hasCustomizer' => false];
  $customizerHidden = 'customizer-hide';
  $adminTitle = SettingsHelper::get('admin_title', config('variables.templateName', 'Star Finance'));
  $adminSubtitle = SettingsHelper::get('admin_subtitle', 'Fintech Portal');
  $adminLogo = SettingsHelper::get('admin_logo');
  $website = app(WebsiteService::class);
  $brand = $website->brand();
  $companyName = !empty($brand['name']) ? $brand['name'] : $adminTitle;
  $logoImg = !empty($brand['logo']) ? $brand['logo'] : $adminLogo;
@endphp

@extends('layouts/blankLayout')

@section('title', 'Sign In — ' . $companyName)

@push('pricing-style')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Caveat:wght@600;700&display=swap" rel="stylesheet">
@endpush

@section('page-style')
<style>
  #template-customizer,
  .template-customizer-open-btn,
  .template-customizer-theme {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    pointer-events: none !important;
  }

  :root {
    --fin-navy: #08101e;
    --fin-navy-card: #0e1e36;
    --fin-blue: #1a5cff;
    --fin-blue-glow: rgba(26, 92, 255, 0.35);
    --fin-cyan: #06b6d4;
    --fin-gold: #f59e0b;
    --fin-ink: #0f172a;
    --fin-muted: #64748b;
    --fin-border: rgba(255, 255, 255, 0.12);
  }

  body {
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    margin: 0;
    padding: 0;
    background: #08101e;
    overflow-x: hidden;
  }

  /* ── Fullscreen Atmospheric Canvas ── */
  .fin-auth-container {
    min-height: 100vh;
    min-height: 100dvh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    padding: 2rem 1.25rem;
    background:
      radial-gradient(1100px 700px at 15% 15%, rgba(26, 92, 255, 0.22), transparent 60%),
      radial-gradient(900px 600px at 85% 85%, rgba(6, 182, 212, 0.16), transparent 55%),
      radial-gradient(700px 500px at 50% 50%, rgba(245, 158, 11, 0.08), transparent 65%),
      linear-gradient(135deg, #060c17 0%, #0c182c 45%, #10223d 100%);
    overflow: hidden;
  }

  /* Subtle background grid texture */
  .fin-auth-container::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
      linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
    z-index: 0;
  }

  /* Glowing ambient orbs */
  .fin-ambient-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    pointer-events: none;
    z-index: 0;
    opacity: 0.6;
    animation: orbFloat 10s ease-in-out infinite alternate;
  }
  .fin-orb-1 {
    width: 450px;
    height: 450px;
    top: -10%;
    left: -5%;
    background: radial-gradient(circle, rgba(26, 92, 255, 0.4) 0%, transparent 70%);
  }
  .fin-orb-2 {
    width: 400px;
    height: 400px;
    bottom: -10%;
    right: -5%;
    background: radial-gradient(circle, rgba(6, 182, 212, 0.3) 0%, transparent 70%);
    animation-duration: 12s;
    animation-delay: -4s;
  }
  @keyframes orbFloat {
    0%   { transform: translate(0, 0) scale(1); }
    50%  { transform: translate(30px, -25px) scale(1.08); }
    100% { transform: translate(-25px, 20px) scale(0.95); }
  }

  .fin-auth-shell {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 1180px;
  }

  /* ── Left Showcase Column ── */
  .fin-brand-pill {
    display: inline-flex;
    align-items: center;
    gap: .75rem;
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    padding: .5rem 1.15rem;
    border-radius: 999px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    margin-bottom: 2rem;
  }
  .fin-brand-logo {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: linear-gradient(135deg, #1a5cff, #06b6d4);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 800;
    font-size: 1rem;
    box-shadow: 0 2px 10px rgba(26, 92, 255, 0.4);
    flex-shrink: 0;
  }
  .fin-brand-logo img {
    max-width: 24px;
    max-height: 24px;
    object-fit: contain;
  }
  .fin-brand-title {
    color: #ffffff;
    font-weight: 800;
    font-size: 1.05rem;
    letter-spacing: -.02em;
  }
  .fin-brand-badge {
    background: rgba(26, 92, 255, 0.3);
    color: #93c5fd;
    font-size: .68rem;
    font-weight: 700;
    padding: .2rem .55rem;
    border-radius: 999px;
    letter-spacing: .04em;
    text-transform: uppercase;
  }

  .fin-hero-title {
    font-size: clamp(2.2rem, 4.2vw, 3.4rem);
    font-weight: 800;
    color: #ffffff;
    line-height: 1.15;
    letter-spacing: -.03em;
    margin-bottom: 1.25rem;
  }
  .fin-hero-title .text-gradient {
    background: linear-gradient(135deg, #60a5fa 0%, #38bdf8 50%, #facc15 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .fin-hero-sub {
    font-size: 1.05rem;
    color: rgba(255, 255, 255, 0.72);
    line-height: 1.65;
    max-width: 520px;
    margin-bottom: 2.25rem;
  }

  /* Micro feature cards */
  .fin-feature-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    max-width: 500px;
    margin-bottom: 2.25rem;
  }
  .fin-feature-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.09);
    padding: .85rem 1.15rem;
    border-radius: 1rem;
    transition: transform .2s ease, background .2s ease, border-color .2s ease;
  }
  .fin-feature-item:hover {
    transform: translateX(4px);
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.2);
  }
  .fin-feature-icon {
    width: 40px;
    height: 40px;
    border-radius: .75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
  }
  .fin-icon-blue { background: rgba(26, 92, 255, 0.2); color: #60a5fa; border: 1px solid rgba(96, 165, 250, 0.3); }
  .fin-icon-cyan { background: rgba(6, 182, 212, 0.2); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); }
  .fin-icon-gold { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.3); }

  .fin-feature-heading {
    font-size: .92rem;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: .15rem;
  }
  .fin-feature-text {
    font-size: .78rem;
    color: rgba(255, 255, 255, 0.6);
    margin: 0;
  }

  /* Stat counters mini-row */
  .fin-stats-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem 2.25rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
  }
  .fin-stat-item strong {
    display: block;
    font-size: 1.35rem;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -.02em;
    line-height: 1.1;
  }
  .fin-stat-item span {
    font-size: .74rem;
    color: rgba(255, 255, 255, 0.55);
    text-transform: uppercase;
    letter-spacing: .04em;
    font-weight: 600;
  }

  /* ── Right Login Card (Glassmorphism) ── */
  .fin-login-card {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(25px);
    -webkit-backdrop-filter: blur(25px);
    border: 1px solid rgba(255, 255, 255, 0.95);
    border-radius: 1.75rem;
    box-shadow:
      0 10px 25px rgba(0, 0, 0, 0.2),
      0 30px 70px rgba(0, 0, 0, 0.35);
    padding: clamp(2rem, 4vw, 2.75rem);
    position: relative;
    overflow: hidden;
  }

  /* Subtle top accent gradient line */
  .fin-login-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #1a5cff 0%, #06b6d4 50%, #f59e0b 100%);
  }

  .fin-card-head {
    margin-bottom: 1.75rem;
  }
  .fin-card-emblem {
    width: 48px;
    height: 48px;
    border-radius: 1rem;
    background: linear-gradient(135deg, #0e2b5c 0%, #1a5cff 100%);
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    box-shadow: 0 8px 20px rgba(26, 92, 255, 0.25);
    margin-bottom: 1rem;
  }
  .fin-card-title {
    font-size: 1.65rem;
    font-weight: 800;
    color: #0e2b5c;
    letter-spacing: -.03em;
    margin-bottom: .35rem;
  }
  .fin-card-sub {
    font-size: .88rem;
    color: #64748b;
    margin: 0;
  }

  /* Inputs */
  .fin-field-wrap {
    margin-bottom: 1.35rem;
  }
  .fin-field-label {
    display: block;
    font-size: .76rem;
    font-weight: 700;
    color: #475569;
    letter-spacing: .04em;
    text-transform: uppercase;
    margin-bottom: .45rem;
  }
  .fin-input-box {
    position: relative;
  }
  .fin-input-box i.input-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 1.15rem;
    pointer-events: none;
    transition: color .2s ease;
  }
  .fin-input-box .form-control {
    height: 50px;
    padding-left: 2.85rem;
    padding-right: 2.85rem;
    border-radius: .85rem;
    border: 1.5px solid #e2e8f0;
    background: #f8fafc;
    font-size: .92rem;
    color: #0f172a;
    transition: all .2s ease;
  }
  .fin-input-box .form-control:focus {
    background: #ffffff;
    border-color: #1a5cff;
    box-shadow: 0 0 0 4px rgba(26, 92, 255, 0.12);
  }
  .fin-input-box .form-control:focus ~ i.input-icon {
    color: #1a5cff;
  }
  .fin-eye-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: .35rem;
    font-size: 1.1rem;
    border-radius: .4rem;
    transition: color .15s ease;
  }
  .fin-eye-toggle:hover {
    color: #0e2b5c;
  }

  /* Row options */
  .fin-form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    font-size: .84rem;
  }
  .fin-forgot-link {
    color: #1a5cff;
    font-weight: 700;
    text-decoration: none;
    transition: color .15s ease;
  }
  .fin-forgot-link:hover {
    color: #0e2b5c;
    text-decoration: underline;
  }

  /* Submit Button */
  .btn-fin-submit {
    width: 100%;
    height: 50px;
    border-radius: 999px;
    background: linear-gradient(135deg, #0e2b5c 0%, #1a5cff 100%);
    color: #ffffff !important;
    font-weight: 800;
    font-size: .95rem;
    letter-spacing: .01em;
    border: none;
    box-shadow: 0 8px 24px rgba(26, 92, 255, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    cursor: pointer;
    transition: all .25s cubic-bezier(0.16, 1, 0.3, 1);
  }
  .btn-fin-submit:hover {
    background: linear-gradient(135deg, #103673 0%, #296bff 100%);
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(26, 92, 255, 0.45);
  }
  .btn-fin-submit:active {
    transform: translateY(0);
  }

  /* Return to Website Pill */
  .fin-back-btn {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .5rem 1.15rem;
    border-radius: 999px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #475569 !important;
    font-size: .82rem;
    font-weight: 700;
    text-decoration: none;
    transition: all .2s ease;
  }
  .fin-back-btn:hover {
    background: #eef4ff;
    border-color: #b8ccff;
    color: #1a5cff !important;
  }

  /* Security footer */
  .fin-security-note {
    font-size: .74rem;
    color: #94a3b8;
    text-align: center;
    margin-top: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .35rem;
  }

  /* Bottom policy bar */
  .fin-policy-bar {
    text-align: center;
    margin-top: 1.75rem;
    font-size: .78rem;
    color: rgba(255, 255, 255, 0.5);
  }
  .fin-policy-bar a {
    color: rgba(255, 255, 255, 0.65);
    text-decoration: none;
    transition: color .15s ease;
  }
  .fin-policy-bar a:hover {
    color: #ffffff;
  }
  .fin-policy-bar .sep {
    margin: 0 .45rem;
    opacity: .4;
  }
</style>
@endsection

@section('content')
<div class="fin-auth-container">
  {{-- Ambient Glowing Orbs --}}
  <div class="fin-ambient-orb fin-orb-1"></div>
  <div class="fin-ambient-orb fin-orb-2"></div>

  <div class="fin-auth-shell">
    <div class="row align-items-center g-4 g-xl-5">

      {{-- ══════════════ LEFT COLUMN: SHOWCASE & VALUE PROPOSITION ══════════════ --}}
      <div class="col-lg-6 col-xl-7 d-none d-lg-block">
        {{-- Brand Pill --}}
        <div class="fin-brand-pill">
          <div class="fin-brand-logo">
            @if(!empty($logoImg))
              <img src="{{ $logoImg }}" alt="{{ $companyName }}">
            @else
              <i class="ri-shield-flash-line"></i>
            @endif
          </div>
          <div>
            <span class="fin-brand-title">{{ $companyName }}</span>
          </div>
          <span class="fin-brand-badge">Enterprise Portal</span>
        </div>

        {{-- Headline & Subtitle --}}
        <h1 class="fin-hero-title">
          Financial Solutions Designed Around <span class="text-gradient">Your Future</span>
        </h1>
        <p class="fin-hero-sub">
          A secure, unified platform for borrowers, agents, and administrators with paperless KYC, instant approvals, and real-time loan tracking.
        </p>

        {{-- 3 Key Micro Feature Cards --}}
        <div class="fin-feature-list">
          <div class="fin-feature-item">
            <div class="fin-feature-icon fin-icon-blue">
              <i class="ri-shield-check-fill"></i>
            </div>
            <div>
              <div class="fin-feature-heading">Bank-Grade 256-bit Security</div>
              <p class="fin-feature-text">Encrypted sessions with multi-role routing (Admin, Staff, Agent, Client).</p>
            </div>
          </div>

          <div class="fin-feature-item">
            <div class="fin-feature-icon fin-icon-cyan">
              <i class="ri-flashlight-fill"></i>
            </div>
            <div>
              <div class="fin-feature-heading">Instant Digital Verification</div>
              <p class="fin-feature-text">Real-time automated Aadhaar, PAN, and Bank account validation.</p>
            </div>
          </div>

          <div class="fin-feature-item">
            <div class="fin-feature-icon fin-icon-gold">
              <i class="ri-line-chart-fill"></i>
            </div>
            <div>
              <div class="fin-feature-heading">Real-Time Portfolio &amp; EMI Insights</div>
              <p class="fin-feature-text">Live collection schedules, ledger statements, and instant loan disbursements.</p>
            </div>
          </div>
        </div>

        {{-- Stat Counters Mini Row --}}
        <div class="fin-stats-row">
          <div class="fin-stat-item">
            <strong>₹50 Cr+</strong>
            <span>Disbursed</span>
          </div>
          <div class="fin-stat-item">
            <strong>2,000+</strong>
            <span>Active Borrowers</span>
          </div>
          <div class="fin-stat-item">
            <strong>99.4%</strong>
            <span>Approval Rate</span>
          </div>
          <div class="fin-stat-item">
            <strong>24h</strong>
            <span>Disbursal SLA</span>
          </div>
        </div>
      </div>

      {{-- ══════════════ RIGHT COLUMN: LOGIN FORM CARD ══════════════ --}}
      <div class="col-lg-6 col-xl-5">
        <div class="fin-login-card">
          <div class="fin-card-head text-center text-sm-start">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
              <div class="fin-card-emblem">
                <i class="ri-shield-keyhole-line"></i>
              </div>
              <span class="badge bg-light text-primary border rounded-pill px-3 py-2 fw-700" style="font-size:.72rem">
                <i class="ri-lock-line me-1"></i> SSL 256-Bit Encrypted
              </span>
            </div>
            <h2 class="fin-card-title">Sign In to Your Account</h2>
            <p class="fin-card-sub">Enter your registered email address to access your account</p>
          </div>

          {{-- Session Flash Alerts --}}
          @if(session('warning'))
            <div class="alert alert-warning alert-dismissible fade show rounded-3 p-3 mb-3" role="alert">
              <div class="d-flex align-items-center gap-2">
                <i class="ri-error-warning-line fs-5"></i>
                <div class="small fw-semibold">{{ session('warning') }}</div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          @elseif(request()->boolean('expired') || request()->boolean('idle'))
            <div class="alert alert-warning alert-dismissible fade show rounded-3 p-3 mb-3" role="alert">
              <div class="d-flex align-items-center gap-2">
                <i class="ri-time-line fs-5"></i>
                <div class="small fw-semibold">Your session expired due to inactivity. Please sign in again.</div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          @endif

          @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show rounded-3 p-3 mb-3 d-flex align-items-center gap-2" role="alert" style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #fca5a5;">
              <i class="ri-error-warning-line fs-5 text-danger flex-shrink-0"></i>
              <div class="small fw-semibold text-danger flex-grow-1">{{ session('error') }}</div>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
          @endif

          @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show rounded-3 p-3 mb-3 d-flex align-items-center gap-2" role="alert" style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #fca5a5;">
              <i class="ri-error-warning-line fs-5 text-danger flex-shrink-0"></i>
              <div class="small fw-semibold text-danger flex-grow-1">
                @if($errors->count() === 1)
                  {{ $errors->first() }}
                @else
                  <ul class="mb-0 ps-3 text-start">
                    @foreach($errors->all() as $err)
                      <li>{{ $err }}</li>
                    @endforeach
                  </ul>
                @endif
              </div>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
          @endif

          @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-3 p-3 mb-3" role="alert">
              <div class="d-flex align-items-center gap-2">
                <i class="ri-checkbox-circle-line fs-5"></i>
                <div class="small fw-semibold">{{ session('success') }}</div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          @endif

          @if(session('status'))
            <div class="alert alert-success alert-dismissible fade show rounded-3 p-3 mb-3" role="alert">
              <div class="d-flex align-items-center gap-2">
                <i class="ri-checkbox-circle-line fs-5"></i>
                <div class="small fw-semibold">{{ session('status') }}</div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          @endif

          {{-- Sign In Form --}}
          <form id="formAuthentication" action="{{ route('login') }}" method="POST" novalidate>
            @csrf

            {{-- Email Address --}}
            <div class="fin-field-wrap">
              <label for="login" class="fin-field-label">Email Address <span class="text-danger">*</span></label>
              <div class="fin-input-box">
                <i class="ri-mail-line input-icon"></i>
                <input type="email"
                  class="form-control @error('login') is-invalid @enderror"
                  id="login"
                  name="login"
                  value="{{ old('login', old('email')) }}"
                  placeholder="name@example.com"
                  autocomplete="email"
                  required
                  autofocus />
              </div>
              @error('login')
                <div class="text-danger small mt-1 fw-semibold"><i class="ri-error-warning-line me-1"></i>{{ $message }}</div>
              @enderror
            </div>

            {{-- Password --}}
            <div class="fin-field-wrap">
              <label for="password" class="fin-field-label">Password <span class="text-danger">*</span></label>
              <div class="fin-input-box">
                <i class="ri-lock-2-line input-icon"></i>
                <input type="password"
                  id="password"
                  class="form-control @error('password') is-invalid @enderror"
                  name="password"
                  placeholder="Enter your password"
                  autocomplete="current-password"
                  required />
                <button type="button" class="fin-eye-toggle" id="btnTogglePassword" aria-label="Toggle password visibility">
                  <i class="ri-eye-off-line" id="eyeIcon"></i>
                </button>
              </div>
              @error('password')
                <div class="text-danger small mt-1 fw-semibold"><i class="ri-error-warning-line me-1"></i>{{ $message }}</div>
              @enderror
            </div>

            {{-- Form Options: Remember Me & Forgot Password --}}
            <div class="fin-form-options">
              <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="remember-me" name="remember" />
                <label class="form-check-label text-muted" for="remember-me">Remember me</label>
              </div>
              <a href="{{ url('forgot-password') }}" class="fin-forgot-link">Forgot password?</a>
            </div>

            {{-- Submit Action --}}
            <button type="submit" class="btn btn-fin-submit" id="btnSubmitLogin">
              <span>Sign In to Portal</span>
              <i class="ri-arrow-right-line"></i>
            </button>

            {{-- Back to Website CTA --}}
            <div class="text-center mt-4">
              <a href="{{ route('website.home') }}" class="fin-back-btn">
                <i class="ri-arrow-left-line"></i>
                <span>Back to {{ $companyName }} Website</span>
              </a>
            </div>

            <div class="fin-security-note">
              <i class="ri-shield-check-fill text-success"></i>
              <span>Official Portal · Authorized Access Only</span>
            </div>
          </form>
        </div>

        {{-- Policy Links below card --}}
        <div class="fin-policy-bar">
          <a href="{{ route('public.privacy-policy') }}">Privacy Policy</a>
          <span class="sep">|</span>
          <a href="{{ route('public.terms-and-conditions') }}">Terms &amp; Conditions</a>
          <span class="sep">|</span>
          <a href="{{ route('public.account-deletion') }}">Account Deletion</a>
          <div class="mt-1" style="opacity:.6">© {{ date('Y') }} {{ $companyName }}. All rights reserved.</div>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const toggleBtn = document.getElementById('btnTogglePassword');
  const passwordInput = document.getElementById('password');
  const eyeIcon = document.getElementById('eyeIcon');

  if (toggleBtn && passwordInput && eyeIcon) {
    toggleBtn.addEventListener('click', function() {
      const isPassword = passwordInput.type === 'password';
      passwordInput.type = isPassword ? 'text' : 'password';
      eyeIcon.className = isPassword ? 'ri-eye-line' : 'ri-eye-off-line';
    });
  }
});
</script>
@endsection
