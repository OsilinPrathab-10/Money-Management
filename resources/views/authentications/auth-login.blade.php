@extends('layouts/sdsAuthLayout')

@section('title', 'Login')

@section('auth-card')
  <h2 class="sds-welcome">Welcome Back <span class="sds-wave">👋</span></h2>
  <p class="sds-welcome-sub">Login to your admin portal and continue managing your operations.</p>

        @if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show sds-alert" role="alert">
            <i class="ri-error-warning-line me-1"></i>{{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
  @elseif(request()->boolean('expired') || request()->boolean('idle'))
    <div class="alert alert-warning alert-dismissible fade show sds-alert" role="alert">
      <i class="ri-error-warning-line me-1"></i>Your session expired due to inactivity. Please sign in again.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif
        @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show sds-alert" role="alert">
            <i class="ri-check-line me-1"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif
  @if(session('status'))
    <div class="alert alert-success alert-dismissible fade show sds-alert" role="alert">
      <i class="ri-check-line me-1"></i>{{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif

  <form id="formAuthentication" action="{{ route('login') }}" method="POST">
          @csrf

    <div class="sds-field form-control-validation">
      <label for="email" class="sds-label">Email Address</label>
      <div class="sds-input">
        <span class="sds-input-icon"><i class="ri-mail-line"></i></span>
              <input type="email" class="form-control @error('email') is-invalid @enderror"
                id="email" name="email" value="{{ old('email') }}"
          placeholder="Enter your Email" autocomplete="email" required />
            </div>
      @error('email')<span class="sds-invalid">{{ $message }}</span>@enderror
          </div>

    <div class="sds-field form-control-validation">
      <label for="password" class="sds-label">Password</label>
      <div class="sds-input form-password-toggle">
        <span class="sds-input-icon"><i class="ri-lock-2-line"></i></span>
              <input type="password" id="password"
                class="form-control @error('password') is-invalid @enderror"
                name="password" placeholder="Enter your password"
                autocomplete="current-password" required />
        <span class="sds-eye cursor-pointer">
          <i class="icon-base ri ri-eye-off-line"></i>
              </span>
            </div>
      @error('password')<span class="sds-invalid">{{ $message }}</span>@enderror
          </div>

    <div class="sds-row">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="remember-me" name="remember" />
              <label class="form-check-label" for="remember-me">Remember me</label>
            </div>
      <a href="{{ url('forgot-password') }}" class="sds-link">Forgot password?</a>
          </div>

    <button type="submit" class="btn sds-btn-signin">
      <i class="ri-arrow-right-line"></i><span>Sign In</span>
            </button>
        </form>
@endsection
