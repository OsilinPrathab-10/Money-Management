@php
$customizerHidden = 'customizer-hide';
$pageConfigs = $pageConfigs ?? ['myLayout' => 'blank'];
@endphp

@extends('layouts/sdsAuthLayout')

@section('title', 'Reset Password')

@section('auth-card')
  <h2 class="sds-welcome">Reset Password</h2>
  <p class="sds-welcome-sub">Choose a new password that’s different from the ones you’ve used before.</p>

  @if (session('status'))
    <div class="alert alert-success alert-dismissible fade show sds-alert" role="alert">
      <i class="ri-check-line me-1"></i>{{ session('status') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show sds-alert" role="alert">
      <i class="ri-error-warning-line me-1"></i>{{ $errors->first() }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form id="formAuthentication" action="{{ route('password.store') }}" method="POST">
    @csrf
    <input type="hidden" name="token" value="{{ $request->route('token') }}">
    <input type="hidden" name="email" value="{{ old('email', $request->email) }}">

    <div class="sds-field form-control-validation">
      <label for="password" class="sds-label">New Password</label>
      <div class="sds-input form-password-toggle">
        <span class="sds-input-icon"><i class="ri-lock-2-line"></i></span>
        <input type="password" id="password"
          class="form-control @error('password') is-invalid @enderror"
          name="password" placeholder="Enter a new password"
          autocomplete="new-password" required />
        <span class="sds-eye cursor-pointer">
          <i class="icon-base ri ri-eye-off-line"></i>
        </span>
      </div>
      @error('password')<span class="sds-invalid">{{ $message }}</span>@enderror
    </div>

    <div class="sds-field form-control-validation">
      <label for="password_confirmation" class="sds-label">Confirm Password</label>
      <div class="sds-input form-password-toggle">
        <span class="sds-input-icon"><i class="ri-lock-password-line"></i></span>
        <input type="password" id="password_confirmation" class="form-control"
          name="password_confirmation" placeholder="Re-enter your new password"
          autocomplete="new-password" required />
        <span class="sds-eye cursor-pointer">
          <i class="icon-base ri ri-eye-off-line"></i>
        </span>
      </div>
    </div>

    <button type="submit" class="btn sds-btn-signin">
      <i class="ri-check-line"></i><span>Set new password</span>
    </button>
  </form>

  <a href="{{ url('/') }}" class="sds-back">
    <i class="ri-arrow-left-line"></i> Back to login
  </a>
@endsection
