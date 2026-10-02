@extends('layouts/layoutMaster')

@section('title', 'Chit Reports')

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-6">
  <div>
    <h4 class="mb-1">Chit Reports</h4>
    <p class="text-muted mb-0">Operational and financial reports for the complete chit portfolio.</p>
  </div>
  <a href="{{ route('chit.dashboard') }}" class="btn btn-outline-secondary">
    <i class="ri-arrow-left-line me-1"></i> Chit Dashboard
  </a>
</div>

<div class="row g-6">
  @foreach ($reports as $key => $report)
    <div class="col-md-6 col-xl-4">
      <div class="card h-100 card-border-shadow-primary">
        <div class="card-body d-flex flex-column">
          <div class="avatar mb-4">
            <span class="avatar-initial rounded-3 bg-label-primary">
              <i class="ri {{ $report['icon'] }} ri-24px"></i>
            </span>
          </div>
          <h5 class="mb-2">{{ $report['title'] }}</h5>
          <p class="text-muted flex-grow-1">{{ $report['description'] }}</p>
          <a href="{{ route('chit.reports.show', $key) }}" class="btn btn-primary">
            Open Report <i class="ri-arrow-right-line ms-1"></i>
          </a>
        </div>
      </div>
    </div>
  @endforeach
</div>
@endsection
