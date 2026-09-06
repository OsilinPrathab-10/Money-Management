@extends('layouts/layoutMaster')

@section('title', 'Agent - Assigned Client Details')

@section('content')

<div class="d-flex flex-column flex-md-row flex-wrap align-items-start align-items-md-center justify-content-between gap-3 mb-6">
  <div>
    <h4 class="mb-0">Assigned Client Details</h4>
    <p class="mb-0 text-muted">Viewing details for {{ $client->client_name }} assigned to {{ $agent->name }}</p>
  </div>
  <a href="{{ route('agent-management.view-work', $agent->id) }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-4 ms-md-auto">
    <i class="icon-base ri ri-arrow-left-line"></i>
    <span>Back to Work Info</span>
  </a>
</div>

<div class="row g-6">
  <!-- Client Profile -->
  <div class="col-xl-4 col-lg-5 col-md-5">
    <div class="card mb-6">
      <div class="card-body pt-12">
        <div class="user-avatar-section">
          <div class="d-flex align-items-center flex-column">
            <div class="user-info text-center">
              <h5 class="mb-2">{{ $client->client_name }}</h5>
              <span class="badge bg-label-primary mt-1">Client ID: #{{ $client->id }}</span>
            </div>
          </div>
        </div>
        
        <div class="d-flex justify-content-between flex-wrap my-6 py-3 border-top border-bottom gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="avatar p-2 rounded-3 bg-lighter">
                    <i class="icon-base ri ri-money-rupee-circle-line text-primary icon-24px"></i>
                </div>
                <div>
                    <h5 class="mb-0 text-primary">₹{{ number_format($assignments->sum('emi.total_amount'), 2) }}</h5>
                    <small>Total Due</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="avatar p-2 rounded-3 bg-lighter">
                    <i class="icon-base ri ri-file-list-3-line text-info icon-24px"></i>
                </div>
                <div>
                    <h5 class="mb-0 text-info">{{ $assignments->count() }}</h5>
                    <small>Assigned EMIs</small>
                </div>
            </div>
        </div>

        <div class="info-container">
          <ul class="list-unstyled mb-0">
            <li class="mb-3">
              <span class="fw-medium text-heading me-2">Phone:</span>
              <span>{{ $client->client_phone }}</span>
            </li>
            @if($client->client_email)
            <li class="mb-3">
              <span class="fw-medium text-heading me-2">Email:</span>
              <span>{{ $client->client_email }}</span>
            </li>
            @endif
             <li class="mb-3">
              <span class="fw-medium text-heading me-2">Location:</span>
              <span>{{ optional($client->location)->name ?? $client->city ?? 'N/A' }}</span>
            </li>
            <li class="mb-0">
              <span class="fw-medium text-heading me-2">Address:</span>
              <span class="d-block mt-1 text-muted">{{ $client->address ?? 'N/A' }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="col-xl-8 col-lg-7 col-md-7">
    <div class="nav-align-top mb-6">
      <ul class="nav nav-pills mb-4" role="tablist">
        <li class="nav-item">
          <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-assignments" aria-controls="navs-assignments" aria-selected="true">
            <i class="icon-base ri ri-file-list-line me-1"></i> Assigned EMIs
          </button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-history" aria-controls="navs-history" aria-selected="false">
             <i class="icon-base ri ri-history-line me-1"></i> Engagement History
          </button>
        </li>
      </ul>
      <div class="tab-content shadow-none p-0 bg-transparent border-0">
        
        <!-- Assignments Tab -->
        <div class="tab-pane fade show active" id="navs-assignments" role="tabpanel">
           @if($assignments->count() > 0)
            @foreach($assignments as $assignment)
                @php $emi = $assignment->emi; @endphp
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h6 class="mb-1">EMI #{{ $emi->id }}</h6>
                                <small class="text-muted">Loan Account: <span class="fw-medium text-heading">#{{ $emi->loanAccount->account_number }}</span></small>
                            </div>
                            @if($emi->status == 'paid')
                                <span class="badge bg-success">Paid</span>
                            @elseif($emi->status == 'overdue')
                                <span class="badge bg-danger">Overdue</span>
                            @else
                                <span class="badge bg-warning">{{ ucfirst($emi->status) }}</span>
                            @endif
                        </div>
                        
                        <div class="row g-4">
                             <div class="col-sm-4">
                                <div class="d-flex flex-column">
                                    <small class="text-muted mb-1">Due Amount</small>
                                    <h6 class="mb-0">₹{{ number_format($emi->total_amount, 2) }}</h6>
                                </div>
                             </div>
                             <div class="col-sm-4">
                                <div class="d-flex flex-column">
                                    <small class="text-muted mb-1">Due Date</small>
                                    <h6 class="mb-0">{{ $emi->due_date ? $emi->due_date->format('d-m-Y') : 'N/A' }}</h6>
                                </div>
                             </div>
                             <div class="col-sm-4">
                                <div class="d-flex flex-column">
                                    <small class="text-muted mb-1">Pending Amount</small>
                                    <h6 class="mb-0 {{ $emi->pending_amount > 0 ? 'text-danger' : 'text-success' }}">₹{{ number_format($emi->pending_amount, 2) }}</h6>
                                </div>
                             </div>
                        </div>
                    </div>
                </div>
            @endforeach
           @else
            <div class="card">
                <div class="card-body text-center p-6">
                    <p class="mb-0">No active assignments found for this client.</p>
                </div>
            </div>
           @endif
        </div>

        <!-- History Tab -->
        <div class="tab-pane fade" id="navs-history" role="tabpanel">
           <div class="card">
             <div class="card-body">
               @if($history->count() > 0)
                <ul class="timeline timeline-dashed mt-3">
                    @foreach($history as $item)
                        <li class="timeline-item timeline-item-transparent pb-4 border-left-dashed">
                            <span class="timeline-point timeline-point-{{ $item['color'] }}"></span>
                            <div class="timeline-event">
                                <div class="timeline-header mb-1">
                                    <h6 class="mb-0">{{ $item['title'] }}</h6>
                                    <small class="text-muted">{{ $item['timestamp']->format('d-m-Y h:i A') }}</small>
                                </div>
                                <p class="mb-0">{{ $item['description'] }}</p>
                                @if(isset($item['link']) && $item['link'])
                                    <a href="{{ $item['link'] }}" class="btn btn-sm btn-outline-primary mt-2">View Details</a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
               @else
                <div class="text-center p-4">
                    <i class="icon-base ri ri-history-line icon-48px text-muted mb-2"></i>
                    <p class="mb-0 text-muted">No interaction history found.</p>
                </div>
               @endif
             </div>
           </div>
        </div>

      </div>
    </div>
  </div>
</div>

@endsection
