@extends('layouts/layoutMaster')

@section('title', 'API Configuration')

@section('page-script')
@vite(['resources/assets/custom-js/api-configuration.js'])
@endsection

@section('content')
@php
  $activeService = old('active_service', session('active_service'));
  $resolveConfig = fn(string $service) => $configurations->get($service);
  $resolveCred = function (string $service, string $field, $default = '') use ($activeService, $resolveConfig) {
    if ($service === $activeService) {
      return old("credentials.$field", '');
    }
    $config = $resolveConfig($service);
    $creds = $config->credentials ?? [];
    return $creds[$field] ?? $default;
  };
  $resolveEnabled = function (string $service) use ($activeService, $resolveConfig) {
    if ($service === $activeService) {
      $oldValue = old('is_enabled');
      if (! is_null($oldValue)) {
        return filter_var($oldValue, FILTER_VALIDATE_BOOLEAN);
      }
    }
    $config = $resolveConfig($service);
    return (bool) ($config->is_enabled ?? false);
  };
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <h4 class="mb-1">API Configuration</h4>
    <p class="text-muted mb-0">Securely manage third-party integrations used across the platform.</p>
  </div>
</div>

<div class="row g-4">
  {{-- WhatsApp Credential --}}
  @php $whatsappEnabled = $resolveEnabled('whatsapp'); @endphp
  <div class="col-12 col-xl-4">
    <div class="card border shadow-none h-100 position-relative">
      <div class="position-absolute top-0 end-0 m-3">
        <span role="button" tabindex="0" class="d-inline-flex align-items-center justify-content-center p-2 rounded-circle bg-label-primary-soft" data-bs-toggle="modal" data-bs-target="#whatsappModal" aria-label="Edit WhatsApp configuration">
          <i class="icon-base ri ri-pencil-line icon-18px text-primary"></i>
        </span>
      </div>
      <div class="card-body">
        <div class="d-flex align-items-center mb-3">
          <div class="me-3">
            <img src="{{ asset('assets/img/logos/whatsapp.png') }}" alt="WhatsApp" width="48" height="48">
          </div>
          <div>
            <h6 class="card-title mb-0">WhatsApp Credential</h6>
            <small class="text-muted">Business Cloud notifications</small>
          </div>
        </div>
        
        <p class="text-muted mb-3">Enable WhatsApp Business Cloud notifications for automated messaging.</p>

        <div class="d-flex justify-content-between align-items-center">
          <span class="badge {{ $whatsappEnabled ? 'bg-label-success' : 'bg-label-secondary' }}" id="whatsappBadge">
            {{ $whatsappEnabled ? 'Active' : 'Inactive' }}
          </span>
          <div class="form-check form-switch mb-0">
            <input type="checkbox" class="form-check-input api-toggle" id="whatsappToggle"
              {{ $whatsappEnabled ? 'checked' : '' }}
              data-service="whatsapp"
              data-badge="whatsappBadge"
              style="cursor: pointer; width: 3rem; height: 1.5rem;">
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Google Maps Credential --}}
  @php $googleEnabled = $resolveEnabled('google_maps'); @endphp
  <div class="col-12 col-xl-4">
    <div class="card border shadow-none h-100 position-relative">
      <div class="position-absolute top-0 end-0 m-3">
        <span role="button" tabindex="0" class="d-inline-flex align-items-center justify-content-center p-2 rounded-circle bg-label-primary-soft" data-bs-toggle="modal" data-bs-target="#googleModal" aria-label="Edit Google Maps configuration">
          <i class="icon-base ri ri-pencil-line icon-18px text-primary"></i>
        </span>
      </div>
      <div class="card-body">
        <div class="d-flex align-items-center mb-3">
          <div class="me-3">
            <img src="{{ asset('assets/img/logos/google.png') }}" alt="Google Maps" width="48" height="48">
          </div>
          <div>
            <h6 class="card-title mb-0">Google Maps Credential</h6>
            <small class="text-muted">Geolocation & Maps</small>
          </div>
        </div>
        
        <p class="text-muted mb-3">Power geolocation, distance matrix, and map embeds across the platform.</p>

        <div class="d-flex justify-content-between align-items-center">
          <span class="badge {{ $googleEnabled ? 'bg-label-success' : 'bg-label-secondary' }}" id="googleBadge">
            {{ $googleEnabled ? 'Active' : 'Inactive' }}
          </span>
          <div class="form-check form-switch mb-0">
            <input type="checkbox" class="form-check-input api-toggle" id="googleToggle"
              {{ $googleEnabled ? 'checked' : '' }}
              data-service="google_maps"
              data-badge="googleBadge"
              style="cursor: pointer; width: 3rem; height: 1.5rem;">
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- WebSMPP Credential --}}
  @php $websmppEnabled = $resolveEnabled('websmpp'); @endphp
  <div class="col-12 col-xl-4">
    <div class="card border shadow-none h-100 position-relative">
      <div class="position-absolute top-0 end-0 m-3">
        <span role="button" tabindex="0" class="d-inline-flex align-items-center justify-content-center p-2 rounded-circle bg-label-primary-soft" data-bs-toggle="modal" data-bs-target="#websmppModal" aria-label="Edit WebSMPP configuration">
          <i class="icon-base ri ri-pencil-line icon-18px text-primary"></i>
        </span>
      </div>
      <div class="card-body">
        <div class="d-flex align-items-center mb-3">
          <div class="me-3">
            <img src="{{ asset('assets/img/logos/websmpp.png') }}" alt="WebSMPP" width="48" height="48">
          </div>
          <div>
            <h6 class="card-title mb-0">WebSMPP Credential</h6>
            <small class="text-muted">Transactional SMS</small>
          </div>
        </div>
        
        <p class="text-muted mb-3">Configure transactional SMS gateway access for system notifications.</p>

        <div class="d-flex justify-content-between align-items-center">
          <span class="badge {{ $websmppEnabled ? 'bg-label-success' : 'bg-label-secondary' }}" id="websmppBadge">
            {{ $websmppEnabled ? 'Active' : 'Inactive' }}
          </span>
          <div class="form-check form-switch mb-0">
            <input type="checkbox" class="form-check-input api-toggle" id="websmppToggle"
              {{ $websmppEnabled ? 'checked' : '' }}
              data-service="websmpp"
              data-badge="websmppBadge"
              style="cursor: pointer; width: 3rem; height: 1.5rem;">
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Firebase Credential --}}
  @php $firebaseEnabled = $resolveEnabled('firebase'); @endphp
  <div class="col-12 col-xl-4">
    <div class="card border shadow-none h-100 position-relative">
      <div class="position-absolute top-0 end-0 m-3">
        <span role="button" tabindex="0" class="d-inline-flex align-items-center justify-content-center p-2 rounded-circle bg-label-primary-soft" data-bs-toggle="modal" data-bs-target="#firebaseModal" aria-label="Edit Firebase configuration">
          <i class="icon-base ri ri-pencil-line icon-18px text-primary"></i>
        </span>
      </div>
      <div class="card-body">
        <div class="d-flex align-items-center mb-3">
          <div class="me-3">
            <img src="{{ asset('assets/img/logos/firebase.png') }}" alt="Firebase" width="48" height="48">
          </div>
          <div>
            <h6 class="card-title mb-0">Firebase</h6>
            <small class="text-muted">Cloud services</small>
          </div>
        </div>
        
        <p class="text-muted mb-3">Use Firebase Cloud Messaging (FCM) to deliver real-time alerts and system notifications to users.</p>

        <div class="d-flex justify-content-between align-items-center">
          <span class="badge {{ $firebaseEnabled ? 'bg-label-success' : 'bg-label-secondary' }}" id="firebaseBadge">
            {{ $firebaseEnabled ? 'Active' : 'Inactive' }}
          </span>
          <div class="form-check form-switch mb-0">
            <input type="checkbox" class="form-check-input api-toggle" id="firebaseToggle"
              {{ $firebaseEnabled ? 'checked' : '' }}
              data-service="firebase"
              data-badge="firebaseBadge"
              style="cursor: pointer; width: 3rem; height: 1.5rem;">
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- CIBIL / Credit bureau API --}}
  @php $cibilEnabled = $resolveEnabled('cibil'); @endphp
  <div class="col-12 col-xl-4">
    <div class="card border shadow-none h-100 position-relative">
      <div class="position-absolute top-0 end-0 m-3">
        <span role="button" tabindex="0" class="d-inline-flex align-items-center justify-content-center p-2 rounded-circle bg-label-primary-soft" data-bs-toggle="modal" data-bs-target="#cibilModal" aria-label="Edit CIBIL API configuration">
          <i class="icon-base ri ri-pencil-line icon-18px text-primary"></i>
        </span>
      </div>
      <div class="card-body">
        <div class="d-flex align-items-center mb-3">
          <div class="me-3">
            <span class="avatar avatar-md rounded bg-label-primary"><i class="icon-base ri ri-bar-chart-box-line ri-24px"></i></span>
          </div>
          <div>
            <h6 class="card-title mb-0">CIBIL / Credit bureau</h6>
            <small class="text-muted">Credit score API</small>
          </div>
        </div>
        <p class="text-muted mb-3">Connect your partner credit bureau endpoint. Without this, Verification → Credit score uses demo data.</p>
        <div class="d-flex justify-content-between align-items-center">
          <span class="badge {{ $cibilEnabled ? 'bg-label-success' : 'bg-label-secondary' }}" id="cibilBadge">
            {{ $cibilEnabled ? 'Active' : 'Inactive' }}
          </span>
          <div class="form-check form-switch mb-0">
            <input type="checkbox" class="form-check-input api-toggle" id="cibilToggle"
              {{ $cibilEnabled ? 'checked' : '' }}
              data-service="cibil"
              data-badge="cibilBadge"
              style="cursor: pointer; width: 3rem; height: 1.5rem;">
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Modals --}}

<!-- CIBIL Modal -->
<div class="modal fade" id="cibilModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">CIBIL / Credit bureau API</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="cibilForm" action="{{ route('setup-configuration-api-configuration.save', 'cibil') }}" method="POST">
          @csrf
          <input type="hidden" name="is_enabled" value="{{ $cibilEnabled ? '1' : '0' }}">
          <input type="hidden" name="active_service" value="cibil">
          <div class="mb-3">
            <label class="form-label">Base URL <span class="text-danger">*</span></label>
            <input type="url" name="credentials[base_url]" class="form-control" required
              value="{{ $resolveCred('cibil', 'base_url') }}" placeholder="https://partner-api.example.com/v1">
          </div>
          <div class="mb-3">
            <label class="form-label">Endpoint path</label>
            <input type="text" name="credentials[endpoint]" class="form-control"
              value="{{ $resolveCred('cibil', 'endpoint', '/credit-report') }}" placeholder="/credit-report">
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">HTTP method</label>
              <select name="credentials[http_method]" class="form-select">
                <option value="POST" @selected($resolveCred('cibil', 'http_method', 'POST') === 'POST')>POST</option>
                <option value="GET" @selected($resolveCred('cibil', 'http_method') === 'GET')>GET</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Auth type</label>
              <select name="credentials[auth_type]" class="form-select">
                <option value="bearer" @selected($resolveCred('cibil', 'auth_type', 'bearer') === 'bearer')>Bearer token (api_key)</option>
                <option value="basic" @selected($resolveCred('cibil', 'auth_type') === 'basic')>Basic (api_key / api_secret)</option>
                <option value="headers" @selected($resolveCred('cibil', 'auth_type') === 'headers')>Headers X-API-Key / X-API-Secret</option>
              </select>
            </div>
          </div>
          <div class="mb-3 mt-2">
            <label class="form-label">API key / token</label>
            <input type="password" name="credentials[api_key]" class="form-control" autocomplete="off"
              value="{{ $resolveCred('cibil', 'api_key') }}" placeholder="Bearer token or username">
          </div>
          <div class="mb-3">
            <label class="form-label">API secret (optional)</label>
            <input type="password" name="credentials[api_secret]" class="form-control" autocomplete="off"
              value="{{ $resolveCred('cibil', 'api_secret') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Score JSON path (dot notation)</label>
            <input type="text" name="credentials[score_json_path]" class="form-control"
              value="{{ $resolveCred('cibil', 'score_json_path', 'score') }}" placeholder="data.score or score">
            <small class="text-muted">Where to read the numeric score from the JSON response.</small>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary save-api-btn" data-form="cibilForm">
          <i class="ri-save-line me-1"></i> Save
        </button>
      </div>
    </div>
  </div>
</div>

<!-- WhatsApp Modal -->
<div class="modal fade" id="whatsappModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">WhatsApp Configuration</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="whatsappForm" action="{{ route('setup-configuration-api-configuration.save', 'whatsapp') }}" method="POST">
          @csrf
          <input type="hidden" name="is_enabled" value="{{ $whatsappEnabled ? '1' : '0' }}">
          <input type="hidden" name="active_service" value="whatsapp">

          <div class="mb-3">
            <label class="form-label">Provider <span class="text-danger">*</span></label>
            <input type="text" name="credentials[provider]" class="form-control"
              value="{{ $resolveCred('whatsapp', 'provider') }}" placeholder="Enter provider name (e.g., Gallabox)" required>
          </div>
          <div class="mb-3">
            <label class="form-label">API Key / Access Token <span class="text-danger">*</span></label>
            <div class="input-group input-group-merge">
              <input type="password" name="credentials[access_token]" class="form-control"
                value="{{ $resolveCred('whatsapp', 'access_token') }}" placeholder="Enter permanent access token" required>
              <span class="input-group-text cursor-pointer" data-toggle-password="whatsappToken">
                <i class="ri-eye-off-line"></i>
              </span>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Workspace ID / Account ID <span class="text-danger">*</span></label>
            <input type="text" name="credentials[workspace_id]" class="form-control"
              value="{{ $resolveCred('whatsapp', 'workspace_id') }}" placeholder="Enter workspace or account ID" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Channel ID <span class="text-danger">*</span></label>
            <input type="text" name="credentials[channel_id]" class="form-control"
              value="{{ $resolveCred('whatsapp', 'channel_id') }}" placeholder="Enter channel ID" required>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary save-api-btn" data-form="whatsappForm">
          <i class="ri-save-line me-1"></i> Save
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Google Maps Modal -->
<div class="modal fade" id="googleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Google Maps Configuration</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="googleForm" action="{{ route('setup-configuration-api-configuration.save', 'google_maps') }}" method="POST">
          @csrf
          <input type="hidden" name="is_enabled" value="{{ $googleEnabled ? '1' : '0' }}">
          <input type="hidden" name="active_service" value="google_maps">

          <div class="mb-3">
            <label class="form-label">API Key <span class="text-danger">*</span></label>
            <input type="text" name="credentials[api_key]" class="form-control"
              value="{{ $resolveCred('google_maps', 'api_key') }}" placeholder="Enter Google Maps API key" required>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary save-api-btn" data-form="googleForm">
          <i class="ri-save-line me-1"></i> Save
        </button>
      </div>
    </div>
  </div>
</div>

<!-- WebSMPP Modal -->
<div class="modal fade" id="websmppModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">WebSMPP Configuration</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="websmppForm" action="{{ route('setup-configuration-api-configuration.save', 'websmpp') }}" method="POST">
          @csrf
          <input type="hidden" name="is_enabled" value="{{ $websmppEnabled ? '1' : '0' }}">
          <input type="hidden" name="active_service" value="websmpp">

          <div class="mb-3">
            <label class="form-label">Username <span class="text-danger">*</span></label>
            <input type="text" name="credentials[user]" class="form-control"
              value="{{ $resolveCred('websmpp', 'user') }}" placeholder="Enter WebSMPP username" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password <span class="text-danger">*</span></label>
            <div class="input-group input-group-merge">
              <input type="password" name="credentials[password]" class="form-control"
                value="{{ $resolveCred('websmpp', 'password') }}" placeholder="Enter WebSMPP password" required>
              <span class="input-group-text cursor-pointer" data-toggle-password="websmppPassword">
                <i class="ri-eye-off-line"></i>
              </span>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Sender ID <span class="text-danger">*</span></label>
            <input type="text" name="credentials[sender_id]" class="form-control"
              value="{{ $resolveCred('websmpp', 'sender_id') }}" placeholder="Max 11 characters" maxlength="11" required>
          </div>
          <div class="mb-3">
            <label class="form-label">PEID</label>
            <input type="text" name="credentials[peid]" class="form-control"
              value="{{ $resolveCred('websmpp', 'peid') }}" placeholder="Optional principal entity ID">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary save-api-btn" data-form="websmppForm">
          <i class="ri-save-line me-1"></i> Save
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Firebase Modal -->
<div class="modal fade" id="firebaseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Firebase Configuration</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="firebaseForm" action="{{ route('setup-configuration-api-configuration.save', 'firebase') }}" method="POST" enctype="multipart/form-data">
          @csrf
          <input type="hidden" name="is_enabled" value="{{ $firebaseEnabled ? '1' : '0' }}">
          <input type="hidden" name="active_service" value="firebase">

          <div class="mb-3">
            <label class="form-label">Project Key <span class="text-danger">*</span></label>
            <div class="input-group input-group-merge">
              <input type="password" name="credentials[project_key]" class="form-control"
                value="{{ $resolveCred('firebase', 'project_key') }}" required>
              <span class="input-group-text cursor-pointer" data-toggle-password="firebaseKey">
                <i class="ri-eye-off-line"></i>
              </span>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">SDK File <span class="text-danger">*</span></label>
            <input type="file" name="sdk_file" id="sdkFileInput" class="form-control" accept=".json">
            <small class="text-muted">Upload Firebase Admin SDK JSON file</small>
            @php
              $sdkPath = $resolveCred('firebase', 'sdk_path');
            @endphp
            @if($sdkPath)
              <div class="mt-2">
                <small class="text-success">
                  <i class="ri-file-check-line me-1"></i>
                  Current file: {{ basename($sdkPath) }}
                </small>
              </div>
            @endif
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary save-api-btn" data-form="firebaseForm">
          <i class="ri-save-line me-1"></i> Save
        </button>
      </div>
    </div>
  </div>
</div>
@endsection
