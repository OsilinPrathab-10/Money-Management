@extends('layouts/layoutMaster')

@section('title', 'Server Status')

@section('content')

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-6">
  <div>
    <h4 class="mb-1">Server Status</h4>
    <p class="text-muted mb-0">Monitor server health and system information</p>
  </div>
</div>

<!-- Server Information -->
<div class="card">
  <div class="card-header">
    <h5 class="mb-0">Server Information</h5>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-4">
      <div class="col-12 col-md-6 col-lg-3">
        <div class="d-flex justify-content-between align-items-center p-3 rounded" style="background-color: rgba(105, 108, 255, 0.08);">
          <div>
            <small class="text-muted d-block mb-1">PHP Version</small>
            <h6 class="mb-0">{{ $serverInfo['php_version'] }}</h6>
          </div>
          <i class="ri-code-s-slash-line" style="font-size: 32px; color: #696cff;"></i>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-3">
        <div class="d-flex justify-content-between align-items-center p-3 rounded" style="background-color: rgba(255, 62, 29, 0.08);">
          <div>
            <small class="text-muted d-block mb-1">Laravel Version</small>
            <h6 class="mb-0">{{ $serverInfo['laravel_version'] }}</h6>
          </div>
          <i class="ri-fire-line" style="font-size: 32px; color: #ff3e1d;"></i>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-3">
        <div class="d-flex justify-content-between align-items-center p-3 rounded" style="background-color: rgba(113, 221, 55, 0.08);">
          <div>
            <small class="text-muted d-block mb-1">Database Status</small>
            <h6 class="mb-0">{{ $databaseStatus }}</h6>
          </div>
          <i class="ri-database-2-line" style="font-size: 32px; color: #71dd37;"></i>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-3">
        <div class="d-flex justify-content-between align-items-center p-3 rounded" style="background-color: rgba(3, 195, 236, 0.08);">
          <div>
            <small class="text-muted d-block mb-1">Server Software</small>
            <h6 class="mb-0 text-truncate" style="max-width: 150px;" title="{{ $serverInfo['server_software'] }}">{{ $serverInfo['server_software'] }}</h6>
          </div>
          <i class="ri-server-line" style="font-size: 32px; color: #03c3ec;"></i>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-12 col-md-6 col-lg-3 mb-3">
        <small class="text-muted d-block mb-1">Server Name</small>
        <p class="mb-0 fw-medium">{{ $serverInfo['server_name'] }}</p>
      </div>
      <div class="col-12 col-md-6 col-lg-3 mb-3">
        <small class="text-muted d-block mb-1">Server Port</small>
        <p class="mb-0 fw-medium">{{ $serverInfo['server_port'] }}</p>
      </div>
      <div class="col-12 col-md-6 col-lg-3 mb-3">
        <small class="text-muted d-block mb-1">Server Protocol</small>
        <p class="mb-0 fw-medium">{{ $serverInfo['server_protocol'] }}</p>
      </div>
      <div class="col-12 col-md-6 col-lg-3 mb-3">
        <small class="text-muted d-block mb-1">Database Version</small>
        <p class="mb-0 fw-medium">{{ $databaseVersion }}</p>
      </div>
    </div>
  </div>
</div>

@endsection
