@extends('layouts/layoutMaster')

@section('title', 'Payment Methods')

@section('page-script')
@vite(['resources/assets/custom-js/payment-methods.js'])
@endsection

@section('content')
<!-- Alert Container -->
<div class="alert-container"
  data-success="{{ session('success') ? e(session('success')) : '' }}"
  data-error="{{ session('error') ? e(session('error')) : '' }}"
  data-warning="{{ session('warning') ? e(session('warning')) : '' }}"
  data-info="{{ session('info') ? e(session('info')) : '' }}">
</div>

<!-- Page Title -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <h4 class="mb-1">Payment Methods</h4>
    <p class="text-muted mb-0">Configure payment gateway credentials</p>
  </div>
</div>

<!-- Payment Method Toggles -->
<div class="row g-4 mb-6">
  <div class="col-12 col-md-6 col-lg-4">
    <div class="card border shadow-none h-100">
      <div class="card-body">
        <h6 class="card-title mb-2">Autopay (eNach)</h6>
        <p class="text-muted small mb-4">Enable automatic EMI deductions via eNach mandates.</p>

        <div class="d-flex justify-content-between align-items-center">
          <span class="badge bg-label-{{ $autopayMethod->is_enabled ? 'success' : 'secondary' }}" id="autopayBadge">
            {{ $autopayMethod->is_enabled ? 'Active' : 'Inactive' }}
          </span>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="autopaySwitch" {{ $autopayMethod->is_enabled ? 'checked' : '' }} style="cursor:pointer;width:3rem;height:1.5rem;">
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-6 col-lg-4">
    <div class="card border shadow-none h-100">
      <div class="card-body">
        <h6 class="card-title mb-2">Manual Payment</h6>
        <p class="text-muted small mb-4">Enable manual repayment collection and Razorpay gateway.</p>

        <div class="d-flex justify-content-between align-items-center">
          <span class="badge bg-label-{{ $manualMethod->is_enabled ? 'success' : 'secondary' }}" id="manualBadge">
            {{ $manualMethod->is_enabled ? 'Active' : 'Inactive' }}
          </span>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="manualSwitch" {{ $manualMethod->is_enabled ? 'checked' : '' }} style="cursor:pointer;width:3rem;height:1.5rem;">
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Manual payment configuration (visible when manual enabled) -->
<div id="razorpaySection" class="{{ $manualMethod->is_enabled ? '' : 'd-none' }}">
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <h5 class="mb-0">Manual Payment Configurations</h5>
      </div>
    </div>
    <div class="card-body">
      <div class="row g-4">
        <!-- Razorpay -->
        <div class="col-12 col-lg-6">
          <div class="card border shadow-none h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
              <div class="d-flex align-items-center gap-3">
                <div class="avatar">
                  <img src="https://razorpay.com/assets/razorpay-glyph.svg" alt="Razorpay" class="rounded" style="width: 40px; height: 40px; object-fit: contain;">
                </div>
                <div>
                  <h5 class="mb-0">Razorpay Credential</h5>
                  <small class="text-muted">Configure your Razorpay payment gateway</small>
                </div>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="razorpayStatus" {{ old('enabled', ($razorpaySettings['enabled'] ?? false) ? 1 : 0) ? 'checked' : '' }}>
                <label class="form-check-label" for="razorpayStatus"></label>
              </div>
            </div>
            <div class="card-body">
              <form action="{{ route('payment-methods-update') }}" method="POST" id="razorpayForm">
                @csrf
                <input type="hidden" name="gateway" value="razorpay">
                <input type="hidden" name="enabled" id="razorpayEnabled" value="{{ old('enabled', ($razorpaySettings['enabled'] ?? false) ? 1 : 0) }}">

                <div class="row mb-4">
                  <label class="col-sm-3 col-form-label" for="razor_key">RAZOR KEY <span class="text-danger">*</span></label>
                  <div class="col-sm-9">
                    <input type="text" id="razor_key" name="razor_key" class="form-control @error('razor_key') is-invalid @enderror"
                      placeholder="RAZOR KEY" value="{{ old('razor_key', $razorpaySettings['razor_key'] ?? '') }}" required />
                    @error('razor_key')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>
                </div>

                <div class="row mb-4">
                  <label class="col-sm-3 col-form-label" for="razor_secret">RAZOR SECRET <span class="text-danger">*</span></label>
                  <div class="col-sm-9">
                    <div class="input-group input-group-merge">
                      <input type="password" id="razor_secret" name="razor_secret" class="form-control @error('razor_secret') is-invalid @enderror"
                        placeholder="RAZOR SECRET" value="{{ old('razor_secret', $razorpaySettings['razor_secret'] ?? '') }}" required />
                      <span class="input-group-text cursor-pointer" id="toggleRazorSecret">
                        <i class="ri-eye-off-line" id="toggleRazorSecretIcon"></i>
                      </span>
                    </div>
                    @error('razor_secret')
                      <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-9 offset-sm-3">
                    <button type="submit" class="btn btn-primary">
                      <i class="ri-save-line me-1"></i> Save
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Cashfree -->
        <div class="col-12 col-lg-6">
          <div class="card border shadow-none h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
              <div class="d-flex align-items-center gap-3">
                <div class="avatar">
                  <img src="{{ asset('assets/img/icons/payments/cashfree-logo.png') }}" alt="Cashfree" class="rounded" style="width: 40px; height: 40px; object-fit: contain;">
                </div>
                <div>
                  <h5 class="mb-0">Cashfree Credential</h5>
                  <small class="text-muted">Configure your Cashfree payment gateway</small>
                </div>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="cashfreeStatus" {{ old('enabled', ($cashfreeSettings['enabled'] ?? false) ? 1 : 0) ? 'checked' : '' }}>
                <label class="form-check-label" for="cashfreeStatus"></label>
              </div>
            </div>
            <div class="card-body">
              <form action="{{ route('payment-methods-update') }}" method="POST" id="cashfreeForm">
                @csrf
                <input type="hidden" name="gateway" value="cashfree">
                <input type="hidden" name="enabled" id="cashfreeEnabled" value="{{ old('enabled', ($cashfreeSettings['enabled'] ?? false) ? 1 : 0) }}">

                <div class="row mb-4">
                  <label class="col-sm-3 col-form-label" for="app_id">APP ID <span class="text-danger">*</span></label>
                  <div class="col-sm-9">
                    <input type="text" id="app_id" name="app_id" class="form-control @error('app_id') is-invalid @enderror"
                      placeholder="CASHFREE APP ID" value="{{ old('app_id', $cashfreeSettings['app_id'] ?? '') }}" required />
                    @error('app_id')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>
                </div>

                <div class="row mb-4">
                  <label class="col-sm-3 col-form-label" for="secret_key">SECRET KEY <span class="text-danger">*</span></label>
                  <div class="col-sm-9">
                    <div class="input-group input-group-merge">
                      <input type="password" id="secret_key" name="secret_key" class="form-control @error('secret_key') is-invalid @enderror"
                        placeholder="CASHFREE SECRET KEY" value="{{ old('secret_key', $cashfreeSettings['secret_key'] ?? '') }}" required />
                      <span class="input-group-text cursor-pointer" id="toggleCashfreeSecret">
                        <i class="ri-eye-off-line" id="toggleCashfreeSecretIcon"></i>
                      </span>
                    </div>
                    @error('secret_key')
                      <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-9 offset-sm-3">
                    <button type="submit" class="btn btn-primary">
                      <i class="ri-save-line me-1"></i> Save
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- PayU -->
        <div class="col-12 col-lg-6">
          <div class="card border shadow-none h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
              <div class="d-flex align-items-center gap-3">
                <div class="avatar">
                  <img src="{{ asset('assets/img/icons/payments/payu-logo.png') }}" alt="PayU" class="rounded" style="width: 40px; height: 40px; object-fit: contain;">
                </div>
                <div>
                  <h5 class="mb-0">PayU Credential</h5>
                  <small class="text-muted">Configure your PayU payment gateway</small>
                </div>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="payuStatus" {{ old('enabled', ($payuSettings['enabled'] ?? false) ? 1 : 0) ? 'checked' : '' }}>
                <label class="form-check-label" for="payuStatus"></label>
              </div>
            </div>
            <div class="card-body">
              <form action="{{ route('payment-methods-update') }}" method="POST" id="payuForm">
                @csrf
                <input type="hidden" name="gateway" value="payu">
                <input type="hidden" name="enabled" id="payuEnabled" value="{{ old('enabled', ($payuSettings['enabled'] ?? false) ? 1 : 0) }}">

                <div class="row mb-4">
                  <label class="col-sm-3 col-form-label" for="payu_key">PAYU KEY <span class="text-danger">*</span></label>
                  <div class="col-sm-9">
                    <input type="text" id="payu_key" name="payu_key" class="form-control @error('payu_key') is-invalid @enderror"
                      placeholder="PAYU KEY" value="{{ old('payu_key', $payuSettings['payu_key'] ?? '') }}" required />
                    @error('payu_key')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>
                </div>

                <div class="row mb-4">
                  <label class="col-sm-3 col-form-label" for="payu_salt">PAYU SALT <span class="text-danger">*</span></label>
                  <div class="col-sm-9">
                    <div class="input-group input-group-merge">
                      <input type="password" id="payu_salt" name="payu_salt" class="form-control @error('payu_salt') is-invalid @enderror"
                        placeholder="PAYU SALT" value="{{ old('payu_salt', $payuSettings['payu_salt'] ?? '') }}" required />
                      <span class="input-group-text cursor-pointer" id="togglePayuSalt">
                        <i class="ri-eye-off-line" id="togglePayuSaltIcon"></i>
                      </span>
                    </div>
                    @error('payu_salt')
                      <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-9 offset-sm-3">
                    <button type="submit" class="btn btn-primary">
                      <i class="ri-save-line me-1"></i> Save
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
