@php
$configData = Helper::appClasses();
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

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
@vite([
'resources/assets/js/pages-auth.js'
])
@endsection

@section('content')
<div class="authentication-wrapper authentication-cover">
  <!-- Logo -->
  <a href="{{url('/')}}" class="auth-cover-brand d-flex align-items-center gap-3" style="z-index: 99; top: 2rem; left: 2.5rem;">
    <span class="app-brand-logo demo">@include('_partials.macros', ['width' => '150', 'height' => '50'])</span>
    <span class="app-brand-text demo text-heading fw-semibold" style="font-size: 1.4rem;">{{config('variables.templateName')}}</span>
  </a>
  <!-- /Logo -->
  <div class="authentication-inner row m-0">
    <!-- /Left Section -->
    <div class="d-none d-lg-flex col-lg-7 col-xl-8 align-items-center justify-content-center p-12 pb-2">
      <img src="{{asset('assets/img/illustrations/auth-login-illustration-'.$configData['theme'].'.png')}}"
        class="auth-cover-illustration w-100" alt="auth-illustration"
        data-app-light-img="illustrations/auth-login-illustration-light.png"
        data-app-dark-img="illustrations/auth-login-illustration-dark.png" />
      <img alt="mask" src="{{asset('assets/img/illustrations/auth-basic-login-mask-'.$configData['theme'].'.png')}}"
        class="authentication-image d-none d-lg-block"
        data-app-light-img="illustrations/auth-basic-login-mask-light.png"
        data-app-dark-img="illustrations/auth-basic-login-mask-dark.png" />
    </div>
    <!-- /Left Section -->

    <!-- Login -->
    <div
      class="d-flex col-12 col-lg-5 col-xl-4 align-items-center authentication-bg position-relative py-lg-12 px-5 px-sm-12 py-6">
      <div class="mx-auto pt-12 pt-lg-0" style="width: 100%; max-width: 400px;">
        <h4 class="mb-1">Welcome to {{config('variables.templateName')}}! 👋</h4>
        <p class="mb-5">Please sign-in to your account and start the adventure</p>

       <form id="formAuthentication" class="mb-5" action="{{ url('/login') }}" method="POST">
          @csrf

          <div class="form-floating form-floating-outline mb-5 form-control-validation">
              <input type="text" class="form-control" id="email" name="email"
                  placeholder="Enter your email" autofocus required />
              <label for="email">Email</label>
          </div>

          <div class="mb-5">
              <div class="form-password-toggle form-control-validation">
                  <div class="input-group input-group-merge">
                      <div class="form-floating form-floating-outline">
                          <input type="password" id="password" class="form-control" name="password"
                              placeholder="••••••••••••" aria-describedby="password" required />
                          <label for="password">Password</label>
                      </div>
                      <span class="input-group-text cursor-pointer">
                          <i class="icon-base ri ri-eye-off-line icon-20px"></i>
                      </span>
                  </div>
              </div>
          </div>

          <div class="mb-5 d-flex justify-content-between align-items-center mt-5">
              <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="remember-me" name="remember" />
                  <label class="form-check-label" for="remember-me"> Remember Me </label>
              </div>
              <a href="{{url('auth/forgot-password-cover')}}" class="mb-1">
                  <span>Forgot Password?</span>
              </a>
          </div>

          <button type="submit" class="btn btn-primary d-grid w-100">Sign in</button>
      </form>

        <p class="text-center">
          <span>New on our platform?</span>
          <a href="{{url('auth/register-cover')}}">
            <span>Create an account</span>
          </a>
        </p>

        <div class="divider my-5">
          <div class="divider-text">or</div>
        </div>

        <div class="d-flex justify-content-center gap-2">
          <a href="javascript:;" class="btn btn-icon rounded-circle btn-text-facebook">
            <i class="icon-base ri  ri-facebook-fill icon-18px"></i>
          </a>

          <a href="javascript:;" class="btn btn-icon rounded-circle btn-text-twitter">
            <i class="icon-base ri  ri-twitter-fill icon-18px"></i>
          </a>

          <a href="javascript:;" class="btn btn-icon rounded-circle btn-text-github">
            <i class="icon-base ri  ri-github-fill icon-18px"></i>
          </a>

          <a href="javascript:;" class="btn btn-icon rounded-circle btn-text-google-plus">
            <i class="icon-base ri  ri-google-fill icon-18px"></i>
          </a>
        </div>

        <!-- Policy Links -->
        <div class="text-end mt-5">
          <p class="mb-0" style="font-size: 0.875rem;">
            <a href="{{ route('public.privacy-policy') }}" class="text-muted" style="text-decoration: none;">Privacy Policy</a>
            <span class="text-muted mx-2">|</span>
            <a href="{{ route('public.terms-and-conditions') }}" class="text-muted" style="text-decoration: none;">Terms and Conditions</a>
            <span class="text-muted mx-2">|</span>
            <a href="{{ route('public.account-deletion') }}" class="text-muted" style="text-decoration: none;">Account Deletion</a>
          </p>
        </div>
      </div>
    </div>
    <!-- /Login -->
  </div>
</div>
@endsection
