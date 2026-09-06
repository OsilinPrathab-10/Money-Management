@php
$configData = Helper::appClasses();
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Reset Password')

@section('vendor-style')
@vite([
'resources/assets/vendor/libs/@form-validation/form-validation.scss'
])
@endsection

@section('page-style')
@vite([
'resources/assets/vendor/scss/pages/page-auth.scss'
])
@endsection

@section('vendor-script')
@vite([
'resources/assets/vendor/libs/@form-validation/popular.js',
'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
'resources/assets/vendor/libs/@form-validation/auto-focus.js'
])
@endsection

@section('content')
<div class="position-relative">
  <div class="authentication-wrapper authentication-cover">
    <!-- Logo -->
    <a href="{{url('/')}}" class="auth-cover-brand d-flex align-items-center gap-2">
      <span class="app-brand-logo demo">@include('_partials.macros')</span>
      <span class="app-brand-text demo text-heading fw-semibold">{{ config('variables.templateName') }}</span>
    </a>
    <!-- /Logo -->
    <div class="authentication-inner row m-0">
      <!-- /Left Section -->
      <div class="d-none d-lg-flex col-lg-7 col-xl-8 align-items-center justify-content-center p-12 pb-2">
        <img src="{{ asset('assets/img/illustrations/reset-password.svg') }}"
          class="auth-cover-illustration w-75 w-xl-60" alt="Reset password illustration" />
      </div>
      <!-- /Left Section -->

      <!-- Reset Password -->
      <div
        class="d-flex col-12 col-lg-5 col-xl-4 align-items-center authentication-bg position-relative py-sm-12 px-12 py-6">
        <div class="w-px-400 mx-auto pt-5 pt-lg-0">
          <h4 class="mb-1">Reset Password 🔒</h4>
          <p class="mb-5">Your new password must be different from previously used passwords</p>
          <form id="formAuthentication" class="mb-5" action="{{ route('password.store') }}" method="POST">
              @csrf

              {{-- Success Message --}}
              @if (session('status'))
                  <div class="alert alert-success mb-4">
                      {{ session('status') }}
                  </div>
              @endif

              {{-- Error Message --}}
              @if ($errors->any())
                  <div class="alert alert-danger mb-4">
                      {{ $errors->first() }}
                  </div>
              @endif

              {{-- Hidden TOKEN --}}
              <input type="hidden" name="token" value="{{ request()->route('token') }}">

              {{-- Hidden EMAIL --}}
              <input type="hidden" name="email" value="{{ request()->email }}">

              {{-- New Password --}}
              <div class="mb-5 form-password-toggle form-control-validation">
                  <div class="input-group input-group-merge">
                      <div class="form-floating form-floating-outline">
                          <input type="password" id="password" class="form-control"
                                name="password" placeholder="********" required />
                          <label for="password">New Password</label>
                      </div>
                      <span class="input-group-text cursor-pointer">
                          <i class="icon-base ri ri-eye-off-line icon-20px"></i>
                      </span>
                  </div>
              </div>

              {{-- Confirm Password --}}
              <div class="mb-5 form-password-toggle form-control-validation">
                  <div class="input-group input-group-merge">
                      <div class="form-floating form-floating-outline">
                          <input type="password" id="password_confirmation" class="form-control"
                                name="password_confirmation" placeholder="********" required />
                          <label for="password_confirmation">Confirm Password</label>
                      </div>
                      <span class="input-group-text cursor-pointer">
                          <i class="icon-base ri ri-eye-off-line icon-20px"></i>
                      </span>
                  </div>
              </div>

              <button class="btn btn-primary d-grid w-100 mb-5">Set new password</button>

              <div class="text-center">
                  <a href="{{ url('/') }}" class="d-flex align-items-center justify-content-center">
                      <i class="icon-base ri ri-arrow-left-s-line scaleX-n1-rtl icon-20px me-1_5"></i>
                      Back to login
                  </a>
              </div>
          </form>
        </div>
      </div>
      <!-- /Reset Password -->
    </div>
  </div>
</div>
@endsection
