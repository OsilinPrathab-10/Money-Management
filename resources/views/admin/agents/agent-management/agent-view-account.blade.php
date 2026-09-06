@extends('layouts/layoutMaster')

@section('title', 'Agent View - Account')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/@form-validation/form-validation.scss'
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/moment/moment.js',
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
    'resources/assets/vendor/libs/cleave-zen/cleave-zen.js',
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/vendor/libs/@form-validation/popular.js',
    'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/auto-focus.js'
  ])
@endsection

@section('page-script')
  @vite([
    'resources/assets/custom-js/agent-view-account.js'
  ])
@endsection

@section('content')

  <div
    class="d-flex flex-column flex-md-row flex-wrap align-items-start align-items-md-center justify-content-between gap-3 mb-6">
    <div class="nav-align-top">
      <ul class="nav nav-pills flex-column flex-md-row row-gap-2">
        <li class="nav-item"><a class="nav-link active" href="javascript:void(0);"><i
              class="icon-base ri ri-user-3-line me-1_5"></i>Account</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('agent-management.view-work', $agent->id) }}"><i
              class="icon-base ri ri-briefcase-line me-1_5"></i>Work Information</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('agent-management.view-visits', $agent->id) }}"><i
              class="icon-base ri ri-map-pin-line me-1_5"></i>Visits</a></li>
      </ul>
    </div>
    <a href="{{ route('agent-management.index') }}"
      class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-4 ms-md-auto">
      <i class="icon-base ri ri-arrow-left-line"></i>
      <span>Back to Agents</span>
    </a>
  </div>

  <div class="row">
    <!-- Agent Sidebar -->
    <div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
      <!-- Agent Card -->
      <div class="card mb-6">
        <div class="card-body pt-12">
          <div class="user-avatar-section">
            <div class=" d-flex align-items-center flex-column">
              <img class="img-fluid rounded mb-4" src="{{asset('assets/img/avatars/3.png')}}" height="120" width="120"
                alt="Agent avatar" />
              <div class="user-info text-center">
                <h5 id="sidebarAgentName">{{ $agent->agent_name ?? 'Agent Name' }}</h5>
                @php
                  $statusBadgeMap = [
                    'active' => ['label' => 'Active', 'badge' => 'success'],
                    'inactive' => ['label' => 'Inactive', 'badge' => 'danger'],
                  ];
                  $statusInfo = $statusBadgeMap[$agent->status] ?? ['label' => 'Inactive', 'badge' => 'danger'];
                @endphp
                <span id="sidebarAgentStatusBadge" class="badge bg-label-{{ $statusInfo['badge'] }} rounded-pill">
                  {{ $statusInfo['label'] }}
                </span>
              </div>
            </div>
          </div>

          <div class="accordion mt-4" id="agentInfoAccordion">
            <!-- Personal Information Accordion -->
            <div class="accordion-item">
              <h2 class="accordion-header" id="headingPersonal">
                <button class="accordion-button" type="button" data-bs-toggle="collapse"
                  data-bs-target="#collapsePersonal" aria-expanded="true" aria-controls="collapsePersonal">
                  <small class="text-primary text-uppercase fw-semibold">Personal Information</small>
                </button>
              </h2>
              <div id="collapsePersonal" class="accordion-collapse collapse show" aria-labelledby="headingPersonal"
                data-bs-parent="#agentInfoAccordion">
                <div class="accordion-body">
                  <div class="row g-4">
                    <div class="col-sm-6">
                      <small class="text-muted text-uppercase">Email</small>
                      <p class="mb-0 text-heading" id="sidebarAgentEmail">{{ $agent->agent_email ?? 'N/A' }}</p>
                    </div>
                    <div class="col-sm-6">
                      <small class="text-muted text-uppercase">Mobile</small>
                      <p class="mb-0 text-heading" id="sidebarAgentPhone">{{ $agent->agent_phone ?? 'N/A' }}</p>
                    </div>
                    <div class="col-sm-6">
                      <small class="text-muted text-uppercase">Agent Code</small>
                      <p class="mb-0 text-heading" id="sidebarAgentCode">{{ $agent->agent_code ?? 'N/A' }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Address Information Accordion -->
            <div class="accordion-item">
              <h2 class="accordion-header" id="headingAddress">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#collapseAddress" aria-expanded="false" aria-controls="collapseAddress">
                  <small class="text-primary text-uppercase fw-semibold">Address Information</small>
                </button>
              </h2>
              <div id="collapseAddress" class="accordion-collapse collapse" aria-labelledby="headingAddress"
                data-bs-parent="#agentInfoAccordion">
                <div class="accordion-body">
                  <div class="row g-4">
                    <div class="col-sm-6">
                      <small class="text-muted text-uppercase">Address</small>
                      <p class="mb-0 text-heading">{{ $agent->address ?? 'N/A' }}</p>
                    </div>
                    <div class="col-sm-6">
                      <small class="text-muted text-uppercase">City</small>
                      <p class="mb-0 text-heading">{{ $agent->city ?? 'N/A' }}</p>
                    </div>
                    <div class="col-sm-6">
                      <small class="text-muted text-uppercase">State</small>
                      <p class="mb-0 text-heading">{{ $agent->state ?? 'N/A' }}</p>
                    </div>
                    <div class="col-sm-6">
                      <small class="text-muted text-uppercase">Pincode</small>
                      <p class="mb-0 text-heading">{{ $agent->pincode ?? 'N/A' }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- /Agent Card -->
    </div>
    <!--/ Agent Sidebar -->

    <!-- Agent Content -->
    <div class="col-xl-8 col-lg-7 col-md-7 order-0 order-md-1">
      <!-- Account Details Card -->
      <div class="card mb-6">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
          <h5 class="mb-0">Account Details</h5>
          <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-icon btn-outline-primary btn-pill " id="enableAccountEditBtn"
              title="Edit Account Details" aria-label="Edit Account Details">
              <i class="icon-base ri ri-pencil-fill"></i>
            </button>
            <div class="alert-container" data-success="{{ session('success') }}" data-error="{{ session('error') }}"
              data-warning="" data-info=""></div>
          </div>
        </div>
        <div class="card-body">
          <form id="formAccountSettings" method="POST" action="{{ route('agent-management.update-account', $agent->id) }}">
            @csrf
            <div class="row g-5">
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input class="form-control" type="text" id="agent_name" name="agent_name"
                    value="{{ $agent->agent_name }}" readonly data-editable="true" />
                  <label for="agent_name">Full Name</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input class="form-control" type="text" name="agent_email" id="agent_email"
                    value="{{ $agent->agent_email ?? '' }}" readonly data-editable="true" />
                  <label for="agent_email">Email</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input type="text" class="form-control" id="agent_phone" name="agent_phone"
                    value="{{ $agent->agent_phone }}" readonly data-editable="true" />
                  <label for="agent_phone">Phone Number</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input type="text" class="form-control" id="agent_code" name="agent_code"
                    value="{{ $agent->agent_code }}" readonly disabled />
                  <label for="agent_code">Agent Code</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <select id="status" name="status" class="form-select" disabled data-editable="true">
                    <option value="active" {{ $agent->status == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ $agent->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                  </select>
                  <label for="status">Status</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input type="text" class="form-control" id="city" name="city" value="{{ $agent->city }}" oninput="this.value = this.value.replace(/[^a-zA-Z\s.]/g, '')" readonly
                    data-editable="true" />
                  <label for="city">City</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input type="text" class="form-control" id="state" name="state" value="{{ $agent->state }}" oninput="this.value = this.value.replace(/[^a-zA-Z\s.]/g, '')" readonly
                    data-editable="true" />
                  <label for="state">State</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <select id="location_id" name="location_id" class="form-select select2" disabled data-editable="true">
                    <option value="">Select Location</option>
                    @foreach($locations as $loc)
                      <option value="{{ $loc->id }}" {{ $agent->location_id == $loc->id ? 'selected' : '' }}>
                        {{ $loc->name }}, {{ $loc->city }}
                      </option>
                    @endforeach
                  </select>
                  <label for="location_id">Service Area / Location</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input type="text" class="form-control" id="pincode" name="pincode" value="{{ $agent->pincode }}"
                    readonly data-editable="true" />
                  <label for="pincode">Pincode</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-password-toggle">
                  <div class="input-group input-group-merge">
                    <div class="form-floating form-floating-outline">
                      <input type="password" class="form-control" id="password" name="password" 
                        value="{{ $agent->user->plain_password ?? '' }}" placeholder="••••••••"
                        readonly data-editable="true" autocomplete="new-password" />
                      <label for="password">New Password (optional)</label>
                    </div>
                    <span class="input-group-text cursor-pointer">
                      <i class="icon-base ri ri-eye-off-line icon-20px"></i>
                    </span>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-password-toggle">
                  <div class="input-group input-group-merge">
                    <div class="form-floating form-floating-outline">
                      <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" 
                        value="{{ $agent->user->plain_password ?? '' }}" placeholder="••••••••"
                        readonly data-editable="true" autocomplete="new-password" />
                      <label for="password_confirmation">Confirm New Password</label>
                    </div>
                    <span class="input-group-text cursor-pointer">
                      <i class="icon-base ri ri-eye-off-line icon-20px"></i>
                    </span>
                  </div>
                </div>
              </div>
              <div class="col-12">
                <div class="form-floating form-floating-outline">
                  <textarea class="form-control h-px-100" id="address" name="address" readonly
                    data-editable="true">{{ $agent->address }}</textarea>
                  <label for="address">Address</label>
                </div>
              </div>
            </div>
            <div class="mt-6 d-flex flex-wrap align-items-center gap-3 d-none" id="accountFormActions">
              <button type="submit" class="btn btn-primary">Save changes</button>
              <button type="reset" class="btn btn-outline-secondary">Cancel</button>
            </div>
          </form>
        </div>
      </div>
      <!--/ Account Details Card -->

      <!-- Agent Live Location Card -->
      <!-- <div class="card mb-6">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
          <h5 class="mb-0 d-flex align-items-center gap-2">
            <i class="ri ri-map-pin-2-line text-primary"></i> Current Location
          </h5>
          @if($liveLocation)
            <small class="text-muted">
              <i class="ri ri-time-line me-1"></i>
              Last updated: {{ $liveLocation->recorded_at->diffForHumans() }}
            </small>
          @endif
        </div>
        <div class="card-body p-0">
          @if($liveLocation && $googleMapsKey)
            <div style="height: 320px; width: 100%; border-radius: 0 0 0.5rem 0.5rem; overflow: hidden;">
              <iframe id="agentLocationMap" width="100%" height="100%" frameborder="0" style="border: 0;" allowfullscreen
                src="https://www.google.com/maps/embed/v1/place?key={{ $googleMapsKey }}&q={{ $liveLocation->latitude }},{{ $liveLocation->longitude }}&zoom=15&maptype=roadmap"
                loading="lazy">
              </iframe>
            </div>
            <div class="px-4 py-3 border-top d-flex align-items-center gap-4 flex-wrap">
              <div class="d-flex align-items-center gap-2">
                <i class="ri ri-navigation-line text-primary fs-5"></i>
                <div>
                  <div class="text-muted" style="font-size: 11px;">LATITUDE</div>
                  <div class="fw-semibold">{{ number_format($liveLocation->latitude, 6) }}</div>
                </div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <i class="ri ri-navigation-fill text-primary fs-5"></i>
                <div>
                  <div class="text-muted" style="font-size: 11px;">LONGITUDE</div>
                  <div class="fw-semibold">{{ number_format($liveLocation->longitude, 6) }}</div>
                </div>
              </div>
              <a href="https://www.google.com/maps?q={{ $liveLocation->latitude }},{{ $liveLocation->longitude }}"
                target="_blank" class="btn btn-sm btn-outline-primary ms-auto d-flex align-items-center gap-1">
                <i class="ri ri-external-link-line"></i> Open in Maps
              </a>
            </div>
          @elseif($liveLocation && !$googleMapsKey)
            {{-- Has location but no Maps API key --}}
            <div class="p-4 text-center text-muted">
              <i class="ri ri-map-pin-line fs-2 mb-2 d-block text-warning"></i>
              <p class="mb-1">Location available but Google Maps API key is not configured.</p>
              <small>Coordinates: {{ $liveLocation->latitude }}, {{ $liveLocation->longitude }}</small>
            </div>
          @else
            <div class="p-5 text-center text-muted">
              <i class="ri ri-map-pin-line fs-1 mb-3 d-block" style="opacity: .3;"></i>
              <p class="mb-0">No location data available.</p>
              <small>The agent hasn't shared their location yet.</small>
            </div>
          @endif
        </div>
      </div> -->
      <!-- /Agent Live Location Card -->
    </div>
    <!--/ Agent Content -->
  </div>

@endsection