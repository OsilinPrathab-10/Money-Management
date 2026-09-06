@php
$configData = Helper::appClasses();
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Forgot Password')

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
<div class="authentication-wrapper authentication-cover">
  <!-- Logo -->
  <a href="{{url('/')}}" class="auth-cover-brand d-flex align-items-center gap-2">
    <span class="app-brand-logo demo">@include('_partials.macros')</span>
    <span class="app-brand-text demo text-heading fw-semibold">{{config('variables.templateName')}}</span>
  </a>
  <!-- /Logo -->
  <div class="authentication-inner row m-0">
    <!-- /Left Section -->
    <div class="d-none d-lg-flex col-lg-7 col-xl-8 align-items-center justify-content-center p-12 pb-2">
      <img src="{{ asset('assets/img/illustrations/forgot-password.svg') }}"
        class="auth-cover-illustration w-75 w-xl-60" alt="Forgot password illustration" />
    </div>
    <!-- /Left Section -->

    <!-- Forgot Password -->
    <div class="d-flex col-12 col-lg-5 col-xl-4 align-items-center authentication-bg p-sm-12 p-6">
      <div class="w-px-400 mx-auto">
        <h4 class="mb-1">Forgot Password? 🔒</h4>
        <p class="mb-5">Enter your email and we'll send you instructions to reset your password</p>
        <form id="formAuthentication" class="mb-5" action="{{ route('password.email') }}" method="POST">
            @csrf

            {{-- SUCCESS MESSAGE --}}
            @if (session('status'))
                <div class="alert alert-success mb-4">
                    {{ session('status') }}
                </div>
            @endif

            <div class="form-floating form-floating-outline mb-5 form-control-validation">
                <input type="text" class="form-control" id="email" name="email" placeholder="Enter your email" value="{{ old('email') }}" autofocus />
                <label for="email">Email</label>

                {{-- ERROR MESSAGE --}}
                @if ($errors->has('email'))
                    <span class="text-danger">
                        {{ $errors->first('email') }}
                    </span>
                @endif
            </div>

            <button class="btn btn-primary d-grid w-100 mb-5">Send Reset Link</button>
        </form>
        <div class="text-center">
          <a href="{{url('/')}}" class="d-flex align-items-center justify-content-center">
            <i class="icon-base ri ri-arrow-left-s-line scaleX-n1-rtl icon-20px me-1_5"></i>
            Back to login
          </a>
        </div>
      </div>
    </div>
    <!-- /Forgot Password -->
  </div>
</div>
@endsection
