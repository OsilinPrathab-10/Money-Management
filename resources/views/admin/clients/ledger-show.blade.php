@extends('layouts/layoutMaster')

@section('title', 'Client Ledger - ' . $client->client_name)

@section('page-style')
<style>
  .ledger-in {
    background-color: rgba(40, 199, 111, 0.16) !important;
    color: #28c76f !important;
  }
  .ledger-out {
    background-color: rgba(234, 84, 85, 0.16) !important;
    color: #ea5455 !important;
  }
  .cibil-excellent {
    color: #28c76f;
    font-weight: bold;
  }
  .cibil-good {
    color: #ff9f43;
    font-weight: bold;
  }
  .cibil-poor {
    color: #ea5455;
    font-weight: bold;
  }
  .avatar-xl {
    width: 100px;
    height: 100px;
    font-size: 2.5rem;
  }
  .tab-content {
    background: transparent;
    padding: 0;
    box-shadow: none;
  }
  .nav-tabs .nav-link.active {
    border-bottom: 3px solid var(--bs-primary) !important;
    background-color: transparent !important;
  }
  .table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
  }
</style>
@endsection

@section('content')
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center gap-2">
      <a href="{{ route('admin-client-ledgers') }}" class="btn btn-outline-secondary btn-sm p-1 rounded-circle">
        <i class="ri-arrow-left-line ri-20px"></i>
      </a>
      <h4 class="mb-0 fw-bold">Consolidated Client Ledger</h4>
    </div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin-client-ledgers') }}">Client Ledgers</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $client->client_name }}</li>
      </ol>
    </nav>
  </div>

  <!-- Client Header Card -->
  <div class="row g-4 mb-4">
    <div class="col-12 col-xl-4">
      <div class="card h-100 shadow-sm border-0">
        <div class="card-body text-center d-flex flex-column align-items-center justify-content-center py-5">
          <div class="position-relative mb-3">
            <img src="{{ $client->profile_image_url }}" alt="Profile Image" class="rounded-circle avatar-xl object-fit-cover img-thumbnail shadow-sm">
            <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-white rounded-circle"></span>
          </div>
          <h4 class="fw-bold mb-1">{{ $client->client_name }}</h4>
          <span class="badge bg-label-primary mb-3">Customer ID: {{ $client->displayCustomerId() }}</span>
          
          <div class="w-100 mt-3 pt-3 border-top text-start px-3">
            <div class="row g-2 mb-2">
              <div class="col-5 text-muted small">Status:</div>
              <div class="col-7 fw-semibold">
                @php
                  $status = strtolower($client->status ?? 'inactive');
                  $color = match($status) {
                    'active', 'verified' => 'success',
                    'pending' => 'warning',
                    'blacklist' => 'danger',
                    default => 'secondary'
                  };
                @endphp
                <span class="badge bg-label-{{ $color }} btn-sm px-2 py-0 text-capitalize">{{ $status }}</span>
              </div>
            </div>
            <div class="row g-2 mb-2">
              <div class="col-5 text-muted small">Email:</div>
              <div class="col-7 text-truncate" title="{{ $client->client_email }}">{{ $client->client_email ?? 'N/A' }}</div>
            </div>
            <div class="row g-2 mb-2">
              <div class="col-5 text-muted small">Phone:</div>
              <div class="col-7">{{ $client->client_phone ?? 'N/A' }}</div>
            </div>
            <div class="row g-2 mb-2">
              <div class="col-5 text-muted small">Area/Zone:</div>
              <div class="col-7"><span class="badge bg-label-secondary">{{ $client->location->name ?? 'N/A' }}</span></div>
            </div>
            <div class="row g-2 mb-2">
              <div class="col-5 text-muted small">CIBIL Score:</div>
              <div class="col-7">
                @php
                  $score = $client->cibil_score ?? 0;
                  $scoreClass = $score >= 750 ? 'cibil-excellent' : ($score >= 600 ? 'cibil-good' : 'cibil-poor');
                @endphp
                <span class="{{ $scoreClass }}">{{ $score > 0 ? $score : 'N/A' }}</span>
              </div>
            </div>
            <div class="row g-2">
              <div class="col-5 text-muted small">Assigned Agent:</div>
              <div class="col-7 fw-semibold text-primary">{{ $client->agent->agent_name ?? 'Not Assigned' }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Financial Summaries -->
    <div class="col-12 col-xl-8">
      <div class="row g-4 h-100">
        <!-- Loans Summary Card -->
        <div class="col-12 col-md-6">
          <div class="card h-100 shadow-sm border-0">
            <div class="card-header d-flex align-items-center justify-content-between pb-2 border-bottom">
              <h5 class="card-title mb-0 fw-bold"><i class="ri-bank-card-line me-2 text-primary"></i>Loans Overview</h5>
              <span class="badge bg-label-primary rounded-pill">{{ $loanStats['active_loans'] }} Active</span>
            </div>
            <div class="card-body pt-3">
              <div class="row g-3">
                <div class="col-6">
                  <div class="bg-light p-3 rounded text-center">
                    <p class="mb-1 text-muted small">Outstanding Balance</p>
                    <h5 class="mb-0 fw-bold text-danger">₹{{ number_format($loanStats['outstanding'], 2) }}</h5>
                  </div>
                </div>
                <div class="col-6">
                  <div class="bg-light p-3 rounded text-center">
                    <p class="mb-1 text-muted small">Total Paid Amount</p>
                    <h5 class="mb-0 fw-bold text-success">₹{{ number_format($loanStats['total_paid'], 2) }}</h5>
                  </div>
                </div>
                <div class="col-12 pt-2">
                  <div class="d-flex justify-content-between">
                    <span class="small text-muted">Total Disbursed Value (Active)</span>
                    <span class="small fw-semibold">₹{{ number_format($loanStats['total_amount'], 2) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Chit Funds Summary Card -->
        <div class="col-12 col-md-6">
          <div class="card h-100 shadow-sm border-0">
            <div class="card-header d-flex align-items-center justify-content-between pb-2 border-bottom">
              <h5 class="card-title mb-0 fw-bold"><i class="ri-hand-coin-line me-2 text-info"></i>Chits Overview</h5>
              <span class="badge bg-label-info rounded-pill">{{ $chitStats['total_subscriptions'] }} Active</span>
            </div>
            <div class="card-body pt-3">
              <div class="row g-3">
                <div class="col-6">
                  <div class="bg-light p-3 rounded text-center">
                    <p class="mb-1 text-muted small">Total Payouts Won</p>
                    <h5 class="mb-0 fw-bold text-primary">₹{{ number_format($chitStats['total_payout_received'], 2) }}</h5>
                  </div>
                </div>
                <div class="col-6">
                  <div class="bg-light p-3 rounded text-center">
                    <p class="mb-1 text-muted small">Total Dividends Received</p>
                    <h5 class="mb-0 fw-bold text-success">₹{{ number_format($chitStats['total_dividend_received'], 2) }}</h5>
                  </div>
                </div>
                <div class="col-12 pt-2">
                  <div class="d-flex justify-content-between mb-1">
                    <span class="small text-muted">Total Paid Installments</span>
                    <span class="small fw-semibold text-success">₹{{ number_format($chitStats['total_paid_installments'], 2) }}</span>
                  </div>
                  <div class="d-flex justify-content-between mb-1">
                    <span class="small text-muted">Total Pending Installments</span>
                    <span class="small fw-semibold text-danger">₹{{ number_format($chitStats['total_pending_installments'], 2) }}</span>
                  </div>
                  <div class="d-flex justify-content-between">
                    <span class="small text-muted">Penalty Paid</span>
                    <span class="small fw-semibold text-warning">₹{{ number_format($chitStats['total_penalty_paid'], 2) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Navigation Tabs -->
  <div class="nav-align-top mb-4">
    <ul class="nav nav-tabs border-bottom mb-4" role="tablist">
      <li class="nav-item">
        <button type="button" class="nav-link active fw-semibold" role="tab" data-bs-toggle="tab" data-bs-target="#tab-ledger" aria-controls="tab-ledger" aria-selected="true">
          <i class="ri-file-list-3-line me-1"></i> Unified Ledger Account
        </button>
      </li>
      <li class="nav-item">
        <button type="button" class="nav-link fw-semibold" role="tab" data-bs-toggle="tab" data-bs-target="#tab-loans" aria-controls="tab-loans" aria-selected="false">
          <i class="ri-bank-card-line me-1"></i> Loan Accounts ({{ $loanStats['active_loans'] }})
        </button>
      </li>
      <li class="nav-item">
        <button type="button" class="nav-link fw-semibold" role="tab" data-bs-toggle="tab" data-bs-target="#tab-chits" aria-controls="tab-chits" aria-selected="false">
          <i class="ri-hand-coin-line me-1"></i> Chit Funds ({{ $chitStats['total_subscriptions'] }})
        </button>
      </li>
    </ul>

    <div class="tab-content">
      <!-- Unified Ledger Tab -->
      <div class="tab-pane fade show active" id="tab-ledger" role="tabpanel">
        @if(($closedLoanAccounts ?? collect())->isNotEmpty() || ($closedChitMemberships ?? collect())->isNotEmpty())
        <div class="row g-4 mb-4">
          @if(($closedLoanAccounts ?? collect())->isNotEmpty())
          <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-header border-bottom">
                <h6 class="mb-0 fw-bold"><i class="ri-checkbox-circle-line text-success me-1"></i>Closed Loan Details</h6>
              </div>
              <div class="table-responsive">
                <table class="table table-sm mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Account</th>
                      <th>Amount</th>
                      <th>Paid</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($closedLoanAccounts as $loan)
                    <tr>
                      <td class="fw-semibold">{{ $loan->account_number }}</td>
                      <td>₹{{ number_format($loan->loan_amount, 2) }}</td>
                      <td class="text-success">₹{{ number_format($loan->paid_amount, 2) }}</td>
                      <td>
                        <a href="{{ route('client-loan-emi-details', $loan->id) }}" class="btn btn-xs btn-outline-primary">Details</a>
                      </td>
                    </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          @endif
          @if(($closedChitMemberships ?? collect())->isNotEmpty())
          <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-header border-bottom">
                <h6 class="mb-0 fw-bold"><i class="ri-checkbox-circle-line text-info me-1"></i>Closed / Ended Chit Details</h6>
              </div>
              <div class="table-responsive">
                <table class="table table-sm mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Group</th>
                      <th>Ticket</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($closedChitMemberships as $membership)
                    <tr>
                      <td class="fw-semibold">{{ $membership->group->group_code ?? '—' }}</td>
                      <td>#{{ $membership->member_number }}</td>
                      <td>
                        <span class="badge bg-label-secondary text-capitalize">
                          {{ ($membership->group->status ?? '') === 'completed' ? 'Group Completed' : $membership->status }}
                        </span>
                      </td>
                    </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          @endif
        </div>
        @endif
        <div class="card border-0 shadow-sm">
          <div class="card-header d-flex justify-content-between align-items-center pb-2 border-bottom">
            <div>
              <h5 class="mb-0 fw-bold">Chronological Transaction Statements</h5>
              <p class="text-muted small mb-0">Consolidated history of payments, payouts, disbursements, and dividends</p>
            </div>
            <button class="btn btn-outline-secondary btn-sm" onclick="window.print();">
              <i class="ri-printer-line me-1"></i> Print Statement
            </button>
          </div>
          <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
              <thead class="table-light">
                <tr>
                  <th>Date & Time</th>
                  <th>Transaction Type</th>
                  <th>Reference ID</th>
                  <th>Details / Narration</th>
                  <th>Payment Mode</th>
                  <th class="text-end">Debit (₹)</th>
                  <th class="text-end">Credit (₹)</th>
                </tr>
              </thead>
              <tbody>
                @forelse($ledgerEntries as $entry)
                    <tr>
                      <td>
                        @php
                          $entryDate = \Carbon\Carbon::parse($entry['date']);
                        @endphp
                        {{ $entryDate->format('H:i:s') === '00:00:00' ? $entryDate->format('d M Y') : $entryDate->format('d M Y, h:i A') }}
                      </td>
                    <td>
                      <span class="badge bg-label-{{ $entry['badge_color'] }} px-2 py-1 text-capitalize">
                        {{ $entry['type'] }}
                      </span>
                    </td>
                    <td><span class="fw-semibold">{{ $entry['reference'] }}</span></td>
                    <td class="text-wrap small">
                      {{ $entry['details'] }}
                      @if(!empty($entry['splits']))
                        <div class="text-muted mt-1" style="font-size:.75rem;">
                          @foreach($entry['splits'] as $split)
                            <div>{{ $split['label'] }} — ₹{{ number_format((float) $split['amount'], 2) }}</div>
                          @endforeach
                        </div>
                      @endif
                    </td>
                    <td><span class="badge bg-light text-dark">{{ $entry['method'] }}</span></td>
                    <td class="text-end fw-bold text-danger">
                      {{ $entry['flow'] === 'OUT' ? '₹' . number_format($entry['amount'], 2) : '—' }}
                    </td>
                    <td class="text-end fw-bold text-success">
                      {{ $entry['flow'] === 'IN' ? '₹' . number_format($entry['amount'], 2) : '—' }}
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                      <i class="ri-information-line ri-24px mb-2 d-block"></i>
                      No ledger transactions found for this client.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Loan Accounts Tab -->
      <div class="tab-pane fade" id="tab-loans" role="tabpanel">
        @forelse($loanAccounts as $loan)
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
              <div>
                <h5 class="mb-0 fw-bold text-primary">Loan Account: {{ $loan->account_number }}</h5>
                <span class="text-muted small">Product: {{ $loan->loanApplication->loanProduct->name ?? 'Standard Loan' }}</span>
              </div>
              <div>
                <span class="badge bg-{{ $loan->status === 'closed' ? 'success' : 'primary' }} text-capitalize px-3 py-1">
                  {{ $loan->status }}
                </span>
              </div>
            </div>
            <div class="card-body pt-3">
              <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                  <span class="text-muted small d-block">Loan Amount</span>
                  <strong class="fs-5">₹{{ number_format($loan->loan_amount, 2) }}</strong>
                </div>
                <div class="col-6 col-md-3">
                  <span class="text-muted small d-block">Total Payable</span>
                  <strong class="fs-5">₹{{ number_format($loan->total_payable, 2) }}</strong>
                </div>
                <div class="col-6 col-md-3">
                  <span class="text-muted small d-block">Paid Amount</span>
                  <strong class="fs-5 text-success">₹{{ number_format($loan->paid_amount, 2) }}</strong>
                </div>
                <div class="col-6 col-md-3">
                  <span class="text-muted small d-block">Outstanding Amount</span>
                  <strong class="fs-5 text-danger">₹{{ number_format($loan->outstanding_amount, 2) }}</strong>
                </div>
              </div>

              <h6 class="fw-bold mb-3 border-bottom pb-2">EMI Schedule & Payment History</h6>
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered mb-0">
                  <thead class="table-light">
                    <tr>
                      <th class="text-center">Instalment #</th>
                      <th>Due Date</th>
                      <th>Total Due</th>
                      <th>Paid Date</th>
                      <th>Paid Amount</th>
                      <th>Original Paid</th>
                      <th>Penalty</th>
                      <th>Pending</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    @php
                      $loanReceipts = \App\Support\ClientLedgerEntries::originalReceiptsForLoan($loan);
                    @endphp
                    @foreach($loan->emis as $emi)
                      @php
                        $receipt = $loanReceipts[$emi->id] ?? ['original' => 0, 'allocated' => (float) ($emi->paid_amount ?? 0), 'is_bulk' => false, 'splits' => []];
                        $allocatedPaid = round((float) ($receipt['allocated'] ?: ($emi->paid_amount ?? 0)), 2);
                        $originalPaid = round((float) ($receipt['original'] ?: $allocatedPaid), 2);
                        $dueAmount = round((float) ($emi->total_amount ?? 0), 2);
                        $paidDiff = round($allocatedPaid - $dueAmount, 2);
                      @endphp
                      <tr>
                        <td class="text-center fw-semibold">#{{ $emi->instalment_number }}</td>
                        <td>{{ $emi->due_date ? $emi->due_date->format('d M Y') : 'N/A' }}</td>
                        <td>₹{{ number_format($dueAmount, 2) }}</td>
                        <td>{{ $emi->paid_date ? $emi->paid_date->format('d M Y') : ($emi->partial_paid_date ? $emi->partial_paid_date->format('d M Y') . ' (Partial)' : '-') }}</td>
                        <td class="text-success fw-bold">₹{{ number_format($allocatedPaid, 2) }}</td>
                        <td class="text-success fw-bold">
                          ₹{{ number_format($originalPaid, 2) }}
                          @if(!empty($receipt['is_bulk']) && !empty($receipt['splits']))
                            <div class="text-muted fw-normal mt-1" style="font-size:.7rem;">
                              @foreach($receipt['splits'] as $split)
                                <div>{{ $split['label'] }} — ₹{{ number_format((float) $split['amount'], 2) }}</div>
                              @endforeach
                            </div>
                          @elseif($originalPaid > 0.01 && abs($paidDiff) >= 0.01)
                            <br><small class="{{ $paidDiff > 0 ? 'text-success' : 'text-danger' }}">{{ $paidDiff > 0 ? 'Excess' : 'Short' }}: ₹{{ number_format(abs($paidDiff), 2) }}</small>
                          @endif
                        </td>
                        <td class="text-warning">₹{{ number_format(round((float) $emi->penalty_amount, 2), 2) }}</td>
                        <td class="text-danger fw-bold">₹{{ number_format(round((float) $emi->pending_amount, 2), 2) }}</td>
                        <td>
                          @php
                            $emiStatus = strtolower($emi->status ?? 'pending');
                            $emiColor = match($emiStatus) {
                              'paid' => 'success',
                              'partial' => 'warning',
                              'overdue' => 'danger',
                              default => 'secondary'
                            };
                          @endphp
                          <span class="badge bg-label-{{ $emiColor }} text-capitalize py-0 px-2">{{ $emiStatus }}</span>
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        @empty
          <div class="card border-0 shadow-sm text-center py-5 text-muted">
            <i class="ri-information-line ri-24px mb-2 d-block"></i>
            No loan accounts associated with this client.
          </div>
        @endforelse
      </div>

      <!-- Chit Funds Tab -->
      <div class="tab-pane fade" id="tab-chits" role="tabpanel">
        @forelse($groupMemberships as $member)
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
              <div>
                <h5 class="mb-0 fw-bold text-info">Chit Group: {{ $member->group->group_code }} ({{ $member->group->group_name }})</h5>
                <span class="text-muted small">Scheme: {{ $member->group->scheme->scheme_name }} | Value: ₹{{ number_format($member->group->scheme->chit_value, 2) }}</span>
              </div>
              <div>
                <span class="badge bg-label-{{ $member->status_badge }} text-capitalize px-3 py-1">
                  {{ $member->status }}
                </span>
              </div>
            </div>
            <div class="card-body pt-3">
              <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                  <span class="text-muted small d-block">Member Number</span>
                  <strong class="fs-5">#{{ $member->member_number }}</strong>
                </div>
                <div class="col-6 col-md-3">
                  <span class="text-muted small d-block">Joined Date</span>
                  <strong class="fs-5">{{ $member->joined_date ? $member->joined_date->format('d M Y') : 'N/A' }}</strong>
                </div>
                <div class="col-6 col-md-3">
                  <span class="text-muted small d-block">Won Auction?</span>
                  <strong class="fs-5 {{ $member->has_won_auction ? 'text-success' : 'text-muted' }}">
                    {{ $member->has_won_auction ? 'YES' : 'NO' }}
                  </strong>
                </div>
                <div class="col-6 col-md-3">
                  <span class="text-muted small d-block">Payout Value (Prize)</span>
                  @php
                    $payout = \App\Models\Payout::where('winner_member_id', $member->id)->first();
                  @endphp
                  <strong class="fs-5 text-primary">
                    {{ $payout ? '₹' . number_format($payout->payout_amount, 2) : 'N/A' }}
                  </strong>
                </div>
              </div>

              <!-- Installments subtable -->
              <h6 class="fw-bold mb-3 border-bottom pb-2">Chit Installments Schedule</h6>
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered mb-0">
                  <thead class="table-light">
                    <tr>
                      <th class="text-center">Month #</th>
                      <th>Due Date</th>
                      <th>Amount Due</th>
                      <th>Paid Date</th>
                      <th>Paid Amount</th>
                      <th>Penalty</th>
                      <th>Reference No</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($member->installments as $inst)
                      <tr>
                        <td class="text-center fw-semibold">Month {{ $inst->month_number }}</td>
                        <td>{{ $inst->due_date ? $inst->due_date->format('d M Y') : '-' }}</td>
                        <td>{{ $inst->amount_display }}</td>
                        <td>{{ $inst->paid_date ? $inst->paid_date->format('d M Y') : '-' }}</td>
                        <td class="text-success fw-bold">₹{{ number_format($inst->paid_amount, 2) }}</td>
                        <td class="text-warning">₹{{ number_format($inst->penalty_amount, 2) }}</td>
                        <td><code>{{ $inst->reference_no ?? '-' }}</code></td>
                        <td>
                          <span class="badge bg-label-{{ $inst->status_badge }} text-capitalize py-0 px-2">{{ $inst->status }}</span>
                        </td>
                      </tr>
                    @empty
                      <tr>
                        <td colspan="8" class="text-center text-muted">No installments generated for this chit subscription.</td>
                      </tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        @empty
          <div class="card border-0 shadow-sm text-center py-5 text-muted">
            <i class="ri-information-line ri-24px mb-2 d-block"></i>
            No chit subscriptions associated with this client.
          </div>
        @endforelse
      </div>
    </div>
  </div>
@endsection
