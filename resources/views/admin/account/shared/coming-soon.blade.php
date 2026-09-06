@extends('layouts/layoutMaster')

@section('title', $pageTitle ?? __('Coming Soon'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y text-center d-flex flex-column justify-content-center align-items-center" style="min-height: 60vh;">
  
  <div class="mb-4">
    <div class="avatar avatar-xl bg-label-primary rounded-circle shadow-sm mx-auto d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
      <i class="ri-hammer-line fs-1"></i>
    </div>
  </div>
  
  <h2 class="mb-2 fw-bold text-heading">{{ $pageTitle ?? __('Under Construction') }}</h2>
  <p class="text-muted mb-4" style="max-width: 500px;">
    {{ __('This report module is currently under active development. Our engineering team is working hard to bring this feature to you soon.') }}
  </p>
  
  <a href="{{ route('account.reports.index') }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
    <i class="ri-arrow-left-line me-2"></i>{{ __('Back to Reports Hub') }}
  </a>

</div>
@endsection
