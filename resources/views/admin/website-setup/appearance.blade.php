@extends('layouts/layoutMaster')

@section('title', 'Appearance Settings')

@section('content')

<!-- Alert Container -->
<div class="alert-container"
  data-success="{{ session('success') ? e(session('success')) : '' }}"
  data-error="{{ session('error') ? e(session('error')) : '' }}"
  data-warning="{{ session('warning') ? e(session('warning')) : '' }}"
  data-info="{{ session('info') ? e(session('info')) : '' }}">
</div>

<div class="row">
  <!-- Template Customizer -->
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">
          <i class="ri-palette-line me-2"></i>Template Customizer
        </h5>
        <p class="text-muted mb-0">Customize and preview in real time</p>
      </div>
      <div class="card-body">
        <form id="appearanceForm" action="{{ route('website-appearance-update') }}" method="POST">
          @csrf
          
          <!-- Theming Section -->
          <div class="mb-6">
            <h6 class="mb-4">
              <span class="badge bg-label-primary rounded-pill px-3 py-2">Theming</span>
            </h6>
            
            <!-- Primary Color -->
            <div class="mb-5">
              <label class="form-label fw-semibold">Primary Color</label>
              <div class="d-flex gap-3 align-items-center flex-wrap">
                <!-- Color Picker -->
                <div class="border rounded p-2" style="width: 120px;">
                  <input type="color" 
                         id="colorPicker" 
                         name="primary_color" 
                         value="{{ $appearance->primary_color ?? '#696cff' }}" 
                         class="form-control form-control-color border-0 p-0" 
                         style="width: 100%; height: 40px;">
                </div>
                
                <!-- Hex Input -->
                <div style="width: 120px;">
                  <input type="text" 
                         id="hexInput" 
                         class="form-control text-center" 
                         value="{{ $appearance->primary_color ?? '#696cff' }}" 
                         placeholder="#696cff"
                         maxlength="7"
                         pattern="^#[0-9A-Fa-f]{6}$">
                </div>
                
                <!-- Color Preview -->
                <div class="border rounded" style="width: 60px; height: 60px;">
                  <div id="colorPreview" 
                       class="w-100 h-100 rounded" 
                       style="background-color: {{ $appearance->primary_color ?? '#696cff' }};"></div>
                </div>
              </div>
            </div>

            <!-- Secondary Color -->
            <div class="mb-5">
              <label class="form-label fw-semibold">Secondary Color</label>
              <div class="d-flex gap-3 align-items-center flex-wrap">
                <!-- Color Picker -->
                <div class="border rounded p-2" style="width: 120px;">
                  <input type="color" 
                         id="secondaryColorPicker" 
                         name="secondary_color" 
                         value="{{ $appearance->secondary_color ?? '#8592a3' }}" 
                         class="form-control form-control-color border-0 p-0" 
                         style="width: 100%; height: 40px;">
                </div>
                
                <!-- Hex Input -->
                <div style="width: 120px;">
                  <input type="text" 
                         id="secondaryHexInput" 
                         class="form-control text-center" 
                         value="{{ $appearance->secondary_color ?? '#8592a3' }}" 
                         placeholder="#8592a3"
                         maxlength="7"
                         pattern="^#[0-9A-Fa-f]{6}$">
                </div>
                
                <!-- Color Preview -->
                <div class="border rounded" style="width: 60px; height: 60px;">
                  <div id="secondaryColorPreview" 
                       class="w-100 h-100 rounded" 
                       style="background-color: {{ $appearance->secondary_color ?? '#8592a3' }};"></div>
                </div>
              </div>
            </div>

            <!-- Theme Mode -->
            <div class="mb-5">
              <label class="form-label fw-semibold">Theme</label>
              <div class="d-flex gap-2">
                <div class="form-check p-0">
                  <input class="form-check-input theme-option d-none" type="radio" name="theme_mode" id="themeLight" value="light" {{ ($appearance->theme_mode ?? 'light') == 'light' ? 'checked' : '' }}>
                  <label class="form-check-label cursor-pointer" for="themeLight">
                    <span class="btn btn-sm {{ ($appearance->theme_mode ?? 'light') == 'light' ? 'btn-primary' : 'btn-outline-secondary' }}">
                      <i class="ri-sun-line me-1"></i>Light
                    </span>
                  </label>
                </div>
                <div class="form-check p-0">
                  <input class="form-check-input theme-option d-none" type="radio" name="theme_mode" id="themeDark" value="dark" {{ ($appearance->theme_mode ?? '') == 'dark' ? 'checked' : '' }}>
                  <label class="form-check-label cursor-pointer" for="themeDark">
                    <span class="btn btn-sm {{ ($appearance->theme_mode ?? '') == 'dark' ? 'btn-primary' : 'btn-outline-secondary' }}">
                      <i class="ri-moon-line me-1"></i>Dark
                    </span>
                  </label>
                </div>
                <div class="form-check p-0">
                  <input class="form-check-input theme-option d-none" type="radio" name="theme_mode" id="themeSystem" value="system" {{ ($appearance->theme_mode ?? '') == 'system' ? 'checked' : '' }}>
                  <label class="form-check-label cursor-pointer" for="themeSystem">
                    <span class="btn btn-sm {{ ($appearance->theme_mode ?? '') == 'system' ? 'btn-primary' : 'btn-outline-secondary' }}">
                      <i class="ri-computer-line me-1"></i>System
                    </span>
                  </label>
                </div>
              </div>
            </div>

            <!-- Page Loader Animation -->
            <div class="mb-5">
              <label class="form-label fw-semibold">Page Loader Animation</label>
              <div class="row g-3">
                <!-- Loader 1 -->
                <div class="col-md-4">
                  <input class="form-check-input loader-option d-none" type="radio" name="loader_animation" id="loader1" value="loader1" {{ ($appearance->loader_animation ?? 'loader1') == 'loader1' ? 'checked' : '' }}>
                  <label class="form-check-label cursor-pointer w-100" for="loader1">
                    <div class="border rounded p-3 text-center {{ ($appearance->loader_animation ?? 'loader1') == 'loader1' ? 'border-primary border-2' : '' }}" style="min-height: 180px;">
                      <h6 class="mb-3">Loader 1</h6>
                      <div class="d-flex justify-content-center align-items-center" style="height: 80px;">
                        <div class="loader-preview loader-preview-1"></div>
                      </div>
                      <small class="text-muted mt-2 d-block">Jumping Box</small>
                    </div>
                  </label>
                </div>

                <!-- Loader 2 -->
                <div class="col-md-4">
                  <input class="form-check-input loader-option d-none" type="radio" name="loader_animation" id="loader2" value="loader2" {{ ($appearance->loader_animation ?? '') == 'loader2' ? 'checked' : '' }}>
                  <label class="form-check-label cursor-pointer w-100" for="loader2">
                    <div class="border rounded p-3 text-center {{ ($appearance->loader_animation ?? '') == 'loader2' ? 'border-primary border-2' : '' }}" style="min-height: 180px;">
                      <h6 class="mb-3">Loader 2</h6>
                      <div class="d-flex justify-content-center align-items-center" style="height: 80px;">
                        <div class="loader-preview loader-preview-2">
                          <svg xmlns="http://www.w3.org/2000/svg" height="64px" width="64px" viewBox="0 0 128 128" class="pl">
                            <defs>
                              <linearGradient y2="1" x2="0" y1="0" x1="0" id="pl-grad-preview">
                                <stop stop-color="{{ $appearance->primary_color ?? '#696cff' }}" offset="0%"></stop>
                                <stop stop-color="{{ $appearance->primary_color ?? '#696cff' }}" offset="100%"></stop>
                              </linearGradient>
                            </defs>
                            <circle stroke-linecap="round" stroke-width="16" stroke="hsla(0,10%,10%,0.1)" fill="none" cy="64" cx="64" r="56" class="pl__ring"></circle>
                            <path stroke-dashoffset="10" stroke-dasharray="44 1111" stroke-linejoin="round" stroke-linecap="round" stroke-width="16" stroke="url(#pl-grad-preview)" fill="none" d="M92,15.492S78.194,4.967,66.743,16.887c-17.231,17.938-28.26,96.974-28.26,96.974L119.85,59.892l-99-31.588,57.528,89.832L97.8,19.349,13.636,88.51l89.012,16.015S81.908,38.332,66.1,22.337C50.114,6.156,36,15.492,36,15.492a56,56,0,1,0,56,0Z" class="pl__worm"></path>
                          </svg>
                        </div>
                      </div>
                      <small class="text-muted mt-2 d-block">Animated Worm</small>
                    </div>
                  </label>
                </div>

                <!-- Loader 3 -->
                <div class="col-md-4">
                  <input class="form-check-input loader-option d-none" type="radio" name="loader_animation" id="loader3" value="loader3" {{ ($appearance->loader_animation ?? '') == 'loader3' ? 'checked' : '' }}>
                  <label class="form-check-label cursor-pointer w-100" for="loader3">
                    <div class="border rounded p-3 text-center {{ ($appearance->loader_animation ?? '') == 'loader3' ? 'border-primary border-2' : '' }}" style="min-height: 180px;">
                      <h6 class="mb-3">Loader 3</h6>
                      <div class="d-flex justify-content-center align-items-center" style="height: 80px;">
                        <div class="loader-preview loader-preview-3"></div>
                      </div>
                      <small class="text-muted mt-2 d-block">Dual Rotating Dots</small>
                    </div>
                  </label>
                </div>

                <!-- Loader Favicon / Logo -->
                <div class="col-md-4">
                  <input class="form-check-input loader-option d-none" type="radio" name="loader_animation" id="loader_favicon" value="loader_favicon" {{ ($appearance->loader_animation ?? '') == 'loader_favicon' ? 'checked' : '' }}>
                  <label class="form-check-label cursor-pointer w-100" for="loader_favicon">
                    <div class="border rounded p-3 text-center {{ ($appearance->loader_animation ?? '') == 'loader_favicon' ? 'border-primary border-2' : '' }}" style="min-height: 180px;">
                      <h6 class="mb-3">Favicon / Logo</h6>
                      <div class="d-flex justify-content-center align-items-center" style="height: 80px;">
                        @if(!empty($appearance->favicon))
                          <img src="{{ route('storage.serve', ['path' => $appearance->favicon]) }}" alt="Favicon" class="img-fluid" style="max-height: 64px; max-width: 64px; object-fit: contain;">
                        @elseif(!empty($appearance->logo))
                          <img src="{{ route('storage.serve', ['path' => $appearance->logo]) }}" alt="Logo" class="img-fluid" style="max-height: 64px; max-width: 64px; object-fit: contain;">
                        @else
                          <span class="text-muted small">Upload favicon/logo above</span>
                        @endif
                      </div>
                      <small class="text-muted mt-2 d-block">Your favicon or logo as preloader</small>
                    </div>
                  </label>
                </div>
              </div>
            </div>

          </div>

          <!-- Save Button -->
          <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">
              <i class="ri-save-line me-1"></i> Save Settings
            </button>
            <button type="button" class="btn btn-outline-secondary" id="resetBtn">
              <i class="ri-refresh-line me-1"></i> Reset to Default
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

@endsection

@section('page-style')
<style>
/* Loader Preview Styles */
.loader-preview {
  display: inline-block;
}

/* Loader 1 - Jumping Box */
.loader-preview-1 {
  width: 48px;
  height: 48px;
  margin: auto;
  position: relative;
}

.loader-preview-1:before {
  content: '';
  width: 48px;
  height: 5px;
  background: #999;
  position: absolute;
  top: 60px;
  left: 0;
  border-radius: 50%;
  animation: shadow324 0.5s linear infinite;
}

.loader-preview-1:after {
  content: '';
  width: 100%;
  height: 100%;
  background: {{ $appearance->primary_color ?? '#696cff' }};
  position: absolute;
  top: 0;
  left: 0;
  border-radius: 4px;
  animation: jump7456 0.5s linear infinite;
}

@keyframes jump7456 {
  15% {
    border-bottom-right-radius: 3px;
  }
  25% {
    transform: translateY(9px) rotate(22.5deg);
  }
  50% {
    transform: translateY(18px) scale(1, .9) rotate(45deg);
    border-bottom-right-radius: 40px;
  }
  75% {
    transform: translateY(9px) rotate(67.5deg);
  }
  100% {
    transform: translateY(0) rotate(90deg);
  }
}

@keyframes shadow324 {
  0%, 100% {
    transform: scale(1, 1);
  }
  50% {
    transform: scale(1.2, 1);
  }
}

/* Loader 2 - Animated Worm SVG */
.loader-preview-2 .pl,
.loader-preview-2 .pl__worm {
  animation-duration: 4s;
  animation-iteration-count: infinite;
}

.loader-preview-2 .pl {
  animation-name: bump5;
  animation-timing-function: linear;
}

.loader-preview-2 .pl__worm {
  animation-name: worm5;
  animation-timing-function: cubic-bezier(0.42,0.17,0.75,0.83);
}

/* Loader 3 - Dual Rotating Dots */
.loader-preview-3 {
  height: 15px;
  aspect-ratio: 4;
  --_g: no-repeat radial-gradient(farthest-side, {{ $appearance->primary_color ?? '#696cff' }} 90%, {{ $appearance->primary_color ?? '#696cff' }});
  background:
    var(--_g) left,
    var(--_g) right;
  background-size: 25% 100%;
  display: grid;
}
.loader-preview-3:before,
.loader-preview-3:after {
  content: "";
  height: inherit;
  aspect-ratio: 1;
  grid-area: 1/1;
  margin: auto;
  border-radius: 50%;
  transform-origin: -100% 50%;
  background: {{ $appearance->primary_color ?? '#696cff' }};
  animation: l49 1s infinite linear;
}
.loader-preview-3:after {
  transform-origin: 200% 50%;
  --s: -1;
  animation-delay: -0.5s;
}

@keyframes bump5 {
  from, 42%, 46%, 51%, 55%, 59%, 63%, 67%, 71%, 74%, 78%, 81%, 85%, 88%, 92%, to {
    transform: translate(0,0);
  }
  44% {
    transform: translate(1.33%,6.75%);
  }
  53% {
    transform: translate(-16.67%,-0.54%);
  }
  61% {
    transform: translate(3.66%,-2.46%);
  }
  69% {
    transform: translate(-0.59%,15.27%);
  }
  76% {
    transform: translate(-1.92%,-4.68%);
  }
  83% {
    transform: translate(9.38%,0.96%);
  }
  90% {
    transform: translate(-4.55%,1.98%);
  }
}

@keyframes worm5 {
  from {
    stroke-dashoffset: 10;
  }
  25% {
    stroke-dashoffset: 295;
  }
  to {
    stroke-dashoffset: 1165;
  }
}

@keyframes l49 {
  58%, 100% {
    transform: rotate(calc(var(--s, 1) * 1turn));
  }
}

/* Loader option selection */
.loader-option:checked + label .border {
  border-color: var(--bs-primary) !important;
  border-width: 2px !important;
}
</style>
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/website-appearance.js'])
@endsection
