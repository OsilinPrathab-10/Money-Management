@extends('layouts/layoutMaster')

@section('title', 'Chit Dashboard')

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
    <h4 class="mb-1">Chit Dashboard</h4>
    <p class="text-muted mb-0">Overview of Chit group balances, collections, settlements, and performance analytics.</p>
  </div>
  <a href="{{ route('dashboard', ['tab' => 'chit']) }}" class="btn btn-label-primary">
    <i class="ri-layout-grid-line me-1"></i> Open in Main Dashboard
  </a>
</div>

@include('admin.chit.partials.dashboard-content')
@endsection
