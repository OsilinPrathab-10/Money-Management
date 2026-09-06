@php
$configData = Helper::appClasses();
$customizerHidden = 'customizer-hide';
@endphp

@extends('layouts/blankLayout')

@section('title', 'Login Cover - Pages')

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
    <!-- Left Section -->
    <div class="d-none d-lg-flex col-lg-7 col-xl-8 align-items-center justify-content-center p-12 pb-2">
      <img src="{{asset('assets/img/illustrations/login-auth.png')}}"
        class="auth-cover-illustration w-100" alt="login-illustration" 
        style="max-width: 50%; height: auto; object-fit: contain;" />
    </div>
    <!-- /Left Section -->

    <!-- Login -->
    <div
      class="d-flex col-12 col-lg-5 col-xl-4 align-items-center authentication-bg position-relative py-sm-12 px-12 py-6">
      <div class="w-px-400 mx-auto pt-12 pt-lg-0">
        <h4 class="mb-1">Welcome to {{config('variables.templateName')}}! 👋</h4>
        <p class="mb-5">Please sign-in to your account and start the adventure</p>

       <form id="formAuthentication" class="mb-5" action="{{ url('/login') }}" method="POST">
          @csrf

          <div class="form-floating form-floating-outline mb-5 form-control-validation">
              <input type="text" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                  value="{{ old('email') }}" placeholder="Enter your email" autofocus required />
              <label for="email">Email</label>
              @error('email')
                  <div class="invalid-feedback d-block">
                      {{ $message }}
                  </div>
              @enderror
          </div>

          <div class="mb-5">
              <div class="form-password-toggle form-control-validation">
                  <div class="input-group input-group-merge">
                      <div class="form-floating form-floating-outline">
                          <input type="password" id="password" class="form-control @error('password') is-invalid @enderror" name="password"
                              placeholder="••••••••••••" aria-describedby="password" required />
                          <label for="password">Password</label>
                      </div>
                      <span class="input-group-text cursor-pointer">
                          <i class="icon-base ri ri-eye-off-line icon-20px"></i>
                      </span>
                  </div>
                  @error('password')
                      <div class="invalid-feedback d-block">
                          {{ $message }}
                      </div>
                  @enderror
              </div>
          </div>

          <div class="mb-5 d-flex justify-content-between mt-5">
              <div class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" id="remember-me" name="remember" />
                  <label class="form-check-label" for="remember-me"> Remember Me </label>
              </div>
              <a href="{{url('auth/forgot-password-cover')}}" class="float-end mb-1 mt-2">
                  <span>Forgot Password?</span>
              </a>
          </div>

          <button type="submit" class="btn btn-primary d-grid w-100">Sign in</button>
      </form>
      </div>
    </div>
    <!-- /Login -->
  </div>
</div>
@endsection
