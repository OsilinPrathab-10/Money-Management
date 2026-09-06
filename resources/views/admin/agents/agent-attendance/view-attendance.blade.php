@extends('layouts/layoutMaster')

@section('title', 'Attendance Details')

<!-- Vendor Styles -->
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/animate-css/animate.scss', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
  <style>
    #map {
      height: 400px;
      width: 100%;
      border-radius: 8px;
    }
    .info-card {
      border-left: 3px solid #696cff;
    }
  </style>
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('content')
  <div class="row">
    <div class="col-12">
      <div class="card mb-6">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Attendance Details #{{ $log->id }}</h5>
          <a href="{{ route('agent-attendance') }}" class="btn btn-sm btn-outline-secondary">
            <i class="icon-base ri ri-arrow-left-line me-1"></i>Back to List
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Agent Information -->
  <div class="row">
    <div class="col-md-4 mb-6">
      <div class="card info-card h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-3">
            <div class="avatar avatar-lg me-3">
              <div class="avatar-initial bg-label-primary rounded-circle">
                <i class="icon-base ri ri-user-line icon-26px"></i>
              </div>
            </div>
            <div>
              <h5 class="mb-0">{{ $log->agent->agent_name ?? 'N/A' }}</h5>
              <small class="text-muted">Agent</small>
            </div>
          </div>
          <div class="mb-2">
            <small class="text-muted">Agent ID:</small>
            <p class="mb-0 fw-medium">{{ $log->agent->id ?? 'N/A' }}</p>
          </div>
          <div class="mb-2">
            <small class="text-muted">Mobile:</small>
            <p class="mb-0 fw-medium">{{ $log->agent->agent_phone ?? 'N/A' }}</p>
          </div>
          <div>
            <small class="text-muted">Status:</small>
            <p class="mb-0">
              @if($log->status === 'checked_in')
                <span class="badge bg-label-success">Checked In</span>
              @else
                <span class="badge bg-label-warning">Checked Out</span>
              @endif
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Check In Details -->
    <div class="col-md-4 mb-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-3">
            <div class="avatar me-3">
              <div class="avatar-initial bg-label-success rounded">
                <i class="icon-base ri ri-login-circle-line icon-22px"></i>
              </div>
            </div>
            <h5 class="mb-0">Check In</h5>
          </div>
          <div class="mb-2">
            <small class="text-muted">Date & Time:</small>
            <p class="mb-0 fw-medium">{{ $log->check_in_at ? $log->check_in_at->format('d-m-Y h:i A') : 'N/A' }}</p>
          </div>
          <div class="mb-2">
            <small class="text-muted">Location:</small>
            <p class="mb-0 fw-medium">
              @if($log->check_in_lat && $log->check_in_long)
                {{ $log->check_in_lat }}, {{ $log->check_in_long }}
              @else
                N/A
              @endif
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Check Out Details -->
    <div class="col-md-4 mb-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-3">
            <div class="avatar me-3">
              <div class="avatar-initial bg-label-warning rounded">
                <i class="icon-base ri ri-logout-circle-line icon-22px"></i>
              </div>
            </div>
            <h5 class="mb-0">Check Out</h5>
          </div>
          <div class="mb-2">
            <small class="text-muted">Date & Time:</small>
            <p class="mb-0 fw-medium">
              @if($log->check_out_at)
                {{ $log->check_out_at->format('d-m-Y h:i A') }}
              @else
                <span class="badge bg-label-success">Still Working</span>
              @endif
            </p>
          </div>
          <div class="mb-2">
            <small class="text-muted">Location:</small>
            <p class="mb-0 fw-medium">
              @if($log->check_out_lat && $log->check_out_long)
                {{ $log->check_out_lat }}, {{ $log->check_out_long }}
              @else
                N/A
              @endif
            </p>
          </div>
          <div>
            <small class="text-muted">Total Hours:</small>
            <p class="mb-0">
              @if($log->check_out_at)
                <span class="badge bg-label-primary">{{ $log->total_hours ?? '00:00' }}</span>
              @else
                <span class="badge bg-label-info">IN PROGRESS</span>
              @endif
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Map Section -->
  @if($log->check_in_lat && $log->check_in_long)
  <div class="row">
    <div class="col-12 mb-6">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Location Map</h5>
        </div>
        <div class="card-body">
          <div id="map"></div>
        </div>
      </div>
    </div>
  </div>
  @endif

  <!-- Notes Section -->
  @if($log->notes)
  <div class="row">
    <div class="col-12 mb-6">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Notes</h5>
        </div>
        <div class="card-body">
          <p class="mb-0">{{ $log->notes }}</p>
        </div>
      </div>
    </div>
  </div>
  @endif

  <!-- Timeline/Status Updates Section -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Activity Timeline</h5>
        </div>
        <div class="card-body">
          <ul class="timeline mb-0">
            @if($log->check_in_at)
            <li class="timeline-item timeline-item-transparent">
              <span class="timeline-point timeline-point-success"></span>
              <div class="timeline-event">
                <div class="timeline-header mb-3">
                  <h6 class="mb-0">Checked In</h6>
                  <small class="text-muted">{{ $log->check_in_at->format('d-m-Y h:i A') }}</small>
                </div>
                <p class="mb-2">Agent checked in at {{ $log->check_in_at->format('h:i A') }}</p>
                @if($log->check_in_lat && $log->check_in_long)
                <p class="mb-0"><small class="text-muted">Location: {{ $log->check_in_lat }}, {{ $log->check_in_long }}</small></p>
                @endif
              </div>
            </li>
            @endif

            @if($log->check_out_at)
            <li class="timeline-item timeline-item-transparent">
              <span class="timeline-point timeline-point-warning"></span>
              <div class="timeline-event">
                <div class="timeline-header mb-3">
                  <h6 class="mb-0">Checked Out</h6>
                  <small class="text-muted">{{ $log->check_out_at->format('d-m-Y h:i A') }}</small>
                </div>
                <p class="mb-2">Agent checked out at {{ $log->check_out_at->format('h:i A') }}</p>
                @if($log->check_out_lat && $log->check_out_long)
                <p class="mb-2"><small class="text-muted">Location: {{ $log->check_out_lat }}, {{ $log->check_out_long }}</small></p>
                @endif
                <p class="mb-0"><strong>Total Hours: {{ $log->total_hours ?? '00:00' }}</strong></p>
              </div>
            </li>
            @else
            <li class="timeline-item timeline-item-transparent">
              <span class="timeline-point timeline-point-info"></span>
              <div class="timeline-event">
                <div class="timeline-header mb-3">
                  <h6 class="mb-0">Currently Working</h6>
                  <small class="text-muted">IN PROGRESS</small>
                </div>
                <p class="mb-0">Agent is currently checked in and working</p>
              </div>
            </li>
            @endif
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- Google Maps Script -->
  @if($log->check_in_lat && $log->check_in_long)
  <script>
    let map;
    
    function initMap() {
      const checkInLocation = { 
        lat: parseFloat('{{ $log->check_in_lat }}'), 
        lng: parseFloat('{{ $log->check_in_long }}') 
      };
      
      map = new google.maps.Map(document.getElementById('map'), {
        zoom: 15,
        center: checkInLocation,
      });

      // Check-in marker
      new google.maps.Marker({
        position: checkInLocation,
        map: map,
        title: 'Check In Location',
        label: 'IN',
        icon: {
          url: 'http://maps.google.com/mapfiles/ms/icons/green-dot.png'
        }
      });

      @if($log->check_out_lat && $log->check_out_long)
      const checkOutLocation = { 
        lat: parseFloat('{{ $log->check_out_lat }}'), 
        lng: parseFloat('{{ $log->check_out_long }}') 
      };

      // Check-out marker
      new google.maps.Marker({
        position: checkOutLocation,
        map: map,
        title: 'Check Out Location',
        label: 'OUT',
        icon: {
          url: 'http://maps.google.com/mapfiles/ms/icons/red-dot.png'
        }
      });

      // Draw line between check-in and check-out
      const path = new google.maps.Polyline({
        path: [checkInLocation, checkOutLocation],
        geodesic: true,
        strokeColor: '#696cff',
        strokeOpacity: 0.8,
        strokeWeight: 3,
        map: map
      });

      // Adjust map bounds to show both markers
      const bounds = new google.maps.LatLngBounds();
      bounds.extend(checkInLocation);
      bounds.extend(checkOutLocation);
      map.fitBounds(bounds);
      @endif
    }

    window.initMap = initMap;
  </script>
  <script async defer src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&callback=initMap"></script>
  @endif

@endsection
