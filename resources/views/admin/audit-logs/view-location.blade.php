@extends('layouts/layoutMaster')

@section('title', 'Client Location')

@section('page-style')
<style>
  #map {
    height: 600px;
    width: 100%;
    border-radius: 0;
  }
</style>
@endsection

@section('content')

<!-- Back Button -->
<div class="mb-4">
  <a href="{{ route('audit-logs-activity-logs') }}" class="btn btn-sm btn-label-secondary">
    <i class="icon-base ri ri-arrow-left-line me-1"></i>
    Back to Activity Logs
  </a>
</div>

<!-- Page Title -->
<div class="mb-4">
  <h4 class="mb-1">Client Location Details</h4>
  <p class="text-muted mb-0">Last login location for {{ $locationData['client_name'] }}</p>
</div>

<!-- Client Information Card -->
<div class="card mb-6">
  <div class="card-header">
    <h5 class="mb-0">
      <i class="icon-base ri ri-user-location-line me-2"></i>
      Client Information
    </h5>
  </div>
  <div class="card-body">
    <div class="row g-5">
      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <input class="form-control" type="text" value="{{ $locationData['client_name'] }}" readonly />
          <label>Client Name</label>
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <input class="form-control" type="text" value="{{ $locationData['phone'] }}" readonly />
          <label>Mobile Number</label>
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <input class="form-control" type="text" value="{{ $locationData['device_name'] ?? $locationData['device_model'] ?? 'Unknown' }}" readonly />
          <label>Device</label>
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <input class="form-control" type="text" value="{{ $locationData['ip_address'] ?? 'N/A' }}" readonly />
          <label>IP Address</label>
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <input class="form-control" type="text" value="{{ $locationData['login_at'] }}" readonly />
          <label>Login Time</label>
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <input class="form-control" type="text" value="{{ $locationData['logout_at'] }}" readonly />
          <label>Logout Time</label>
        </div>
      </div>
      @if($locationData['latitude'] && $locationData['longitude'])
      <div class="col-12">
        <div class="alert alert-primary d-flex align-items-center" role="alert">
          <i class="icon-base ri ri-map-pin-2-line me-2"></i>
          <div>
            <strong>Coordinates:</strong> 
            Latitude: {{ number_format($locationData['latitude'], 6) }}, 
            Longitude: {{ number_format($locationData['longitude'], 6) }}
          </div>
        </div>
      </div>
      @endif
    </div>
  </div>
</div>

<!-- Map Card -->
<div class="card">
  <div class="card-header">
    <h5 class="mb-0">
      <i class="icon-base ri ri-map-2-line me-2"></i>
      Last Login Location
    </h5>
  </div>
  <div class="card-body p-0">
    @if($locationData['latitude'] && $locationData['longitude'])
      <div id="map"></div>
    @else
      <div class="text-center py-5">
        <i class="icon-base ri ri-map-pin-off-line text-muted" style="font-size: 64px;"></i>
        <p class="text-muted mt-3 mb-0">Location data not available</p>
      </div>
    @endif
  </div>
</div>

@endsection

@section('page-script')
@if($locationData['latitude'] && $locationData['longitude'] && $googleMapsApiKey)
<script>
  let map;
  let marker;

  function initMap() {
    const location = {
      lat: parseFloat({{ $locationData['latitude'] }}),
      lng: parseFloat({{ $locationData['longitude'] }})
    };

    // Initialize map
    map = new google.maps.Map(document.getElementById('map'), {
      center: location,
      zoom: 15,
      mapTypeControl: true,
      streetViewControl: true,
      fullscreenControl: true,
      zoomControl: true,
      styles: [
        {
          featureType: 'poi',
          elementType: 'labels',
          stylers: [{ visibility: 'on' }]
        }
      ]
    });

    // Add marker
    marker = new google.maps.Marker({
      position: location,
      map: map,
      title: 'Last Login Location',
      animation: google.maps.Animation.DROP,
      icon: {
        url: 'http://maps.google.com/mapfiles/ms/icons/red-dot.png'
      }
    });

    // Add info window
    const infoWindow = new google.maps.InfoWindow({
      content: `
        <div style="padding: 8px;">
          <h6 style="margin: 0 0 8px 0; color: #333;">Last Login Location</h6>
          <p style="margin: 0; font-size: 13px; color: #666;">
            <strong>{{ $locationData['client_name'] }}</strong><br>
            {{ $locationData['login_at'] }}
          </p>
        </div>
      `
    });

    marker.addListener('click', () => {
      infoWindow.open(map, marker);
    });

    // Open info window by default
    infoWindow.open(map, marker);
  }

  window.initMap = initMap;
</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&callback=initMap"></script>
@else
<script>
  console.error('Google Maps API key not configured or location data missing');
</script>
@endif
@endsection
