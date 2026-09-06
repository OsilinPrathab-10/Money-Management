@extends('layouts/layoutMaster')

@section('title', 'Appearance')

@section('page-script')
@vite(['resources/assets/custom-js/appearance.js'])
@endsection

@section('content')

<!-- Alert Container -->
<div class="alert-container"
  data-success="{{ session('success') ? e(session('success')) : '' }}"
  data-error="{{ session('error') ? e(session('error')) : '' }}"
  data-warning="{{ session('warning') ? e(session('warning')) : '' }}"
  data-info="{{ session('info') ? e(session('info')) : '' }}">
</div>

@php
  $logoUrl = $appearance->logo ? route('storage.serve', ['path' => $appearance->logo]) : null;
  $faviconUrl = $appearance->favicon ? route('storage.serve', ['path' => $appearance->favicon]) : null;
@endphp

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-6">
  <div>
    <h4 class="mb-1">Appearance Settings</h4>
    <p class="text-muted mb-0">Manage branding, versioning, and color scheme for the mobile app</p>
  </div>
</div>

<form action="{{ route('app-setup-appearance-update') }}" method="POST" id="appearanceForm" class="mb-6" enctype="multipart/form-data">
  @csrf
  <input type="hidden" name="section" value="all">
  <div class="card mb-6">
    <div class="card-header">
      <h5 class="mb-0">Color Configuration</h5>
    </div>
    <div class="card-body">
      <div class="row mb-5">
        <label class="col-sm-3 col-form-label" for="primary_color">Primary Color <span class="text-danger">*</span></label>
        <div class="col-sm-9">
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <input type="color" id="primary_color" name="primary_color" class="form-control form-control-color @error('primary_color') is-invalid @enderror"
              value="{{ old('primary_color', $appearance->primary_color) }}" required style="width: 80px; height: 50px;" />
            <input type="text" id="primary_color_text" class="form-control color-hex-input"
              value="{{ old('primary_color', $appearance->primary_color) }}" style="max-width: 140px;"
              placeholder="#696cff" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" spellcheck="false" autocomplete="off" />
            <div id="primaryColorPreview" class="color-preview rounded" style="width: 50px; height: 50px; background-color: {{ $appearance->primary_color }}; border: 2px solid #ddd;"></div>
          </div>
          @error('primary_color')
            <div class="invalid-feedback d-block">{{ $message }}</div>
          @enderror
          <small class="text-muted">This color will be used for buttons, links, and primary UI elements.</small>
        </div>
      </div>

      <div class="row mb-5">
        <label class="col-sm-3 col-form-label" for="secondary_color">Secondary Color <span class="text-danger">*</span></label>
        <div class="col-sm-9">
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <input type="color" id="secondary_color" name="secondary_color" class="form-control form-control-color @error('secondary_color') is-invalid @enderror"
              value="{{ old('secondary_color', $appearance->secondary_color) }}" required style="width: 80px; height: 50px;" />
            <input type="text" id="secondary_color_text" class="form-control color-hex-input"
              value="{{ old('secondary_color', $appearance->secondary_color) }}" style="max-width: 140px;"
              placeholder="#8592a3" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" spellcheck="false" autocomplete="off" />
            <div id="secondaryColorPreview" class="color-preview rounded" style="width: 50px; height: 50px; background-color: {{ $appearance->secondary_color }}; border: 2px solid #ddd;"></div>
          </div>
          @error('secondary_color')
            <div class="invalid-feedback d-block">{{ $message }}</div>
          @enderror
          <small class="text-muted">This color will be used for secondary UI elements and text.</small>
        </div>
      </div>

      <div class="row mb-5">
        <label class="col-sm-3 col-form-label" for="title">App Title <span class="text-danger">*</span></label>
        <div class="col-sm-9">
          <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title"
            value="{{ old('title', $appearance->title) }}" placeholder="Enter app display name" required>
          @error('title')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
          <small class="text-muted">Shown in the app header and other branding locations.</small>
        </div>
      </div>

      <div class="row mb-5 align-items-center">
        <label class="col-sm-3 col-form-label" for="logo">App Logo</label>
        <div class="col-sm-9">
          <div class="d-flex flex-column flex-md-row gap-4 align-items-md-center">
            <div class="logo-preview border rounded p-3 d-flex align-items-center justify-content-center" style="width:120px;height:120px;background-color:#f5f5f9;">
              <img id="logoPreviewImage" src="{{ $logoUrl ?? '' }}" data-initial-src="{{ $logoUrl ?? '' }}" alt="App Logo" class="img-fluid" style="max-height: 100%; {{ $logoUrl ? '' : 'display: none;' }}">
              <span id="logoPreviewPlaceholder" class="text-muted" data-initial-visible="{{ $logoUrl ? '0' : '1' }}" style="{{ $logoUrl ? 'display: none;' : '' }}">No logo</span>
            </div>
            <div class="flex-grow-1">
              <input type="file" class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" accept="image/png,image/jpeg,image/svg+xml">
              @error('logo')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
              <small class="text-muted">Recommended square image (PNG/JPG/SVG). Suggested size: 512x512px.</small>
            </div>
          </div>
        </div>
      </div>

      <div class="row mb-5 align-items-center">
        <label class="col-sm-3 col-form-label" for="favicon">App Favicon</label>
        <div class="col-sm-9">
          <div class="d-flex flex-column flex-md-row gap-4 align-items-md-center">
            <div class="favicon-preview border rounded p-3 d-flex align-items-center justify-content-center" style="width:80px;height:80px;background-color:#f5f5f9;">
              <img id="faviconPreviewImage" src="{{ $faviconUrl ?? '' }}" data-initial-src="{{ $faviconUrl ?? '' }}" alt="App Favicon" class="img-fluid" style="max-height: 100%; {{ $faviconUrl ? '' : 'display: none;' }}">
              <span id="faviconPreviewPlaceholder" class="text-muted small" data-initial-visible="{{ $faviconUrl ? '0' : '1' }}" style="{{ $faviconUrl ? 'display: none;' : '' }}">No favicon</span>
            </div>
            <div class="flex-grow-1">
              <input type="file" class="form-control @error('favicon') is-invalid @enderror" id="favicon" name="favicon" accept="image/png,image/jpeg,image/x-icon,image/svg+xml">
              @error('favicon')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
              <small class="text-muted">PNG/JPG/ICO/SVG supported. Recommended size: 48x48px.</small>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-end flex-wrap gap-3 mt-4">
        <button type="button" class="btn btn-outline-secondary" id="resetAppearanceBtn">
          <i class="icon-base ri ri-refresh-line me-1"></i> Reset
        </button>
        <button type="submit" class="btn btn-primary">
          <i class="icon-base ri ri-save-line me-1"></i> Save Changes
        </button>
      </div>
    </div>
  </div>
</form>

@endsection
