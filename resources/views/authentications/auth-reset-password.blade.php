@php
  use App\Helpers\SettingsHelper;
  use App\Services\WebsiteService;

  $pageConfigs = $pageConfigs ?? ['myLayout' => 'blank'];
  $adminTitle = SettingsHelper::get('admin_title', config('variables.templateName', 'Star Finance'));
  $website = app(WebsiteService::class);
  $brand = $website->brand();
  $companyName = !empty($brand['name']) ? $brand['name'] : $adminTitle;
@endphp

@extends('layouts/blankLayout')

@section('title', 'Reset Password — ' . $companyName)

@push('pricing-style')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
@endpush

@section('page-style')
<style>
  body {
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    margin: 0;
    padding: 0;
    background: #08101e;
    overflow-x: hidden;
  }

  .fin-auth-container {
    min-height: 100vh;
    min-height: 100dvh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    padding: 2.5rem 1.25rem;
    background:
      radial-gradient(1100px 700px at 15% 15%, rgba(26, 92, 255, 0.22), transparent 60%),
      radial-gradient(900px 600px at 85% 85%, rgba(6, 182, 212, 0.16), transparent 55%),
      linear-gradient(135deg, #060c17 0%, #0c182c 45%, #10223d 100%);
    overflow: hidden;
  }

  .fin-ambient-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    pointer-events: none;
    z-index: 0;
    opacity: 0.6;
  }
  .fin-orb-1 {
    width: 450px;
    height: 450px;
    top: -10%;
    left: -5%;
    background: radial-gradient(circle, rgba(26, 92, 255, 0.35) 0%, transparent 70%);
  }
  .fin-orb-2 {
    width: 400px;
    height: 400px;
    bottom: -10%;
    right: -5%;
    background: radial-gradient(circle, rgba(6, 182, 212, 0.25) 0%, transparent 70%);
  }

  .fin-login-card {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(25px);
    -webkit-backdrop-filter: blur(25px);
    border: 1px solid rgba(255, 255, 255, 0.95);
    border-radius: 1.75rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2), 0 30px 70px rgba(0, 0, 0, 0.35);
    padding: clamp(2rem, 4vw, 2.75rem);
    position: relative;
    overflow: hidden;
    width: 100%;
    max-width: 480px;
    z-index: 1;
  }
  .fin-login-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #1a5cff 0%, #06b6d4 100%);
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
    font-size: 1.6rem;
    font-weight: 800;
    color: #0e2b5c;
    letter-spacing: -.03em;
    margin-bottom: .35rem;
  }
  .fin-card-sub {
    font-size: .88rem;
    color: #64748b;
    margin-bottom: 1.5rem;
    line-height: 1.55;
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
    margin-bottom: 1.35rem;
  }
  .fin-input-box i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 1.15rem;
  }
  .fin-input-box .form-control {
    height: 50px;
    padding-left: 2.85rem;
    border-radius: .85rem;
    border: 1.5px solid #e2e8f0;
    background: #f8fafc;
    font-size: .92rem;
  }
  .fin-input-box .form-control:focus {
    background: #fff;
    border-color: #1a5cff;
    box-shadow: 0 0 0 4px rgba(26, 92, 255, 0.12);
  }

  .btn-fin-submit {
    width: 100%;
    height: 50px;
    border-radius: 999px;
    background: linear-gradient(135deg, #0e2b5c 0%, #1a5cff 100%);
    color: #ffffff !important;
    font-weight: 800;
    font-size: .95rem;
    border: none;
    box-shadow: 0 8px 24px rgba(26, 92, 255, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    cursor: pointer;
    transition: all .2s ease;
  }
  .btn-fin-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(26, 92, 255, 0.45);
  }

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
    margin-top: 1.25rem;
  }
  .fin-back-btn:hover {
    background: #eef4ff;
    border-color: #b8ccff;
    color: #1a5cff !important;
  }
</style>
@endsection

@section('content')
<div class="fin-auth-container">
  <div class="fin-ambient-orb fin-orb-1"></div>
  <div class="fin-ambient-orb fin-orb-2"></div>

  <div class="fin-login-card text-center">
    <div class="fin-card-emblem mx-auto">
      <i class="ri-shield-keyhole-line"></i>
    </div>
    <h2 class="fin-card-title">Reset Password</h2>
    <p class="fin-card-sub">Choose a new, strong password that is different from previous ones.</p>

    @if (session('status'))
      <div class="alert alert-success alert-dismissible fade show rounded-3 p-3 mb-3 text-start" role="alert">
        <div class="d-flex align-items-center gap-2">
          <i class="ri-checkbox-circle-line fs-5"></i>
          <div class="small fw-semibold">{{ session('status') }}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    @if ($errors->any())
      <div class="alert alert-danger alert-dismissible fade show rounded-3 p-3 mb-3 text-start" role="alert">
        <div class="d-flex align-items-center gap-2">
          <i class="ri-error-warning-line fs-5"></i>
          <div class="small fw-semibold">{{ $errors->first() }}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <form id="formAuthentication" action="{{ route('password.store') }}" method="POST" novalidate class="text-start">
      @csrf
      <input type="hidden" name="token" value="{{ $request->route('token') ?? '' }}">
      <input type="hidden" name="email" value="{{ old('email', $request->email ?? '') }}">

      <label for="password" class="fin-field-label">New Password <span class="text-danger">*</span></label>
      <div class="fin-input-box">
        <i class="ri-lock-2-line"></i>
        <input type="password" id="password"
          class="form-control @error('password') is-invalid @enderror"
          name="password" placeholder="Enter a new password"
          autocomplete="new-password" required autofocus />
      </div>
      @error('password')
        <div class="text-danger small mt-n2 mb-3 fw-semibold"><i class="ri-error-warning-line me-1"></i>{{ $message }}</div>
      @enderror

      <label for="password_confirmation" class="fin-field-label">Confirm Password <span class="text-danger">*</span></label>
      <div class="fin-input-box">
        <i class="ri-lock-password-line"></i>
        <input type="password" id="password_confirmation" class="form-control"
          name="password_confirmation" placeholder="Re-enter your new password"
          autocomplete="new-password" required />
      </div>

      <button type="submit" class="btn btn-fin-submit">
        <i class="ri-check-line"></i>
        <span>Set New Password</span>
      </button>
    </form>

    <div class="text-center">
      <a href="{{ route('login') }}" class="fin-back-btn">
        <i class="ri-arrow-left-line"></i>
        <span>Back to Sign In</span>
      </a>
    </div>
  </div>
</div>
@endsection
