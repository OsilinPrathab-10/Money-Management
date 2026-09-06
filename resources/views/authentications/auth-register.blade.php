@php
$configData = Helper::appClasses();
$customizerHidden = 'customizer-hide';
@endphp

@extends('layouts/blankLayout')

@section('title', 'Register')

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

@section('page-script')

@endsection

@section('content')
<div class="authentication-wrapper authentication-cover">
  <!-- Logo -->
  <a href="{{url('/')}}" class="auth-cover-brand d-flex align-items-center gap-2">
    <span class="app-brand-logo demo">@include('_partials.macros')</span>
    <span class="app-brand-text demo text-heading fw-semibold">{{config('variables.templateName')}}</span>
  </a>
  <!-- /Logo -->
  <div class="authentication-inner row m-0">
    <!-- /Left Text -->
    <div class="d-none d-lg-flex col-lg-7 col-xl-8 align-items-center justify-content-center p-12 pb-2">
      <img src="{{asset('assets/img/illustrations/sign-up-auth.png')}}"
        class="auth-cover-illustration w-100" alt="login-illustration" 
        style="max-width: 50%; height: auto; object-fit: contain;" />
    </div>
    <!-- /Left Text -->

    <!-- Register -->
    <div
      class="d-flex col-12 col-lg-5 col-xl-4 align-items-center authentication-bg position-relative py-sm-12 px-12 py-6">
      <div class="w-px-400 mx-auto pt-12 pt-lg-0">
        <h4 class="mb-1">Adventure starts here 🚀</h4>
        <p class="mb-5">Make your app management easy and fun!</p>

        <form id="formAuthentication" class="mb-5" action="{{ url('/register') }}" method="POST">
          @csrf

          <div class="form-floating form-floating-outline mb-5 form-control-validation">
              <input type="text" class="form-control" id="username" name="name"
                  placeholder="Enter your username" value="{{ old('name') }}" required autofocus>
              <label for="username">Username</label>
              @error('name')
                  <small class="text-danger">{{ $message }}</small>
              @enderror
          </div>

          <div class="form-floating form-floating-outline mb-5 form-control-validation">
              <input type="email" class="form-control" id="email" name="email"
                  placeholder="Enter your email" value="{{ old('email') }}" required>
              <label for="email">Email</label>
              @error('email')
                  <small class="text-danger">{{ $message }}</small>
              @enderror
          </div>

          <div class="mb-5 form-password-toggle form-control-validation">
              <div class="input-group input-group-merge">
                  <div class="form-floating form-floating-outline">
                      <input type="password" id="password" class="form-control" name="password"
                          placeholder="••••••••••••" required>
                      <label for="password">Password</label>
                  </div>
                  <span class="input-group-text cursor-pointer"><i class="icon-base ri ri-eye-off-line"></i></span>
              </div>
              @error('password')
                  <small class="text-danger">{{ $message }}</small>
              @enderror
          </div>

          <div class="mb-5 form-password-toggle form-control-validation">
              <div class="form-floating form-floating-outline">
                  <input type="password" id="password_confirmation" class="form-control"
                      name="password_confirmation" placeholder="••••••••••••" required>
                  <label for="password_confirmation">Confirm Password</label>
              </div>
          </div>

          <div class="mb-5 form-control-validation">
              <div class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" id="terms-conditions" name="terms" required>
                  <label class="form-check-label" for="terms-conditions">
                      I agree to
                      <a href="#">privacy policy & terms</a>
                  </label>
              </div>
          </div>

          <button type="submit" class="btn btn-primary d-grid w-100">Sign up</button>
      </form>

        <p class="text-center mb-5">
          <span>Already have an account?</span>
          <a href="{{url('/')}}">
            <span>Sign in instead</span>
          </a>
        </p>
      </div>
    </div>
    <!-- /Register -->
  </div>
</div>
@endsection
