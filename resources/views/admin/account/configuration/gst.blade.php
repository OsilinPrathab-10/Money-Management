@extends('layouts/layoutMaster')

@section('title', 'GST Configuration')

@section('page-script')
  @vite(['resources/assets/custom-js/account-gst-configuration.js'])
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <h4 class="mb-1">GST Configuration</h4>
    <p class="text-muted mb-0">Turn GST on or off for accounting and set the default GST percentage for future tax calculations.</p>
  </div>
  <a href="{{ route('account.index') }}" class="btn btn-outline-secondary">
    <i class="ri-arrow-left-line me-1"></i> Account Dashboard
  </a>
</div>

@if($errors->any())
  <div class="alert alert-danger alert-dismissible mb-4" role="alert">
    <ul class="mb-0">
      @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="row g-6">
  <div class="col-lg-7 col-12">
    <form action="{{ route('account.configuration.gst.update') }}" method="POST" id="gstConfigForm">
      @csrf
      <div class="card border shadow-none h-100">
        <div class="card-header border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
          <h5 class="card-title mb-0"><i class="ri-percent-line me-2 text-primary"></i>GST Settings</h5>
          <div class="d-flex align-items-center gap-3">
            <span class="badge {{ $gstEnabled ? 'bg-label-success' : 'bg-label-secondary' }}" id="gstStatusBadge">
              {{ $gstEnabled ? 'ON' : 'OFF' }}
            </span>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" id="gstStatusSwitch" {{ $gstEnabled ? 'checked' : '' }} style="cursor:pointer;width:3rem;height:1.5rem;">
            </div>
          </div>
        </div>
        <div class="card-body pt-6 d-flex flex-column justify-content-between">
          <input type="hidden" name="gst_enabled" id="gstEnabled" value="{{ $gstEnabled ? '1' : '0' }}">

          <div>
            <div class="mb-4" id="gstPercentageWrap">
              <label class="form-label fw-semibold" for="gstPercentageInput">GST Percentage (%)</label>
              <div class="input-group">
                <input type="number"
                       step="0.01"
                       min="0"
                       max="100"
                       name="gst_percentage"
                       id="gstPercentageInput"
                       class="form-control"
                       value="{{ old('gst_percentage', number_format($gstPercentage, 2, '.', '')) }}"
                       {{ $gstEnabled ? '' : 'disabled' }}>
                <span class="input-group-text">%</span>
              </div>
              <small class="text-muted d-block mt-1">Used when GST calculations are enabled in a later phase. Valid range: 0 to 100.</small>
            </div>

            <div class="alert alert-info mb-0">
              <i class="ri-information-line me-1"></i>
              These settings are stored for future GST calculations. Loan, chit, FD fees, receipts, and accounting reports are not changed yet.
            </div>
          </div>

          <div class="pt-4">
            <button type="submit" class="btn btn-primary">
              <i class="ri-save-line me-1"></i> Save GST Configuration
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>

  <div class="col-lg-5 col-12">
    <div class="card border shadow-none h-100">
      <div class="card-header border-bottom">
        <h5 class="card-title mb-0"><i class="ri-building-2-line me-2 text-secondary"></i>Company GSTIN</h5>
      </div>
      <div class="card-body pt-6">
        <p class="text-muted small mb-3">The legal GSTIN used on certificates and documents is maintained in Website Setup, not on this page.</p>
        <dl class="row mb-4">
          <dt class="col-sm-4">GSTIN</dt>
          <dd class="col-sm-8">
            @if($companyGstNumber)
              <code>{{ $companyGstNumber }}</code>
            @else
              <span class="text-muted">Not configured</span>
            @endif
          </dd>
        </dl>
        <a href="{{ $websiteSetupUrl }}" class="btn btn-outline-secondary btn-sm">
          <i class="ri-external-link-line me-1"></i> Open Website Setup
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
