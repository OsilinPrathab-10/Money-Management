@extends('layouts/blankLayout')

@section('title', 'Client Login')

@section('page-style')
@vite([
  'resources/assets/vendor/scss/pages/page-auth.scss'
])
<style>
  .auth-inner {
    max-width: 450px !important;
  }
</style>
@endsection

@section('content')
<div class="position-relative">
  <div class="authentication-wrapper authentication-basic container-p-y p-4 p-sm-0">
    <div class="authentication-inner py-6">

      <!-- Login Card -->
      <div class="card p-md-7 p-1">
        <div class="card-body">
          <!-- Logo -->
          <div class="app-brand justify-content-center mb-6">
            <a href="{{url('/')}}" class="app-brand-link gap-2">
              <span class="app-brand-logo demo">@include('_partials.macros',["width"=>25,"withbg"=>'#666ee8'])</span>
              <span class="app-brand-text demo text-heading fw-semibold">{{config('variables.templateName')}}</span>
            </a>
          </div>
          <!-- /Logo -->
          <h4 class="mb-1">Welcome to Customer Portal! 👋</h4>
          <p class="mb-6">Please sign-in to your account using your registered mobile number.</p>

          <form id="formSentOtp" class="mb-6">
            <div class="mb-5">
              <label for="phone" class="form-label">Mobile Number</label>
              <div class="input-group input-group-merge">
                <span class="input-group-text">+91</span>
                <input type="text" class="form-control" id="phone" name="phone" placeholder="Enter your 10 digit mobile number" autofocus maxlength="10">
              </div>
            </div>
            <div class="mb-5">
              <button class="btn btn-primary d-grid w-100" type="submit" id="btnSendOtp">Send OTP</button>
            </div>
          </form>

          <form id="formVerifyOtp" class="mb-6 d-none">
            <div class="mb-5">
                <label class="form-label">Enter 6-digit OTP</label>
                <input type="text" class="form-control text-center mb-2" id="otp" name="otp" placeholder="· · · · · ·" maxlength="6" style="letter-spacing: 0.5rem; font-size: 1.5rem;">
                <div class="text-center">
                    <small>Sent to <span id="displayPhone"></span></small>
                </div>
            </div>
            <div class="mb-5">
              <button class="btn btn-success d-grid w-100" type="submit" id="btnVerify">Verify & Login</button>
            </div>
            <p class="text-center">
                <span>Didn't get the code?</span>
                <a href="javascript:void(0);" id="btnResend">
                  <span>Resend</span>
                </a>
            </p>
          </form>
          
          <div class="alert alert-info d-none" id="testOtpAlert"></div>

        </div>
      </div>
      <!-- /Login Card -->
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- jQuery (CDN for synchronous loading) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
$(function() {
    let phoneNum = '';
    const baseUrl = "{{ url('/') }}/";

    $('#formSentOtp').on('submit', function(e) {
        e.preventDefault();
        const phone = $('#phone').val();
        if (phone.length !== 10) {
            Swal.fire('Error', 'Please enter a valid 10-digit mobile number.', 'error');
            return;
        }

        const btn = $('#btnSendOtp');
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Sending...');

        $.ajax({
            url: baseUrl + 'client/send-otp',
            type: 'POST',
            data: { 
                phone: phone,
                _token: "{{ csrf_token() }}"
            },
            success: function(res) {
                if (res.success) {
                    phoneNum = phone;
                    $('#displayPhone').text('+91 ' + phoneNum);
                    $('#formSentOtp').addClass('d-none');
                    $('#formVerifyOtp').removeClass('d-none');
                    
                    if (res.test_otp) {
                        $('#testOtpAlert').removeClass('d-none').text('Test OTP: ' + res.test_otp);
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'OTP Sent!',
                        text: res.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            },
            error: function(xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'Something went wrong', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).text('Send OTP');
            }
        });
    });

    $('#formVerifyOtp').on('submit', function(e) {
        e.preventDefault();
        const otp = $('#otp').val();
        if (otp.length !== 6) {
            Swal.fire('Error', 'Please enter the 6-digit OTP.', 'error');
            return;
        }

        const btn = $('#btnVerify');
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Verifying...');

        $.ajax({
            url: baseUrl + 'client/verify-otp',
            type: 'POST',
            data: {
                phone: phoneNum,
                otp: otp,
                _token: "{{ csrf_token() }}"
            },
            success: function(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Authenticated!',
                        text: 'Welcome back.',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = res.redirect;
                    });
                }
            },
            error: function(xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'Invalid OTP', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).text('Verify & Login');
            }
        });
    });

    $('#btnResend').on('click', function() {
        $('#formSentOtp').submit();
    });
});
</script>
@endsection
