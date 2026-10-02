@extends('layouts/blankLayout')

@section('title', 'Client Login')

@section('page-style')
@vite([
  'resources/assets/vendor/scss/pages/page-auth.scss'
])
<style>
  /* Premium Aesthetics & Dark Mode Fusion */
  .authentication-wrapper {
    background: radial-gradient(circle at 10% 20%, rgba(105, 108, 255, 0.15) 0%, rgba(0, 0, 0, 0) 40%),
                radial-gradient(circle at 90% 80%, rgba(40, 199, 111, 0.1) 0%, rgba(0, 0, 0, 0) 50%),
                #0f111a !important;
    position: relative;
    overflow: hidden;
    color: #e1e4ed;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  /* Animated background glowing blobs */
  .auth-bg-blob {
    position: absolute;
    width: 500px;
    height: 500px;
    border-radius: 50%;
    filter: blur(120px);
    opacity: 0.25;
    pointer-events: none;
    z-index: 1;
    animation: pulseBlob 8s infinite alternate ease-in-out;
  }
  .auth-bg-blob-1 {
    background: #696cff;
    top: -100px;
    left: -100px;
  }
  .auth-bg-blob-2 {
    background: #28c76f;
    bottom: -150px;
    right: -100px;
    animation-delay: 2s;
  }

  @keyframes pulseBlob {
    0% { transform: scale(1) translate(0, 0); }
    100% { transform: scale(1.2) translate(50px, 30px); }
  }

  /* Grid overlay for tech look */
  .auth-grid-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
    z-index: 2;
  }

  /* Glassmorphic Login Card */
  .login-card-container {
    background: rgba(255, 255, 255, 0.02) !important;
    border: 1px solid rgba(255, 255, 255, 0.05) !important;
    border-radius: 24px !important;
    padding: 2.5rem !important;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3) !important;
    width: 100%;
    max-width: 440px;
    position: relative;
    overflow: hidden;
    z-index: 5;
    backdrop-filter: blur(25px);
    -webkit-backdrop-filter: blur(25px);
  }

  .login-header h4 {
    color: #fff;
    font-weight: 700;
    font-size: 1.75rem;
    letter-spacing: -0.5px;
  }
  
  .login-header p {
    color: #7b83a3;
    font-size: 0.95rem;
  }

  /* Input overrides */
  .form-control {
    background-color: rgba(255, 255, 255, 0.03) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    color: #fff !important;
    border-radius: 12px !important;
    padding: 0.75rem 1rem !important;
    transition: all 0.3s ease;
  }
  
  .form-control:focus {
    border-color: #696cff !important;
    box-shadow: 0 0 0 3px rgba(105, 108, 255, 0.25) !important;
    background-color: rgba(255, 255, 255, 0.05) !important;
  }
  
  .form-label {
    color: #7b83a3 !important;
    font-weight: 500;
  }

  .input-group-text {
    background-color: rgba(255, 255, 255, 0.03) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    color: #7b83a3 !important;
    border-top-left-radius: 12px !important;
    border-bottom-left-radius: 12px !important;
  }
  
  /* Buttons styling */
  .btn-submit-premium {
    background: linear-gradient(135deg, #696cff 0%, #4f52e6 100%) !important;
    border: none !important;
    color: #fff !important;
    font-weight: 600;
    padding: 0.85rem !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 15px rgba(105, 108, 255, 0.3) !important;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
  }
  
  .btn-submit-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(105, 108, 255, 0.5) !important;
    background: linear-gradient(135deg, #787bff 0%, #5d60f5 100%) !important;
  }
  
  .btn-verify-premium {
    background: linear-gradient(135deg, #28c76f 0%, #1f9a55 100%) !important;
    border: none !important;
    color: #fff !important;
    font-weight: 600;
    padding: 0.85rem !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 15px rgba(40, 199, 111, 0.3) !important;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
  }
  
  .btn-verify-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(40, 199, 111, 0.5) !important;
    background: linear-gradient(135deg, #34d87d 0%, #23ab5f 100%) !important;
  }

  .text-link-premium {
    color: #696cff !important;
    font-weight: 500;
    transition: all 0.2s ease;
  }
  .text-link-premium:hover {
    color: #8587ff !important;
    text-decoration: underline !important;
  }
  
  .alert-info-premium {
    background: rgba(0, 207, 232, 0.1) !important;
    border: 1px solid rgba(0, 207, 232, 0.25) !important;
    color: #00cfe8 !important;
    border-radius: 12px;
  }
</style>
@endsection

@section('content')
<div class="authentication-wrapper">
  <!-- Glowing background blobs -->
  <div class="auth-bg-blob auth-bg-blob-1"></div>
  <div class="auth-bg-blob auth-bg-blob-2"></div>
  <div class="auth-grid-overlay"></div>

  <div class="login-card-container mx-auto">
    <!-- Logo -->
    <div class="app-brand justify-content-center mb-6">
      <a href="{{url('/')}}" class="app-brand-link gap-2">
        <span class="app-brand-logo demo">@include('_partials.macros',["width"=>25,"withbg"=>'#696cff'])</span>
        <span class="app-brand-text demo text-heading fw-semibold" style="color: #fff !important;">{{config('variables.templateName')}}</span>
      </a>
    </div>
    <!-- /Logo -->
    <div class="login-header text-center mb-6">
      <h4>Customer Portal 👋</h4>
      <p>Please sign-in to your account using your registered mobile number.</p>
    </div>

    <form id="formSentOtp" class="mb-6">
      <div class="mb-5">
        <label for="phone" class="form-label">Mobile Number</label>
        <div class="input-group input-group-merge">
          <span class="input-group-text">+91</span>
          <input type="text" class="form-control" id="phone" name="phone" placeholder="Enter your 10 digit mobile number" autofocus maxlength="10">
        </div>
      </div>
      <div class="mb-5">
        <button class="btn btn-submit-premium d-grid w-100" type="submit" id="btnSendOtp">Send OTP</button>
      </div>
    </form>

    <form id="formVerifyOtp" class="mb-6 d-none">
      <div class="mb-5">
          <label class="form-label">Enter 6-digit OTP</label>
          <input type="text" class="form-control text-center mb-2" id="otp" name="otp" placeholder="· · · · · ·" maxlength="6" style="letter-spacing: 0.5rem; font-size: 1.5rem; color: #fff !important; background-color: rgba(255, 255, 255, 0.05) !important;">
          <div class="text-center">
              <small class="text-muted-premium">Sent to <span id="displayPhone" class="text-white fw-bold"></span></small>
          </div>
      </div>
      <div class="mb-5">
        <button class="btn btn-verify-premium d-grid w-100" type="submit" id="btnVerify">Verify & Login</button>
      </div>
      <p class="text-center mb-0">
          <span class="text-muted-premium">Didn't get the code?</span>
          <a href="javascript:void(0);" id="btnResend" class="text-link-premium ms-1">
            <span>Resend</span>
          </a>
      </p>
    </form>
    
    <div class="alert alert-info-premium d-none" id="testOtpAlert"></div>

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
