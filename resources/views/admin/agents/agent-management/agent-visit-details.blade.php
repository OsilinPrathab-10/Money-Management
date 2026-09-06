 @extends('layouts/layoutMaster')

@section('title', 'Agent - Visit Details')

@section('content')

<div class="d-flex flex-column flex-md-row flex-wrap align-items-start align-items-md-center justify-content-between gap-3 mb-6">
  <div>
    <h4 class="mb-0">Visit Details</h4>
    <p class="mb-0 text-muted">Visit ID: #{{ $visit->id }}</p>
  </div>
  <a href="{{ route('agent-management.view-visits', $agent->id) }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-4 ms-md-auto">
    <i class="icon-base ri ri-arrow-left-line"></i>
    <span>Back to Visits</span>
  </a>
</div>

<!-- Map Section -->
<div class="card mb-6">
  <div class="card-body p-0">
    @if($googleMapsKey)
      <div id="visitMap" style="height: 450px; width: 100%; border-radius: 0.375rem;"></div>
    @else
      <div class="d-flex align-items-center justify-content-center bg-lighter" style="height: 450px; border-radius: 0.375rem;">
        <div class="text-center">
          <i class="icon-base ri ri-map-off-line icon-48px text-muted mb-2"></i>
          <p class="mb-0 text-muted">Google Maps API Key not configured.</p>
        </div>
      </div>
    @endif
  </div>
</div>

<div class="row g-6">
  <!-- Visit Info -->
  <div class="col-xl-4 col-lg-5 col-md-5">
    <div class="card mb-6">
      <div class="card-header border-bottom">
        <h5 class="card-title mb-0">Visit Summary</h5>
      </div>
      <div class="card-body pt-4">
        <div class="d-flex flex-column gap-3">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">Status</span>
                @if($visit->ended_at)
                    <span class="badge bg-success">Completed</span>
                @else
                    <span class="badge bg-warning">IN PROGRESS</span>
                @endif
            </div>
            
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">Date</span>
                <span class="fw-medium">{{ $visit->started_at ? $visit->started_at->format('d-m-Y') : 'N/A' }}</span>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">Start Time</span>
                <span class="fw-medium">{{ $visit->started_at ? $visit->started_at->format('h:i A') : 'N/A' }}</span>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">End Time</span>
                <span class="fw-medium">{{ $visit->ended_at ? $visit->ended_at->format('h:i A') : '-' }}</span>
            </div>

             <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">Duration</span>
                <span class="fw-medium">
                    @if($visit->started_at && $visit->ended_at)
                        {{ $visit->started_at->diff($visit->ended_at)->format('%H:%I:%S') }}
                    @elseif($visit->started_at)
                        Ongoing
                    @else
                        N/A
                    @endif
                </span>
            </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Client & EMI Info -->
  <div class="col-xl-8 col-lg-7 col-md-7">
    @php
        $emi = $visit->emi;
        $client = $emi ? $emi->loanAccount->client : null;
    @endphp

    @if($client)
    <div class="card mb-6">
      <div class="card-header border-bottom">
        <h5 class="card-title mb-0">Client Information</h5>
      </div>
      <div class="card-body pt-4">
        <div class="row g-4">
          <div class="col-md-6">
             <div class="d-flex align-items-center">
                <div>
                   <h6 class="mb-0">{{ $client->client_name }}</h6>
                   <small class="text-muted">Client ID: #{{ $client->id }}</small>
                </div>
             </div>
          </div>
          <div class="col-md-6">
             <div class="d-flex flex-column">
                <span class="fw-medium">Contact Info</span>
                <span class="d-flex align-items-center mt-1"><i class="icon-base ri ri-phone-line me-2"></i> {{ $client->client_phone }}</span>
                @if($client->client_email)
                <span class="d-flex align-items-center mt-1"><i class="icon-base ri ri-mail-line me-2"></i> {{ $client->client_email }}</span>
                @endif
             </div>
          </div>
          <div class="col-12">
             <span class="fw-medium">Address</span>
             <p class="mb-0 mt-1 text-muted">{{ $client->address ?? 'N/A' }}</p>
          </div>
        </div>
      </div>
    </div>
    @endif

    @if($emi)
    <div class="card">
       <div class="card-header border-bottom">
          <h5 class="card-title mb-0">Assigned EMI Details</h5>
       </div>
       <div class="card-body pt-4">
          <div class="row g-4">
             <div class="col-md-4">
                <label class="form-label text-muted mb-1">Loan Account ID</label>
                <div class="fw-semibold">#{{ $emi->loan_account_id }}</div>
             </div>
             <div class="col-md-4">
                <label class="form-label text-muted mb-1">{{ (optional($emi->loanAccount)->loan_mode === 'interest_only') ? 'Cycle Interest' : 'EMI Amount' }}</label>
                <div class="fw-semibold">₹{{ number_format($emi->total_amount ?? $emi->emi_amount, 2) }}</div>
             </div>
              <div class="col-md-4">
                <label class="form-label text-muted mb-1">Due Date</label>
                <div class="fw-semibold">{{ \Carbon\Carbon::parse($emi->due_date)->format('d-m-Y') }}</div>
             </div>
             <div class="col-md-4">
                <label class="form-label text-muted mb-1">Penalty Amount</label>
                 <div class="fw-semibold text-danger">₹{{ number_format($emi->penalty_amount, 2) }}</div>
             </div>
             <div class="col-md-4">
                <label class="form-label text-muted mb-1">Total Amount</label>
                 <div class="fw-semibold">₹{{ number_format($emi->total_amount, 2) }}</div>
             </div>
             <div class="col-md-4">
                <label class="form-label text-muted mb-1">Status</label>
                 <div>
                    @if($emi->status == 'paid')
                        <span class="badge bg-label-success">Paid</span>
                    @elseif($emi->status == 'overdue')
                        <span class="badge bg-label-danger">Overdue</span>
                    @else
                        <span class="badge bg-label-warning">{{ ucfirst($emi->status) }}</span>
                    @endif
                 </div>
             </div>
          </div>
       </div>
    </div>
    @else
    <div class="alert alert-warning" role="alert">
       <h6 class="alert-heading mb-1">No Assigned EMI Found</h6>
       <span>This visit log is not directly linked to a specific EMI assignment.</span>
    </div>
    @endif

    <!-- Activity Log Section -->
    <div class="card mt-6">
      <div class="card-header border-bottom">
        <h5 class="card-title mb-0">Visit Activity Log</h5>
      </div>
      <div class="card-body pt-4">
        @if(isset($activities) && $activities->count() > 0)
            <ul class="timeline timeline-dashed mt-3">
                @foreach($activities as $activity)
                    <li class="timeline-item timeline-item-transparent pb-4 border-left-dashed">
                        <span class="timeline-point timeline-point-{{ $activity['color'] }}"></span>
                        <div class="timeline-event">
                            <div class="timeline-header mb-1">
                                <h6 class="mb-0">{{ $activity['activity_type'] }}</h6>
                                <small class="text-muted">{{ $activity['timestamp']->format('h:i A') }}</small>
                            </div>
                            <p class="mb-0">{{ $activity['description'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="text-center p-4">
                <i class="icon-base ri ri-file-list-3-line icon-48px text-muted mb-2"></i>
                <p class="mb-0 text-muted">No activity recorded during this visit.</p>
            </div>
        @endif
      </div>
    </div>
  </div>
</div>

@endsection

@section('page-script')
@if($googleMapsKey)
<script src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsKey }}&callback=initMap" async defer></script>
<script>
  function initMap() {
    const startLat = {{ $visit->start_latitude ?? 'null' }};
    const startLng = {{ $visit->start_longitude ?? 'null' }};
    const endLat = {{ $visit->end_latitude ?? 'null' }};
    const endLng = {{ $visit->end_longitude ?? 'null' }};

    let map;
    let bounds = new google.maps.LatLngBounds();
    let hasStart = startLat !== null && startLng !== null;
    let hasEnd = endLat !== null && endLng !== null;

    // Default center if no coords
    const defaultCenter = { lat: 20.5937, lng: 78.9629 }; // India center

    if (hasStart) {
        map = new google.maps.Map(document.getElementById("visitMap"), {
            zoom: 15,
            center: { lat: startLat, lng: startLng },
            mapId: "DEMO_MAP_ID", // Optional
        });
    } else if (hasEnd) {
         map = new google.maps.Map(document.getElementById("visitMap"), {
            zoom: 15,
            center: { lat: endLat, lng: endLng },
        });
    } else {
         map = new google.maps.Map(document.getElementById("visitMap"), {
            zoom: 5,
            center: defaultCenter,
        });
    }

    // Add Start Marker (Green)
    if (hasStart) {
        const startPos = { lat: startLat, lng: startLng };
        new google.maps.Marker({
            position: startPos,
            map: map,
            title: "Start Location",
            label: "S",
            icon: {
                url: "http://maps.google.com/mapfiles/ms/icons/green-dot.png"
            }
        });
        bounds.extend(startPos);
    }

    // Add End Marker (Red)
    if (hasEnd) {
        const endPos = { lat: endLat, lng: endLng };
        new google.maps.Marker({
            position: endPos,
            map: map,
            title: "End Location",
            label: "E",
            icon: {
                url: "http://maps.google.com/mapfiles/ms/icons/red-dot.png"
            }
        });
        bounds.extend(endPos);
    }

    // Draw Line
    if (hasStart && hasEnd) {
        const path = [
            { lat: startLat, lng: startLng },
            { lat: endLat, lng: endLng },
        ];
        const flightPath = new google.maps.Polyline({
            path: path,
            geodesic: true,
            strokeColor: "#FF0000",
            strokeOpacity: 1.0,
            strokeWeight: 2,
        });
        flightPath.setMap(map);
        
        // Fit bounds to show both markers
        map.fitBounds(bounds);
    }
  }
</script>
@endif
@endsection
