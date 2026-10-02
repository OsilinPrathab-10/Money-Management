@php
use App\Helpers\SettingsHelper;

$configData = Helper::appClasses();
$customizerHidden = 'customizer-hide';
$adminTitle = SettingsHelper::get('admin_title', config('variables.templateName'));
@endphp

@extends('layouts/blankLayout')

@section('title', 'Login')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/@form-validation/form-validation.scss'])
@endsection

@section('page-style')
@vite(['resources/assets/vendor/scss/pages/page-auth.scss'])
<style>
  /* ── Premium rich-look fintech background ── */
  .authentication-wrapper {
    min-height: 100vh !important;
    min-height: 100dvh !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    position: relative;
    overflow: hidden;
    background: #2d3554;
    padding: 1.25rem;
  }

  .auth-bg-scene {
    position: absolute;
    inset: 0;
    z-index: 0;
    overflow: hidden;
    background: #0f1225;
  }

  .auth-bg-video {
    position: absolute;
    top: 50%;
    left: 50%;
    min-width: 100%;
    min-height: 100%;
    width: auto;
    height: auto;
    transform: translateX(-50%) translateY(-50%);
    object-fit: cover;
    opacity: 0.6; /* Dim the video slightly for better contrast */
    pointer-events: none;
  }

  .auth-video-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(15,18,37,0.7) 0%, rgba(45,53,84,0.4) 100%);
    z-index: 1;
    pointer-events: none;
  }

  /* ── Centered login card ── */
  .auth-center-stage {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 430px;
    margin: auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    animation: stageIn 0.9s cubic-bezier(0.16, 1, 0.3, 1) both;
  }
  @keyframes stageIn {
    from { opacity: 0; transform: translateY(40px) scale(0.94); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
  }

  /* Animated gradient border — gold + brand */
  .login-card-glow {
    position: relative;
    width: 100%;
    padding: 2px;
    border-radius: 28px;
    background: linear-gradient(135deg, rgba(255,175,60,0.5), rgba(105,108,255,0.4), rgba(255,175,60,0.3), rgba(105,108,255,0.5));
    background-size: 300% 300%;
    animation: borderGlow 6s ease infinite, cardFloat 6s ease-in-out infinite;
    box-shadow:
      0 0 40px rgba(105, 108, 255, 0.12),
      0 20px 60px rgba(20, 30, 60, 0.15);
  }
  @keyframes borderGlow {
    0%, 100% { background-position: 0% 50%; }
    50%       { background-position: 100% 50%; }
  }
  @keyframes cardFloat {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(-8px); }
  }

  .login-card-inner {
    position: relative;
    width: 100%;
    border-radius: 26px;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    padding: 2.25rem 2rem 2rem;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.8);
  }

  .login-card-inner::before {
    content: '';
    position: absolute;
    top: 0; left: -100%;
    width: 60%; height: 100%;
    background: linear-gradient(105deg, transparent 40%, rgba(255,175,60,0.06) 50%, transparent 60%);
    animation: cardShimmer 7s ease-in-out infinite;
    pointer-events: none;
  }

  @keyframes cardShimmer {
    0%, 100% { left: -100%; }
    50%       { left: 150%; }
  }

  /* Top accent line */
  .login-card-inner::after {
    content: '';
    position: absolute;
    top: 0; left: 50%;
    transform: translateX(-50%);
    width: 40%;
    height: 2px;  
    background: linear-gradient(90deg, transparent, #ffb03a, #696cff, transparent);
    animation: accentPulse 3s ease-in-out infinite;
  }
  @keyframes accentPulse {
    0%, 100% { opacity: 0.5; width: 30%; }
    50%       { opacity: 1; width: 50%; }
  }

  /* Brand */
  .auth-brand {
    text-align: center;
    margin-bottom: 1.75rem;
    animation: fadeUp 0.7s cubic-bezier(0.16,1,0.3,1) 0.15s both;
  }
  .auth-brand .app-brand-text {
    font-size: 1.2rem !important;
    font-weight: 700 !important;
    color: #2d3548 !important;
    letter-spacing: -0.3px;
  }
  .auth-brand-logo-ring {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 52px; height: 52px;
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(255,175,60,0.12), rgba(105,108,255,0.1));
    border: 1px solid rgba(255,175,60,0.3);
    margin-bottom: 0.75rem;
    position: relative;
    animation: logoRing 4s ease-in-out infinite;
  }
  .auth-brand-logo-ring::before {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 20px;
    border: 1px solid transparent;
    border-top-color: rgba(255,175,60,0.7);
    animation: ringSpin 3s linear infinite;
  }
  @keyframes ringSpin { to { transform: rotate(360deg); } }
  @keyframes logoRing {
    0%, 100% { box-shadow: 0 0 20px rgba(105,108,255,0.2); }
    50%       { box-shadow: 0 0 35px rgba(105,108,255,0.4); }
  }

  /* Welcome */
  .login-welcome {
    text-align: center;
    margin-bottom: 1.75rem;
    animation: fadeUp 0.7s cubic-bezier(0.16,1,0.3,1) 0.25s both;
  }
  .login-welcome h4 {
    font-size: 1.65rem;
    font-weight: 700;
    letter-spacing: -0.5px;
    margin-bottom: 0.35rem;
    background: linear-gradient(135deg, #2d3548 0%, #696cff 50%, #e8940a 100%);
    background-size: 200% auto;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: textShine 4s linear infinite;
  }
  @keyframes textShine {
    0%   { background-position: 0% center; }
    100% { background-position: 200% center; }
  }
  .login-welcome p {
    color: #6c757d;
    font-size: 0.875rem;
    margin: 0;
  }
  .welcome-emoji {
    display: inline-block;
    animation: waveHand 2.5s ease-in-out infinite;
    transform-origin: 70% 70%;
  }
  @keyframes waveHand {
    0%,100%{transform:rotate(0)}
    15%{transform:rotate(14deg)}
    30%{transform:rotate(-8deg)}
    45%{transform:rotate(14deg)}
  }
  .portal-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    margin-top: 0.75rem;
    padding: 0.25rem 0.75rem;
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #e8940a;
    background: rgba(255,175,60,0.1);
    border: 1px solid rgba(255,175,60,0.3);
    border-radius: 999px;
    animation: pillPulse 3s ease-in-out infinite;
  }
  @keyframes pillPulse {
    0%,100%{border-color:rgba(105,108,255,0.25)}
    50%{border-color:rgba(105,108,255,0.55)}
  }

  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  /* Form fields — staggered entrance */
  .auth-field {
    margin-bottom: 1.15rem;
    animation: fadeUp 0.6s cubic-bezier(0.16,1,0.3,1) both;
  }
  .auth-field:nth-child(1) { animation-delay: 0.35s; }
  .auth-field:nth-child(2) { animation-delay: 0.45s; }
  .auth-field-row          { animation: fadeUp 0.6s cubic-bezier(0.16,1,0.3,1) 0.55s both; }
  .auth-field-btn          { animation: fadeUp 0.6s cubic-bezier(0.16,1,0.3,1) 0.65s both; }

  .auth-field-label {
    color: #566a7f !important;
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 0.4rem;
    display: block;
    transition: color 0.25s ease;
  }
  .auth-field:focus-within .auth-field-label { color: #696cff !important; }

  .auth-input-wrap {
    position: relative;
    display: flex;
    align-items: stretch;
    border-radius: 14px;
    background: #f5f7fb;
    border: 1.5px solid #e0e4ef;
    transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
    overflow: hidden;
  }
  .auth-input-wrap::after {
    content: '';
    position: absolute;
    bottom: 0; left: 50%;
    transform: translateX(-50%) scaleX(0);
    width: 100%; height: 2px;
    background: linear-gradient(90deg, #ffb03a, #696cff);
    transition: transform 0.35s cubic-bezier(0.16,1,0.3,1);
    border-radius: 2px;
  }
  .auth-input-wrap:focus-within {
    border-color: rgba(105,108,255,0.5);
    background: #fff;
    box-shadow: 0 0 0 4px rgba(105,108,255,0.1), 0 4px 16px rgba(105,108,255,0.12);
    transform: translateY(-1px);
  }
  .auth-input-wrap:focus-within::after { transform: translateX(-50%) scaleX(1); }

  .auth-input-icon {
    display: flex;
    align-items: center;
    padding: 0 0.85rem;
    color: #696cff;
    font-size: 1.1rem;
    transition: transform 0.3s ease;
  }
  .auth-input-wrap:focus-within .auth-input-icon { transform: scale(1.1); }

  .auth-input-wrap .form-control {
    background: transparent !important;
    border: none !important;
    color: #384551 !important;
    padding: 0.8rem 0.85rem 0.8rem 0 !important;
    font-size: 0.9rem;
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .auth-input-wrap .form-control:focus {
    background: transparent !important;
    box-shadow: none !important;
    color: #2d3548 !important;
  }
  .auth-input-wrap .form-control::placeholder { color: #a1acb8 !important; }
  .auth-input-wrap .form-control:-webkit-autofill {
    -webkit-box-shadow: 0 0 0 1000px #f5f7fb inset !important;
    -webkit-text-fill-color: #384551 !important;
    caret-color: #384551;
    transition: background-color 5000s;
  }
  .auth-toggle-eye {
    display: flex;
    align-items: center;
    padding: 0 0.85rem;
    color: #a1acb8;
    cursor: pointer;
    transition: color 0.2s, transform 0.2s;
  }
  .auth-toggle-eye:hover { color: #696cff; transform: scale(1.1); }

  /* Sign in button */
  .btn-sign-in {
    position: relative;
    width: 100%;
    padding: 0.9rem !important;
    border: none !important;
    border-radius: 14px !important;
    font-weight: 600;
    font-size: 0.95rem;
    color: #fff !important;
    background: linear-gradient(135deg, #ffb03a 0%, #696cff 50%, #5a5de8 100%) !important;
    background-size: 200% auto !important;
    box-shadow: 0 4px 20px rgba(105,108,255,0.4) !important;
    overflow: hidden;
    transition: transform 0.25s, box-shadow 0.25s, background-position 0.4s !important;
    animation: btnGradient 4s ease infinite;
  }
  @keyframes btnGradient {
    0%, 100% { background-position: 0% center; }
    50%       { background-position: 100% center; }
  }
  .btn-sign-in::before {
    content: '';
    position: absolute;
    top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.25), transparent);
    animation: btnShine 2.5s ease-in-out infinite;
  }
  @keyframes btnShine {
    0%   { left: -100%; }
    60%, 100% { left: 100%; }
  }
  .btn-sign-in:hover {
    transform: translateY(-3px) !important;
    box-shadow: 0 8px 32px rgba(105,108,255,0.55) !important;
  }
  .btn-sign-in:active { transform: translateY(0) !important; }
  .btn-sign-in span { position: relative; z-index: 1; }

  .form-check-input {
    background-color: #fff !important;
    border: 1.5px solid #d4d8e3 !important;
  }
  .form-check-label { color: #566a7f !important; font-size: 0.85rem; }
  .text-link-auth {
    color: #696cff !important;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    transition: color 0.2s;
  }
  .text-link-auth:hover { color: #4f52e6 !important; text-decoration: underline !important; }

  .form-check-input:checked {
    background-color: #696cff !important;
    border-color: #696cff !important;
  }

  /* Policy links */
  .policy-links-container {
    position: absolute;
    bottom: 1rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10;
    text-align: center;
    animation: fadeUp 0.8s ease 0.8s both;
  }
  .policy-links-container a {
    color: rgba(255, 255, 255, 0.6) !important;
    font-size: 0.75rem;
    text-decoration: none;
    transition: color 0.2s;
  }
  .policy-links-container a:hover { color: #ffb03a !important; }
  .policy-links-container .sep { color: rgba(255, 255, 255, 0.25); margin: 0 0.4rem; }

  @media (max-width: 991.98px) {
    .auth-rupee-emblem {
      right: -8%;
      opacity: 0.35;
      width: min(55vw, 280px);
      height: min(55vw, 280px);
    }
  }

  @media (max-width: 575.98px) {
    .login-card-inner { padding: 1.75rem 1.35rem 1.5rem; }
    .login-welcome h4 { font-size: 1.45rem; }
  }
</style>
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/@form-validation/popular.js',
  'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
  'resources/assets/vendor/libs/@form-validation/auto-focus.js'
])
@endsection

@section('page-script')
<script>
// Video background handles animation, no canvas JS needed
</script>
@endsection

@section('content')
<div class="authentication-wrapper">

  <div class="auth-bg-scene">
    <video autoplay loop muted playsinline class="auth-bg-video">
      <source src="https://assets.mixkit.co/videos/preview/mixkit-abstract-technology-plexus-background-28842-large.mp4" type="video/mp4">
      Your browser does not support the video tag.
    </video>
    <div class="auth-video-overlay"></div>
  </div>

  {{-- Centered login --}}
  <div class="auth-center-stage">
    <div class="login-card-glow">
      <div class="login-card-inner">

        <div class="auth-brand">
          <div class="auth-brand-logo-ring">
            @include('_partials.macros', ['width' => 26, 'withbg' => '#696cff'])
          </div>
          <div class="app-brand-text">{{ $adminTitle }}</div>
        </div>

        <div class="login-welcome">
          <h4>Welcome back<span class="welcome-emoji">!</span> 👋</h4>
          <p>Sign in to your administrative portal</p>
          <span class="portal-pill"><i class="ri-shield-keyhole-line"></i> Secure Admin Access</span>
        </div>

        @if(session('warning'))
          <div class="alert alert-warning alert-dismissible fade show rounded-3 mb-3 py-2" role="alert">
            <i class="ri-error-warning-line me-1"></i>{{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif
        @if(session('success'))
          <div class="alert alert-success alert-dismissible fade show rounded-3 mb-3 py-2" role="alert">
            <i class="ri-check-line me-1"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif

        <form id="formAuthentication" action="{{ url('/login') }}" method="POST">
          @csrf

          <div class="auth-field form-control-validation">
            <label for="email" class="auth-field-label">Email address</label>
            <div class="auth-input-wrap">
              <span class="auth-input-icon"><i class="ri-mail-line"></i></span>
              <input type="email" class="form-control @error('email') is-invalid @enderror"
                id="email" name="email" value="{{ old('email') }}"
                placeholder="Enter your Email" autofocus autocomplete="email" required />
            </div>
            @error('email')<div class="invalid-feedback d-block mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="auth-field form-control-validation">
            <label for="password" class="auth-field-label">Password</label>
            <div class="auth-input-wrap form-password-toggle">
              <span class="auth-input-icon"><i class="ri-lock-password-line"></i></span>
              <input type="password" id="password"
                class="form-control @error('password') is-invalid @enderror"
                name="password" placeholder="Enter your password"
                autocomplete="current-password" required />
              <span class="auth-toggle-eye cursor-pointer">
                <i class="icon-base ri ri-eye-off-line icon-20px"></i>
              </span>
            </div>
            @error('password')<div class="invalid-feedback d-block mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="auth-field-row d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="remember-me" name="remember" />
              <label class="form-check-label" for="remember-me">Remember me</label>
            </div>
            <a href="{{ url('forgot-password') }}" class="text-link-auth">Forgot password?</a>
          </div>

          <div class="auth-field-btn">
            <button type="submit" class="btn btn-sign-in">
              <span><i class="ri-login-circle-line me-1"></i> Sign In</span>
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>

  @include('_partials._modals.modal-credit-check')

  <div class="policy-links-container">
    <a href="{{ route('public.privacy-policy') }}">Privacy Policy</a>
    <span class="sep">|</span>
    <a href="{{ route('public.terms-and-conditions') }}">Terms</a>
    <span class="sep">|</span>
    <a href="{{ route('public.account-deletion') }}">Account Deletion</a>
  </div>

</div>
@endsection
