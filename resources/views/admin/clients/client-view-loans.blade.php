@extends('layouts/layoutMaster')

@section('title', 'Client View - Loans')

@section('vendor-style')
@vite([
'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
'resources/assets/vendor/libs/animate-css/animate.scss',
'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('vendor-script')
@vite([
'resources/assets/vendor/libs/moment/moment.js',
'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/client-view-loans.js', 'resources/assets/custom-js/emi-calculator.js'])
@endsection

@section('content')

<div class="row">
  <div class="col-12">
    <!-- User Tabs -->
    <div class="d-flex flex-column flex-md-row flex-wrap align-items-start align-items-md-center justify-content-between gap-3 mb-6">
      <div class="nav-align-top w-100 w-md-auto">
        <ul class="nav nav-pills flex-column flex-md-row row-gap-2">
          <li class="nav-item"><a class="nav-link" href="{{ url('/clients/view/account/'.$client->id) }}"><i class="icon-base ri ri-user-3-line me-1_5"></i>Account</a></li>
          @if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'))
          <li class="nav-item"><a class="nav-link" href="{{ url('/clients/view/kyc/'.$client->id) }}"><i class="icon-base ri ri-shield-check-line me-1_5"></i>KYC</a></li>
          @endif
          <li class="nav-item"><a class="nav-link active" href="javascript:void(0);"><i class="icon-base ri ri-file-list-3-line me-1_5"></i>Loans</a></li>
        </ul>
      </div>
      <div class="d-flex gap-2 w-100 w-sm-auto ms-md-auto">
          <!-- <a href="{{ route('client-management-add') }}" class="btn btn-sm btn-outline-primary w-sm-auto d-inline-flex align-items-center justify-content-center">
            <i class="icon-base ri ri-user-add-line me-1"></i>
            <span>Add Client</span>
          </a> -->
        <a href="{{ route('client-management') }}" class="btn btn-sm btn-outline-secondary w-sm-auto d-inline-flex align-items-center justify-content-center">
          <i class="icon-base ri ri-arrow-left-line me-1"></i>
          <span>Back to Clients</span>
        </a>
      </div>
    </div>

    <!-- Loan Applications Card -->
    <div class="card mb-6">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0"><i class="icon-base ri ri-file-text-line me-2 text-primary"></i>Loan Applications</h5>
        <span class="badge bg-label-primary rounded-pill">{{ $loanApplications->count() }} Total</span>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>S.No</th>
                <th>Application No.</th>
                <th>Product</th>
                <th>Amount</th>
                <th>Tenure</th>
                <th>Status</th>
                <th>Applied On</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($loanApplications as $index => $app)
              @php
                $statusMap = [
                  'pending' => ['badge' => 'warning', 'icon' => 'ri-time-line'],
                  'approved' => ['badge' => 'info', 'icon' => 'ri-checkbox-circle-line'],
                  'process' => ['badge' => 'primary', 'icon' => 'ri-loader-4-line'],
                  'disbursed' => ['badge' => 'success', 'icon' => 'ri-check-double-line'],
                  'rejected' => ['badge' => 'danger', 'icon' => 'ri-close-circle-line'],
                ];
                $status = $statusMap[$app->status] ?? ['badge' => 'secondary', 'icon' => 'ri-question-line'];
              @endphp
              <tr>
                <td>{{ $index + 1 }}</td>
                <td><span class="fw-semibold">{{ $app->application_number }}</span></td>
                <td>{{ optional($app->product)->loan_name ?? 'N/A' }}</td>
                <td>
                  @if($app->loan_amount && $app->loan_amount > 0)
                    ₹{{ number_format($app->loan_amount, 0) }}
                  @else
                    <span class="text-muted">Credit: ₹{{ number_format(optional($app->product)->loan_amount_max ?? 0, 0) }}</span>
                  @endif
                </td>
                <td>
                  @php
                    $unit = $app->term_unit ?: optional($app->product)->term_unit ?: 'months';
                    $unitLabel = in_array(strtolower($unit), ['days', 'day', 'daily']) ? 'days' : (in_array(strtolower($unit), ['weeks', 'week', 'weekly']) ? 'weeks' : 'months');
                  @endphp
                  {{ $app->tenure ?? 'N/A' }} {{ $unitLabel }}
                </td>
                <td>
                  <span class="badge bg-label-{{ $status['badge'] }}">
                    <i class="icon-base {{ $status['icon'] }} me-1"></i>{{ ucfirst($app->status) }}
                  </span>
                </td>
                <td>{{ $app->created_at->format('d-m-Y') }}</td>
                <td>
                  <a href="{{ route('loan-application-view', $app->getRouteKey()) }}" class="btn btn-sm btn-outline-primary">
                    <i class="icon-base ri ri-eye-line me-1"></i>View
                  </a>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="8" class="text-center text-muted py-6">
                  <i class="icon-base ri ri-file-search-line" style="font-size: 2rem;"></i>
                  <p class="mb-0 mt-2">No loan applications found</p>
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Loan Accounts (Active/Closed) Card -->
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0"><i class="icon-base ri ri-bank-line me-2 text-success"></i>Loan Accounts</h5>
        <span class="badge bg-label-success rounded-pill">{{ $loanAccounts->count() }} Active</span>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table id="loanHistoryTable" class="table table-hover" data-has-rows="{{ $loanAccounts->isNotEmpty() ? 'true' : 'false' }}">
            <thead>
              <tr>
                <th>S.No</th>
                <th>Account Number</th>
                <th>Loan Amount</th>
                <th>Outstanding</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($loanAccounts as $index => $account)
              @php
                $acctStatusMap = [
                  'active' => 'success',
                  'closed' => 'info',
                  'overdue' => 'danger',
                  'defaulted' => 'dark',
                ];
              @endphp
              <tr>
                <td>{{ $index + 1 }}</td>
                <td><span class="fw-semibold">{{ $account->account_number }}</span></td>
                <td>₹{{ number_format($account->loan_amount, 0) }}</td>
                <td>₹{{ number_format($account->outstanding_amount ?? 0, 0) }}</td>
                <td>
                  <span class="badge bg-label-{{ $acctStatusMap[$account->status] ?? 'secondary' }}">
                    {{ ucfirst($account->status) }}
                  </span>
                </td>
                <td>
                  <a href="{{ route('client-loan-emi-details', $account->id) }}" class="btn btn-sm btn-primary">
                    <i class="icon-base ri ri-eye-line me-1"></i>EMI Details
                  </a>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="6" class="text-center text-muted py-6">
                  <i class="icon-base ri ri-bank-line" style="font-size: 2rem;"></i>
                  <p class="mb-0 mt-2">No loan accounts yet. Loans appear here after disbursement.</p>
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
