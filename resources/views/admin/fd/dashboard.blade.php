@extends('layouts/layoutMaster')

@section('title', 'FD Dashboard')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/apex-charts/apex-charts.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/apex-charts/apexcharts.js'
])
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <h4 class="mb-1">Fixed Deposit Dashboard</h4>
    <p class="text-muted mb-0">Overview of active deposits, maturity pipeline, interest liability and payout trends.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('fd.deposits.create') }}" class="btn btn-primary">
      <i class="ri-add-line me-1"></i> New Deposit
    </a>
    <a href="{{ route('fd.reports.index') }}" class="btn btn-label-primary">
      <i class="ri-file-chart-line me-1"></i> Reports
    </a>
  </div>
</div>

@include('admin.fd.partials.dashboard-content')
@endsection
