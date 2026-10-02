@php
$customizerHidden = 'customizer-hide';
$pageConfigs = $pageConfigs ?? ['myLayout' => 'blank'];
@endphp

@extends('layouts/sdsAuthLayout')

@section('title', 'Forgot Password')

@section('auth-card')
  <h2 class="sds-welcome">Forgot Password?</h2>
  <p class="sds-welcome-sub">Enter the email on your account and we’ll send you a link to reset your password.</p>

  @if (session('status'))
    <div class="alert alert-success alert-dismissible fade show sds-alert" role="alert">
      <i class="ri-check-line me-1"></i>{{ session('status') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form id="formAuthentication" action="{{ route('password.email') }}" method="POST">
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

    <button type="submit" class="btn sds-btn-signin">
      <i class="ri-mail-send-line"></i><span>Send Reset Link</span>
    </button>
  </form>

  <a href="{{ url('/') }}" class="sds-back">
    <i class="ri-arrow-left-line"></i> Back to login
  </a>
@endsection
