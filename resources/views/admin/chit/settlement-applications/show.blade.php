@extends('layouts/layoutMaster')

@section('title', 'Chit Settlement Application — ' . (optional($member->client)->client_name ?? 'View'))

@section('content')

<!-- Statistics Header Row (Matching Loan Accounts View Layout) -->
<div class="row g-4 mb-6">
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-primary h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1 overflow-hidden">
          <div class="avatar me-2 flex-shrink-0">
            <span class="avatar-initial rounded bg-label-primary"><i class="icon-base ri ri-hashtag icon-20px"></i></span>
          </div>
          <h4 class="ms-1 mb-0 text-truncate w-100" title="{{ $payout->payout_code }}">{{ $payout->payout_code }}</h4>
        </div>
        <p class="mb-0 text-muted small text-uppercase fw-medium">Payout Code</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-{{ $payout->status_badge }} h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1 overflow-hidden">
          <div class="avatar me-2 flex-shrink-0">
            <span class="avatar-initial rounded bg-label-{{ $payout->status_badge }}"><i class="icon-base ri ri-checkbox-circle-line icon-20px"></i></span>
          </div>
          <h4 class="ms-1 mb-0 text-truncate text-{{ $payout->status_badge }} w-100">{{ $payout->status_label }}</h4>
        </div>
        <p class="mb-0 text-muted small text-uppercase fw-medium">Current Status</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-info h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1 overflow-hidden">
          <div class="avatar me-2 flex-shrink-0">
            <span class="avatar-initial rounded bg-label-info"><i class="icon-base ri ri-calendar-line icon-20px"></i></span>
          </div>
          <h4 class="ms-1 mb-0 text-truncate w-100">{{ optional($payout->created_at)->format('d-m-Y') }}</h4>
        </div>
        <p class="mb-0 text-muted small text-uppercase fw-medium">Applied On</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-success h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1 overflow-hidden">
          <div class="avatar me-2 flex-shrink-0">
            <span class="avatar-initial rounded bg-label-success"><i class="icon-base ri ri-user-line icon-20px"></i></span>
          </div>
          <h4 class="ms-1 mb-0 text-truncate w-100" title="{{ optional($member->client)->client_name ?? 'N/A' }}">{{ optional($member->client)->client_name ?? 'N/A' }}</h4>
        </div>
        <p class="mb-0 text-muted small text-uppercase fw-medium">Client Name</p>
      </div>
    </div>
  </div>
</div>

<!-- Back Navigation & Actions Header Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <a href="{{ route('chit.settlement-applications.index') }}" class="btn btn-outline-secondary">
    <i class="icon-base ri ri-arrow-left-line me-1"></i> Back To Applications
  </a>
  <div class="d-flex flex-wrap gap-2">
    @if($payout->status === 'pending' || $payout->status === 'processing')
      <a href="{{ route('chit.settlements.confirm', [$group, $member]) }}?payout_id={{ $payout->id }}" class="btn btn-success">
        <i class="icon-base ri ri-money-rupee-circle-line me-1"></i> Release / Confirm Settlement
      </a>
      <form method="POST" action="{{ route('chit.settlements.cancel', $payout) }}" class="d-inline" onsubmit="return confirm('Cancel this settlement request?');">
        @csrf
        <button type="submit" class="btn btn-outline-danger">
          <i class="icon-base ri ri-close-circle-line me-1"></i> Cancel Request
        </button>
      </form>
    @endif
    @if($payout->status === 'paid')
      <a href="{{ route('chit.settlements.promissory-note', $payout) }}" class="btn btn-outline-secondary" target="_blank">
        <i class="icon-base ri ri-printer-line me-1"></i> Print Promissory Note
      </a>
      <a href="{{ route('chit.settlements.promissory-note.pdf', $payout) }}" class="btn btn-outline-success">
        <i class="icon-base ri ri-file-pdf-2-line me-1"></i> Download PDF
      </a>
    @endif
    @if($member->client_id)
    <a href="{{ route('client-view-chits', $member->client_id) }}" class="btn btn-outline-info">
      <i class="icon-base ri ri-user-3-line me-1"></i> Client Profile
    </a>
    @endif
    <a href="{{ route('chit.groups.show', $group) }}" class="btn btn-outline-primary">
      <i class="icon-base ri ri-group-line me-1"></i> View Chit Group
    </a>
  </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible mb-4" role="alert">
  {{ session('success') }}
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible mb-4" role="alert">
  {{ session('error') }}
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Main Overview Card (Matching Loan Accounts View Card Layout) -->
<div class="card shadow-sm mb-6">
  <div class="card-header border-bottom py-4 px-5">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h5 class="card-title mb-0">Chit Settlement Application Overview</h5>
        <small class="text-muted">Detailed review of payout request, member details, bank account, and fee calculation</small>
      </div>
      <span class="badge rounded-pill bg-label-{{ $payout->status_badge }} fs-6 px-3 py-2">
        <i class="icon-base ri ri-checkbox-circle-line me-1"></i> {{ $payout->status_label }}
      </span>
    </div>
  </div>
  <div class="card-body p-5">
    
    <!-- Application & Client Section -->
    <div class="mb-6">
      <h6 class="text-primary text-uppercase fw-semibold mb-4 d-flex align-items-center">
        <i class="icon-base ri ri-file-list-3-line me-2"></i> Application & Member Details
      </h6>
      <div class="row g-4">
        <div class="col-sm-6 col-md-3">
          <small class="text-muted text-uppercase d-block mb-1">Client Name</small>
          <span class="fw-semibold text-heading fs-6">{{ optional($member->client)->client_name ?? '—' }}</span>
          @if(!empty(optional($member->client)->client_phone))
            <div class="small text-muted"><i class="icon-base ri ri-phone-line me-1"></i>{{ $member->client->client_phone }}</div>
          @endif
        </div>
        <div class="col-sm-6 col-md-3">
          <small class="text-muted text-uppercase d-block mb-1">Chit Group</small>
          <span class="badge bg-label-secondary fs-6 px-3 py-1">{{ $group->group_code ?? '—' }}</span>
          <div class="small text-muted mt-1">{{ $group->scheme->name ?? '—' }}</div>
        </div>
        <div class="col-sm-6 col-md-3">
          <small class="text-muted text-uppercase d-block mb-1">Member Slot #</small>
          <span class="fw-semibold text-heading fs-6">#{{ $member->member_number }}</span>
        </div>
        <div class="col-sm-6 col-md-3">
          <small class="text-muted text-uppercase d-block mb-1">Status & Source</small>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-{{ $payout->status_badge }} fs-6 px-3 py-1">{{ $payout->status_label }}</span>
            <span class="badge bg-label-{{ $payout->source_badge }} fs-6 px-2 py-1">
              <i class="icon-base ri {{ $payout->source === 'customer' ? 'ri-smartphone-line' : ($payout->source === 'agent' ? 'ri-user-star-line' : 'ri-computer-line') }} me-1"></i>{{ $payout->source_label }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <hr class="my-6">

    <!-- Financial Amounts Section -->
    <div class="mb-6">
      <h6 class="text-primary text-uppercase fw-semibold mb-4 d-flex align-items-center">
        <i class="icon-base ri ri-money-rupee-circle-line me-2"></i> Financial Amounts & Applied Month
      </h6>
      <div class="row g-4">
        <div class="col-sm-6 col-md-3">
          <small class="text-muted text-uppercase d-block mb-1">Chit Value</small>
          <span class="fw-bold text-heading fs-5">₹{{ number_format((float) ($payout->chit_value ?? $group->chit_value ?? 0), 2) }}</span>
        </div>
        <div class="col-sm-6 col-md-3">
          <small class="text-muted text-uppercase d-block mb-1">Gross Payout Amount</small>
          <span class="fw-bold text-success fs-5">₹{{ number_format((float) $payout->payout_amount, 2) }}</span>
          @php $payoutSharePct = (float) ($payout->share_percentage ?? $member?->effective_share_percentage ?? 100); @endphp
          @if($payoutSharePct < 99.999)
            <div class="small text-info">{{ rtrim(rtrim(number_format($payoutSharePct, 2), '0'), '.') }}% Independent Share</div>
          @endif
        </div>
        <div class="col-sm-6 col-md-3">
          <small class="text-muted text-uppercase d-block mb-1">Applied Settlement Month</small>
          @php
            $currentAppliedMonth = (int) ($payout->month_number ?? $payout->auction?->month_number ?? 1);
            $startDate = $group->start_date
              ? \Carbon\Carbon::parse($group->start_date)
              : ($group->created_at ? \Carbon\Carbon::parse($group->created_at) : now());
            $monthDateName = $startDate->copy()->addMonths($currentAppliedMonth - 1)->format('M Y');
          @endphp
          <span class="badge bg-label-warning fs-6 px-3 py-1 mb-1">
            <i class="icon-base ri ri-calendar-check-line me-1"></i>Month {{ $currentAppliedMonth }} — {{ $monthDateName }}
          </span>
          <div class="mt-1">
            <span class="badge bg-label-{{ $payout->payout_kind_badge }}">{{ $payout->payout_kind_label }}</span>
          </div>
          <div class="small text-muted mt-1">
            @if($payout->isContributionSettlement())
              {{ $payout->isOutgoingRelease() ? 'Outgoing transfer release (paid − foreman)' : 'Cancel / withdrawn settlement (paid − foreman)' }}
            @else
              Scheme Payout Month
            @endif
          </div>
        </div>
        <div class="col-sm-6 col-md-3">
          <small class="text-muted text-uppercase d-block mb-1">Applied Date &amp; By</small>
          <span class="fw-semibold text-heading fs-6">{{ optional($payout->created_at)->format('d M Y, h:i A') }}</span>
          @php
            $applicant = $payout->initiatedBy;
            $applicantLabel = $applicant?->name ?: ($applicant?->phone ?: ($applicant?->email ?: 'System / Admin'));
          @endphp
          <div class="small text-muted">
            <i class="icon-base ri ri-user-line me-1"></i>{{ $applicantLabel }}
            @if($payout->source === 'customer')
              <span class="badge bg-label-info ms-1">Customer App Login</span>
            @endif
          </div>
          @if($applicant?->phone)
            <div class="small text-muted"><i class="icon-base ri ri-phone-line me-1"></i>{{ $applicant->phone }}</div>
          @endif
        </div>
      </div>

      @if(in_array($payout->status, ['pending', 'processing']))
        @php
          $totalMonths = (int) ($group->total_months ?: 20);
          $canEditSettlementMonth = $canEditSettlementMonth ?? true;
          $isTransferredMember = $isTransferredMember ?? false;
        @endphp
        @if($canEditSettlementMonth)
        <div class="mt-4 p-3 bg-light border rounded">
          <form action="{{ route('chit.settlement-applications.update-month', $payout) }}" method="POST" class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            @csrf
            <div>
              <label class="form-label mb-0 fw-semibold text-primary">
                <i class="icon-base ri ri-edit-box-line me-1"></i>Check &amp; Update Applied Settlement Month:
              </label>
              <div class="form-text mt-0">
                @if($isTransferredMember)
                  Transferred member — only Admin can change the settlement month (defaults to last eligible month).
                @else
                  Select a different month to recalculate and update the applied settlement payout amount.
                @endif
              </div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <select name="month_number" class="form-select form-select-sm fw-semibold text-primary" style="min-width: 250px;">
                @for($m = 1; $m <= $totalMonths; $m++)
                  @if($group->isForemanCommissionMonth($m)) @continue @endif
                  @php
                    $mPayout = $member
                      ? $member->seatSettlementAmount($m)
                      : (float) $group->resolvePayoutAmountForMonth($m);
                    $mDateName = $startDate->copy()->addMonths($m - 1)->format('M Y');
                  @endphp
                  <option value="{{ $m }}" @selected($m === $currentAppliedMonth)>
                    Month {{ $m }} — {{ $mDateName }} (₹{{ number_format($mPayout, 2) }})
                  </option>
                @endfor
              </select>
              <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                <i class="icon-base ri ri-save-line me-1"></i>Update Month
              </button>
            </div>
          </form>
        </div>
        @elseif($isTransferredMember)
        <div class="mt-4 p-3 bg-light border rounded">
          <div class="text-muted small mb-0">
            <i class="icon-base ri ri-lock-line me-1"></i>
            Settlement month for transferred members can only be edited by Admin.
          </div>
        </div>
        @endif
      @endif
    </div>

    <hr class="my-6">

    <!-- Bank Details Section (Auto-fetched from KYC) -->
    <div class="mb-6">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h6 class="text-primary text-uppercase fw-semibold mb-0 d-flex align-items-center">
          <i class="icon-base ri ri-bank-line me-2"></i> Client Bank Account Details (KYC)
        </h6>
        @if(isset($clientKyc) && ($clientKyc->bank_name || $clientKyc->account_number))
          <span class="badge bg-label-success rounded-pill px-3 py-1"><i class="icon-base ri ri-checkbox-circle-line me-1"></i> Auto-fetched from KYC</span>
        @else
          <span class="badge bg-label-warning rounded-pill px-3 py-1">No Bank Info in KYC</span>
        @endif
      </div>

      @if(isset($clientKyc) && ($clientKyc->bank_name || $clientKyc->account_number))
      <div class="row g-4">
        <div class="col-sm-6 col-md-3">
          <small class="text-muted text-uppercase d-block mb-1">Bank Name</small>
          <span class="fw-semibold text-heading fs-6">{{ $clientKyc->bank_name ?? '—' }}</span>
        </div>
        <div class="col-sm-6 col-md-3">
          <small class="text-muted text-uppercase d-block mb-1">Account Number</small>
          <span class="font-monospace text-primary fw-bold fs-6">{{ $clientKyc->account_number ?? '—' }}</span>
        </div>
        <div class="col-sm-6 col-md-3">
          <small class="text-muted text-uppercase d-block mb-1">IFSC Code</small>
          <span class="font-monospace fw-semibold text-heading fs-6">{{ $clientKyc->ifsc_code ?? '—' }}</span>
        </div>
        <div class="col-sm-6 col-md-3">
          <small class="text-muted text-uppercase d-block mb-1">Account Holder Name</small>
          <span class="fw-semibold text-heading fs-6">{{ $clientKyc->account_holder_name ?? ($member->client->client_name ?? '—') }}</span>
        </div>
        @if($clientKyc->branch_name)
        <div class="col-sm-6 col-md-3 mt-3">
          <small class="text-muted text-uppercase d-block mb-1">Branch Name</small>
          <span class="fw-semibold text-heading fs-6">{{ $clientKyc->branch_name }}</span>
        </div>
        @endif
      </div>
      @else
      <p class="text-muted mb-0 small"><i class="icon-base ri ri-information-line me-1"></i>No bank details saved in client's KYC record. Staff can input bank details manually during settlement release.</p>
      @endif
    </div>

    @if($payout->settlement_document || $payout->other_document || $payout->collateral_document)
    <hr class="my-6">
    <div class="mb-6">
      <h6 class="text-primary text-uppercase fw-semibold mb-4 d-flex align-items-center">
        <i class="icon-base ri ri-folder-shield-2-line me-2"></i> Attached Settlement & Collateral Documents
      </h6>
      <div class="row g-4">
        @if($payout->settlement_document)
        <div class="col-md-6">
          <div class="p-3 border rounded d-flex align-items-center justify-content-between bg-light">
            <div class="d-flex align-items-center">
              <i class="icon-base ri ri-file-text-line icon-24px text-primary me-2"></i>
              <div>
                <span class="fw-semibold text-heading d-block">Settlement Deed / Release Document</span>
                <small class="text-muted">Uploaded during settlement release</small>
              </div>
            </div>
            <a href="{{ asset('storage/' . $payout->settlement_document) }}" target="_blank" class="btn btn-sm btn-outline-primary">
              <i class="icon-base ri ri-eye-line me-1"></i> View
            </a>
          </div>
        </div>
        @endif
        @if($payout->other_document)
        <div class="col-md-6">
          <div class="p-3 border rounded d-flex align-items-center justify-content-between bg-light">
            <div class="d-flex align-items-center">
              <i class="icon-base ri ri-file-paper-line icon-24px text-info me-2"></i>
              <div>
                <span class="fw-semibold text-heading d-block">Collateral / Other Document</span>
                <small class="text-muted">Uploaded during settlement release</small>
              </div>
            </div>
            <a href="{{ asset('storage/' . $payout->other_document) }}" target="_blank" class="btn btn-sm btn-outline-info">
              <i class="icon-base ri ri-eye-line me-1"></i> View
            </a>
          </div>
        </div>
        @endif
      </div>
    </div>
    @endif

    <hr class="my-6">

    <!-- Charges & Estimated Net Disbursement Section -->
    <div>
      <h6 class="text-primary text-uppercase fw-semibold mb-4 d-flex align-items-center">
        <i class="icon-base ri ri-calculator-line me-2"></i> Charges & Net Disbursement Breakdown
      </h6>
      
      <div class="table-responsive border rounded">
        <table class="table table-hover mb-0">
          <tbody class="table-border-bottom-0">
            <tr>
              <td class="fw-medium text-heading">Gross Payout Amount</td>
              <td class="text-end fw-bold text-heading">₹{{ number_format((float) $payout->payout_amount, 2) }}</td>
            </tr>
            <tr>
              <td class="text-muted">Estimated Processing Fee</td>
              <td class="text-end text-danger">- ₹{{ number_format((float) ($payout->processing_fee ?? $defaultFees['processing_fee']), 2) }}</td>
            </tr>
            <tr>
              <td class="text-muted">Estimated Document Charges</td>
              <td class="text-end text-danger">- ₹{{ number_format((float) ($payout->document_charges ?? $defaultFees['document_charges']), 2) }}</td>
            </tr>
            <tr>
              <td class="text-muted">Other Charges</td>
              <td class="text-end text-danger">- ₹{{ number_format((float) ($payout->other_charges ?? $defaultFees['other_charges'] ?? 0), 2) }}</td>
            </tr>
            <tr>
              <td class="text-muted">Bank Transfer Charges (company)</td>
              <td class="text-end text-muted">₹{{ number_format((float) ($payout->banking_charges ?? $defaultFees['banking_charges'] ?? 0), 2) }}</td>
            </tr>
            <tr class="table-light">
              <td class="fw-bold fs-5 text-heading">Estimated Net Payable</td>
              @php
                $estFees = (float) ($payout->processing_fee ?? $defaultFees['processing_fee'] ?? 0)
                    + (float) ($payout->document_charges ?? $defaultFees['document_charges'] ?? 0)
                    + (float) ($payout->other_charges ?? $defaultFees['other_charges'] ?? 0);
              @endphp
              <td class="text-end fw-bold fs-5 text-success">₹{{ number_format((float) ($payout->net_payout_amount ?? max(0, $payout->payout_amount - $estFees)), 2) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    @if($payout->remarks)
    <div class="mt-6 p-4 bg-lighter rounded">
      <small class="text-muted text-uppercase d-block mb-1 fw-semibold"><i class="icon-base ri ri-chat-1-line me-1"></i>Remarks & Notes</small>
      <p class="mb-0 text-heading">{{ $payout->remarks }}</p>
    </div>
    @endif

  </div>
</div>

@endsection
