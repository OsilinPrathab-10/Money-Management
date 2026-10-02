@extends('layouts/layoutMaster')

@section('title', 'Client View - Loans')

@section('vendor-style')
@vite([
'resources/assets/vendor/libs/animate-css/animate.scss',
'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('vendor-script')
@vite([
'resources/assets/vendor/libs/moment/moment.js',
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
          <li class="nav-item"><a class="nav-link" href="{{ url('/client/view/chits/'.$client->id) }}"><i class="icon-base ri ri-group-2-line me-1_5"></i>Chits</a></li>
          <li class="nav-item"><a class="nav-link" href="{{ url('/clients/view/ledger/'.$client->id) }}"><i class="icon-base ri ri-wallet-3-line me-1_5"></i>Ledger</a></li>
          <li class="nav-item"><a class="nav-link" href="{{ url('/clients/view/notifications/'.$client->id) }}"><i class="icon-base ri ri-notification-3-line me-1_5"></i>Notifications</a></li>
        </ul>
      </div>
      <div class="d-flex gap-2 w-100 w-sm-auto ms-md-auto">
          <!-- <a href="{{ route('client-management-add') }}" class="btn btn-sm btn-outline-primary w-sm-auto d-inline-flex align-items-center justify-content-center">
            <i class="icon-base ri ri-user-add-line me-1"></i>
            <span>Add Client</span>
          </a> -->
        <a href="{{ route('client-management') }}" class="btn btn-xs btn-outline-secondary px-2.5 py-1 d-inline-flex align-items-center" style="font-size: 0.78rem;">
          <i class="icon-base ri ri-arrow-left-line me-1" style="font-size: 0.85rem;"></i>
          <span>Back to Clients</span>
        </a>
      </div>
    </div>

    <!-- Filter Toolbar -->
    <div class="card mb-4 shadow-sm border">
      <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
          <div class="d-flex align-items-center gap-2">
            <i class="icon-base ri ri-filter-3-line text-primary fs-5"></i>
            <span class="fw-semibold text-heading">Filter Client Loans:</span>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
              <label for="clientLoanModeFilter" class="form-label mb-0 text-nowrap small fw-medium">Loan Mode:</label>
              <select id="clientLoanModeFilter" class="form-select form-select-sm no-search" style="width: 130px;">
                <option value="">All Modes</option>
                <option value="emi">Standard EMI</option>
                <option value="interest_only">Open Loan</option>
              </select>
            </div>
            <div class="d-flex align-items-center gap-2">
              <label for="clientLoanTypeFilter" class="form-label mb-0 text-nowrap small fw-medium">Loan Type:</label>
              <select id="clientLoanTypeFilter" class="form-select form-select-sm no-search" style="min-width: 160px; max-width: 220px;">
                <option value="">All Loan Types</option>
                @if(isset($loanTypes))
                  @foreach($loanTypes as $lt)
                    <option value="{{ $lt->id }}">{{ trim($lt->name) }}</option>
                  @endforeach
                @endif
              </select>
            </div>
            <button type="button" id="resetClientLoanFilters" class="btn btn-xs btn-outline-secondary px-2.5 py-1 d-inline-flex align-items-center">
              <i class="icon-base ri ri-refresh-line me-1"></i>Reset
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Loan Applications Card -->
    <div class="card mb-6">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0"><i class="icon-base ri ri-file-text-line me-2 text-primary"></i>Loan Applications</h5>
        <span class="badge bg-label-primary rounded-pill" id="badgeTotalApplications">{{ $loanApplications->count() }} Total</span>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover" id="clientLoanApplicationsTable">
            <thead>
              <tr>
                <th>S.No</th>
                <th>Application No.</th>
                <th>Product / Type</th>
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
                  'applied' => ['badge' => 'warning', 'icon' => 'ri-time-line'],
                  'approved' => ['badge' => 'info', 'icon' => 'ri-checkbox-circle-line'],
                  'process' => ['badge' => 'primary', 'icon' => 'ri-loader-4-line'],
                  'disbursed' => ['badge' => 'success', 'icon' => 'ri-check-double-line'],
                  'rejected' => ['badge' => 'danger', 'icon' => 'ri-close-circle-line'],
                ];
                $status = $statusMap[$app->status] ?? ['badge' => 'secondary', 'icon' => 'ri-question-line'];
                $appLoanMode = $app->loan_mode ?? 'emi';
                $appLoanTypeId = optional($app->product)->loan_type_id ?? '';
                $appLoanTypeName = optional(optional($app->product)->loanType)->name;
              @endphp
              <tr class="client-loan-app-row"
                  data-loan-mode="{{ $appLoanMode }}"
                  data-loan-type-id="{{ $appLoanTypeId }}">
                <td class="app-row-sno">{{ $index + 1 }}</td>
                <td>
                  <span class="fw-semibold">{{ $app->application_number }}</span>
                  <span class="badge bg-label-{{ $appLoanMode === 'interest_only' ? 'info' : 'primary' }} ms-1" style="font-size: 0.72rem;">
                    {{ $appLoanMode === 'interest_only' ? 'Open Loan' : 'EMI' }}
                  </span>
                </td>
                <td>
                  <span class="fw-medium">{{ optional($app->product)->loan_name ?? 'N/A' }}</span>
                  @if($appLoanTypeName)
                    <small class="text-muted d-block" style="font-size: 0.78rem;">{{ $appLoanTypeName }}</small>
                  @endif
                </td>
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
                <td>{{ $app->applied_date->format('d-m-Y') }}</td>
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
              <tr class="client-filter-empty-app d-none">
                <td colspan="8" class="text-center text-muted py-4">No loan applications match the selected filter.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Loan Accounts Card -->
    <div class="card mb-4">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0"><i class="icon-base ri ri-bank-line me-2 text-success"></i>Active Loan Accounts</h5>
        <span class="badge bg-label-success rounded-pill" id="badgeActiveLoans">{{ $loanAccounts->where('status', '!=', 'closed')->count() }} Active</span>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table id="loanHistoryTable" class="table table-hover">
            <thead>
              <tr>
                <th>S.No</th>
                <th>Account Number</th>
                <th>Loan Type / Product</th>
                <th>Loan Amount</th>
                <th>Outstanding</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @php $activeLoans = $loanAccounts->where('status', '!=', 'closed')->values(); @endphp
              @forelse($activeLoans as $index => $account)
              @php
                $acctStatusMap = [
                  'active' => 'success',
                  'overdue' => 'danger',
                  'defaulted' => 'dark',
                ];
                $accProduct = $account->product ?? optional($account->loanApplication)->product;
                $accLoanMode = $account->loan_mode ?? optional($account->loanApplication)->loan_mode ?? 'emi';
                $accLoanTypeId = optional($accProduct)->loan_type_id ?? '';
                $accLoanTypeName = optional(optional($accProduct)->loanType)->name;
              @endphp
              <tr class="client-loan-acc-row"
                  data-loan-mode="{{ $accLoanMode }}"
                  data-loan-type-id="{{ $accLoanTypeId }}">
                <td class="acc-row-sno">{{ $index + 1 }}</td>
                <td>
                  <span class="fw-semibold">{{ $account->account_number }}</span>
                  <span class="badge bg-label-{{ $accLoanMode === 'interest_only' ? 'info' : 'primary' }} ms-1" style="font-size: 0.72rem;">
                    {{ $accLoanMode === 'interest_only' ? 'Open Loan' : 'EMI' }}
                  </span>
                </td>
                <td>
                  <span class="fw-medium">{{ optional($accProduct)->loan_name ?? 'N/A' }}</span>
                  @if($accLoanTypeName)
                    <small class="text-muted d-block" style="font-size: 0.78rem;">{{ $accLoanTypeName }}</small>
                  @endif
                </td>
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
                <td colspan="7" class="text-center text-muted py-6">
                  <i class="icon-base ri ri-bank-line" style="font-size: 2rem;"></i>
                  <p class="mb-0 mt-2">No active loan accounts.</p>
                </td>
              </tr>
              @endforelse
              <tr class="client-filter-empty-acc d-none">
                <td colspan="7" class="text-center text-muted py-4">No active loan accounts match the selected filter.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    @php $closedLoans = $loanAccounts->where('status', 'closed')->values(); @endphp
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0"><i class="icon-base ri ri-checkbox-circle-line me-2 text-info"></i>Closed Loan Details</h5>
        <span class="badge bg-label-info rounded-pill" id="badgeClosedLoans">{{ $closedLoans->count() }} Closed</span>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover" id="clientClosedLoansTable">
            <thead>
              <tr>
                <th>S.No</th>
                <th>Account Number</th>
                <th>Loan Type / Product</th>
                <th>Loan Amount</th>
                <th>Paid Amount</th>
                <th>Closed At</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($closedLoans as $index => $account)
              @php
                $clsProduct = $account->product ?? optional($account->loanApplication)->product;
                $clsLoanMode = $account->loan_mode ?? optional($account->loanApplication)->loan_mode ?? 'emi';
                $clsLoanTypeId = optional($clsProduct)->loan_type_id ?? '';
                $clsLoanTypeName = optional(optional($clsProduct)->loanType)->name;
              @endphp
              <tr class="client-closed-acc-row"
                  data-loan-mode="{{ $clsLoanMode }}"
                  data-loan-type-id="{{ $clsLoanTypeId }}">
                <td class="closed-row-sno">{{ $index + 1 }}</td>
                <td>
                  <span class="fw-semibold">{{ $account->account_number }}</span>
                  <span class="badge bg-label-{{ $clsLoanMode === 'interest_only' ? 'info' : 'primary' }} ms-1" style="font-size: 0.72rem;">
                    {{ $clsLoanMode === 'interest_only' ? 'Open Loan' : 'EMI' }}
                  </span>
                </td>
                <td>
                  <span class="fw-medium">{{ optional($clsProduct)->loan_name ?? 'N/A' }}</span>
                  @if($clsLoanTypeName)
                    <small class="text-muted d-block" style="font-size: 0.78rem;">{{ $clsLoanTypeName }}</small>
                  @endif
                </td>
                <td>₹{{ number_format($account->loan_amount, 0) }}</td>
                <td class="text-success">₹{{ number_format($account->paid_amount ?? 0, 0) }}</td>
                <td>{{ $account->closed_at ? \Carbon\Carbon::parse($account->closed_at)->format('d M Y') : '—' }}</td>
                <td>
                  <a href="{{ route('client-loan-emi-details', $account->id) }}" class="btn btn-sm btn-outline-primary">
                    <i class="icon-base ri ri-eye-line me-1"></i>Details
                  </a>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="7" class="text-center text-muted py-4">No closed loans for this client.</td>
              </tr>
              @endforelse
              <tr class="client-filter-empty-closed d-none">
                <td colspan="7" class="text-center text-muted py-4">No closed loans match the selected filter.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
