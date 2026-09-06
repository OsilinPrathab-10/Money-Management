@extends('layouts/layoutMaster')

@section('title', 'Agent Dashboard')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/apex-charts/apex-charts.scss',
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
  ])
@endsection

@section('page-style')
<style>
  .card-stats {
    transition: all 0.3s ease;
    border: none;
    border-radius: 15px;
  }
  .card-stats:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
  }
  .icon-box {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
  }
  .premium-gradient {
    background: linear-gradient(135deg, #666cff 0%, #a2a7ff 100%);
    color: white;
  }
  .table-premium thead th {
    background-color: #f8f9fa;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 1px;
    font-weight: 700;
  }
  .status-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
  }
</style>
@endsection

@section('content')
<div class="row g-4 mb-4">
  <div class="col-12">
    <div class="card premium-gradient p-4 border-0 shadow-sm">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="text-white mb-1">Welcome back, {{ auth()->user()->name }}!</h3>
          <p class="mb-0 opacity-75">Here's what's happening with your clients today.</p>
        </div>
        <div class="d-none d-md-block">
          <i class="ri-user-star-line ri-4x opacity-25"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="col-sm-6 col-xl-3">
    <div class="card card-stats shadow-sm">
      <div class="card-body">
        <div class="d-flex align-items-center">
          <div class="icon-box bg-label-primary me-3">
            <i class="ri-user-add-line ri-24px"></i>
          </div>
          <div>
            <h4 class="mb-0">{{ $stats['total_clients'] }}</h4>
            <small class="text-muted">Clients Added</small>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card card-stats shadow-sm">
      <div class="card-body">
        <div class="d-flex align-items-center">
          <div class="icon-box bg-label-success me-3">
            <i class="ri-hand-coin-line ri-24px"></i>
          </div>
          <div>
            <h4 class="mb-0">{{ $stats['active_loans'] }}</h4>
            <small class="text-muted">Active Loans</small>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card card-stats shadow-sm">
      <div class="card-body">
        <div class="d-flex align-items-center">
          <div class="icon-box bg-label-warning me-3">
            <i class="ri-calendar-todo-line ri-24px"></i>
          </div>
          <div>
            <h4 class="mb-0">{{ $stats['pending_followups'] }}</h4>
            <small class="text-muted">Pending Followups</small>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card card-stats shadow-sm">
      <div class="card-body">
        <div class="d-flex align-items-center">
          <div class="icon-box bg-label-danger me-3">
            <i class="ri-error-warning-line ri-24px"></i>
          </div>
          <div>
            <h4 class="mb-0">{{ $stats['overdue_emis'] }}</h4>
            <small class="text-muted">Overdue & Upcoming EMIs</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Upcoming Follow-ups -->
  <div class="col-12 col-xl-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom py-3">
        <h5 class="mb-0 fw-bold"><i class="ri-notification-3-line me-2 text-primary"></i>Upcoming Follow-ups</h5>
        <a href="{{ route('agent-collections') }}" class="btn btn-sm btn-primary">View All Collections</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-premium mb-0">
          <thead>
            <tr>
              <th>Client</th>
              <th>Loan Code</th>
              <th>EMI Due Date</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($upcomingFollowups as $followup)
            <tr>
              <td>
                <div class="d-flex align-items-center">
                  <div class="avatar avatar-sm me-2">
                    <span class="avatar-initial rounded-circle bg-label-primary">{{ substr($followup->emi->loanAccount->client->client_name, 0, 1) }}</span>
                  </div>
                  <div>
                    <div class="fw-bold text-heading">{{ $followup->emi->loanAccount->client->client_name }}</div>
                    <small class="text-muted">{{ $followup->emi->loanAccount->client->client_phone }}</small>
                  </div>
                </div>
              </td>
              <td><span class="badge bg-label-secondary">{{ $followup->emi->loanAccount->loan_code }}</span></td>
              <td>
                <div class="fw-semibold">{{ \Carbon\Carbon::parse($followup->emi->due_date)->format('d-m-Y') }}</div>
                @if(\Carbon\Carbon::parse($followup->emi->due_date)->isToday())
                  <small class="text-danger fw-bold">Due Today</small>
                @else
                  <small class="text-muted">{{ \Carbon\Carbon::parse($followup->emi->due_date)->diffForHumans() }}</small>
                @endif
              </td>
              <td class="fw-bold text-heading">₹{{ number_format($followup->emi->total_amount, 2) }}</td>
              <td>
                <span class="badge bg-label-{{ $followup->status == 'assigned' ? 'warning' : 'info' }} status-badge">
                  {{ ucfirst($followup->status) }}
                </span>
              </td>
              <td>
                <a href="{{ route('agent-collections') }}?emi_id={{ $followup->emi->id }}" class="btn btn-sm btn-icon btn-label-primary rounded-pill">
                  <i class="ri-money-dollar-circle-line"></i>
                </a>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="6" class="text-center py-5">
                <div class="text-muted">
                  <i class="ri-inbox-line ri-3x mb-3 d-block opacity-25"></i>
                  No upcoming follow-ups for the next 7 days.
                </div>
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Recent Clients -->
  <div class="col-12 col-xl-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom py-3">
        <h5 class="mb-0 fw-bold"><i class="ri-user-add-line me-2 text-success"></i>Recently Added Clients</h5>
        <a href="{{ route('client-management') }}" class="btn btn-sm btn-label-success">View All</a>
      </div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          @forelse($recentClients as $client)
          <li class="list-group-item border-0 border-bottom p-3">
            <div class="d-flex align-items-center">
              <div class="avatar me-3">
                <img src="{{ $client->profile_image_url }}" alt="Avatar" class="rounded-circle">
              </div>
              <div class="flex-grow-1">
                <div class="fw-bold text-heading">{{ $client->client_name }}</div>
                <small class="text-muted">{{ $client->client_phone }}</small>
              </div>
              <div>
                <span class="badge bg-label-{{ $client->status == 'active' ? 'success' : 'warning' }}">
                  {{ ucfirst($client->status) }}
                </span>
              </div>
            </div>
          </li>
          @empty
          <li class="list-group-item text-center py-5">
            <small class="text-muted">No clients added yet.</small>
          </li>
          @endforelse
        </ul>
      </div>
      <div class="card-footer bg-light bg-opacity-50 border-0 p-3">
        <a href="{{ route('client-management-add') }}" class="btn btn-primary w-100 shadow-sm">
          <i class="ri-user-add-line me-2"></i>Add New Client
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
