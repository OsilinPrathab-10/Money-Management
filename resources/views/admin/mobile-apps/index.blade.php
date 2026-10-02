@extends('layouts/layoutMaster')

@section('title', ucfirst($dbAppType) . ' App Management')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
  ])
@endsection

@section('page-style')
<style>
  .app-preview-card {
    border-radius: 12px;
    transition: all 0.3s ease;
  }
  .color-picker-wrapper {
    height: 48px;
    padding: 4px;
    border-radius: 8px;
  }
  .color-picker-input {
    width: 40px;
    height: 100%;
    border: none;
    border-radius: 6px;
    cursor: pointer;
  }
  .form-control-color {
    width: 36px !important;
    height: 36px !important;
    padding: 0 !important;
    border: none !important;
    background: transparent !important;
    cursor: pointer !important;
  }
  .form-control-color::-webkit-color-swatch-wrapper {
    padding: 0;
  }
  .form-control-color::-webkit-color-swatch {
    border: none;
    border-radius: 6px;
  }
  .color-swatch-btn {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: inline-block;
    cursor: pointer;
    border: 2px solid #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    transition: transform 0.15s ease;
  }
  .color-swatch-btn:hover {
    transform: scale(1.3);
  }
  .banner-thumb-card {
    position: relative;
    border-radius: 10px;
    overflow: hidden;
    group: hover;
  }
  .banner-thumb-img {
    height: 110px;
    width: 100%;
    object-fit: cover;
  }
  .banner-overlay-delete {
    position: absolute;
    top: 6px;
    right: 6px;
    background: rgba(220, 53, 69, 0.85);
    color: #fff;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  .banner-overlay-delete:hover {
    background: #dc3545;
    transform: scale(1.1);
  }
  .nav-pills .nav-link.active {
    box-shadow: 0 4px 12px rgba(var(--bs-primary-rgb), 0.25);
  }
</style>
@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
  <div>
    <h4 class="fw-bold mb-1 d-flex align-items-center gap-2">
      <i class="ri-smartphone-line text-primary fs-3"></i>
      <span>{{ ucfirst($dbAppType) }} App Management</span>
      <span class="badge bg-label-primary fs-tiny text-uppercase rounded-pill ms-2">{{ $dbAppType }}</span>
    </h4>
    <p class="text-muted mb-0">Configure branding, visuals, colors, banner media, welcome message, maintenance mode, and legal policies.</p>
  </div>
  <div class="btn-group" role="group" aria-label="App Switcher">
    <a href="{{ route('admin.mobile-apps.index', 'customer-app') }}" class="btn btn-sm btn-outline-primary {{ $dbAppType === 'customer' ? 'active' : '' }}">
      <i class="ri-user-smile-line me-1"></i> Customer App
    </a>
    <a href="{{ route('admin.mobile-apps.index', 'agent-app') }}" class="btn btn-sm btn-outline-primary {{ $dbAppType === 'agent' ? 'active' : '' }}">
      <i class="ri-user-star-line me-1"></i> Agent App
    </a>
  </div>
</div>

<!-- Alert Messages -->
@if (session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
  <div class="d-flex align-items-center">
    <i class="ri-checkbox-circle-line me-2 fs-4"></i>
    <div>{{ session('success') }}</div>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if ($errors->any())
<div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
  <h6 class="alert-heading mb-1"><i class="ri-error-warning-line me-2"></i>Please resolve the following errors:</h6>
  <ul class="mb-0 ps-3">
    @foreach ($errors->all() as $error)
      <li>{{ $error }}</li>
    @endforeach
  </ul>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Tab Navigation Header -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-2">
        <ul class="nav nav-pills nav-justified gap-2" id="appManagementTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active py-3 fw-semibold d-flex align-items-center justify-content-center gap-2" id="tab-settings-tab" data-bs-toggle="pill" data-bs-target="#tab-settings" type="button" role="tab" aria-controls="tab-settings" aria-selected="true">
              <i class="ri-settings-4-line fs-5"></i>
              <span>App Details & Settings</span>
            </button>
          </li>
          @if($dbAppType === 'customer')
          <li class="nav-item" role="presentation">
            <button class="nav-link py-3 fw-semibold d-flex align-items-center justify-content-center gap-2" id="tab-banners-tab" data-bs-toggle="pill" data-bs-target="#tab-banners" type="button" role="tab" aria-controls="tab-banners" aria-selected="false">
              <i class="ri-slideshow-3-line fs-5"></i>
              <span>Banner Media Sliders</span>
            </button>
          </li>
          @endif
          @if($dbAppType === 'customer')
          <li class="nav-item" role="presentation">
            <button class="nav-link py-3 fw-semibold d-flex align-items-center justify-content-center gap-2" id="tab-onboarding-tab" data-bs-toggle="pill" data-bs-target="#tab-onboarding" type="button" role="tab" aria-controls="tab-onboarding" aria-selected="false">
              <i class="ri-pages-line fs-5"></i>
              <span>Onboarding Screens</span>
              @php $onboardingCount = is_array($setting->onboarding_screens) ? count($setting->onboarding_screens) : 0; @endphp
              <span class="badge bg-primary rounded-pill">{{ $onboardingCount }}</span>
            </button>
          </li>
          @endif
          <li class="nav-item" role="presentation">
            <button class="nav-link py-3 fw-semibold d-flex align-items-center justify-content-center gap-2" id="tab-privacy-tab" data-bs-toggle="pill" data-bs-target="#tab-privacy" type="button" role="tab" aria-controls="tab-privacy" aria-selected="false">
              <i class="ri-shield-user-line fs-5"></i>
              <span>Privacy Policy</span>
              <span class="badge bg-primary rounded-pill">{{ $privacyPolicies->count() }}</span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link py-3 fw-semibold d-flex align-items-center justify-content-center gap-2" id="tab-terms-tab" data-bs-toggle="pill" data-bs-target="#tab-terms" type="button" role="tab" aria-controls="tab-terms" aria-selected="false">
              <i class="ri-file-text-line fs-5"></i>
              <span>Terms & Conditions</span>
              <span class="badge bg-primary rounded-pill">{{ $termsConditions->count() }}</span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link py-3 fw-semibold d-flex align-items-center justify-content-center gap-2" id="tab-about-tab" data-bs-toggle="pill" data-bs-target="#tab-about" type="button" role="tab" aria-controls="tab-about" aria-selected="false">
              <i class="ri-information-line fs-5"></i>
              <span>About Us</span>
              <span class="badge bg-primary rounded-pill">{{ $aboutUsPolicies->count() }}</span>
            </button>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Tab Contents -->
<div class="tab-content border-0 p-0" id="appManagementTabContent">
  
  <!-- TAB 1: APP DETAILS & SETTINGS -->
  <div class="tab-pane fade show active" id="tab-settings" role="tabpanel" aria-labelledby="tab-settings-tab">
    <form action="{{ route('admin.mobile-apps.update-settings', $appType) }}" method="POST" enctype="multipart/form-data" id="appSettingsForm">
      @csrf

      <div class="row g-4">
        <!-- Left Column: Brand Images & Maintenance Mode -->
        <div class="col-lg-6 d-flex flex-column gap-4">
          <div class="card border-0 shadow-sm">
            <div class="card-header border-bottom bg-label-primary py-3">
              <h5 class="card-title mb-0 d-flex align-items-center gap-2 text-primary">
                <i class="ri-image-2-line"></i> Brand Images & Media
              </h5>
            </div>
            <div class="card-body p-4">
              
              <!-- App Logo -->
              <div class="mb-4">
                <label for="app_logo" class="form-label fw-semibold">App Logo</label>
                <div class="d-flex align-items-center gap-3">
                  <div class="avatar avatar-xl rounded border bg-light p-1 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                    @if($setting->app_logo && file_exists(public_path($setting->app_logo)))
                      <img src="{{ asset($setting->app_logo) }}" alt="App Logo" class="img-fluid rounded object-fit-contain" id="logoPreview" style="max-height: 100%;">
                    @else
                      <i class="ri-smartphone-line fs-1 text-muted" id="logoPlaceholder"></i>
                      <img src="" alt="App Logo" class="img-fluid rounded object-fit-contain d-none" id="logoPreview" style="max-height: 100%;">
                    @endif
                  </div>
                  <div class="flex-grow-1">
                    <input type="file" class="form-control mb-1" id="app_logo" name="app_logo" accept="image/*" onchange="previewImage(this, '#logoPreview', '#logoPlaceholder')">
                    <small class="text-muted d-block">PNG, JPG or WebP (Recommended: 512x512px, Max: 4MB)</small>
                  </div>
                </div>
              </div>

              <hr class="my-4">

              <!-- Splash Image -->
              <div class="mb-3">
                <label for="splash_image" class="form-label fw-semibold">Splash Screen Image</label>
                <div class="d-flex align-items-center gap-3">
                  <div class="avatar avatar-xl rounded border bg-light p-1 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                    @if($setting->splash_image && file_exists(public_path($setting->splash_image)))
                      <img src="{{ asset($setting->splash_image) }}" alt="Splash Image" class="img-fluid rounded object-fit-contain" id="splashPreview" style="max-height: 100%;">
                    @else
                      <i class="ri-image-line fs-1 text-muted" id="splashPlaceholder"></i>
                      <img src="" alt="Splash Image" class="img-fluid rounded object-fit-contain d-none" id="splashPreview" style="max-height: 100%;">
                    @endif
                  </div>
                  <div class="flex-grow-1">
                    <input type="file" class="form-control mb-1" id="splash_image" name="splash_image" accept="image/*" onchange="previewImage(this, '#splashPreview', '#splashPlaceholder')">
                    <small class="text-muted d-block">Shown when app launches (Recommended: 1080x1920px, Max: 4MB)</small>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- General Configuration & Maintenance Mode -->
          <div class="card border-0 shadow-sm">
            <div class="card-header border-bottom bg-label-primary py-3">
              <h5 class="card-title mb-0 d-flex align-items-center gap-2 text-primary">
                <i class="ri-toggle-line"></i> Welcome Message & Maintenance Mode
              </h5>
            </div>
            <div class="card-body p-4">
              <div class="row g-4">
                <div class="col-md-12">
                  <label for="welcome_message" class="form-label fw-semibold">App Home Welcome Greeting Message</label>
                  <input type="text" class="form-control" id="welcome_message" name="welcome_message" value="{{ $setting->welcome_message }}" placeholder="e.g. Welcome back to {{ ucfirst($dbAppType) }} Portal!">
                  <small class="text-muted">Appears at the top of the user's dashboard home screen</small>
                </div>

                <div class="col-md-12">
                  <label class="form-label fw-semibold d-block">Maintenance Mode</label>
                  <div class="form-check form-switch form-switch-lg mt-2">
                    <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" {{ $setting->maintenance_mode ? 'checked' : '' }}>
                    <label class="form-check-label fw-medium ms-2" for="maintenance_mode">
                      @if($setting->maintenance_mode)
                        <span class="badge bg-danger">Maintenance Enabled</span>
                      @else
                        <span class="badge bg-success">App Online (Normal)</span>
                      @endif
                    </label>
                  </div>
                  <small class="text-muted d-block mt-1">When enabled, users opening the app will see a maintenance screen</small>
                </div>
              </div>
            </div>
            <div class="card-footer border-top bg-light text-end py-3 px-4">
              <button type="submit" class="btn btn-primary px-5 shadow-sm">
                <i class="ri-save-line me-1"></i> Save App Settings
              </button>
            </div>
          </div>
        </div>

        <!-- Color Palette & Theme -->
        <div class="col-lg-6">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-bottom bg-label-primary py-3 d-flex align-items-center justify-content-between">
              <h5 class="card-title mb-0 d-flex align-items-center gap-2 text-primary">
                <i class="ri-palette-line"></i> Color Scheme & Theme
              </h5>
              <small class="text-muted">Interactive Color Pickers</small>
            </div>
            <div class="card-body p-4">
              
              <!-- Core App Theme Colors -->
              <h6 class="fw-semibold mb-3 d-flex align-items-center gap-2">
                <i class="ri-paint-brush-line text-primary"></i> Base App Theme Colors
              </h6>
              <div class="row g-3 mb-4">
                <!-- Primary Color -->
                <div class="col-md-6 col-xl-4">
                  <label for="primary_color" class="form-label fw-semibold">Primary Color</label>
                  <div class="input-group color-picker-group">
                    <label class="input-group-text p-1 cursor-pointer bg-light" title="Click to open color picker">
                      <input type="color" class="form-control form-control-color border-0" id="primaryColorPicker" value="{{ str_starts_with($setting->primary_color ?? '', '#') ? $setting->primary_color : '#' . ($setting->primary_color ?? '696CFF') }}" onchange="syncColorInput(this, '#primary_color')" oninput="syncColorInput(this, '#primary_color')">
                    </label>
                    <input type="text" class="form-control fw-monospace text-uppercase" id="primary_color" name="primary_color" value="{{ $setting->primary_color ?? '#696CFF' }}" maxlength="7" oninput="syncColorPicker(this, '#primaryColorPicker')" onkeyup="syncColorPicker(this, '#primaryColorPicker')">
                    <button type="button" class="btn btn-outline-secondary px-2" onclick="document.getElementById('primaryColorPicker').click()" title="Pick Color">
                      <i class="ri-palette-line"></i>
                    </button>
                  </div>
                  <div class="d-flex align-items-center gap-1 mt-2 flex-wrap">
                    <span class="color-swatch-btn" style="background-color: #696CFF;" onclick="applySwatch('#696CFF', '#primaryColorPicker', '#primary_color')" title="Indigo"></span>
                    <span class="color-swatch-btn" style="background-color: #7367F0;" onclick="applySwatch('#7367F0', '#primaryColorPicker', '#primary_color')" title="Purple"></span>
                    <span class="color-swatch-btn" style="background-color: #28C76F;" onclick="applySwatch('#28C76F', '#primaryColorPicker', '#primary_color')" title="Green"></span>
                    <span class="color-swatch-btn" style="background-color: #00CFDD;" onclick="applySwatch('#00CFDD', '#primaryColorPicker', '#primary_color')" title="Cyan"></span>
                    <span class="color-swatch-btn" style="background-color: #FF9F43;" onclick="applySwatch('#FF9F43', '#primaryColorPicker', '#primary_color')" title="Orange"></span>
                    <span class="color-swatch-btn" style="background-color: #FF4C51;" onclick="applySwatch('#FF4C51', '#primaryColorPicker', '#primary_color')" title="Red"></span>
                  </div>
                </div>

                <!-- Secondary Color -->
                <div class="col-md-6 col-xl-4">
                  <label for="secondary_color" class="form-label fw-semibold">Secondary Color</label>
                  <div class="input-group color-picker-group">
                    <label class="input-group-text p-1 cursor-pointer bg-light" title="Click to open color picker">
                      <input type="color" class="form-control form-control-color border-0" id="secondaryColorPicker" value="{{ str_starts_with($setting->secondary_color ?? '', '#') ? $setting->secondary_color : '#' . ($setting->secondary_color ?? '8592A3') }}" onchange="syncColorInput(this, '#secondary_color')" oninput="syncColorInput(this, '#secondary_color')">
                    </label>
                    <input type="text" class="form-control fw-monospace text-uppercase" id="secondary_color" name="secondary_color" value="{{ $setting->secondary_color ?? '#8592A3' }}" maxlength="7" oninput="syncColorPicker(this, '#secondaryColorPicker')" onkeyup="syncColorPicker(this, '#secondaryColorPicker')">
                    <button type="button" class="btn btn-outline-secondary px-2" onclick="document.getElementById('secondaryColorPicker').click()" title="Pick Color">
                      <i class="ri-palette-line"></i>
                    </button>
                  </div>
                  <div class="d-flex align-items-center gap-1 mt-2 flex-wrap">
                    <span class="color-swatch-btn" style="background-color: #8592A3;" onclick="applySwatch('#8592A3', '#secondaryColorPicker', '#secondary_color')" title="Grey"></span>
                    <span class="color-swatch-btn" style="background-color: #5D596C;" onclick="applySwatch('#5D596C', '#secondaryColorPicker', '#secondary_color')" title="Dark Slate"></span>
                    <span class="color-swatch-btn" style="background-color: #A1ACBA;" onclick="applySwatch('#A1ACBA', '#secondaryColorPicker', '#secondary_color')" title="Light Slate"></span>
                  </div>
                </div>

                <!-- Background Color -->
                <div class="col-md-6 col-xl-4">
                  <label for="background_color" class="form-label fw-semibold">Background Color</label>
                  <div class="input-group color-picker-group">
                    <label class="input-group-text p-1 cursor-pointer bg-light" title="Click to open color picker">
                      <input type="color" class="form-control form-control-color border-0" id="backgroundColorPicker" value="{{ str_starts_with($setting->background_color ?? '', '#') ? $setting->background_color : '#' . ($setting->background_color ?? 'F5F5F9') }}" onchange="syncColorInput(this, '#background_color')" oninput="syncColorInput(this, '#background_color')">
                    </label>
                    <input type="text" class="form-control fw-monospace text-uppercase" id="background_color" name="background_color" value="{{ $setting->background_color ?? '#F5F5F9' }}" maxlength="7" oninput="syncColorPicker(this, '#backgroundColorPicker')" onkeyup="syncColorPicker(this, '#backgroundColorPicker')">
                    <button type="button" class="btn btn-outline-secondary px-2" onclick="document.getElementById('backgroundColorPicker').click()" title="Pick Color">
                      <i class="ri-palette-line"></i>
                    </button>
                  </div>
                  <div class="d-flex align-items-center gap-1 mt-2 flex-wrap">
                    <span class="color-swatch-btn" style="background-color: #F5F5F9;" onclick="applySwatch('#F5F5F9', '#backgroundColorPicker', '#background_color')" title="Off White"></span>
                    <span class="color-swatch-btn" style="background-color: #FFFFFF;" onclick="applySwatch('#FFFFFF', '#backgroundColorPicker', '#background_color')" title="Pure White"></span>
                    <span class="color-swatch-btn" style="background-color: #121212;" onclick="applySwatch('#121212', '#backgroundColorPicker', '#background_color')" title="Dark Mode BG"></span>
                  </div>
                </div>
              </div>

              <hr class="my-4">

              <!-- Dynamic Module Tab Colors -->
              <h6 class="fw-semibold mb-3 d-flex align-items-center gap-2">
                <i class="ri-layout-grid-line text-primary"></i> Dynamic Module Tab Colors
              </h6>
              <div class="row g-3 mb-4">
                <!-- Overview Tab Color -->
                <div class="col-md-6">
                  <label for="overview_tab_color" class="form-label fw-semibold">Overview Tab Color</label>
                  <div class="input-group color-picker-group">
                    <label class="input-group-text p-1 cursor-pointer bg-light" title="Click to open color picker">
                      <input type="color" class="form-control form-control-color border-0" id="overviewTabColorPicker" value="{{ str_starts_with($setting->overview_tab_color ?? '', '#') ? $setting->overview_tab_color : '#' . ($setting->overview_tab_color ?? '696CFF') }}" onchange="syncColorInput(this, '#overview_tab_color')" oninput="syncColorInput(this, '#overview_tab_color')">
                    </label>
                    <input type="text" class="form-control fw-monospace text-uppercase" id="overview_tab_color" name="overview_tab_color" value="{{ $setting->overview_tab_color ?? '#696CFF' }}" maxlength="7" oninput="syncColorPicker(this, '#overviewTabColorPicker')" onkeyup="syncColorPicker(this, '#overviewTabColorPicker')">
                    <button type="button" class="btn btn-outline-secondary px-2" onclick="document.getElementById('overviewTabColorPicker').click()" title="Pick Color">
                      <i class="ri-palette-line"></i>
                    </button>
                  </div>
                  <div class="d-flex align-items-center gap-1 mt-2 flex-wrap">
                    <span class="color-swatch-btn" style="background-color: #696CFF;" onclick="applySwatch('#696CFF', '#overviewTabColorPicker', '#overview_tab_color')" title="Indigo"></span>
                    <span class="color-swatch-btn" style="background-color: #00CFDD;" onclick="applySwatch('#00CFDD', '#overviewTabColorPicker', '#overview_tab_color')" title="Cyan"></span>
                    <span class="color-swatch-btn" style="background-color: #7367F0;" onclick="applySwatch('#7367F0', '#overviewTabColorPicker', '#overview_tab_color')" title="Purple"></span>
                    <span class="color-swatch-btn" style="background-color: #28C76F;" onclick="applySwatch('#28C76F', '#overviewTabColorPicker', '#overview_tab_color')" title="Green"></span>
                    <span class="color-swatch-btn" style="background-color: #FF9F43;" onclick="applySwatch('#FF9F43', '#overviewTabColorPicker', '#overview_tab_color')" title="Orange"></span>
                    <span class="color-swatch-btn" style="background-color: #FF4C51;" onclick="applySwatch('#FF4C51', '#overviewTabColorPicker', '#overview_tab_color')" title="Red"></span>
                  </div>
                </div>

                <!-- Loan Tab Color -->
                <div class="col-md-6">
                  <label for="loan_tab_color" class="form-label fw-semibold">Loan Tab Color</label>
                  <div class="input-group color-picker-group">
                    <label class="input-group-text p-1 cursor-pointer bg-light" title="Click to open color picker">
                      <input type="color" class="form-control form-control-color border-0" id="loanTabColorPicker" value="{{ str_starts_with($setting->loan_tab_color ?? '', '#') ? $setting->loan_tab_color : '#' . ($setting->loan_tab_color ?? '00CFDD') }}" onchange="syncColorInput(this, '#loan_tab_color')" oninput="syncColorInput(this, '#loan_tab_color')">
                    </label>
                    <input type="text" class="form-control fw-monospace text-uppercase" id="loan_tab_color" name="loan_tab_color" value="{{ $setting->loan_tab_color ?? '#00CFDD' }}" maxlength="7" oninput="syncColorPicker(this, '#loanTabColorPicker')" onkeyup="syncColorPicker(this, '#loanTabColorPicker')">
                    <button type="button" class="btn btn-outline-secondary px-2" onclick="document.getElementById('loanTabColorPicker').click()" title="Pick Color">
                      <i class="ri-palette-line"></i>
                    </button>
                  </div>
                  <div class="d-flex align-items-center gap-1 mt-2 flex-wrap">
                    <span class="color-swatch-btn" style="background-color: #00CFDD;" onclick="applySwatch('#00CFDD', '#loanTabColorPicker', '#loan_tab_color')" title="Cyan"></span>
                    <span class="color-swatch-btn" style="background-color: #28C76F;" onclick="applySwatch('#28C76F', '#loanTabColorPicker', '#loan_tab_color')" title="Green"></span>
                    <span class="color-swatch-btn" style="background-color: #696CFF;" onclick="applySwatch('#696CFF', '#loanTabColorPicker', '#loan_tab_color')" title="Indigo"></span>
                    <span class="color-swatch-btn" style="background-color: #7367F0;" onclick="applySwatch('#7367F0', '#loanTabColorPicker', '#loan_tab_color')" title="Purple"></span>
                    <span class="color-swatch-btn" style="background-color: #FF9F43;" onclick="applySwatch('#FF9F43', '#loanTabColorPicker', '#loan_tab_color')" title="Orange"></span>
                    <span class="color-swatch-btn" style="background-color: #16B1FF;" onclick="applySwatch('#16B1FF', '#loanTabColorPicker', '#loan_tab_color')" title="Blue"></span>
                  </div>
                </div>

                <!-- Chit Tab Color -->
                <div class="col-md-6">
                  <label for="chit_tab_color" class="form-label fw-semibold">Chit Tab Color</label>
                  <div class="input-group color-picker-group">
                    <label class="input-group-text p-1 cursor-pointer bg-light" title="Click to open color picker">
                      <input type="color" class="form-control form-control-color border-0" id="chitTabColorPicker" value="{{ str_starts_with($setting->chit_tab_color ?? '', '#') ? $setting->chit_tab_color : '#' . ($setting->chit_tab_color ?? '7367F0') }}" onchange="syncColorInput(this, '#chit_tab_color')" oninput="syncColorInput(this, '#chit_tab_color')">
                    </label>
                    <input type="text" class="form-control fw-monospace text-uppercase" id="chit_tab_color" name="chit_tab_color" value="{{ $setting->chit_tab_color ?? '#7367F0' }}" maxlength="7" oninput="syncColorPicker(this, '#chitTabColorPicker')" onkeyup="syncColorPicker(this, '#chitTabColorPicker')">
                    <button type="button" class="btn btn-outline-secondary px-2" onclick="document.getElementById('chitTabColorPicker').click()" title="Pick Color">
                      <i class="ri-palette-line"></i>
                    </button>
                  </div>
                  <div class="d-flex align-items-center gap-1 mt-2 flex-wrap">
                    <span class="color-swatch-btn" style="background-color: #7367F0;" onclick="applySwatch('#7367F0', '#chitTabColorPicker', '#chit_tab_color')" title="Purple"></span>
                    <span class="color-swatch-btn" style="background-color: #FF9F43;" onclick="applySwatch('#FF9F43', '#chitTabColorPicker', '#chit_tab_color')" title="Orange"></span>
                    <span class="color-swatch-btn" style="background-color: #696CFF;" onclick="applySwatch('#696CFF', '#chitTabColorPicker', '#chit_tab_color')" title="Indigo"></span>
                    <span class="color-swatch-btn" style="background-color: #28C76F;" onclick="applySwatch('#28C76F', '#chitTabColorPicker', '#chit_tab_color')" title="Green"></span>
                    <span class="color-swatch-btn" style="background-color: #FF4C51;" onclick="applySwatch('#FF4C51', '#chitTabColorPicker', '#chit_tab_color')" title="Red"></span>
                    <span class="color-swatch-btn" style="background-color: #00CFDD;" onclick="applySwatch('#00CFDD', '#chitTabColorPicker', '#chit_tab_color')" title="Cyan"></span>
                  </div>
                </div>

                <!-- Fixed Deposit / Collection Tab Color -->
                <div class="col-md-6">
                  <label for="fixed_deposit_tab_color" class="form-label fw-semibold">{{ $dbAppType === 'agent' ? 'Collection / FD Tab Color' : 'Fixed Deposit Tab Color' }}</label>
                  <div class="input-group color-picker-group">
                    <label class="input-group-text p-1 cursor-pointer bg-light" title="Click to open color picker">
                      <input type="color" class="form-control form-control-color border-0" id="fixedDepositTabColorPicker" value="{{ str_starts_with($setting->fixed_deposit_tab_color ?? '', '#') ? $setting->fixed_deposit_tab_color : '#' . ($setting->fixed_deposit_tab_color ?? 'FF4C51') }}" onchange="syncColorInput(this, '#fixed_deposit_tab_color')" oninput="syncColorInput(this, '#fixed_deposit_tab_color')">
                    </label>
                    <input type="text" class="form-control fw-monospace text-uppercase" id="fixed_deposit_tab_color" name="fixed_deposit_tab_color" value="{{ $setting->fixed_deposit_tab_color ?? '#FF4C51' }}" maxlength="7" oninput="syncColorPicker(this, '#fixedDepositTabColorPicker')" onkeyup="syncColorPicker(this, '#fixedDepositTabColorPicker')">
                    <button type="button" class="btn btn-outline-secondary px-2" onclick="document.getElementById('fixedDepositTabColorPicker').click()" title="Pick Color">
                      <i class="ri-palette-line"></i>
                    </button>
                  </div>
                  <div class="d-flex align-items-center gap-1 mt-2 flex-wrap">
                    <span class="color-swatch-btn" style="background-color: #FF4C51;" onclick="applySwatch('#FF4C51', '#fixedDepositTabColorPicker', '#fixed_deposit_tab_color')" title="Red"></span>
                    <span class="color-swatch-btn" style="background-color: #FF9F43;" onclick="applySwatch('#FF9F43', '#fixedDepositTabColorPicker', '#fixed_deposit_tab_color')" title="Orange"></span>
                    <span class="color-swatch-btn" style="background-color: #7367F0;" onclick="applySwatch('#7367F0', '#fixedDepositTabColorPicker', '#fixed_deposit_tab_color')" title="Purple"></span>
                    <span class="color-swatch-btn" style="background-color: #28C76F;" onclick="applySwatch('#28C76F', '#fixedDepositTabColorPicker', '#fixed_deposit_tab_color')" title="Green"></span>
                    <span class="color-swatch-btn" style="background-color: #696CFF;" onclick="applySwatch('#696CFF', '#fixedDepositTabColorPicker', '#fixed_deposit_tab_color')" title="Indigo"></span>
                    <span class="color-swatch-btn" style="background-color: #16B1FF;" onclick="applySwatch('#16B1FF', '#fixedDepositTabColorPicker', '#fixed_deposit_tab_color')" title="Blue"></span>
                  </div>
                </div>
              </div>

              <hr class="my-4">

              <!-- Real-Time Live Mobile Phone Screen Mockup Preview -->
              <h6 class="fw-semibold mb-3 d-flex align-items-center gap-2">
                <i class="ri-smartphone-line text-primary"></i> Real-Time Live App Theme Preview
              </h6>
              <div class="border rounded p-3 bg-light text-center">
                <div class="d-inline-block border rounded shadow-sm text-start bg-white" style="width: 280px; overflow: hidden; border-radius: 16px !important;">
                  <!-- Phone Status / App Bar -->
                  <div id="livePreviewHeader" class="p-3 text-white d-flex align-items-center justify-content-between" style="background-color: {{ $setting->primary_color ?? '#696CFF' }}; transition: background-color 0.2s ease;">
                    <div class="d-flex align-items-center gap-2">
                      <i class="ri-smartphone-line fs-5"></i>
                      <span class="fw-semibold small">{{ ucfirst($dbAppType) }} App</span>
                    </div>
                    <i class="ri-notification-3-line small"></i>
                  </div>
                  <!-- Phone Body -->
                  <div id="livePreviewBody" class="p-3" style="background-color: {{ $setting->background_color ?? '#F5F5F9' }}; min-height: 140px; transition: background-color 0.2s ease;">
                    <small class="text-muted d-block mb-2 fw-semibold">Dynamic Tab Bar Preview:</small>
                    <div class="d-flex align-items-center gap-1 flex-wrap">
                      <span id="previewPillOverview" class="badge rounded-pill text-white px-2 py-1 small" style="background-color: {{ $setting->overview_tab_color ?? '#696CFF' }};">Overview</span>
                      <span id="previewPillLoan" class="badge rounded-pill text-white px-2 py-1 small" style="background-color: {{ $setting->loan_tab_color ?? '#00CFDD' }};">Loan</span>
                      <span id="previewPillChit" class="badge rounded-pill text-white px-2 py-1 small" style="background-color: {{ $setting->chit_tab_color ?? '#7367F0' }};">Chit</span>
                      <span id="previewPillFd" class="badge rounded-pill text-white px-2 py-1 small" style="background-color: {{ $setting->fixed_deposit_tab_color ?? '#FF4C51' }};">{{ $dbAppType === 'agent' ? 'Collection' : 'FD' }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <hr class="my-4">

              <!-- Theme Mode Dropdown -->
              <div class="mb-3">
                <label for="theme_mode" class="form-label fw-semibold">App Theme Mode</label>
                <div class="input-group input-group-merge">
                  <span class="input-group-text"><i class="ri-sun-line text-muted"></i></span>
                  <select class="form-select" id="theme_mode" name="theme_mode" required>
                    <option value="light" {{ $setting->theme_mode == 'light' ? 'selected' : '' }}>Light Mode (Default)</option>
                    <option value="dark" {{ $setting->theme_mode == 'dark' ? 'selected' : '' }}>Dark Mode</option>
                    <option value="system" {{ $setting->theme_mode == 'system' ? 'selected' : '' }}>System Default (Matches User Device)</option>
                  </select>
                </div>
              </div>

            </div>
          </div>
        </div>





      </div>
    </form>
  </div>

  <!-- TAB BANNERS (Customer Only) -->
  @if($dbAppType === 'customer')
  <div class="tab-pane fade" id="tab-banners" role="tabpanel" aria-labelledby="tab-banners-tab">
    <form action="{{ route('admin.mobile-apps.update-settings', $appType) }}" method="POST" enctype="multipart/form-data" id="appBannersForm">
      @csrf
      <div class="card border-0 shadow-sm">
        <div class="card-header border-bottom bg-label-primary py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <h5 class="card-title mb-0 d-flex align-items-center gap-2 text-primary">
              <i class="ri-slideshow-3-line"></i> In-App Banner Media Sliders
            </h5>
            <small class="text-muted">Upload promotional banners for Overview, Loan, Chit, and FD screens</small>
          </div>
        </div>
        <div class="card-body p-4">
          @php
            $bannerGroups = [
              ['key' => 'overview', 'input' => 'banner_images', 'label' => 'Overview / Home', 'images' => $setting->banner_images ?? []],
              ['key' => 'loan', 'input' => 'loan_banner_images', 'label' => 'Loan', 'images' => $setting->loan_banner_images ?? []],
              ['key' => 'chit', 'input' => 'chit_banner_images', 'label' => 'Chit', 'images' => $setting->chit_banner_images ?? []],
              ['key' => 'fd', 'input' => 'fd_banner_images', 'label' => 'Fixed Deposit', 'images' => $setting->fd_banner_images ?? []],
            ];
          @endphp

          <!-- Nav tabs -->
          <ul class="nav nav-tabs mb-4" role="tablist">
            @foreach($bannerGroups as $index => $group)
              <li class="nav-item" role="presentation">
                <button class="nav-link {{ $index === 0 ? 'active' : '' }}" id="banner-tab-{{ $group['key'] }}" data-bs-toggle="tab" data-bs-target="#banner-pane-{{ $group['key'] }}" type="button" role="tab" aria-controls="banner-pane-{{ $group['key'] }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                  {{ $group['label'] }} Banners
                </button>
              </li>
            @endforeach
          </ul>

          <!-- Tab panes -->
          <div class="tab-content border-0 p-0">
            @foreach($bannerGroups as $index => $group)
              <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}" id="banner-pane-{{ $group['key'] }}" role="tabpanel" aria-labelledby="banner-tab-{{ $group['key'] }}">
                <div class="mb-4">
                  <label for="{{ $group['input'] }}" class="form-label">Upload New Images (Multiple)</label>
                  <input type="file" class="form-control" id="{{ $group['input'] }}" name="{{ $group['input'] }}[]" accept="image/*" multiple>
                  <small class="text-muted">Hold Ctrl/Cmd to select multiple images. Recommended ratio: 16:9 or 2:1</small>
                </div>

                @if(is_array($group['images']) && count($group['images']) > 0)
                  <div class="row g-3">
                    @foreach($group['images'] as $bIndex => $bPath)
                      @if(file_exists(public_path($bPath)))
                        <div class="col-6 col-sm-4 col-md-3 col-lg-2" id="bannerCard_{{ $group['key'] }}_{{ $bIndex }}">
                          <div class="banner-thumb-card border shadow-sm">
                            <img src="{{ asset($bPath) }}" alt="{{ $group['label'] }} Banner {{ $bIndex + 1 }}" class="banner-thumb-img">
                            <div class="banner-overlay-delete" title="Delete Banner" onclick="deleteBannerImage('{{ $appType }}', '{{ $bPath }}', '#bannerCard_{{ $group['key'] }}_{{ $bIndex }}', '{{ $group['key'] }}')">
                              <i class="ri-delete-bin-line fs-6"></i>
                            </div>
                          </div>
                        </div>
                      @endif
                    @endforeach
                  </div>
                @else
                  <div class="p-3 text-center border rounded bg-light">
                    <p class="mb-0 text-muted small">No {{ strtolower($group['label']) }} banners uploaded yet.</p>
                  </div>
                @endif
              </div>
            @endforeach
          </div>
        </div>
        <div class="card-footer border-top bg-light text-end py-3 px-4">
          <button type="submit" class="btn btn-primary px-5 shadow-sm">
            <i class="ri-save-line me-1"></i> Save Banners
          </button>
        </div>
      </div>
    </form>
  </div>
  @endif

  <!-- TAB 2: PRIVACY POLICY MANAGEMENT -->
  <div class="tab-pane fade" id="tab-privacy" role="tabpanel" aria-labelledby="tab-privacy-tab">
    <div class="card border-0 shadow-sm">
      <div class="card-header border-bottom py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <h5 class="card-title mb-0 d-flex align-items-center gap-2">
            <i class="ri-shield-user-line text-primary"></i> Dynamic Privacy Policy Records
          </h5>
          <small class="text-muted">Manage privacy policy versions and legal disclosures for {{ ucfirst($dbAppType) }} App</small>
        </div>
        <button class="btn btn-primary shadow-sm" onclick="openCreatePolicyModal('privacy_policy')">
          <i class="ri-add-line me-1"></i> Add Privacy Policy
        </button>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive text-nowrap">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th width="60">#</th>
                <th>Title</th>
                <th>Version</th>
                <th>Effective Date</th>
                <th>Status</th>
                <th>Created At</th>
                <th class="text-center" width="160">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($privacyPolicies as $index => $item)
                <tr>
                  <td>{{ $index + 1 }}</td>
                  <td class="fw-semibold text-heading">{{ $item->title }}</td>
                  <td><span class="badge bg-label-info">{{ $item->version }}</span></td>
                  <td>{{ $item->effective_date ? $item->effective_date->format('d M Y') : 'N/A' }}</td>
                  <td>
                    @if($item->status == 'active')
                      <span class="badge bg-label-success">Active</span>
                    @elseif($item->status == 'draft')
                      <span class="badge bg-label-warning">Draft</span>
                    @else
                      <span class="badge bg-label-secondary">Archived</span>
                    @endif
                  </td>
                  <td>{{ $item->created_at ? $item->created_at->format('d M Y, h:i A') : 'N/A' }}</td>
                  <td class="text-center">
                    <div class="d-flex align-items-center justify-content-center gap-1">
                      <button class="btn btn-sm btn-icon btn-label-info shadow-sm" title="Preview Document" onclick="previewPolicy({{ json_encode($item) }})">
                        <i class="ri-eye-line"></i>
                      </button>
                      <button class="btn btn-sm btn-icon btn-label-primary shadow-sm" title="Edit" onclick="editPolicy({{ json_encode($item) }})">
                        <i class="ri-pencil-line"></i>
                      </button>
                      <button class="btn btn-sm btn-icon btn-label-warning shadow-sm" title="Toggle Status" onclick="togglePolicyStatus({{ $item->id }})">
                        <i class="ri-toggle-line"></i>
                      </button>
                      <button class="btn btn-sm btn-icon btn-label-danger shadow-sm" title="Delete" onclick="deletePolicy({{ $item->id }})">
                        <i class="ri-delete-bin-line"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center py-5 text-muted">
                    <i class="ri-file-search-line fs-1 mb-2 d-block opacity-50"></i>
                    <p class="mb-0">No Privacy Policy records found for {{ ucfirst($dbAppType) }} App.</p>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- TAB 3: TERMS & CONDITIONS MANAGEMENT -->
  <div class="tab-pane fade" id="tab-terms" role="tabpanel" aria-labelledby="tab-terms-tab">
    <div class="card border-0 shadow-sm">
      <div class="card-header border-bottom py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <h5 class="card-title mb-0 d-flex align-items-center gap-2">
            <i class="ri-file-text-line text-primary"></i> Dynamic Terms & Conditions Records
          </h5>
          <small class="text-muted">Manage terms of service agreements and rules for {{ ucfirst($dbAppType) }} App</small>
        </div>
        <button class="btn btn-primary shadow-sm" onclick="openCreatePolicyModal('terms_conditions')">
          <i class="ri-add-line me-1"></i> Add Terms & Conditions
        </button>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive text-nowrap">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th width="60">#</th>
                <th>Title</th>
                <th>Version</th>
                <th>Effective Date</th>
                <th>Status</th>
                <th>Created At</th>
                <th class="text-center" width="160">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($termsConditions as $index => $item)
                <tr>
                  <td>{{ $index + 1 }}</td>
                  <td class="fw-semibold text-heading">{{ $item->title }}</td>
                  <td><span class="badge bg-label-info">{{ $item->version }}</span></td>
                  <td>{{ $item->effective_date ? $item->effective_date->format('d M Y') : 'N/A' }}</td>
                  <td>
                    @if($item->status == 'active')
                      <span class="badge bg-label-success">Active</span>
                    @elseif($item->status == 'draft')
                      <span class="badge bg-label-warning">Draft</span>
                    @else
                      <span class="badge bg-label-secondary">Archived</span>
                    @endif
                  </td>
                  <td>{{ $item->created_at ? $item->created_at->format('d M Y, h:i A') : 'N/A' }}</td>
                  <td class="text-center">
                    <div class="d-flex align-items-center justify-content-center gap-1">
                      <button class="btn btn-sm btn-icon btn-label-info shadow-sm" title="Preview Document" onclick="previewPolicy({{ json_encode($item) }})">
                        <i class="ri-eye-line"></i>
                      </button>
                      <button class="btn btn-sm btn-icon btn-label-primary shadow-sm" title="Edit" onclick="editPolicy({{ json_encode($item) }})">
                        <i class="ri-pencil-line"></i>
                      </button>
                      <button class="btn btn-sm btn-icon btn-label-warning shadow-sm" title="Toggle Status" onclick="togglePolicyStatus({{ $item->id }})">
                        <i class="ri-toggle-line"></i>
                      </button>
                      <button class="btn btn-sm btn-icon btn-label-danger shadow-sm" title="Delete" onclick="deletePolicy({{ $item->id }})">
                        <i class="ri-delete-bin-line"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center py-5 text-muted">
                    <i class="ri-file-search-line fs-1 mb-2 d-block opacity-50"></i>
                    <p class="mb-0">No Terms & Conditions records found for {{ ucfirst($dbAppType) }} App.</p>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- TAB 4: ABOUT US MANAGEMENT -->
  <div class="tab-pane fade" id="tab-about" role="tabpanel" aria-labelledby="tab-about-tab">
    <div class="card border-0 shadow-sm">
      <div class="card-header border-bottom py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <h5 class="card-title mb-0 d-flex align-items-center gap-2">
            <i class="ri-information-line text-primary"></i> Dynamic About Us Records
          </h5>
          <small class="text-muted">Manage company details and about us content for {{ ucfirst($dbAppType) }} App</small>
        </div>
        <button class="btn btn-primary shadow-sm" onclick="openCreatePolicyModal('about_us')">
          <i class="ri-add-line me-1"></i> Add About Us
        </button>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive text-nowrap">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th width="60">#</th>
                <th>Title</th>
                <th>Version</th>
                <th>Effective Date</th>
                <th>Status</th>
                <th>Created At</th>
                <th class="text-center" width="160">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($aboutUsPolicies as $index => $item)
                <tr>
                  <td>{{ $index + 1 }}</td>
                  <td class="fw-semibold text-heading">{{ $item->title }}</td>
                  <td><span class="badge bg-label-info">{{ $item->version }}</span></td>
                  <td>{{ $item->effective_date ? $item->effective_date->format('d M Y') : 'N/A' }}</td>
                  <td>
                    @if($item->status == 'active')
                      <span class="badge bg-label-success">Active</span>
                    @elseif($item->status == 'draft')
                      <span class="badge bg-label-warning">Draft</span>
                    @else
                      <span class="badge bg-label-secondary">Archived</span>
                    @endif
                  </td>
                  <td>{{ $item->created_at ? $item->created_at->format('d M Y, h:i A') : 'N/A' }}</td>
                  <td class="text-center">
                    <div class="d-flex align-items-center justify-content-center gap-1">
                      <button class="btn btn-sm btn-icon btn-label-info shadow-sm" title="Preview Document" onclick="previewPolicy({{ json_encode($item) }})">
                        <i class="ri-eye-line"></i>
                      </button>
                      <button class="btn btn-sm btn-icon btn-label-primary shadow-sm" title="Edit" onclick="editPolicy({{ json_encode($item) }})">
                        <i class="ri-pencil-line"></i>
                      </button>
                      <button class="btn btn-sm btn-icon btn-label-warning shadow-sm" title="Toggle Status" onclick="togglePolicyStatus({{ $item->id }})">
                        <i class="ri-toggle-line"></i>
                      </button>
                      <button class="btn btn-sm btn-icon btn-label-danger shadow-sm" title="Delete" onclick="deletePolicy({{ $item->id }})">
                        <i class="ri-delete-bin-line"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center py-5 text-muted">
                    <i class="ri-file-search-line fs-1 mb-2 d-block opacity-50"></i>
                    <p class="mb-0">No About Us records found for {{ ucfirst($dbAppType) }} App.</p>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- TAB 5: ONBOARDING SCREENS MANAGEMENT -->
  @if($dbAppType === 'customer')
  <div class="tab-pane fade" id="tab-onboarding" role="tabpanel" aria-labelledby="tab-onboarding-tab">
    <div class="card border-0 shadow-sm">
      <div class="card-header border-bottom py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <h5 class="card-title mb-0 d-flex align-items-center gap-2">
            <i class="ri-pages-line text-primary"></i> Onboarding Screens
          </h5>
          <small class="text-muted">Manage the onboarding screens for the Customer App</small>
        </div>
        <button class="btn btn-primary shadow-sm" onclick="openCreateOnboardingModal()">
          <i class="ri-add-line me-1"></i> Add Screen
        </button>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive text-nowrap">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th width="60">#</th>
                <th width="100">Image</th>
                <th>Title</th>
                <th>Description</th>
                <th class="text-center" width="100">Actions</th>
              </tr>
            </thead>
            <tbody>
              @php $onboardingScreens = is_array($setting->onboarding_screens) ? $setting->onboarding_screens : []; @endphp
              @forelse($onboardingScreens as $index => $screen)
                <tr>
                  <td>{{ $index + 1 }}</td>
                  <td>
                    <img src="{{ url($screen['image']) }}" alt="Screen Image" class="img-thumbnail" style="width: 80px; height: 80px; object-fit: contain; border-radius: 8px;">
                  </td>
                  <td class="fw-semibold text-heading">{{ $screen['title'] }}</td>
                  <td><small class="text-muted text-wrap" style="max-width: 300px; display: inline-block;">{{ \Illuminate\Support\Str::limit($screen['description'], 100) }}</small></td>
                  <td class="text-center">
                    <button class="btn btn-sm btn-icon btn-label-danger shadow-sm" title="Delete" onclick="deleteOnboardingScreen('{{ $screen['id'] }}')">
                      <i class="ri-delete-bin-line"></i>
                    </button>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="text-center py-5 text-muted">
                    <i class="ri-pages-line fs-1 mb-2 d-block opacity-50"></i>
                    <p class="mb-0">No Onboarding screens found for Customer App.</p>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  @endif

</div>

<!-- Modal: Create/Edit Policy or Terms -->
<div class="modal fade" id="policyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-label-primary border-bottom py-3 px-4">
        <h5 class="modal-title fw-bold text-primary" id="policyModalTitle">Add New Document</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="policyForm" method="POST" action="">
        @csrf
        <input type="hidden" name="_method" id="policyFormMethod" value="POST">
        <input type="hidden" name="type" id="policyTypeInput" value="privacy_policy">
        
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="policyTitle" class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="policyTitle" name="title" placeholder="e.g. Privacy Policy 2026" required>
            </div>

            <div class="col-md-3">
              <label for="policyVersion" class="form-label fw-semibold">Version <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="policyVersion" name="version" placeholder="v1.0" required>
            </div>

            <div class="col-md-3">
              <label for="policyStatus" class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
              <select class="form-select" id="policyStatus" name="status" required>
                <option value="active">Active</option>
                <option value="draft">Draft</option>
                <option value="archived">Archived</option>
              </select>
            </div>

            <div class="col-md-6">
              <label for="policyEffectiveDate" class="form-label fw-semibold">Effective Date</label>
              <input type="date" class="form-control" id="policyEffectiveDate" name="effective_date">
            </div>

            <div class="col-12">
              <label for="policyContent" class="form-label fw-semibold">Document Content (HTML supported) <span class="text-danger">*</span></label>
              <textarea class="form-control" id="policyContent" name="content" rows="8" placeholder="Enter terms/privacy policy details..." required></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light border-top py-3 px-4">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4 shadow-sm" id="savePolicyBtn">Save Document</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Preview Policy Document -->
<div class="modal fade" id="previewPolicyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-bottom py-3 px-4 bg-light">
        <div class="d-flex align-items-center gap-2">
          <h5 class="modal-title fw-bold text-heading mb-0" id="previewDocTitle">Document Preview</h5>
          <span class="badge bg-label-info ms-2" id="previewDocVersion">v1.0</span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3 text-muted small">
          <div><strong>App:</strong> {{ ucfirst($dbAppType) }} App</div>
          <div><strong>Effective Date:</strong> <span id="previewDocDate">N/A</span></div>
        </div>
        <div class="document-content-body p-3 bg-body-tertiary border rounded" id="previewDocContent" style="max-height: 450px; overflow-y: auto;">
          <!-- Rendered policy text -->
        </div>
      </div>
      <div class="modal-footer border-top py-2 px-4">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Create Onboarding Screen -->
<div class="modal fade" id="onboardingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-label-primary border-bottom py-3 px-4">
        <h5 class="modal-title fw-bold text-primary">Add Onboarding Screen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="{{ route('admin.mobile-apps.store-onboarding', $appType) }}" enctype="multipart/form-data">
        @csrf
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="title" placeholder="e.g. Welcome to App" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
              <textarea class="form-control" name="description" rows="3" placeholder="Enter short description" required></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Image <span class="text-danger">*</span></label>
              <input class="form-control" type="file" name="image" accept="image/*" required onchange="previewImage(this, '#onboardingImagePreview')">
              <div class="mt-2 text-center">
                <img id="onboardingImagePreview" src="" alt="Preview" class="d-none img-thumbnail" style="max-height: 150px;">
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light border-top py-3 px-4">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4 shadow-sm">Save Screen</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
  function previewImage(input, previewSelector, placeholderSelector) {
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function (e) {
        $(previewSelector).attr('src', e.target.result).removeClass('d-none');
        if (placeholderSelector) {
          $(placeholderSelector).addClass('d-none');
        }
      };
      reader.readAsDataURL(input.files[0]);
    }
  }

  function syncColorInput(picker, targetInput) {
    let val = $(picker).val();
    if (!val.startsWith('#')) val = '#' + val;
    $(targetInput).val(val.toUpperCase());
    updateAppLivePreview();
  }

  function syncColorPicker(input, targetPicker) {
    let val = $(input).val().trim();
    if (!val.startsWith('#') && val.length > 0) {
      val = '#' + val;
    }
    if (/^#[0-9A-F]{6}$/i.test(val)) {
      $(targetPicker).val(val);
      updateAppLivePreview();
    }
  }

  function applySwatch(color, pickerSelector, inputSelector) {
    $(pickerSelector).val(color);
    $(inputSelector).val(color.toUpperCase());
    updateAppLivePreview();
  }

  function updateAppLivePreview() {
    const primary = $('#primary_color').val() || '#696CFF';
    const bg = $('#background_color').val() || '#F5F5F9';
    const overview = $('#overview_tab_color').val() || '#696CFF';
    const loan = $('#loan_tab_color').val() || '#00CFDD';
    const chit = $('#chit_tab_color').val() || '#7367F0';
    const fd = $('#fixed_deposit_tab_color').val() || '#FF4C51';

    $('#livePreviewHeader').css('background-color', primary);
    $('#livePreviewBody').css('background-color', bg);
    $('#previewPillOverview').css('background-color', overview);
    $('#previewPillLoan').css('background-color', loan);
    $('#previewPillChit').css('background-color', chit);
    $('#previewPillFd').css('background-color', fd);
  }

  function deleteBannerImage(appType, imagePath, cardSelector, bannerGroup) {
    Swal.fire({
      title: 'Remove Banner?',
      text: 'Are you sure you want to remove this banner image?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it',
      customClass: {
        confirmButton: 'btn btn-danger me-3',
        cancelButton: 'btn btn-label-secondary'
      },
      buttonsStyling: false
    }).then(result => {
      if (result.isConfirmed) {
        $.ajax({
          url: "{{ route('admin.mobile-apps.delete-banner', $appType) }}",
          method: 'POST',
          data: {
            _token: '{{ csrf_token() }}',
            image_path: imagePath,
            banner_group: bannerGroup || 'overview'
          },
          success: function (res) {
            if (res.success) {
              $(cardSelector).fadeOut(300, function () { $(this).remove(); });
              Swal.fire({ icon: 'success', title: 'Deleted', text: res.message, timer: 1200, showConfirmButton: false });
            }
          },
          error: function () {
            Swal.fire('Error', 'Could not delete banner image.', 'error');
          }
        });
      }
    });
  }

  function openCreatePolicyModal(type) {
    const form = document.getElementById('policyForm');
    form.action = "{{ route('admin.mobile-apps.store-policy', $appType) }}";
    document.getElementById('policyFormMethod').value = 'POST';
    document.getElementById('policyTypeInput').value = type;
    
    document.getElementById('policyTitle').value = type === 'privacy_policy' ? 'Privacy Policy' : 'Terms & Conditions';
    document.getElementById('policyVersion').value = 'v1.0';
    document.getElementById('policyStatus').value = 'active';
    document.getElementById('policyEffectiveDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('policyContent').value = '';

    const titleText = type === 'privacy_policy' ? 'Add New Privacy Policy' : (type === 'about_us' ? 'Add New About Us' : 'Add New Terms & Conditions');
    document.getElementById('policyModalTitle').textContent = titleText;

    const modal = new bootstrap.Modal(document.getElementById('policyModal'));
    modal.show();
  }

  function openCreateOnboardingModal() {
    $('#onboardingImagePreview').addClass('d-none').attr('src', '');
    const modal = new bootstrap.Modal(document.getElementById('onboardingModal'));
    modal.show();
  }

  function deleteOnboardingScreen(id) {
    Swal.fire({
      title: 'Delete Onboarding Screen?',
      text: 'Are you sure you want to delete this screen?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it',
      customClass: {
        confirmButton: 'btn btn-danger me-3',
        cancelButton: 'btn btn-label-secondary'
      },
      buttonsStyling: false
    }).then(result => {
      if (result.isConfirmed) {
        $.ajax({
          url: "{{ route('admin.mobile-apps.delete-onboarding', $appType) }}",
          method: 'POST',
          data: {
            _token: '{{ csrf_token() }}',
            id: id
          },
          success: function (res) {
            if (res.success) {
              Swal.fire({ icon: 'success', title: 'Deleted', text: res.message, timer: 1200, showConfirmButton: false }).then(() => {
                window.location.reload();
              });
            }
          },
          error: function () {
            Swal.fire('Error', 'Could not delete onboarding screen.', 'error');
          }
        });
      }
    });
  }

  function editPolicy(item) {
    const form = document.getElementById('policyForm');
    form.action = baseUrl + 'admin/mobile-apps/policies/' + item.id;
    document.getElementById('policyFormMethod').value = 'PUT';
    document.getElementById('policyTypeInput').value = item.type;

    document.getElementById('policyTitle').value = item.title;
    document.getElementById('policyVersion').value = item.version;
    document.getElementById('policyStatus').value = item.status;
    
    if (item.effective_date) {
      const d = new Date(item.effective_date);
      document.getElementById('policyEffectiveDate').value = d.toISOString().split('T')[0];
    } else {
      document.getElementById('policyEffectiveDate').value = '';
    }

    document.getElementById('policyContent').value = item.content || '';

    const titleText = item.type === 'privacy_policy' ? 'Edit Privacy Policy' : 'Edit Terms & Conditions';
    document.getElementById('policyModalTitle').textContent = titleText;

    const modal = new bootstrap.Modal(document.getElementById('policyModal'));
    modal.show();
  }

  function previewPolicy(item) {
    document.getElementById('previewDocTitle').textContent = item.title;
    document.getElementById('previewDocVersion').textContent = item.version || 'v1.0';
    document.getElementById('previewDocDate').textContent = item.effective_date ? new Date(item.effective_date).toLocaleDateString('en-GB') : 'N/A';
    document.getElementById('previewDocContent').innerHTML = item.content || '<p class="text-muted mb-0">No content entered.</p>';

    const modal = new bootstrap.Modal(document.getElementById('previewPolicyModal'));
    modal.show();
  }

  function togglePolicyStatus(id) {
    $.ajax({
      url: baseUrl + 'admin/mobile-apps/policies/' + id + '/toggle-status',
      method: 'POST',
      data: {
        _token: '{{ csrf_token() }}'
      },
      success: function (res) {
        if (res.success) {
          Swal.fire({ icon: 'success', title: 'Updated', text: res.message, timer: 1200, showConfirmButton: false }).then(() => {
            window.location.reload();
          });
        }
      },
      error: function () {
        Swal.fire('Error', 'Failed to toggle status.', 'error');
      }
    });
  }

  function deletePolicy(id) {
    Swal.fire({
      title: 'Delete Record?',
      text: 'Are you sure you want to delete this legal policy record?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete',
      customClass: {
        confirmButton: 'btn btn-danger me-3',
        cancelButton: 'btn btn-label-secondary'
      },
      buttonsStyling: false
    }).then(result => {
      if (result.isConfirmed) {
        $.ajax({
          url: baseUrl + 'admin/mobile-apps/policies/' + id,
          method: 'POST',
          data: {
            _token: '{{ csrf_token() }}',
            _method: 'DELETE'
          },
          success: function (res) {
            if (res.success) {
              Swal.fire({ icon: 'success', title: 'Deleted', text: res.message, timer: 1200, showConfirmButton: false }).then(() => {
                window.location.reload();
              });
            }
          },
          error: function () {
            Swal.fire('Error', 'Failed to delete record.', 'error');
          }
        });
      }
    });
  }
</script>
@endsection
