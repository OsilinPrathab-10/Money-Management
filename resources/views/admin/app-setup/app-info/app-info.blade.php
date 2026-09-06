@extends('layouts/layoutMaster')

@section('title', 'App Info')

@section('content')

  <!-- Alerts -->
  @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="icon-base ri ri-check-line me-1"></i>
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="icon-base ri ri-close-circle-line me-1"></i>
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <div class="d-flex justify-content-between align-items-center mb-6">
    <div>
      <h4 class="mb-1">Application Information</h4>
      <p class="text-muted mb-0">Manage versioning, package name, and release metadata for the mobile app.</p>
    </div>
  </div>

  <form action="{{ route('app-setup-app-info-update') }}" method="POST" id="appInfoForm">
    @csrf
    <div class="card">
      <div class="card-body">
        <div class="row mb-5">
          <label class="col-sm-3 col-form-label" for="app_name">App Name <span class="text-danger">*</span></label>
          <div class="col-sm-9">
            <input type="text" id="app_name" name="app_name"
              class="form-control @error('app_name') is-invalid @enderror"
              value="{{ old('app_name', $appInfo->app_name) }}" placeholder="Enter app name" required>
            @error('app_name')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <small class="text-muted">Shown in app metadata and onboarding screens.</small>
          </div>
        </div>

        <div class="row mb-5">
          <label class="col-sm-3 col-form-label" for="version_name">Version Name <span class="text-danger">*</span></label>
          <div class="col-sm-4">
            <input type="text" id="version_name" name="version_name"
              class="form-control @error('version_name') is-invalid @enderror"
              value="{{ old('version_name', $appInfo->version_name) }}" placeholder="1.0.0" required>
            @error('version_name')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          <label class="col-sm-2 col-form-label text-sm-end" for="version_code">Version Code <span class="text-danger">*</span></label>
          <div class="col-sm-3">
            <input type="number" id="version_code" name="version_code"
              class="form-control @error('version_code') is-invalid @enderror"
              value="{{ old('version_code', $appInfo->version_code) }}" min="1" required>
            @error('version_code')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
        </div>

        <div class="row mb-5">
          <label class="col-sm-3 col-form-label" for="package_name">Package Name</label>
          <div class="col-sm-9">
            <input type="text" id="package_name" name="package_name"
              class="form-control @error('package_name') is-invalid @enderror"
              value="{{ old('package_name', $appInfo->package_name) }}" placeholder="com.company.app">
            @error('package_name')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
        </div>

        <div class="row mb-5 align-items-center">
          <label class="col-sm-3 col-form-label" for="force_update">Force Update</label>
          <div class="col-sm-9">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="force_update" name="force_update" value="1"
                {{ old('force_update', $appInfo->force_update) ? 'checked' : '' }}>
              <label class="form-check-label" for="force_update">Require users to update before continuing</label>
            </div>
          </div>
        </div>

        <div class="row mb-5">
          <label class="col-sm-3 col-form-label" for="release_notes">Release Notes</label>
          <div class="col-sm-9">
            <textarea id="release_notes" name="release_notes" rows="3"
              class="form-control @error('release_notes') is-invalid @enderror"
              placeholder="Highlights of this release">{{ old('release_notes', $appInfo->release_notes) }}</textarea>
            @error('release_notes')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
        </div>

        <div class="text-end">
          <button type="submit" class="btn btn-primary">
            <i class="icon-base ri ri-save-line me-1"></i> Save Application Info
          </button>
        </div>
      </div>
    </div>
  </form>

@endsection
