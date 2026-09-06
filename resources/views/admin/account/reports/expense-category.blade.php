@extends('layouts/layoutMaster')

@section('title', $pageTitle ?? __('Expense Category Summary'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="ri-donut-chart-line text-primary me-2"></i>{{ $pageTitle ?? __('Expense Category Summary') }}</h4>
    <a href="{{ route('account.reports.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm">
      <i class="ri-arrow-left-line me-1"></i> {{ __('Back to Hub') }}
    </a>
  </div>

  <div class="card shadow-sm border-0 rounded-4">
    <div class="card-body text-center p-5">
      <div class="avatar avatar-xl bg-label-primary rounded-circle shadow-sm mx-auto d-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
        <i class="ri-hammer-line fs-1"></i>
      </div>
      <h3 class="fw-bold">{{ __('Under Construction') }}</h3>
      <p class="text-muted mx-auto" style="max-width: 500px;">
        {{ __('The :title module is currently under active development. Data filters and tables will appear here once the backend integration is complete.', ['title' => $pageTitle ?? 'Expense Category Summary']) }}
      </p>
    </div>
  </div>
</div>
@endsection
