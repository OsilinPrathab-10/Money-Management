@php
  $isMenu = false;
  $navbarFull = true;
@endphp
@extends('layouts/layoutMaster')

@section('title', 'My Dashboard')

@section('content')
<div class="row g-6">
    <!-- Welcome Card -->
    <div class="col-12">
        <div class="card bg-primary text-white shadow-none border-0 overflow-hidden">
            <div class="card-body p-8">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h2 class="text-white mb-1">Welcome back, {{ $client->client_name }}! 👋</h2>
                        <p class="mb-0 opacity-75">Check your loan status and repayment schedule below.</p>
                    </div>
                    <div class="d-none d-md-block">
                        <img src="{{ asset('assets/img/illustrations/rocket.png') }}" alt="Rocket" width="100">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100 card-border-shadow-primary">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="avatar me-4 bg-label-primary rounded p-2">
                        <i class="ri-money-rupee-circle-line ri-24px"></i>
                    </div>
                    <h4 class="mb-0">₹{{ number_format($stats['total_loand_amount'], 2) }}</h4>
                </div>
                <p class="mb-0 text-muted">Total Loan Amount</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100 card-border-shadow-danger">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="avatar me-4 bg-label-danger rounded p-2">
                        <i class="ri-bank-card-line ri-24px"></i>
                    </div>
                    <h4 class="mb-0 text-danger">₹{{ number_format($stats['total_outstanding'], 2) }}</h4>
                </div>
                <p class="mb-0 text-muted">Current Outstanding</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100 card-border-shadow-warning">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="avatar me-4 bg-label-warning rounded p-2">
                        <i class="ri-calendar-event-line ri-24px"></i>
                    </div>
                    @if($stats['next_payment'])
                        <h4 class="mb-0">{{ $stats['next_payment']->due_date->format('d-m-Y') }}</h4>
                    @else
                        <h4 class="mb-0">No Dues</h4>
                    @endif
                </div>
                <p class="mb-0 text-muted">Next Due Date</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100 card-border-shadow-info">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="avatar me-4 bg-label-info rounded p-2">
                        <i class="ri-file-list-3-line ri-24px"></i>
                    </div>
                    <h4 class="mb-0">{{ count($activeLoans) }}</h4>
                </div>
                <p class="mb-0 text-muted">Active Loans</p>
            </div>
        </div>
    </div>

    <!-- Active Loans Table -->
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0">My Active Loans</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="bg-light">
                        <tr>
                            <th>A/C Number</th>
                            <th>Loan Product</th>
                            <th>Amount</th>
                            <th>Tenure</th>
                            <th>Outstanding</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activeLoans as $loan)
                        <tr>
                            <td><span class="fw-bold text-primary">{{ $loan->account_number }}</span></td>
                            <td>{{ optional($loan->loanApplication->product)->loan_name ?? 'N/A' }}</td>
                            <td>₹{{ number_format($loan->loan_amount, 2) }}</td>
                            <td>{{ $loan->tenure }} {{ $loan->tenure_type }}</td>
                            <td class="text-danger">₹{{ number_format($loan->outstanding_amount, 2) }}</td>
                            <td><span class="badge bg-label-success">Active</span></td>
                            <td>
                                <a href="{{ route('client.loan-view', $loan->id) }}" class="btn btn-sm btn-outline-primary">
                                    View Schedule
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <p class="text-muted mb-0">No active loans found.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Loan Applications Table -->
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">My Loan Applications</h5>
                <span class="badge bg-label-secondary">{{ count($loanApplications) }} Total Applications</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="bg-light">
                        <tr>
                            <th>App Number</th>
                            <th>Loan Product</th>
                            <th>Requested Amount</th>
                            <th>Status</th>
                            <th>Applied On</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loanApplications as $app)
                        <tr>
                            <td><span class="fw-bold text-info">{{ $app->application_number }}</span></td>
                            <td>{{ optional($app->product)->loan_name ?? 'N/A' }}</td>
                            <td>₹{{ number_format($app->loan_amount, 2) }}</td>
                            <td>
                                @php
                                    $statusClass = match(strtolower($app->status)) {
                                        'pending' => 'bg-label-warning',
                                        'approved' => 'bg-label-info',
                                        'disbursed' => 'bg-label-success',
                                        'rejected' => 'bg-label-danger',
                                        default => 'bg-label-secondary'
                                    };
                                @endphp
                                <span class="badge {{ $statusClass }}">{{ ucfirst($app->status) }}</span>
                            </td>
                            <td>{{ $app->created_at->format('d-m-Y') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <p class="text-muted mb-0">No loan applications found.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
