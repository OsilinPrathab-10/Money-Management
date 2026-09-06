@php
use App\Helpers\SettingsHelper;

$configData = Helper::appClasses();
$customizerHidden = 'customizer-hide';
$adminTitle = SettingsHelper::get('admin_title', config('variables.templateName'));
@endphp

@extends('layouts/blankLayout')

@section('title', 'Login')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/@form-validation/form-validation.scss'
])
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
@endsection

@section('page-style')
@vite([
  'resources/assets/vendor/scss/pages/page-auth.scss'
])
<style>
  :root {
    --primary-glow: #6366f1;
    --secondary-glow: #d946ef;
    --bg-gradient-start: #0b0f19;
    --bg-gradient-end: #111827;
    --card-bg: rgba(17, 24, 39, 0.7);
    --card-border: rgba(255, 255, 255, 0.08);
    --input-bg: rgba(31, 41, 55, 0.4);
    --input-border: rgba(255, 255, 255, 0.1);
    --text-primary: #f3f4f6;
    --text-secondary: #9ca3af;
  }

  [data-bs-theme="light"] {
    --primary-glow: #4f46e5;
    --secondary-glow: #c084fc;
    --bg-gradient-start: #f8fafc;
    --bg-gradient-end: #e2e8f0;
    --card-bg: rgba(255, 255, 255, 0.8);
    --card-border: rgba(0, 0, 0, 0.06);
    --input-bg: rgba(255, 255, 255, 0.9);
    --input-border: rgba(0, 0, 0, 0.1);
    --text-primary: #1e293b;
    --text-secondary: #64748b;
  }

  body {
    font-family: 'Outfit', sans-serif !important;
  }

  .login-bg {
    background: linear-gradient(135deg, var(--bg-gradient-start) 0%, var(--bg-gradient-end) 100%);
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
    overflow-y: auto;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  /* Glowing blobs */
  .glow-blob {
    position: absolute;
    width: 600px;
    height: 600px;
    border-radius: 50%;
    filter: blur(140px);
    opacity: 0.18;
    z-index: 0;
    pointer-events: none;
  }
  .glow-blob-1 {
    background: var(--primary-glow);
    top: -15%;
    left: -15%;
    animation: float-slow 20s infinite alternate;
  }
  .glow-blob-2 {
    background: var(--secondary-glow);
    bottom: -15%;
    right: -15%;
    animation: float-slow 25s infinite alternate-reverse;
  }

  @keyframes float-slow {
    0% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(60px, 40px) scale(1.15); }
    100% { transform: translate(-40px, -60px) scale(0.9); }
  }

  /* Glass card styling */
  .glass-login-card {
    background: var(--card-bg);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--card-border);
    border-radius: 24px;
    padding: 2.5rem;
    width: 100%;
    max-width: 460px;
    z-index: 10;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
  }

  /* Inputs and Forms */
  .glass-input {
    background: var(--input-bg) !important;
    border: 1px solid var(--input-border) !important;
    color: var(--text-primary) !important;
    border-radius: 12px !important;
    padding: 12px 16px !important;
    height: auto !important;
    transition: all 0.3s ease !important;
  }
  .glass-input:focus {
    border-color: var(--primary-glow) !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2) !important;
  }
  .glass-input::placeholder {
    color: var(--text-secondary) !important;
    opacity: 0.7;
  }

  /* Text gradients */
  .text-primary-gradient {
    background: linear-gradient(to right, var(--primary-glow), var(--secondary-glow));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: 800;
  }

  /* Glass widgets on left panel */
  .glass-widget {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    transform: translateY(0);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .glass-widget:hover {
    transform: translateY(-6px);
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.1);
  }
  [data-bs-theme="light"] .glass-widget {
    background: rgba(255, 255, 255, 0.65);
    border-color: rgba(0, 0, 0, 0.04);
  }

  .form-password-toggle .input-group-text {
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    border-left: none;
    border-radius: 0 12px 12px 0;
  }
  .form-password-toggle .glass-input {
    border-radius: 12px 0 0 12px !important;
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
@endsection

@section('content')
<div class="login-bg">
  <!-- Glowing background blobs -->
  <div class="glow-blob glow-blob-1"></div>
  <div class="glow-blob glow-blob-2"></div>

  <!-- Floating Theme Switcher at top right -->
  <div class="position-absolute" style="top: 25px; right: 25px; z-index: 100;">
    <button class="btn btn-icon btn-text-secondary rounded-circle bg-white shadow-md border border-light" id="login-theme-toggle" style="width: 44px; height: 44px;" title="Toggle Theme">
      <i class="ri-sun-line icon-22px text-warning" id="login-theme-icon"></i>
    </button>
  </div>

  <div class="container position-relative z-index-10 py-5">
    <div class="row align-items-center justify-content-center min-vh-100">
      
      <!-- Left Info Panel (Hidden on mobile) -->
      <div class="col-lg-6 d-none d-lg-block pe-lg-5">
        <div class="auth-left-content text-start">
          <div class="app-brand mb-6 d-flex align-items-center gap-3">
            <span class="app-brand-logo demo">@include('_partials.macros', ['width' => '50', 'height' => '50'])</span>
            <h2 class="app-brand-text demo text-heading fw-bold mb-0" style="font-size: 2.2rem; font-family: 'Outfit', sans-serif;">{{ $adminTitle }}</h2>
          </div>
          <h1 class="display-4 fw-bold mb-4" style="line-height: 1.15; font-family: 'Outfit', sans-serif;">
            Empowering Your <br>
            <span class="text-primary-gradient">Financial Future</span>
          </h1>
          <p class="fs-5 text-secondary mb-6" style="max-width: 480px;">
            Secure, transparent, and robust financial services platform for loans, chit funds, and daily collections.
          </p>
          
          <!-- Floating card widgets -->
          <div class="floating-widgets mt-6">
            <div class="glass-widget mb-4 d-flex align-items-center gap-4 p-4">
              <div class="widget-icon rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(99, 102, 241, 0.12); color: #6366f1; width: 56px; height: 56px;">
                <i class="ri-shield-user-line ri-2x"></i>
              </div>
              <div>
                <h6 class="mb-1 fw-bold text-heading" style="font-size: 1.05rem;">Secure Staff Portal</h6>
                <p class="mb-0 text-muted small" style="font-size: 0.85rem;">Secure login using role-based credential verification.</p>
              </div>
            </div>
            <div class="glass-widget d-flex align-items-center gap-4 p-4">
              <div class="widget-icon rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(217, 70, 239, 0.12); color: #d946ef; width: 56px; height: 56px;">
                <i class="ri-flashlight-line ri-2x"></i>
              </div>
              <div>
                <h6 class="mb-1 fw-bold text-heading" style="font-size: 1.05rem;">Real-time Dynamic Sync</h6>
                <p class="mb-0 text-muted small" style="font-size: 0.85rem;">Instantly process daily EMIs, chit installments, and payout ledgers.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Right Panel: Login Card -->
      <div class="col-12 col-md-8 col-lg-6 col-xl-5">
        <div class="glass-login-card mx-auto">
          <!-- Logo for Mobile View -->
          <div class="d-flex d-lg-none justify-content-center mb-6">
            <div class="app-brand d-flex align-items-center gap-2">
              <span class="app-brand-logo demo">@include('_partials.macros', ['width' => '40', 'height' => '40'])</span>
              <h3 class="app-brand-text demo text-heading fw-bold mb-0" style="font-size: 1.8rem;">{{ $adminTitle }}</h3>
            </div>
          </div>

          <!-- Alert notifications -->
          @if(session('warning'))
              <div class="alert alert-warning alert-dismissible fade show rounded-3 mb-4" role="alert">
                  <i class="ri-error-warning-line me-2"></i>
                  {{ session('warning') }}
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
          @endif
          @if(session('success'))
              <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
                  <i class="ri-check-line me-2"></i>
                  {{ session('success') }}
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
          @endif

          <div class="text-center mb-6">
            <span class="badge bg-primary-light text-primary px-3 py-2 mb-3" style="font-size: 0.85rem; border-radius: 8px; background: rgba(99, 102, 241, 0.12);">Welcome Back! 👋</span>
            <h4 class="mb-2 fw-bold text-heading">Sign In</h4>
            <p class="mb-0 text-secondary">Access the administration system dashboard</p>
          </div>

          <form id="formAuthentication" action="{{ url('/login') }}" method="POST">
            @csrf

            <div class="mb-5">
              <label for="email" class="form-label text-secondary mb-2">Email Address</label>
              <input type="email" class="form-control glass-input @error('email') is-invalid @enderror" id="email" name="email"
                  value="{{ old('email') }}" placeholder="Enter your email address" autofocus required />
              @error('email')
                  <div class="invalid-feedback d-block mt-2">
                      {{ $message }}
                  </div>
              @enderror
            </div>

            <div class="mb-5">
              <div class="d-flex justify-content-between mb-2">
                <label for="password" class="form-label text-secondary mb-0">Password</label>
                <a href="{{url('forgot-password')}}" class="small fw-semibold text-primary">Forgot Password?</a>
              </div>
              <div class="form-password-toggle form-control-validation">
                <div class="input-group input-group-merge">
                  <input type="password" id="password" class="form-control glass-input @error('password') is-invalid @enderror" name="password"
                      placeholder="••••••••••••" aria-describedby="password" required />
                  <span class="input-group-text cursor-pointer">
                    <i class="icon-base ri ri-eye-off-line icon-20px text-secondary"></i>
                  </span>
                </div>
                @error('password')
                    <div class="invalid-feedback d-block mt-2">
                        {{ $message }}
                    </div>
                @enderror
              </div>
            </div>

            <div class="mb-5">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="remember-me" name="remember" />
                <label class="form-check-label text-secondary" for="remember-me"> Remember Me </label>
              </div>
            </div>

            <button type="submit" class="btn btn-primary d-grid w-100 py-3 fw-bold shadow-sm" style="border-radius: 12px;">Sign in</button>
          </form>
        </div>
      </div>
      
    </div>
  </div>

  @include('_partials._modals.modal-credit-check')

  <!-- Policy Links - Bottom Right -->
  <div class="d-none d-md-block" style="position: absolute; bottom: 25px; right: 25px; z-index: 10;">
    <p class="mb-0" style="font-size: 0.85rem;">
      <a href="{{ route('public.privacy-policy') }}" class="text-muted hover-underline" style="text-decoration: none;">Privacy Policy</a>
      <span class="text-muted mx-2">|</span>
      <a href="{{ route('public.terms-and-conditions') }}" class="text-muted hover-underline" style="text-decoration: none;">Terms & Conditions</a>
    </p>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- jQuery (CDN for synchronous loading) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
$(function() {
  // ================= THEME TOGGLE SCRIPT =================
  const themeToggleBtn = document.getElementById('login-theme-toggle');
  const themeIcon = document.getElementById('login-theme-icon');

  function updateLoginThemeIcon(theme) {
    if (theme === 'dark') {
      themeIcon.className = 'ri-moon-clear-line icon-22px text-info';
    } else {
      themeIcon.className = 'ri-sun-line icon-22px text-warning';
    }
  }

  let currentTheme = localStorage.getItem('templateCustomizer-vertical-menu-template--Theme') || 
                     document.documentElement.getAttribute('data-bs-theme') || 
                     'light';
  if (currentTheme === 'system') {
    currentTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  updateLoginThemeIcon(currentTheme);

  themeToggleBtn.addEventListener('click', () => {
    const htmlEl = document.documentElement;
    const isDark = htmlEl.getAttribute('data-bs-theme') === 'dark';
    const newTheme = isDark ? 'light' : 'dark';
    
    htmlEl.setAttribute('data-bs-theme', newTheme);
    localStorage.setItem('templateCustomizer-vertical-menu-template--Theme', newTheme);
    
    // Write cookies for both admin and front layouts
    document.cookie = `admin-mode=${newTheme}; max-age=31536000; path=/`;
    document.cookie = `front-mode=${newTheme}; max-age=31536000; path=/`;
    
    updateLoginThemeIcon(newTheme);
  });
});
</script>
@endsection
