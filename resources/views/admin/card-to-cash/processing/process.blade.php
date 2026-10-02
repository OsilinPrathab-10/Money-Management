@extends('layouts/layoutMaster')

@section('title', 'Process Lead #' . $lead->lead_number)

@section('content')
<!-- Header & Actions -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <div class="d-flex align-items-center gap-3">
      <h4 class="mb-0 fw-bold">Process Lead #{{ $lead->lead_number }}</h4>
      <span class="badge {{ $lead->status_badge }} fs-6">{{ $lead->status_label }}</span>
      @if ($lead->transaction_type === 'bill_payment')
        <span class="badge bg-label-primary"><i class="ri-bank-card-line me-1"></i> Bill Payment</span>
      @else
        <span class="badge bg-label-info"><i class="ri-swap-box-line me-1"></i> Card Swipe</span>
      @endif
    </div>
    <p class="text-muted mb-0">Customer: <strong class="text-heading">{{ optional($lead->customer)->customer_name }}</strong> ({{ $lead->phone_number }}) · Card: <strong class="text-heading">{{ $lead->card_name }} ({{ $lead->masked_card_number }})</strong> - {{ $lead->csr_bank_name }}@if ($lead->card_holder_phone) · Holder Mobile: <strong class="text-heading font-monospace">{{ $lead->card_holder_phone }}</strong>@endif · Due: <strong class="text-heading">{{ $lead->due_date ? $lead->due_date->format('d M Y') : '—' }}</strong> · Requested: <strong class="text-primary">₹{{ number_format($lead->requested_amount, 2) }}</strong>
      <button type="button" class="btn btn-xs btn-outline-secondary ms-2 py-0 px-2" data-bs-toggle="modal" data-bs-target="#modalEditLeadAmount" title="Edit Requested Amount & Card Details">
        <i class="ri-edit-line me-1"></i> Edit Details
      </button>
    </p>
  </div>
  <div class="d-flex gap-2">
    <!-- WhatsApp Confirmation Link -->
    <a href="{{ $whatsAppLink ?? '#' }}" target="_blank" class="btn btn-success" title="Open WhatsApp confirmation">
      <i class="ri-whatsapp-line me-1"></i> WhatsApp Customer
    </a>
    <a href="{{ route('card-cash.leads.show', $lead->id) }}" class="btn btn-outline-primary">
      <i class="ri-eye-line me-1"></i> View Detail
    </a>
    <a href="{{ route('card-cash.processing.index') }}" class="btn btn-outline-secondary">
      <i class="ri-arrow-left-line me-1"></i> Back to Queue
    </a>
  </div>
</div>

@if (session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <strong>Success!</strong> {{ session('success') }}
    @if (session('whatsapp_link'))
      <div class="mt-2">
        <a href="{{ session('whatsapp_link') }}" target="_blank" class="btn btn-sm btn-success">
          <i class="ri-whatsapp-line me-1"></i> Click to Send WhatsApp Confirmation to Customer Now
        </a>
      </div>
    @endif
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if (session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong>Notice:</strong> {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if ($errors->any())
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong>Validation failed:</strong>
    <ul class="mb-0 mt-2 ps-3">
      @foreach ($errors->all() as $err)
        <li>{{ $err }}</li>
      @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<!-- Lead Progression Wizard Bar -->
<div class="card mb-6">
  <div class="card-body py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
      <!-- Step 1: Lead Created -->
      <div class="d-flex align-items-center gap-2">
        <span class="avatar avatar-sm avatar-initial rounded-circle bg-success text-white">
          <i class="ri-check-line"></i>
        </span>
        <div>
          <h6 class="mb-0 fw-bold">1. Lead Created</h6>
          <small class="text-muted">₹{{ number_format($lead->requested_amount, 2) }}</small>
        </div>
      </div>

      <i class="ri-arrow-right-s-line text-muted fs-4"></i>

      <!-- Step 2: Payment / Swipe -->
      <div class="d-flex align-items-center gap-2">
        @if ($lead->billPayment || $lead->swipeTransaction)
          <span class="avatar avatar-sm avatar-initial rounded-circle bg-success text-white">
            <i class="ri-check-line"></i>
          </span>
        @else
          <span class="avatar avatar-sm avatar-initial rounded-circle bg-primary text-white">
            2
          </span>
        @endif
        <div>
          <h6 class="mb-0 fw-bold">2. {{ $lead->transaction_type === 'bill_payment' ? 'Bill Payment' : 'Card Swipe' }}</h6>
          <small class="text-muted">
            {{ $lead->billPayment || $lead->swipeTransaction ? 'Processed' : 'Action Required' }}
          </small>
        </div>
      </div>

      <i class="ri-arrow-right-s-line text-muted fs-4"></i>

      <!-- Step 3: Return Settlement -->
      <div class="d-flex align-items-center gap-2">
        @if ($lead->returnSettlement)
          <span class="avatar avatar-sm avatar-initial rounded-circle bg-success text-white">
            <i class="ri-check-line"></i>
          </span>
        @elseif (in_array($lead->status, ['payment_success', 'return_pending']))
          <span class="avatar avatar-sm avatar-initial rounded-circle bg-warning text-white">
            3
          </span>
        @else
          <span class="avatar avatar-sm avatar-initial rounded-circle bg-secondary text-white">
            3
          </span>
        @endif
        <div>
          <h6 class="mb-0 fw-bold">3. Return Settlement</h6>
          <small class="text-muted">
            {{ $lead->returnSettlement ? 'Settled' : (in_array($lead->status, ['payment_success', 'return_pending']) ? 'Ready to Settle' : 'Pending Step 2') }}
          </small>
        </div>
      </div>

      <i class="ri-arrow-right-s-line text-muted fs-4"></i>

      <!-- Step 4: Completed -->
      <div class="d-flex align-items-center gap-2">
        @if ($lead->status === 'completed')
          <span class="avatar avatar-sm avatar-initial rounded-circle bg-success text-white">
            <i class="ri-checkbox-circle-fill"></i>
          </span>
        @else
          <span class="avatar avatar-sm avatar-initial rounded-circle bg-secondary text-white">
            4
          </span>
        @endif
        <div>
          <h6 class="mb-0 fw-bold">4. Complete</h6>
          <small class="text-muted">{{ $lead->status === 'completed' ? 'Completed' : 'Final Step' }}</small>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-6">
  <!-- Left Column: Operations -->
  <div class="col-lg-7">

    <!-- ============================================== -->
    <!-- 1. BILL PAYMENT EXECUTION PANEL                -->
    <!-- ============================================== -->
    <!-- 1. BILL PAYMENT EXECUTION PANEL                -->
    <!-- ============================================== -->
    @if ($lead->transaction_type === 'bill_payment')
      <div class="card mb-6 border-2 {{ !$lead->billPayment ? 'border-primary' : 'border-success' }} shadow-xs">
        <div class="card-header d-flex justify-content-between align-items-center bg-transparent pb-3">
          <div class="d-flex align-items-center gap-2">
            <span class="avatar avatar-sm avatar-initial rounded bg-label-primary">
              <i class="ri-bank-card-line"></i>
            </span>
            <div>
              <h5 class="card-title mb-0">Step 2: Bill Payment Processing</h5>
              <small class="text-muted">Pay customer's credit card bill from business wallet</small>
            </div>
          </div>
          @if ($lead->billPayment)
            <span class="badge bg-label-success fs-6"><i class="ri-check-line me-1"></i> Payment Success</span>
          @else
            <span class="badge bg-label-warning fs-6">Pending Payment</span>
          @endif
        </div>

        <div class="card-body pt-2">
          @if (!$lead->billPayment)
            <!-- Form to Process Bill Payment -->
            <form id="billPaymentForm" action="{{ route('card-cash.bill-payments.process', $lead->id) }}" method="POST" enctype="multipart/form-data">
              @csrf

              <!-- Primary Amount & Debit Wallet Row -->
              <div class="row g-3 mb-3">
                <div class="col-md-5">
                  <label class="form-label required fw-medium">Bill Payment Amount (₹)</label>
                  <div class="input-group">
                    <span class="input-group-text bg-light fw-bold text-primary">₹</span>
                    <input type="number" step="any" min="1" name="amount" id="billPaymentAmount" class="form-control fw-bold fs-5" 
                      value="{{ old('amount', (float)$lead->requested_amount) }}" onwheel="this.blur()" required>
                  </div>
                  <small class="text-muted">Requested: ₹{{ number_format($lead->requested_amount, 2) }}</small>
                </div>

                <div class="col-md-7">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label required fw-medium mb-0">Debit Wallet Account</label>
                    <div class="form-check form-switch mb-0">
                      <input class="form-check-input cursor-pointer" type="checkbox" id="toggleSplitWallets" onchange="toggleSplitMode(this.checked)">
                      <label class="form-check-label small fw-bold text-primary cursor-pointer" for="toggleSplitWallets">
                        <i class="ri-git-merge-line me-1"></i>Split Wallets
                      </label>
                    </div>
                  </div>

                  <!-- Single Wallet Select -->
                  <div id="billSingleWalletContainer">
                    @php
                      $sourcesByWalletId = $paymentSources->groupBy(fn ($s) => (int) ($s->wallet_id ?: 0));
                      $unlinkedBillSources = $paymentSources->filter(fn ($s) => empty($s->wallet_id));
                    @endphp
                    <select id="billSingleWalletSelect" class="form-select" onchange="onSingleWalletSelectChange()" required>
                      <option value="">-- Choose Wallet to Debit --</option>
                      @foreach ($wallets as $w)
                        @php $linkedSources = $sourcesByWalletId->get($w->id, collect()); @endphp
                        <option value="{{ $w->id }}"
                          data-balance="{{ (float)$w->current_balance }}"
                          data-name="{{ $w->wallet_name }}"
                          data-code="{{ $w->wallet_code }}"
                          data-source-id="{{ optional($linkedSources->first())->id }}"
                          data-source-name="{{ $linkedSources->pluck('source_name')->filter()->implode(', ') }}"
                          data-source-type="{{ optional($linkedSources->first())->source_type }}"
                          {{ old('wallet_id') == $w->id ? 'selected' : '' }}>
                          {{ $w->wallet_name }}@if ($linkedSources->isNotEmpty()) — {{ $linkedSources->pluck('source_name')->implode(', ') }}@endif (Avail: ₹{{ number_format($w->current_balance, 2) }})
                        </option>
                      @endforeach

                      @if ($unlinkedBillSources->isNotEmpty())
                        <optgroup label="Bill Payment Sources">
                          @foreach ($unlinkedBillSources as $source)
                            <option value="ext_{{ $source->id }}"
                              data-type="external"
                              data-id="{{ $source->id }}"
                              data-source-name="{{ $source->source_name }}"
                              data-source-type="{{ $source->source_type }}">
                              {{ $source->source_name }} ({{ ucfirst($source->source_type) }})
                            </option>
                          @endforeach
                        </optgroup>
                      @endif
                    </select>

                    <input type="hidden" name="wallet_id" id="hiddenBillWalletId" value="{{ old('wallet_id') }}">
                    <input type="hidden" name="payment_source_id" id="hiddenBillPaymentSourceId" value="{{ old('payment_source_id') }}">

                    <div id="billDebitWalletDetails" class="mt-2 p-2 bg-white border rounded-2 small" style="display: none;">
                      <div class="d-flex justify-content-between gap-2">
                        <span class="text-muted">Debit Wallet</span>
                        <span class="fw-semibold text-heading text-end" id="billDebitWalletName">—</span>
                      </div>
                      <div class="d-flex justify-content-between gap-2 mt-1">
                        <span class="text-muted">Bill Payment Source</span>
                        <span class="fw-semibold text-primary text-end" id="billDebitSourceName">—</span>
                      </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-1">
                      <small class="text-muted" id="singleWalletHintText">Select a wallet account above</small>
                      <span id="singleWalletBadge" class="badge bg-label-success fs-tiny" style="display: none;"></span>
                    </div>
                    <div id="singleWalletShortfallAlert" class="text-danger small mt-1" style="display: none;">
                      <i class="ri-error-warning-line me-1"></i> Balance is less than bill amount. Switch to <strong>Split Wallets</strong> to combine.
                    </div>
                  </div>

                  <!-- Split Mode Header Notice -->
                  <div id="billSplitActiveNotice" class="alert alert-primary py-2 px-3 mb-0 small d-flex align-items-center justify-content-between" style="display: none;">
                    <div class="d-flex align-items-center gap-1">
                      <i class="ri-git-merge-line fs-5"></i>
                      <span><strong>Split Mode:</strong> Allocate amounts below</span>
                    </div>
                    <button type="button" class="btn btn-xs btn-label-primary fw-bold" onclick="autoDistributeSplitWallets()">
                      <i class="ri-magic-line me-1"></i> Auto-Allocate
                    </button>
                  </div>
                </div>

                <!-- Split Across Multiple Wallets Section -->
                <div class="col-12" id="billSplitWalletsSection" style="display: none;">
                  <input type="hidden" name="is_split_wallet" id="billIsSplitWallet" value="0">
                  <div class="border rounded-3 p-3 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                      <span class="small fw-bold text-dark text-uppercase">
                        <i class="ri-git-merge-line text-primary me-1"></i> Multi-Wallet Allocations
                      </span>
                      <button type="button" class="btn btn-xs btn-outline-primary" onclick="autoDistributeSplitWallets()">
                        <i class="ri-magic-line me-1"></i> Auto-Allocate
                      </button>
                    </div>

                    <div class="table-responsive bg-white rounded border mb-2">
                      <table class="table table-sm table-hover align-middle mb-0" id="splitWalletsTable">
                        <thead class="table-light">
                          <tr>
                            <th style="width: 36px;" class="text-center">#</th>
                            <th>Wallet Account</th>
                            <th>Available</th>
                            <th style="width: 200px;">Amount to Debit (₹)</th>
                            <th style="width: 85px;" class="text-end">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          @forelse ($wallets as $w)
                            <tr class="split-wallet-row" data-wallet-id="{{ $w->id }}" data-balance="{{ (float)$w->current_balance }}">
                              <td class="text-center">
                                <input type="checkbox" class="form-check-input split-wallet-check" 
                                  id="split_check_{{ $w->id }}" 
                                  data-wallet-id="{{ $w->id }}"
                                  onchange="onSplitCheckboxToggled({{ $w->id }})">
                              </td>
                              <td>
                                <label for="split_check_{{ $w->id }}" class="fw-bold mb-0 cursor-pointer text-heading fs-6">
                                  {{ $w->wallet_name }}
                                </label>
                                <small class="text-muted d-block font-monospace fs-tiny">{{ $w->wallet_code }}</small>
                              </td>
                              <td>
                                <span class="badge {{ $w->current_balance > 0 ? 'bg-label-success' : 'bg-label-secondary' }} fs-tiny">
                                  ₹{{ number_format($w->current_balance, 2) }}
                                </span>
                              </td>
                              <td>
                                <div class="input-group input-group-sm">
                                  <span class="input-group-text">₹</span>
                                  <input type="number" step="0.01" min="0" max="{{ (float)$w->current_balance }}"
                                    name="split_wallets[{{ $w->id }}][amount]"
                                    id="split_amt_{{ $w->id }}"
                                    class="form-control fw-bold split-wallet-amt"
                                    placeholder="0.00"
                                    data-wallet-id="{{ $w->id }}"
                                    oninput="recalculateSplitWalletsTotal()"
                                    disabled>
                                  <input type="hidden" name="split_wallets[{{ $w->id }}][wallet_id]" value="{{ $w->id }}" 
                                    id="split_id_{{ $w->id }}" disabled>
                                </div>
                                <div class="split-wallet-feedback text-danger small mt-1" id="split_error_{{ $w->id }}" style="display: none;"></div>
                              </td>
                              <td class="text-end">
                                <button type="button" class="btn btn-xs btn-label-primary btn-fill-remaining" 
                                  id="btn_fill_{{ $w->id }}" 
                                  data-wallet-id="{{ $w->id }}" 
                                  onclick="autoFillRemainingToWallet({{ $w->id }})" 
                                  disabled>
                                  Fill Rest
                                </button>
                              </td>
                            </tr>
                          @empty
                            <tr>
                              <td colspan="5" class="text-center text-muted py-3">No active credit card wallets found.</td>
                            </tr>
                          @endforelse
                        </tbody>
                      </table>
                    </div>

                    <!-- Live Split Allocation Summary -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-1">
                      <div class="small">
                        Total: <strong class="text-dark" id="splitSummaryRequired">₹0.00</strong>
                        &nbsp;|&nbsp; Allocated: <strong id="splitSummaryAllocated">₹0.00</strong>
                        &nbsp;|&nbsp; Diff: <strong id="splitSummaryDiff">₹0.00</strong>
                      </div>
                      <div id="splitStatusAlert" class="small fw-semibold text-secondary">
                        Check wallets to allocate.
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Transaction Reference & Date -->
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-medium">Transaction Reference / UTR Number</label>
                  <input type="text" name="transaction_reference" id="billPaymentUtr" class="form-control font-monospace" 
                    placeholder="e.g. UTR1234567890" value="{{ old('transaction_reference') }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-medium">Payment Date & Time</label>
                  <input type="datetime-local" name="payment_date" class="form-control" 
                    value="{{ date('Y-m-d\TH:i') }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-medium">Attach Screenshot / Proof (Optional)</label>
                  <input type="file" name="screenshot_proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-medium">Gateway Ref / Remarks (Optional)</label>
                  <input type="text" name="remarks" class="form-control" placeholder="Optional notes or gateway ref...">
                </div>
              </div>

              <div class="pt-2">
                <button type="button" class="btn btn-primary btn-lg w-100 shadow-sm fw-bold" onclick="openConfirmBillPaymentModal()">
                  <i class="ri-check-line me-1"></i> Record Successful Bill Payment
                </button>
              </div>
            </form>
          @else
            <!-- Display Existing Bill Payment Info -->
            <div class="alert alert-success d-flex align-items-center mb-3">
              <i class="ri-checkbox-circle-line fs-3 me-3"></i>
              <div>
                <h6 class="alert-heading mb-1 fw-bold">Bill Payment Completed!</h6>
                <span>₹{{ number_format($lead->billPayment->amount, 2) }} paid via 
                  <strong>{{ $lead->billPayment->wallet_summary }}</strong> 
                  on {{ $lead->billPayment->payment_date ? $lead->billPayment->payment_date->format('d M Y, h:i A') : '-' }}
                </span>
              </div>
            </div>

            @if ($lead->billPayment->is_split_wallet && !empty($lead->billPayment->wallet_split_breakdown))
              <div class="card bg-label-secondary border-0 p-3 mb-3 rounded-3">
                <div class="small fw-bold text-uppercase text-muted mb-2">
                  <i class="ri-git-merge-line me-1"></i> Split Wallet Breakdown
                </div>
                <div class="d-flex flex-wrap gap-2">
                  @foreach ($lead->billPayment->wallet_split_breakdown as $splitItem)
                    <span class="badge bg-white text-dark border shadow-xs py-2 px-3 fs-6">
                      <i class="ri-wallet-3-line text-primary me-1"></i> {{ $splitItem['wallet_name'] ?? 'Wallet' }}:
                      <strong class="text-primary ms-1">₹{{ number_format($splitItem['amount'] ?? 0, 2) }}</strong>
                    </span>
                  @endforeach
                </div>
              </div>
            @endif

            <div class="row g-3 p-3 bg-light rounded-2 mb-3">
              <div class="col-sm-6">
                <span class="text-muted small">Debit Wallet Account:</span>
                <p class="fw-semibold text-heading mb-0">{{ optional($lead->billPayment->wallet)->wallet_name ?: ($lead->billPayment->is_split_wallet ? 'Split Wallets' : '—') }}</p>
              </div>
              <div class="col-sm-6">
                <span class="text-muted small">Bill Payment Source:</span>
                <p class="fw-semibold text-primary mb-0">{{ optional($lead->billPayment->paymentSource)->source_name ?: '—' }}</p>
                @if (optional($lead->billPayment->paymentSource)->source_type)
                  <small class="text-muted text-capitalize">{{ $lead->billPayment->paymentSource->source_type }}</small>
                @endif
              </div>
              <div class="col-sm-6">
                <span class="text-muted small">UTR / Transaction Ref:</span>
                <p class="fw-bold font-monospace mb-0">{{ $lead->billPayment->transaction_reference ?: 'N/A' }}</p>
              </div>
              <div class="col-sm-6">
                <span class="text-muted small">Proof Screenshot:</span>
                @if ($lead->billPayment->proof_url)
                  <div>
                    <a href="{{ $lead->billPayment->proof_url }}" target="_blank" class="btn btn-xs btn-outline-primary mt-1">
                      <i class="ri-image-line me-1"></i> View Proof
                    </a>
                  </div>
                @else
                  <p class="text-muted mb-0 small">No screenshot uploaded.</p>
                @endif
              </div>
            </div>

            <form action="{{ route('card-cash.bill-payments.proof', $lead->billPayment->id) }}" method="POST" enctype="multipart/form-data" class="d-flex gap-2">
              @csrf
              <input type="file" name="screenshot_proof" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf" required>
              <button type="submit" class="btn btn-sm btn-label-primary text-nowrap">Upload / Replace Proof</button>
            </form>
          @endif
        </div>
      </div>
    @endif


    <!-- ============================================== -->
    <!-- 2. CARD SWIPE EXECUTION PANEL                  -->
    <!-- ============================================== -->
    @if ($lead->transaction_type === 'swipe')
      <div class="card mb-6 border-2 {{ !$lead->swipeTransaction ? 'border-info' : 'border-success' }} shadow-xs">
        <div class="card-header d-flex justify-content-between align-items-center bg-transparent pb-3">
          <div class="d-flex align-items-center gap-2">
            <span class="avatar avatar-sm avatar-initial rounded bg-label-info">
              <i class="ri-swap-box-line"></i>
            </span>
            <div>
              <h5 class="card-title mb-0">Step 2: Card Swipe Processing</h5>
              <small class="text-muted">Swipe customer card via gateway and credit wallet</small>
            </div>
          </div>
          @if ($lead->swipeTransaction)
            <span class="badge bg-label-success fs-6"><i class="ri-check-line me-1"></i> Swipe Success</span>
          @else
            <span class="badge bg-label-warning fs-6">Pending Swipe</span>
          @endif
        </div>

        <div class="card-body pt-2">
          @if (!$lead->swipeTransaction)
            <!-- Form to Process Swipe -->
            <form id="swipeProcessForm" action="{{ route('card-cash.swipes.process', $lead->id) }}" method="POST" enctype="multipart/form-data">
              @csrf

              <!-- Gateway & Wallet Selection -->
              <div class="row g-3 mb-3">
                <div class="col-md-5">
                  <label class="form-label required fw-medium">Withdrawal Gateway</label>
                  <select name="withdrawal_gateway_id" id="withdrawalGatewaySelect" class="form-select" onchange="onWithdrawalGatewaySelectChange()" required>
                    <option value="">-- Choose Company / Gateway --</option>
                    @foreach ($withdrawalGateways->groupBy(fn ($g) => optional($g->company)->company_name ?: 'Other') as $companyName => $gws)
                      <optgroup label="{{ $companyName }}">
                        @foreach ($gws as $gw)
                          <option value="{{ $gw->id }}"
                                  data-company="{{ optional($gw->company)->company_name ?? '' }}"
                                  data-gateway="{{ $gw->gateway_name }}"
                                  data-debit="{{ (float)$gw->debit_percentage }}"
                                  data-credit="{{ (float)$gw->credit_percentage }}"
                                  data-prepaid="{{ (float)$gw->prepaid_percentage }}"
                                  data-business="{{ (float)$gw->business_percentage }}">
                            {{ $gw->gateway_name }}{{ $gw->gateway_code ? ' (' . $gw->gateway_code . ')' : '' }}
                          </option>
                        @endforeach
                      </optgroup>
                    @endforeach
                  </select>
                  <div id="gatewayRatesRow" class="small mt-1 d-flex flex-wrap gap-1" style="display: none !important;">
                    <span class="badge bg-label-secondary fs-tiny" id="badgeGwDebit">Debit: 0%</span>
                    <span class="badge bg-label-primary fs-tiny" id="badgeGwCredit">Credit: 0%</span>
                    <span class="badge bg-label-warning fs-tiny" id="badgeGwPrepaid">Prepaid: 0%</span>
                    <span class="badge bg-label-danger fs-tiny" id="badgeGwBusiness">Business: 0%</span>
                  </div>
                </div>

                <div class="col-md-7">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label required fw-medium mb-0">Debit Wallet Account</label>
                    <div class="form-check form-switch mb-0">
                      <input class="form-check-input cursor-pointer" type="checkbox" id="toggleSwipeSplitWallets" onchange="toggleSwipeSplitMode(this.checked)">
                      <label class="form-check-label small fw-bold text-info cursor-pointer" for="toggleSwipeSplitWallets">
                        <i class="ri-git-merge-line me-1"></i>Split Wallets
                      </label>
                    </div>
                  </div>

                  <!-- Single Wallet Select -->
                  <div id="swipeSingleWalletContainer">
                    <select name="wallet_id" id="swipeWalletSelect" class="form-select" onchange="onSwipeSingleWalletSelectChange()" required>
                      <option value="">-- Choose Wallet to Debit --</option>
                      @foreach ($wallets as $w)
                        <option value="{{ $w->id }}" data-balance="{{ (float)$w->current_balance }}" data-name="{{ $w->wallet_name }}">
                          {{ $w->wallet_name }} (Avail: ₹{{ number_format($w->current_balance, 2) }})
                        </option>
                      @endforeach
                    </select>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                      <small class="text-muted" id="swipeWalletBalanceHint">Balance will be atomically checked and debited.</small>
                      <span id="swipeSingleWalletBadge" class="badge bg-label-info fs-tiny" style="display: none;"></span>
                    </div>
                  </div>

                  <!-- Split Mode Active Notice for Swipe -->
                  <div id="swipeSplitActiveNotice" class="alert alert-info py-2 px-3 mb-0 small d-flex align-items-center justify-content-between" style="display: none;">
                    <div class="d-flex align-items-center gap-1">
                      <i class="ri-git-merge-line fs-5"></i>
                      <span><strong>Split Mode:</strong> Allocate swipe debit below</span>
                    </div>
                    <button type="button" class="btn btn-xs btn-label-info fw-bold" onclick="autoDistributeSwipeSplitWallets()">
                      <i class="ri-magic-line me-1"></i> Auto-Allocate
                    </button>
                  </div>
                </div>

                <!-- Swipe Split Across Wallets Section -->
                <div class="col-12" id="swipeSplitWalletsSection" style="display: none;">
                  <input type="hidden" name="is_split_wallet" id="swipeIsSplitWallet" value="0">
                  <div class="border rounded-3 p-3 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                      <span class="small fw-bold text-dark text-uppercase">
                        <i class="ri-git-merge-line text-info me-1"></i> Multi-Wallet Swipe Allocations
                      </span>
                      <button type="button" class="btn btn-xs btn-outline-info" onclick="autoDistributeSwipeSplitWallets()">
                        <i class="ri-magic-line me-1"></i> Auto-Allocate
                      </button>
                    </div>

                    <div class="table-responsive bg-white rounded border mb-2">
                      <table class="table table-sm table-hover align-middle mb-0" id="swipeSplitWalletsTable">
                        <thead class="table-light">
                          <tr>
                            <th style="width: 36px;" class="text-center">#</th>
                            <th>Wallet Account</th>
                            <th>Available</th>
                            <th style="width: 200px;">Amount to Debit (₹)</th>
                            <th style="width: 85px;" class="text-end">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          @forelse ($wallets as $w)
                            <tr class="swipe-split-wallet-row" data-wallet-id="{{ $w->id }}" data-balance="{{ (float)$w->current_balance }}">
                              <td class="text-center">
                                <input type="checkbox" class="form-check-input swipe-split-wallet-check" 
                                  id="swipe_split_check_{{ $w->id }}" 
                                  data-wallet-id="{{ $w->id }}"
                                  onchange="onSwipeSplitCheckboxToggled({{ $w->id }})">
                              </td>
                              <td>
                                <label for="swipe_split_check_{{ $w->id }}" class="fw-bold mb-0 cursor-pointer text-heading fs-6">
                                  {{ $w->wallet_name }}
                                </label>
                                <small class="text-muted d-block font-monospace fs-tiny">{{ $w->wallet_code }}</small>
                              </td>
                              <td>
                                <span class="badge {{ $w->current_balance > 0 ? 'bg-label-success' : 'bg-label-secondary' }} fs-tiny">
                                  ₹{{ number_format($w->current_balance, 2) }}
                                </span>
                              </td>
                              <td>
                                <div class="input-group input-group-sm">
                                  <span class="input-group-text">₹</span>
                                  <input type="number" step="0.01" min="0" max="{{ (float)$w->current_balance }}"
                                    name="split_wallets[{{ $w->id }}][amount]"
                                    id="swipe_split_amt_{{ $w->id }}"
                                    class="form-control fw-bold swipe-split-wallet-amt"
                                    placeholder="0.00"
                                    data-wallet-id="{{ $w->id }}"
                                    oninput="recalculateSwipeSplitWalletsTotal()"
                                    disabled>
                                  <input type="hidden" name="split_wallets[{{ $w->id }}][wallet_id]" value="{{ $w->id }}" 
                                    id="swipe_split_id_{{ $w->id }}" disabled>
                                </div>
                                <div class="text-danger small mt-1" id="swipe_split_error_{{ $w->id }}" style="display: none;"></div>
                              </td>
                              <td class="text-end">
                                <button type="button" class="btn btn-xs btn-label-info btn-swipe-fill-remaining" 
                                  id="btn_swipe_fill_{{ $w->id }}" 
                                  data-wallet-id="{{ $w->id }}" 
                                  onclick="autoFillRemainingToSwipeWallet({{ $w->id }})" 
                                  disabled>
                                  Fill Rest
                                </button>
                              </td>
                            </tr>
                          @empty
                            <tr>
                              <td colspan="5" class="text-center text-muted py-3">No active credit card wallets found.</td>
                            </tr>
                          @endforelse
                        </tbody>
                      </table>
                    </div>

                    <!-- Swipe Live Split Summary -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-1">
                      <div class="small">
                        Total Swipe: <strong class="text-dark" id="swipeSplitSummaryRequired">₹0.00</strong>
                        &nbsp;|&nbsp; Allocated: <strong id="swipeSplitSummaryAllocated">₹0.00</strong>
                        &nbsp;|&nbsp; Diff: <strong id="swipeSplitSummaryDiff">₹0.00</strong>
                      </div>
                      <div id="swipeSplitStatusAlert" class="small fw-semibold text-secondary">
                        Check wallets to allocate.
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Streamlined Calculation Ribbon -->
              <div class="p-3 bg-light rounded-3 mb-3 border">
                <div class="row g-3 align-items-center">
                  <div class="col-sm-6 col-lg-3">
                    <label class="form-label small fw-bold text-muted mb-1">Swipe Amount (₹)</label>
                    <div class="input-group">
                      <span class="input-group-text bg-white fw-bold text-info">₹</span>
                      <input type="number" step="any" min="1" name="swipe_amount" id="swipeAmount" 
                        class="form-control fw-bold fs-5" value="{{ old('swipe_amount', (float)$lead->requested_amount) }}" onwheel="this.blur()" required>
                    </div>
                  </div>

                  <div class="col-sm-6 col-lg-3">
                    <label class="form-label small fw-bold text-muted mb-1">Fee Rate (%)</label>
                    <div class="input-group mb-1">
                      <input type="number" step="any" min="0" max="100" name="charges_percentage" id="swipeChargesPct" 
                        class="form-control fw-bold" placeholder="2.0" value="{{ old('charges_percentage', '') }}" onwheel="this.blur()">
                      <span class="input-group-text bg-white">%</span>
                    </div>
                    <div class="d-flex gap-1 flex-wrap">
                      <button type="button" class="badge bg-label-secondary border-0 cursor-pointer" onclick="setSwipeFeePct(1)">1%</button>
                      <button type="button" class="badge bg-label-secondary border-0 cursor-pointer" onclick="setSwipeFeePct(1.5)">1.5%</button>
                      <button type="button" class="badge bg-label-primary border-0 cursor-pointer" onclick="setSwipeFeePct(2)">2%</button>
                      <button type="button" class="badge bg-label-secondary border-0 cursor-pointer" onclick="setSwipeFeePct(2.5)">2.5%</button>
                      <button type="button" class="badge bg-label-secondary border-0 cursor-pointer" onclick="setSwipeFeePct(3)">3%</button>
                    </div>
                  </div>

                  <div class="col-sm-6 col-lg-3">
                    <label class="form-label small fw-bold text-muted mb-1">Swipe Fee (₹)</label>
                    <div class="input-group">
                      <span class="input-group-text bg-white">₹</span>
                      <input type="number" step="any" min="0" name="charges" id="swipeCharges" 
                        class="form-control fw-bold text-danger" value="{{ old('charges', 0.00) }}" onwheel="this.blur()">
                    </div>
                    <small class="text-muted" id="swipeChargesBreakdownText">Calculated from %</small>
                  </div>

                  <div class="col-sm-6 col-lg-3">
                    <label class="form-label small fw-bold text-muted mb-1">Net Settlement (₹)</label>
                    <div class="input-group">
                      <span class="input-group-text bg-white">₹</span>
                      <input type="text" id="swipeNet" class="form-control bg-white fw-bold text-success fs-5" 
                        value="₹{{ number_format($lead->requested_amount, 2) }}" readonly>
                    </div>
                    <small class="text-muted">Swipe - Fee</small>
                  </div>
                </div>
              </div>

              <!-- References & Proof -->
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-medium">Slip Reference / RRN</label>
                  <input type="text" name="transaction_reference" class="form-control font-monospace" 
                    placeholder="e.g. RRN123456789">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-medium">Attach POS Slip (Optional)</label>
                  <input type="file" name="screenshot_proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                </div>
                <div class="col-12">
                  <label class="form-label fw-medium">Remarks / Notes (Optional)</label>
                  <input type="text" name="remarks" class="form-control" placeholder="Optional transaction notes...">
                </div>
              </div>

              <div class="pt-2">
                <button type="button" class="btn btn-info btn-lg w-100 text-white shadow-sm fw-bold" onclick="openConfirmSwipeProcessModal()">
                  <i class="ri-check-line me-1"></i> Process Swipe & Debit Wallet
                </button>
              </div>
            </form>
          @else
            <!-- Display Existing Swipe Record -->
            <div class="alert alert-info d-flex align-items-center mb-3">
              <i class="ri-checkbox-circle-line fs-3 me-3"></i>
              <div>
                <h6 class="alert-heading mb-1 fw-bold">Swipe Transaction Recorded!</h6>
                <span>₹{{ number_format($lead->swipeTransaction->swipe_amount, 2) }} swiped on {{ optional($lead->swipeTransaction->gateway)->gateway_name }} ({{ $lead->swipeTransaction->wallet_summary }})</span>
              </div>
            </div>

            @if ($lead->swipeTransaction->is_split_wallet && !empty($lead->swipeTransaction->wallet_split_breakdown))
              <div class="card bg-label-secondary border-0 p-3 mb-3 rounded-3">
                <div class="small fw-bold text-uppercase text-muted mb-2">
                  <i class="ri-git-merge-line me-1"></i> Split Wallet Breakdown
                </div>
                <div class="d-flex flex-wrap gap-2">
                  @foreach ($lead->swipeTransaction->wallet_split_breakdown as $splitItem)
                    <span class="badge bg-white text-dark border shadow-xs py-2 px-3 fs-6">
                      <i class="ri-wallet-3-line text-info me-1"></i> {{ $splitItem['wallet_name'] ?? 'Wallet' }}:
                      <strong class="text-info ms-1">₹{{ number_format($splitItem['amount'] ?? 0, 2) }}</strong>
                    </span>
                  @endforeach
                </div>
              </div>
            @endif

            <div class="row g-3 p-3 bg-light rounded-2 mb-3">
              <div class="col-sm-4">
                <span class="text-muted small">Swipe Amount:</span>
                <p class="fw-bold mb-0">₹{{ number_format($lead->swipeTransaction->swipe_amount, 2) }}</p>
              </div>
              <div class="col-sm-4">
                <span class="text-muted small">Fee / Charges:</span>
                <p class="fw-bold text-danger mb-0">
                  ₹{{ number_format($lead->swipeTransaction->charges, 2) }}
                  @if ($lead->swipeTransaction->charges_percentage > 0)
                    <span class="badge bg-label-danger fs-tiny ms-1">{{ $lead->swipeTransaction->charges_percentage }}%</span>
                  @endif
                </p>
              </div>
              <div class="col-sm-4">
                <span class="text-muted small">Net Amount:</span>
                <p class="fw-bold text-success mb-0">₹{{ number_format($lead->swipeTransaction->net_amount, 2) }}</p>
              </div>
              <div class="col-sm-6">
                <span class="text-muted small">Reference / RRN:</span>
                <p class="fw-medium font-monospace mb-0">{{ $lead->swipeTransaction->transaction_reference ?: 'N/A' }}</p>
              </div>
              <div class="col-sm-6">
                <span class="text-muted small">Slip Screenshot:</span>
                @if ($lead->swipeTransaction->proof_url)
                  <div>
                    <a href="{{ $lead->swipeTransaction->proof_url }}" target="_blank" class="btn btn-xs btn-outline-info mt-1">
                      <i class="ri-attachment-line me-1"></i> View POS Slip
                    </a>
                  </div>
                @else
                  <p class="text-muted mb-0 small">No slip attached.</p>
                @endif
              </div>
            </div>

            <form action="{{ route('card-cash.swipes.proof', $lead->swipeTransaction->id) }}" method="POST" enctype="multipart/form-data" class="d-flex gap-2">
              @csrf
              <input type="file" name="screenshot_proof" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf" required>
              <button type="submit" class="btn btn-sm btn-label-info text-nowrap">Upload / Replace Slip</button>
            </form>
          @endif
        </div>
      </div>
    @endif


    <!-- ============================================== -->
    <!-- 3. RETURN / SETTLEMENT EXECUTION PANEL         -->
    <!-- ============================================== -->
    <div class="card border-2 {{ !$lead->returnSettlement ? 'border-warning' : 'border-success' }} shadow-xs">
      <div class="card-header d-flex justify-content-between align-items-center bg-transparent pb-3">
        <div class="d-flex align-items-center gap-2">
          <span class="avatar avatar-sm avatar-initial rounded bg-label-warning">
            <i class="ri-refund-2-line"></i>
          </span>
          <div>
            <h5 class="card-title mb-0">Step 3: Customer Return / Settlement</h5>
            <small class="text-muted">
              @if ($lead->transaction_type === 'bill_payment')
                Customer returns settlement / pays back for the credit card bill
              @else
                Payout return funds to customer after credit card swipe
              @endif
            </small>
          </div>
        </div>
        @if ($lead->returnSettlement)
          <span class="badge bg-label-success fs-6"><i class="ri-check-line me-1"></i> Completed</span>
        @else
          <span class="badge bg-label-warning fs-6">Pending Settlement</span>
        @endif
      </div>

      <div class="card-body pt-2">
        @if (!$lead->returnSettlement)
          @if ($lead->billPayment || $lead->swipeTransaction)
            <!-- Form to Process Return Settlement -->
            <form id="returnSettlementForm" action="{{ route('card-cash.returns.process', $lead->id) }}" method="POST" enctype="multipart/form-data">
              @csrf

              @include('admin.card-to-cash.partials.settlement-details', [
                'lead' => $lead,
                'return' => null,
                'title' => 'Settlement Details',
                'wrapperClass' => 'p-3 border rounded-3 mb-3 bg-white',
              ])

              <input type="hidden" name="return_percentage" id="returnPct" value="{{ $cardReturnPercentage }}">

              <!-- Amounts first (payment methods sit below with their options) -->
              <div class="p-3 bg-light rounded-3 mb-3 border">
                <div class="row g-3 align-items-center">
                  <div class="col-sm-6 col-lg-4">
                    <label class="form-label small fw-bold text-muted mb-1">Gross Amount (₹)</label>
                    <div class="input-group">
                      <span class="input-group-text bg-white">₹</span>
                      <input type="number" step="any" name="gross_amount" id="grossAmount" class="form-control fw-bold fs-5" 
                        value="{{ $lead->billPayment ? (float)$lead->billPayment->amount : ($lead->swipeTransaction ? (float)$lead->swipeTransaction->swipe_amount : (float)$lead->requested_amount) }}" onwheel="this.blur()">
                    </div>
                  </div>

                  <div class="col-sm-6 col-lg-4">
                    <label class="form-label small fw-bold text-muted mb-1">Total Return (₹)</label>
                    <div class="input-group">
                      <span class="input-group-text bg-white">₹</span>
                      <input type="number" step="any" name="return_amount" id="returnAmount" class="form-control fw-bold fs-5 text-success" 
                        value="{{ $returnPreview['return_amount'] }}" onwheel="this.blur()">
                    </div>
                    <small class="text-muted">Payout to customer</small>
                  </div>

                  <div class="col-sm-6 col-lg-4">
                    <label class="form-label small fw-bold text-muted mb-1">Charges Retained</label>
                    <div class="p-2 bg-white rounded border fw-bold text-danger fs-6 text-center">
                      ₹<span id="chargesPreview">{{ number_format($returnPreview['charges'], 2) }}</span>
                    </div>
                    <small class="text-muted d-block text-center">Business Margin</small>
                  </div>
                </div>
              </div>

              <!-- Settlement method + matching options (below amounts, not in header) -->
              <div class="p-3 border rounded-3 mb-3 bg-white">
                <label class="form-label required fw-medium mb-2">Settlement Method</label>
                <div class="row g-2 mb-3">
                  <div class="col-6 col-sm-4 col-md-2">
                    <input class="btn-check" type="radio" name="return_method" id="methodWallet" value="wallet" checked>
                    <label class="btn btn-outline-primary w-100 py-2 text-center" for="methodWallet">
                      <i class="ri-wallet-3-line fs-5 d-block mb-1"></i>
                      <span class="fw-bold fs-tiny">Wallet</span>
                    </label>
                  </div>
                  <div class="col-6 col-sm-4 col-md-2">
                    <input class="btn-check" type="radio" name="return_method" id="methodCard" value="card">
                    <label class="btn btn-outline-primary w-100 py-2 text-center" for="methodCard">
                      <i class="ri-bank-card-line fs-5 d-block mb-1"></i>
                      <span class="fw-bold fs-tiny">Card</span>
                    </label>
                  </div>
                  <div class="col-6 col-sm-4 col-md-2">
                    <input class="btn-check" type="radio" name="return_method" id="methodUpi" value="upi">
                    <label class="btn btn-outline-info w-100 py-2 text-center" for="methodUpi">
                      <i class="ri-qr-code-line fs-5 d-block mb-1"></i>
                      <span class="fw-bold fs-tiny">UPI</span>
                    </label>
                  </div>
                  <div class="col-6 col-sm-4 col-md-2">
                    <input class="btn-check" type="radio" name="return_method" id="methodImps" value="imps">
                    <label class="btn btn-outline-success w-100 py-2 text-center" for="methodImps">
                      <i class="ri-bank-line fs-5 d-block mb-1"></i>
                      <span class="fw-bold fs-tiny">IMPS/Bank</span>
                    </label>
                  </div>
                  <div class="col-6 col-sm-4 col-md-2">
                    <input class="btn-check" type="radio" name="return_method" id="methodOther" value="other">
                    <label class="btn btn-outline-secondary w-100 py-2 text-center" for="methodOther">
                      <i class="ri-money-dollar-circle-line fs-5 d-block mb-1"></i>
                      <span class="fw-bold fs-tiny">Cash</span>
                    </label>
                  </div>
                  <div class="col-6 col-sm-4 col-md-2">
                    <input class="btn-check" type="radio" name="return_method" id="methodSplit" value="split">
                    <label class="btn btn-outline-warning w-100 py-2 text-center" for="methodSplit">
                      <i class="ri-git-merge-line fs-5 d-block mb-1"></i>
                      <span class="fw-bold fs-tiny">Split</span>
                    </label>
                  </div>
                </div>

              <!-- =============================================== -->
              <!-- WALLET SELECTION CONTAINER (Shown when Wallet is selected) -->
              <!-- =============================================== -->
              <div id="returnWalletSection" class="p-3 rounded-3 border border-primary bg-label-primary bg-opacity-10">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="fw-bold text-primary">
                    <i class="ri-wallet-3-line me-1"></i> Settlement Wallet Account
                  </span>
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input cursor-pointer" type="checkbox" id="toggleReturnSplitWallets" onchange="toggleReturnSplitMode(this.checked)">
                    <label class="form-check-label small fw-bold text-primary cursor-pointer" for="toggleReturnSplitWallets">
                      <i class="ri-git-merge-line me-1"></i>Split Wallets
                    </label>
                  </div>
                </div>

                @php
                  $returnCompanies = $withdrawalGateways
                    ->map(fn ($g) => [
                      'id' => (int) ($g->company_id ?: 0),
                      'name' => optional($g->company)->company_name ?: 'Other',
                    ])
                    ->unique('id')
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);
                @endphp
                <div class="row g-2 mb-2">
                  <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted" for="returnSettlementCompanySelect">Company Name</label>
                    <select id="returnSettlementCompanySelect" class="form-select" onchange="onReturnSettlementCompanyChange()">
                      <option value="">-- Choose Company --</option>
                      @foreach ($returnCompanies as $company)
                        <option value="{{ $company['id'] }}">{{ $company['name'] }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted" for="returnSettlementGatewaySelect">Gateway</label>
                    <select id="returnSettlementGatewaySelect" class="form-select" onchange="onReturnSettlementGatewayChange()">
                      <option value="">-- Choose Gateway --</option>
                      @foreach ($withdrawalGateways as $gw)
                        <option value="{{ $gw->id }}"
                                data-company-id="{{ (int) ($gw->company_id ?: 0) }}"
                                data-company="{{ optional($gw->company)->company_name ?: 'Other' }}"
                                data-gateway="{{ $gw->gateway_name }}">
                          {{ $gw->gateway_name }}{{ $gw->gateway_code ? ' (' . $gw->gateway_code . ')' : '' }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>

                <!-- Single Wallet Select -->
                <div id="returnSingleWalletContainer">
                  <label class="form-label small fw-semibold text-muted" for="returnSingleWalletSelect">Settlement Amount Wallet</label>
                  <select name="wallet_id" id="returnSingleWalletSelect" class="form-select" onchange="onReturnSingleWalletSelectChange()">
                    <option value="">-- Choose Settlement Amount Wallet --</option>
                    @foreach ($wallets as $w)
                      <option value="{{ $w->id }}" data-balance="{{ (float)$w->current_balance }}" data-name="{{ $w->wallet_name }}">
                        {{ $w->wallet_name }} (Avail: ₹{{ number_format($w->current_balance, 2) }})
                      </option>
                    @endforeach
                  </select>
                  <div class="d-flex justify-content-between align-items-center mt-1">
                    <small class="text-muted" id="returnSingleWalletHint">Select the wallet account used to settle this return</small>
                    <span id="returnSingleWalletBadge" class="badge bg-label-primary fs-tiny" style="display: none;"></span>
                  </div>
                </div>

                <!-- Return Split Across Multiple Wallets Table -->
                <div id="returnSplitWalletsSection" style="display: none;">
                  <input type="hidden" name="is_split_wallet" id="returnIsSplitWallet" value="0">
                  <div class="bg-white rounded border p-2 mt-2">
                    <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                      <small class="fw-bold text-dark text-uppercase">Allocate across wallets to match return total</small>
                      <button type="button" class="btn btn-xs btn-outline-primary" onclick="autoDistributeReturnSplitWallets()">
                        <i class="ri-magic-line me-1"></i> Auto-Allocate
                      </button>
                    </div>

                    <div class="table-responsive">
                      <table class="table table-sm table-hover align-middle mb-0" id="returnSplitWalletsTable">
                        <thead class="table-light">
                          <tr>
                            <th style="width: 36px;" class="text-center">#</th>
                            <th>Wallet</th>
                            <th>Balance</th>
                            <th style="width: 180px;">Amount (₹)</th>
                            <th style="width: 80px;" class="text-end">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          @foreach ($wallets as $w)
                            <tr class="return-split-wallet-row" data-wallet-id="{{ $w->id }}" data-balance="{{ (float)$w->current_balance }}">
                              <td class="text-center">
                                <input type="checkbox" class="form-check-input return-split-wallet-check" 
                                  id="return_split_check_{{ $w->id }}" 
                                  data-wallet-id="{{ $w->id }}"
                                  onchange="onReturnSplitCheckboxToggled({{ $w->id }})">
                              </td>
                              <td>
                                <label for="return_split_check_{{ $w->id }}" class="fw-bold mb-0 cursor-pointer fs-tiny">
                                  {{ $w->wallet_name }}
                                </label>
                              </td>
                              <td>
                                <span class="badge {{ $w->current_balance > 0 ? 'bg-label-success' : 'bg-label-secondary' }} fs-tiny">
                                  ₹{{ number_format($w->current_balance, 2) }}
                                </span>
                              </td>
                              <td>
                                <div class="input-group input-group-sm">
                                  <span class="input-group-text">₹</span>
                                  <input type="number" step="0.01" min="0" max="{{ (float)$w->current_balance }}"
                                    name="split_wallets[{{ $w->id }}][amount]"
                                    id="return_split_amt_{{ $w->id }}"
                                    class="form-control fw-bold return-split-wallet-amt"
                                    placeholder="0.00"
                                    data-wallet-id="{{ $w->id }}"
                                    oninput="recalculateReturnSplitWalletsTotal()"
                                    disabled>
                                  <input type="hidden" name="split_wallets[{{ $w->id }}][wallet_id]" value="{{ $w->id }}" 
                                    id="return_split_id_{{ $w->id }}" disabled>
                                </div>
                              </td>
                              <td class="text-end">
                                <button type="button" class="btn btn-xs btn-label-primary btn-return-fill-remaining" 
                                  id="btn_return_fill_{{ $w->id }}" 
                                  data-wallet-id="{{ $w->id }}" 
                                  onclick="autoFillRemainingToReturnWallet({{ $w->id }})" 
                                  disabled>
                                  Fill Rest
                                </button>
                              </td>
                            </tr>
                          @endforeach
                        </tbody>
                      </table>
                    </div>

                    <!-- Return Split Summary Footer -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 px-1">
                      <div class="small">
                        Target: <strong class="text-dark" id="returnSplitSummaryRequired">₹0.00</strong>
                        &nbsp;|&nbsp; Allocated: <strong id="returnSplitSummaryAllocated">₹0.00</strong>
                        &nbsp;|&nbsp; Diff: <strong id="returnSplitSummaryDiff">₹0.00</strong>
                      </div>
                      <div id="returnSplitStatusAlert" class="small fw-semibold text-secondary">
                        Select wallets to allocate.
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Split Settlement Breakdown Box (Shown when Split method is selected) -->
              <div id="splitSection" class="p-3 rounded-3 border border-warning bg-label-warning bg-opacity-10" style="display: none;">
                <input type="hidden" name="is_split" id="isSplitInput" value="0">
                <h6 class="fw-bold text-dark mb-1"><i class="ri-git-merge-line me-1 text-warning"></i> Split Return Distribution (Card/Wallet + Cash)</h6>
                <p class="small text-muted mb-3">Enter rupee amounts. Card/Wallet and Cash must add up to the total return.</p>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label small fw-bold text-primary">Card / Wallet Amount (₹)</label>
                    <div class="input-group">
                      <span class="input-group-text">₹</span>
                      <input type="number" step="any" min="0" name="card_amount" id="cardAmtInput" class="form-control fw-bold text-primary" value="" onwheel="this.blur()">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small fw-bold text-success">Cash Amount (₹)</label>
                    <div class="input-group">
                      <span class="input-group-text">₹</span>
                      <input type="number" step="any" min="0" name="cash_amount" id="cashAmtInput" class="form-control fw-bold text-success" value="" onwheel="this.blur()">
                    </div>
                  </div>
                  <div class="col-12">
                    <small class="text-muted" id="splitAmountHint">Both amounts together must equal Total Return.</small>
                  </div>
                </div>
              </div>

              <!-- UPI Fields -->
              <div id="upiFields" class="row g-3" style="display: none;">
                <div class="col-12">
                  <label class="form-label required fw-medium">Customer UPI ID / VPA</label>
                  <input type="text" name="upi_id" class="form-control font-monospace" placeholder="e.g. mobile@okhdfcbank or user@upi">
                </div>
              </div>

              <!-- IMPS / Bank Fields -->
              <div id="impsFields" class="row g-3" style="display: none;">
                <div class="col-md-6">
                  <label class="form-label required fw-medium">Account Holder Name</label>
                  <input type="text" name="account_holder_name" class="form-control" placeholder="Beneficiary name" value="{{ optional($lead->customer)->customer_name }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label required fw-medium">Bank Name</label>
                  <input type="text" name="bank_name" class="form-control" placeholder="e.g. HDFC Bank">
                </div>
                <div class="col-md-6">
                  <label class="form-label required fw-medium">Account Number</label>
                  <input type="text" name="account_number" class="form-control font-monospace" placeholder="Bank account number">
                </div>
                <div class="col-md-6">
                  <label class="form-label required fw-medium">IFSC Code</label>
                  <input type="text" name="ifsc_code" class="form-control font-monospace" placeholder="e.g. HDFC0001234">
                </div>
              </div>
              </div>

              <!-- Live Settlement Summary Display Banner -->
              <div class="alert alert-primary d-flex align-items-center mb-3 py-2">
                <i class="ri-information-line fs-4 me-2"></i>
                <div>
                  <span class="small fw-semibold d-block text-uppercase">Settlement Breakdown:</span>
                  <span id="settlementSummaryText" class="fw-bold fs-6">Wallet: ₹{{ number_format($returnPreview['return_amount'], 2) }}</span>
                </div>
              </div>

              <!-- Common Reference, Proof, Remarks -->
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-medium">Payment Reference / UTR</label>
                  <input type="text" name="payment_reference" id="returnPaymentReference" class="form-control font-monospace" placeholder="Settlement reference #">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-medium">Attach Settlement Receipt / Proof (Optional)</label>
                  <input type="file" name="payment_proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                </div>
                <div class="col-12">
                  <label class="form-label fw-medium">Remarks / Settlement Notes</label>
                  <input type="text" name="remarks" class="form-control" placeholder="Optional settlement remarks...">
                </div>
              </div>

              <button type="button" class="btn btn-success btn-lg w-100 shadow-sm fw-bold" onclick="openConfirmReturnSettlementModal()">
                <i class="ri-check-double-line me-1"></i> Execute Return Settlement & Complete Lead
              </button>
            </form>
          @else
            <div class="alert alert-warning mb-0">
              <i class="ri-error-warning-line me-1"></i> Please complete <strong>Step 2 ({{ $lead->transaction_type === 'bill_payment' ? 'Bill Payment' : 'Card Swipe' }})</strong> first before executing customer return.
            </div>
          @endif
        @else
          <!-- Display Existing Return -->
          <div class="alert alert-success d-flex align-items-center mb-3">
            <i class="ri-checkbox-circle-fill fs-3 me-3 text-success"></i>
            <div>
              <h6 class="alert-heading mb-1 fw-bold">Settlement Completed & Lead Closed!</h6>
              <span>{{ $lead->returnSettlement->settlement_summary }} on {{ $lead->returnSettlement->processed_at ? $lead->returnSettlement->processed_at->format('d M Y, h:i A') : '-' }}</span>
            </div>
          </div>

          @include('admin.card-to-cash.partials.settlement-details', [
            'lead' => $lead,
            'return' => $lead->returnSettlement,
            'title' => 'Settlement Details',
            'wrapperClass' => 'p-3 bg-light rounded-3 mb-3',
          ])

          @if ($lead->returnSettlement->is_split_wallet && !empty($lead->returnSettlement->wallet_split_breakdown))
            <div class="card bg-label-secondary border-0 p-3 mb-3 rounded-3">
              <div class="small fw-bold text-uppercase text-muted mb-2">
                <i class="ri-git-merge-line me-1"></i> Split Wallet Settlement Breakdown
              </div>
              <div class="d-flex flex-wrap gap-2">
                @foreach ($lead->returnSettlement->wallet_split_breakdown as $splitItem)
                  <span class="badge bg-white text-dark border shadow-xs py-2 px-3 fs-6">
                    <i class="ri-wallet-3-line text-primary me-1"></i> {{ $splitItem['wallet_name'] ?? 'Wallet' }}:
                    <strong class="text-primary ms-1">₹{{ number_format($splitItem['amount'] ?? 0, 2) }}</strong>
                  </span>
                @endforeach
              </div>
            </div>
          @endif

          <div class="row g-3 p-3 bg-light rounded-2">
            <div class="col-sm-4">
              <span class="text-muted small">Charges Retained:</span>
              <p class="fw-bold text-warning fs-5 mb-0">₹{{ number_format($lead->returnSettlement->charges, 2) }}</p>
            </div>
            <div class="col-sm-4">
              <span class="text-muted small">Reference:</span>
              <p class="fw-medium font-monospace mb-0">{{ $lead->returnSettlement->payment_reference ?: 'N/A' }}</p>
            </div>
            <div class="col-sm-4">
              <span class="text-muted small">Proof:</span>
              @if ($lead->returnSettlement->proof_url)
                <div>
                  <a href="{{ $lead->returnSettlement->proof_url }}" target="_blank" class="btn btn-xs btn-label-success">
                    <i class="ri-attachment-line me-1"></i> View Receipt
                  </a>
                </div>
              @else
                <p class="text-muted mb-0 small">No receipt attached.</p>
              @endif
            </div>
          </div>
        @endif
      </div>
    </div>
  </div>

  <!-- Right Column: Lead Overview, WhatsApp Card, & Timeline -->
  <div class="col-lg-5">
    <!-- WhatsApp Quick Card -->
    <div class="card mb-6 border-2 border-success">
      <div class="card-header d-flex justify-content-between align-items-center py-3">
        <h6 class="card-title mb-0 fw-bold text-success"><i class="ri-whatsapp-line me-1"></i> WhatsApp Message Preview</h6>
        <a href="{{ $whatsAppLink ?? '#' }}" target="_blank" class="btn btn-xs btn-success">
          <i class="ri-send-plane-fill me-1"></i> Send Now
        </a>
      </div>
      <div class="card-body">
        <div class="p-3 bg-light rounded-2 font-monospace small text-muted" style="white-space: pre-wrap;">{{ $whatsAppMessage ?? 'No preview available' }}</div>
      </div>
    </div>

    <!-- Dynamic Lifecycle Status Card -->
    <div class="card mb-6">
      <div class="card-header d-flex justify-content-between align-items-center py-3">
        <h6 class="card-title mb-0 fw-bold">
          <i class="ri-pulse-line text-primary me-1"></i> Live Status
        </h6>
        <span class="badge {{ $lead->status_badge }} fs-6">{{ $lead->status_label }}</span>
      </div>
      <div class="card-body">
        <div class="p-3 bg-light rounded-2 mb-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small">Current Stage:</span>
            <strong class="text-heading">
              @if ($lead->status === 'completed')
                <span class="text-success"><i class="ri-check-double-line me-1"></i> Cycle Completed</span>
              @elseif ($lead->returnSettlement)
                <span class="text-success"><i class="ri-check-line me-1"></i> Return Settled</span>
              @elseif ($lead->billPayment || $lead->swipeTransaction)
                <span class="text-warning"><i class="ri-time-line me-1"></i> Awaiting Return Settlement</span>
              @elseif ($lead->status === 'processing')
                <span class="text-info"><i class="ri-loader-4-line me-1"></i> Ready for {{ $lead->transaction_type === 'bill_payment' ? 'Bill Payment' : 'Card Swipe' }}</span>
              @else
                <span class="text-secondary">{{ $lead->status_label }}</span>
              @endif
            </strong>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted small">Transaction Flow:</span>
            <span class="badge {{ $lead->transaction_type === 'bill_payment' ? 'bg-label-primary' : 'bg-label-info' }}">
              <i class="{{ $lead->transaction_type === 'bill_payment' ? 'ri-bank-card-line' : 'ri-swap-box-line' }} me-1"></i>
              {{ $lead->transaction_type === 'bill_payment' ? 'Bill Payment' : 'Card Swipe' }}
            </span>
          </div>
        </div>

        <div class="small text-muted mb-3 d-flex align-items-center gap-1">
          <i class="ri-sparkling-line text-primary"></i>
          <span>Status updates dynamically as each workflow step is completed.</span>
        </div>

        @include('admin.card-to-cash.partials.settlement-details', [
          'lead' => $lead,
          'return' => $lead->returnSettlement,
          'title' => 'Settlement Details',
          'wrapperClass' => 'p-3 border rounded-3 mb-3 bg-white',
        ])

        @if (!in_array($lead->status, ['completed', 'cancelled', 'rejected', 'failed'], true))
          <button type="button" class="btn btn-sm btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#modalCancelLead">
            <i class="ri-close-circle-line me-1"></i> Cancel / Reject Lead
          </button>
        @endif
      </div>
    </div>

    <!-- Activity Audit Timeline (Dropdown / Collapsible View) -->
    <div class="card" id="cardActivityAuditTimeline">
      <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap gap-2" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#collapseActivityAuditTimeline" aria-expanded="true" aria-controls="collapseActivityAuditTimeline">
        <div class="d-flex align-items-center gap-2">
          <i class="ri-history-line text-primary fs-5"></i>
          <h6 class="card-title mb-0 fw-bold">Activity Audit Timeline</h6>
          <span class="badge bg-label-primary rounded-pill small">{{ count($lead->activityLogs) }}</span>
        </div>
        <div class="d-flex align-items-center gap-2" onclick="event.stopPropagation();">
          <button type="button" class="btn btn-icon btn-xs btn-outline-secondary rounded-circle" id="btnToggleAllAuditLogs" title="Expand / Collapse All Details">
            <i class="ri-arrow-up-down-line"></i>
          </button>
          <button type="button" class="btn btn-icon btn-xs btn-label-primary rounded-circle" data-bs-toggle="collapse" data-bs-target="#collapseActivityAuditTimeline" aria-expanded="true" aria-controls="collapseActivityAuditTimeline" title="Toggle Timeline">
            <i class="ri-arrow-down-s-line fs-5 audit-timeline-card-arrow"></i>
          </button>
        </div>
      </div>

      <div class="collapse show" id="collapseActivityAuditTimeline">
        <!-- Dropdown Filter Toolbar -->
        <div class="px-3 py-2 bg-light border-bottom border-top d-flex justify-content-between align-items-center gap-2">
          <div class="d-flex align-items-center gap-1 w-100">
            <label for="filterActivityAction" class="small text-muted text-nowrap me-1">
              <i class="ri-filter-3-line me-1"></i> Filter:
            </label>
            <select id="filterActivityAction" class="form-select form-select-sm">
              <option value="all">All Activities ({{ count($lead->activityLogs) }})</option>
              <option value="status_changed">Status Changes Only</option>
              <option value="payment">Bill Payment & Swipes</option>
              <option value="settlement">Return Settlements</option>
              <option value="leads">Lead Lifecycle & Edits</option>
            </select>
          </div>
        </div>

        <div class="card-body p-3" style="max-height: 480px; overflow-y: auto;">
          <ul class="timeline timeline-dashed mb-0" id="activityTimelineList">
            @forelse ($lead->activityLogs as $index => $log)
              @php
                // Classify action category for dropdown filter
                $cat = 'leads';
                if ($log->action === 'status_changed') {
                  $descLower = strtolower($log->description);
                  if (str_contains($descLower, 'swipe') || str_contains($descLower, 'bill payment') || str_contains($descLower, 'payment')) {
                    $cat = 'payment';
                  } elseif (str_contains($descLower, 'return') || str_contains($descLower, 'settle') || str_contains($descLower, 'completed')) {
                    $cat = 'settlement';
                  } else {
                    $cat = 'status_changed';
                  }
                } elseif (in_array($log->action, ['bill_payment_processed', 'swipe_processed'])) {
                  $cat = 'payment';
                } elseif (in_array($log->action, ['return_settled', 'return_processed'])) {
                  $cat = 'settlement';
                }

                // Point color based on action & status
                $pointClass = 'timeline-point-primary';
                if ($log->new_status === 'completed' || str_contains(strtolower($log->action), 'success') || $log->new_status === 'return_processed') {
                  $pointClass = 'timeline-point-success';
                } elseif ($log->new_status === 'return_pending' || in_array($log->action, ['lead_assigned', 'lead_updated'])) {
                  $pointClass = 'timeline-point-warning';
                } elseif (in_array($log->new_status, ['rejected', 'cancelled', 'failed'])) {
                  $pointClass = 'timeline-point-danger';
                } elseif ($log->action === 'swipe_processed' || $log->action === 'bill_payment_processed') {
                  $pointClass = 'timeline-point-info';
                }
              @endphp

              <li class="timeline-item timeline-item-transparent mb-3 activity-log-item" data-category="{{ $cat }}" data-action="{{ $log->action }}">
                <span class="timeline-point {{ $pointClass }}"></span>
                <div class="timeline-event card border shadow-none mb-0">
                  <!-- Clickable Dropdown-wise Header for each Log -->
                  <div class="card-header py-2 px-3 d-flex justify-content-between align-items-center bg-transparent" 
                       style="cursor: pointer;" 
                       data-bs-toggle="collapse" 
                       data-bs-target="#logDetails{{ $log->id }}" 
                       aria-expanded="{{ $index === 0 ? 'true' : 'false' }}"
                       aria-controls="logDetails{{ $log->id }}">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                      <span class="badge bg-label-primary fs-tiny py-0 px-1 text-uppercase font-monospace">{{ str_replace('_', ' ', $log->action) }}</span>
                      <strong class="small text-heading">{{ $log->created_at->format('d M, h:i A') }}</strong>
                      @if ($log->user)
                        <span class="text-muted small">· <i class="ri-user-line text-secondary"></i> {{ $log->user->name }}</span>
                      @endif
                    </div>
                    <i class="ri-arrow-down-s-line text-muted log-chevron-icon"></i>
                  </div>

                  <!-- Collapsible / Dropdown Details Body -->
                  <div class="collapse {{ $index === 0 ? 'show' : '' }} log-details-collapse" id="logDetails{{ $log->id }}">
                    <div class="card-body pt-0 px-3 pb-3">
                      <p class="mb-2 small text-heading">{{ $log->description }}</p>

                      @if ($log->old_status || $log->new_status)
                        <div class="d-flex align-items-center gap-1 mb-2 small">
                          <span class="text-muted">Status:</span>
                          @if ($log->old_status)
                            <span class="badge bg-label-secondary fs-tiny">{{ ucwords(str_replace('_', ' ', $log->old_status)) }}</span>
                            <i class="ri-arrow-right-line text-muted small"></i>
                          @endif
                          <span class="badge bg-label-success fs-tiny">{{ ucwords(str_replace('_', ' ', $log->new_status)) }}</span>
                        </div>
                      @endif

                      @if (!empty($log->metadata) && is_array($log->metadata))
                        <div class="p-2 bg-light rounded-2 small mt-1 font-monospace" style="font-size: 0.78rem;">
                          <div class="fw-bold text-muted mb-1 font-sans-serif" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="ri-code-box-line me-1"></i> Event Parameters:
                          </div>
                          <div class="row g-1">
                            @foreach ($log->metadata as $metaKey => $metaVal)
                              @if (!is_array($metaVal))
                                <div class="col-sm-6 text-truncate" title="{{ $metaKey }}: {{ (string)$metaVal }}">
                                  <span class="text-muted">{{ ucwords(str_replace('_', ' ', $metaKey)) }}:</span>
                                  <strong class="text-heading">{{ (string)$metaVal }}</strong>
                                </div>
                              @endif
                            @endforeach
                          </div>
                        </div>
                      @endif

                      <div class="d-flex justify-content-between align-items-center mt-2 pt-1 border-top" style="font-size: 0.72rem;">
                        <span class="text-muted font-monospace">Log ID #{{ $log->id }}</span>
                        <span class="text-muted">{{ $log->created_at->diffForHumans() }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </li>
            @empty
              <li class="timeline-item timeline-item-transparent">
                <span class="timeline-point timeline-point-secondary"></span>
                <div class="timeline-event">
                  <p class="text-muted mb-0 small">No activity logged.</p>
                </div>
              </li>
            @endforelse
          </ul>
          
          <div id="noActivityMatches" class="text-center py-4 text-muted small" style="display: none;">
            <i class="ri-search-line fs-3 d-block mb-1 text-secondary"></i>
            No activities matching the selected filter.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ============================================== -->
<!-- CONFIRM BILL PAYMENT MODAL POPUP               -->
<!-- ============================================== -->
<div class="modal fade" id="modalConfirmBillPayment" tabindex="-1" aria-labelledby="modalConfirmBillPaymentLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white py-3">
        <h5 class="modal-title text-white fw-bold d-flex align-items-center mb-0" id="modalConfirmBillPaymentLabel">
          <i class="ri-shield-check-line me-2 fs-4"></i> Confirm Bill Payment
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <div class="avatar avatar-xl bg-label-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
          <i class="ri-bank-card-line fs-1 text-primary"></i>
        </div>
        
        <h4 class="fw-bold mb-2 text-heading">
          Confirm processing bill payment of ₹<span id="confirmModalAmountText" class="text-primary">{{ number_format($lead->requested_amount, 2) }}</span>?
        </h4>
        <p class="text-muted small mb-4">
          Please verify the credit card and payment details before proceeding. Once confirmed, this bill payment will be recorded and the lead will advance to customer return settlement.
        </p>

        <div class="card bg-light border-0 text-start p-3 mb-0 rounded-3">
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Lead Reference:</span>
            <span class="fw-bold font-monospace text-heading">{{ $lead->lead_number }}</span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Customer:</span>
            <span class="fw-semibold text-heading">{{ optional($lead->customer)->customer_name }}</span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Credit Card:</span>
            <span class="fw-medium text-heading">{{ $lead->card_name }} ({{ $lead->masked_card_number }})</span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Issuing Bank:</span>
            <span class="fw-medium text-heading">{{ $lead->csr_bank_name ?: 'N/A' }}</span>
          </div>
          @if ($lead->card_holder_phone)
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted small">Cardholder Mobile:</span>
              <span class="fw-medium font-monospace text-heading">{{ $lead->card_holder_phone }}</span>
            </div>
          @endif
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Debit Wallet / Bill Payment Source:</span>
            <span class="fw-semibold text-primary" id="confirmModalSourceText">-</span>
          </div>
          <div class="py-1 border-bottom" id="confirmModalSplitBreakdownRow" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="text-muted small">Split Allocations:</span>
              <span class="badge bg-label-info fs-tiny">Multi-Wallet</span>
            </div>
            <div id="confirmModalSplitBreakdownText" class="small ps-1"></div>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">UTR / Transaction Ref:</span>
            <span class="fw-medium font-monospace text-heading" id="confirmModalUtrText">-</span>
          </div>
          <div class="d-flex justify-content-between align-items-center pt-2">
            <span class="fw-bold text-heading">Bill Payment Amount:</span>
            <span class="fw-bold fs-4 text-primary">₹<span id="confirmModalAmountSubText">{{ number_format($lead->requested_amount, 2) }}</span></span>
          </div>
        </div>
      </div>
      <div class="modal-footer bg-light border-0 justify-content-center py-3 gap-2">
        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i> Cancel
        </button>
        <button type="button" class="btn btn-primary px-4 fw-bold" id="btnSubmitBillPayment" onclick="confirmAndSubmitBillPayment()">
          <i class="ri-check-line me-1"></i> Yes, Confirm & Process
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================== -->
<!-- CONFIRM SWIPE PROCESS MODAL POPUP              -->
<!-- ============================================== -->
<div class="modal fade" id="modalConfirmSwipeProcess" tabindex="-1" aria-labelledby="modalConfirmSwipeProcessLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-info text-white py-3">
        <h5 class="modal-title text-white fw-bold d-flex align-items-center mb-0" id="modalConfirmSwipeProcessLabel">
          <i class="ri-swap-box-line me-2 fs-4"></i> Confirm Swipe Processing
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <div class="avatar avatar-xl bg-label-info rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
          <i class="ri-wallet-3-line fs-1 text-info"></i>
        </div>
        
        <h4 class="fw-bold mb-2 text-heading">
          Confirm swipe of ₹<span id="confirmSwipeAmountHeader" class="text-info">0.00</span>?
        </h4>
        <p class="text-muted small mb-4">
          Please verify the gateway, wallet, swipe fee charges, and net settlement before proceeding. Once confirmed, the wallet will be debited and lead will advance to customer return settlement.
        </p>

        <div class="card bg-light border-0 text-start p-3 mb-0 rounded-3">
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Lead Reference:</span>
            <span class="fw-bold font-monospace text-heading">{{ $lead->lead_number }}</span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Customer:</span>
            <span class="fw-semibold text-heading">{{ optional($lead->customer)->customer_name }}</span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Credit Card:</span>
            <span class="fw-medium text-heading">{{ $lead->card_name }} ({{ $lead->masked_card_number }})</span>
          </div>
          @if ($lead->card_holder_phone)
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted small">Cardholder Mobile:</span>
              <span class="fw-medium font-monospace text-heading">{{ $lead->card_holder_phone }}</span>
            </div>
          @endif
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Withdrawal Gateway:</span>
            <span class="fw-semibold text-heading" id="confirmSwipeGatewayText">-</span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Debit Wallet:</span>
            <span class="fw-bold text-heading" id="confirmSwipeWalletText">-</span>
          </div>
          <div class="py-1 border-bottom" id="confirmSwipeSplitBreakdownRow" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="text-muted small">Split Allocations:</span>
              <span class="badge bg-label-info fs-tiny">Multi-Wallet</span>
            </div>
            <div id="confirmSwipeSplitBreakdownText" class="small ps-1"></div>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Gross Swipe Amount:</span>
            <span class="fw-bold text-heading">₹<span id="confirmSwipeGrossText">0.00</span></span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Swipe Fee / Charges:</span>
            <span class="fw-bold text-danger">₹<span id="confirmSwipeChargesText">0.00</span> <span id="confirmSwipePctBadge" class="badge bg-label-danger fs-tiny ms-1" style="display:none;"></span></span>
          </div>
          <div class="d-flex justify-content-between align-items-center pt-2">
            <span class="fw-bold text-heading">Net Debited to Wallet:</span>
            <span class="fw-bold fs-4 text-success" id="confirmSwipeNetText">₹0.00</span>
          </div>
        </div>
      </div>
      <div class="modal-footer bg-light border-0 justify-content-center py-3 gap-2">
        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i> Cancel
        </button>
        <button type="button" class="btn btn-info text-white px-4 fw-bold" id="btnSubmitSwipeProcess" onclick="confirmAndSubmitSwipeProcess()">
          <i class="ri-check-line me-1"></i> Yes, Process Swipe
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================== -->
<!-- CONFIRM RETURN SETTLEMENT MODAL POPUP          -->
<!-- ============================================== -->
<div class="modal fade" id="modalConfirmReturnSettlement" tabindex="-1" aria-labelledby="modalConfirmReturnSettlementLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-success text-white py-3">
        <h5 class="modal-title text-white fw-bold d-flex align-items-center mb-0" id="modalConfirmReturnSettlementLabel">
          <i class="ri-refund-2-line me-2 fs-4"></i> Confirm Return Settlement
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <div class="avatar avatar-xl bg-label-success rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
          <i class="ri-check-double-line fs-1 text-success"></i>
        </div>
        
        <h4 class="fw-bold mb-2 text-heading">
          Confirm settlement to customer and complete lead?
        </h4>
        <p class="text-muted small mb-4">
          Please verify the customer return payout and breakdown below before finalizing. Once confirmed, the lead status will be updated to <strong>Completed</strong>.
        </p>

        <div class="card bg-light border-0 text-start p-3 mb-0 rounded-3">
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Name:</span>
            <span class="fw-semibold text-heading">{{ optional($lead->customer)->customer_name ?: 'N/A' }}</span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Bank Name:</span>
            <span class="fw-semibold text-heading">{{ $lead->csr_bank_name ?: '—' }}</span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Card Number:</span>
            <span class="fw-semibold font-monospace text-heading">{{ $lead->masked_card_number ?: '—' }}</span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Due Date:</span>
            <span class="fw-semibold text-heading">{{ $lead->due_date ? $lead->due_date->format('d M Y') : '—' }}</span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Lead Reference:</span>
            <span class="fw-bold font-monospace text-heading">{{ $lead->lead_number }}</span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Gross Processed Amount:</span>
            <span class="fw-bold text-heading">₹<span id="confirmGrossAmountText">-</span></span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Charges Retained:</span>
            <span class="fw-medium text-heading">₹<span id="confirmChargesText">-</span></span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Amount:</span>
            <span class="fw-bold text-success">₹<span id="confirmReturnAmountInline">-</span></span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted small">Settlement Details:</span>
            <span class="fw-bold text-success text-end ms-2" id="confirmMethodText">-</span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom" id="confirmReturnWalletRow" style="display: none;">
            <span class="text-muted small">Settlement Wallet:</span>
            <span class="fw-semibold text-primary" id="confirmReturnWalletText">-</span>
          </div>
          <div class="py-1 border-bottom" id="confirmReturnSplitBreakdownRow" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="text-muted small">Wallet Allocations:</span>
              <span class="badge bg-label-primary fs-tiny">Multi-Wallet</span>
            </div>
            <div id="confirmReturnSplitBreakdownText" class="small ps-1"></div>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom" id="confirmDestinationRow" style="display: none;">
            <span class="text-muted small">Payout Destination:</span>
            <span class="fw-medium font-monospace text-heading" id="confirmDestinationText">-</span>
          </div>
          <div class="d-flex justify-content-between align-items-center pt-2">
            <span class="fw-bold text-heading">Total Customer Return:</span>
            <span class="fw-bold fs-4 text-success">₹<span id="confirmReturnAmountText">-</span></span>
          </div>
        </div>
      </div>
      <div class="modal-footer bg-light border-0 justify-content-center py-3 gap-2">
        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i> Cancel
        </button>
        <button type="button" class="btn btn-success px-4 fw-bold" id="btnSubmitReturnSettlement" onclick="confirmAndSubmitReturnSettlement()">
          <i class="ri-check-line me-1"></i> Yes, Complete Lead
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================== -->
<!-- EDIT LEAD DETAILS & AMOUNT MODAL POPUP         -->
<!-- ============================================== -->
<div class="modal fade" id="modalEditLeadAmount" tabindex="-1" aria-labelledby="modalEditLeadAmountLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <form action="{{ route('card-cash.leads.update', $lead->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="modal-header bg-primary text-white py-3">
          <h5 class="modal-title text-white fw-bold d-flex align-items-center mb-0" id="modalEditLeadAmountLabel">
            <i class="ri-edit-2-line me-2"></i> Edit Lead #{{ $lead->lead_number }}
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label required fw-medium">Requested Amount (₹)</label>
            <div class="input-group">
              <span class="input-group-text">₹</span>
              <input type="number" step="any" min="1" name="requested_amount" class="form-control fw-bold fs-5" 
                value="{{ old('requested_amount', (float)$lead->requested_amount) }}" onwheel="this.blur()" required>
            </div>
            <small class="text-muted">Enter the exact requested amount (e.g. 50000).</small>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Due Date</label>
            <input type="date" name="due_date" class="form-control" value="{{ old('due_date', $lead->due_date?->format('Y-m-d')) }}">
            <small class="text-muted">Card bill / settlement due date.</small>
          </div>
          <div class="mb-3">
            <label class="form-label required fw-medium">Credit Card Name</label>
            <input type="text" name="card_name" class="form-control" value="{{ old('card_name', $lead->card_name) }}" required>
          </div>
          <div class="mb-3">
            <label class="form-label required fw-medium">CSR Bank Name</label>
            <input type="text" name="csr_bank_name" class="form-control" value="{{ old('csr_bank_name', $lead->csr_bank_name) }}" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Cardholder Mobile <span class="badge bg-label-secondary ms-1 fw-normal">Optional</span></label>
            <input type="tel" name="card_holder_phone" class="form-control font-monospace" 
              placeholder="10-digit mobile number" maxlength="10" pattern="[0-9]{10}" inputmode="numeric"
              oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)" 
              value="{{ old('card_holder_phone', $lead->card_holder_phone) }}">
            <small class="text-muted">Mobile registered with this credit card for OTP / verification.</small>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Internal Remarks</label>
            <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $lead->remarks) }}</textarea>
          </div>
        </div>
        <div class="modal-footer bg-light border-0 py-3">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold">
            <i class="ri-check-line me-1"></i> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
<!-- ============================================== -->
<!-- CANCEL / REJECT LEAD MODAL POPUP               -->
<!-- ============================================== -->
<div class="modal fade" id="modalCancelLead" tabindex="-1" aria-labelledby="modalCancelLeadLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <form action="{{ route('card-cash.processing.status', $lead->id) }}" method="POST">
        @csrf
        <div class="modal-header bg-danger text-white py-3">
          <h5 class="modal-title text-white fw-bold d-flex align-items-center mb-0" id="modalCancelLeadLabel">
            <i class="ri-close-circle-line me-2 fs-4"></i> Cancel / Reject Lead #{{ $lead->lead_number }}
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <p class="text-muted small mb-3">
            Are you sure you want to terminate processing for this lead? Please select the outcome and provide a mandatory reason.
          </p>
          <div class="mb-3">
            <label class="form-label required fw-medium">Termination Outcome</label>
            <select name="status" class="form-select" required>
              <option value="cancelled">Cancelled (Customer Request / Not Interested)</option>
              <option value="rejected">Rejected (Bank Decline / Policy Non-compliance)</option>
              <option value="failed">Failed (Technical / Gateway Failure)</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label required fw-medium">Reason / Remarks</label>
            <textarea name="remarks" class="form-control" rows="3" placeholder="Enter specific reason for cancellation or rejection..." required></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light border-0 py-3">
          <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Keep Active</button>
          <button type="submit" class="btn btn-danger px-4 fw-bold" onclick="return confirm('Are you sure you want to cancel/reject this lead?');">
            <i class="ri-check-line me-1"></i> Confirm Termination
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // =========================================================================
  // 1. SWIPE CALCULATIONS (STEP 2)
  // =========================================================================
  const swipeAmount = document.getElementById('swipeAmount');
  const swipeChargesPct = document.getElementById('swipeChargesPct');
  const swipeCharges = document.getElementById('swipeCharges');
  const swipeNet = document.getElementById('swipeNet');
  const swipeChargesBreakdownText = document.getElementById('swipeChargesBreakdownText');

  window.setSwipeFeePct = function(pct) {
    if (swipeChargesPct) {
      swipeChargesPct.value = pct;
      calcSwipeFromPct();
    }
  };

  function calcSwipeFromPct() {
    if (!swipeAmount || !swipeCharges) return;
    const amt = parseFloat(swipeAmount.value) || 0;
    const pctVal = swipeChargesPct ? swipeChargesPct.value.trim() : '';

    if (pctVal !== '' && !isNaN(pctVal)) {
      const pct = parseFloat(pctVal);
      const fee = Math.round((amt * (pct / 100)) * 100) / 100;
      swipeCharges.value = fee.toFixed(2);
      if (swipeChargesBreakdownText) {
        swipeChargesBreakdownText.innerHTML = `${pct}% of ₹${amt.toLocaleString('en-IN', { minimumFractionDigits: 2 })} = <strong class="text-danger">₹${fee.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</strong>`;
      }
    } else {
      if (swipeChargesBreakdownText) {
        swipeChargesBreakdownText.textContent = 'Calculated from % or enter flat';
      }
    }
    updateSwipeNet();
  }

  function calcSwipeFromCharges() {
    if (!swipeAmount || !swipeCharges) return;
    const amt = parseFloat(swipeAmount.value) || 0;
    const feeVal = swipeCharges.value.trim();

    if (feeVal !== '' && !isNaN(feeVal)) {
      const fee = parseFloat(feeVal);
      if (amt > 0) {
        const pct = Math.round(((fee / amt) * 100) * 100) / 100;
        if (swipeChargesPct) swipeChargesPct.value = pct;
        if (swipeChargesBreakdownText) {
          swipeChargesBreakdownText.innerHTML = `${pct}% of ₹${amt.toLocaleString('en-IN', { minimumFractionDigits: 2 })} = <strong class="text-danger">₹${fee.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</strong>`;
        }
      } else {
        if (swipeChargesPct) swipeChargesPct.value = '0';
        if (swipeChargesBreakdownText) {
          swipeChargesBreakdownText.textContent = 'Flat fee: ₹' + fee.toFixed(2);
        }
      }
    } else {
      if (swipeChargesPct) swipeChargesPct.value = '';
      if (swipeChargesBreakdownText) {
        swipeChargesBreakdownText.textContent = 'Calculated from % or enter flat';
      }
    }
    updateSwipeNet();
  }

  function updateSwipeNet() {
    if (!swipeAmount || !swipeNet) return;
    const amt = parseFloat(swipeAmount.value) || 0;
    const chg = parseFloat(swipeCharges?.value) || 0;
    const net = Math.max(0, Math.round((amt - chg) * 100) / 100);
    swipeNet.value = '₹' + net.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  if (swipeAmount) {
    swipeAmount.addEventListener('input', function() {
      const pctVal = swipeChargesPct ? swipeChargesPct.value.trim() : '';
      if (pctVal !== '' && !isNaN(pctVal)) {
        calcSwipeFromPct();
      } else {
        calcSwipeFromCharges();
      }
      recalculateSwipeSplitWalletsTotal();
    });
  }

  if (swipeChargesPct) swipeChargesPct.addEventListener('input', calcSwipeFromPct);
  if (swipeCharges) swipeCharges.addEventListener('input', calcSwipeFromCharges);

  // Initial swipe calc on load
  if (swipeAmount) {
    const pctVal = swipeChargesPct ? swipeChargesPct.value.trim() : '';
    if (pctVal !== '' && !isNaN(pctVal)) {
      calcSwipeFromPct();
    } else if (swipeCharges && parseFloat(swipeCharges.value) > 0) {
      calcSwipeFromCharges();
    } else {
      updateSwipeNet();
    }
  }

  // Swipe Wallet & Split Mode Handlers (Step 2)
  window.toggleSwipeSplitMode = function(isSplit) {
    const singleContainer = document.getElementById('swipeSingleWalletContainer');
    const splitSection = document.getElementById('swipeSplitWalletsSection');
    const activeNotice = document.getElementById('swipeSplitActiveNotice');
    const isSplitInput = document.getElementById('swipeIsSplitWallet');
    const singleSelect = document.getElementById('swipeWalletSelect');

    if (isSplit) {
      if (singleContainer) singleContainer.style.display = 'none';
      if (activeNotice) activeNotice.style.display = 'flex';
      if (splitSection) splitSection.style.display = 'block';
      if (isSplitInput) isSplitInput.value = '1';
      if (singleSelect) singleSelect.removeAttribute('required');

      document.querySelectorAll('.swipe-split-wallet-check').forEach(chk => {
        const wid = chk.dataset.walletId;
        const amtInput = document.getElementById('swipe_split_amt_' + wid);
        const idInput = document.getElementById('swipe_split_id_' + wid);
        const fillBtn = document.getElementById('btn_swipe_fill_' + wid);
        if (chk.checked) {
          if (amtInput) amtInput.disabled = false;
          if (idInput) idInput.disabled = false;
          if (fillBtn) fillBtn.disabled = false;
        } else {
          if (amtInput) amtInput.disabled = true;
          if (idInput) idInput.disabled = true;
          if (fillBtn) fillBtn.disabled = true;
        }
      });
      recalculateSwipeSplitWalletsTotal();
    } else {
      if (singleContainer) singleContainer.style.display = 'block';
      if (activeNotice) activeNotice.style.display = 'none';
      if (splitSection) splitSection.style.display = 'none';
      if (isSplitInput) isSplitInput.value = '0';
      if (singleSelect) singleSelect.setAttribute('required', 'required');

      document.querySelectorAll('.swipe-split-wallet-amt').forEach(inp => inp.disabled = true);
      document.querySelectorAll('input[name^="split_wallets"][name$="[wallet_id]"]').forEach(inp => inp.disabled = true);
      onSwipeSingleWalletSelectChange();
    }
  };

  window.onSwipeSingleWalletSelectChange = function() {
    const select = document.getElementById('swipeWalletSelect');
    const hint = document.getElementById('swipeWalletBalanceHint');
    const badge = document.getElementById('swipeSingleWalletBadge');
    if (!select || !select.value) {
      if (hint) hint.textContent = 'Balance will be atomically checked and debited.';
      if (badge) badge.style.display = 'none';
      return;
    }
    const opt = select.options[select.selectedIndex];
    const balance = parseFloat(opt.dataset.balance) || 0;
    const name = opt.dataset.name || opt.text;
    if (hint) hint.textContent = name;
    if (badge) {
      badge.textContent = 'Avail: ₹' + balance.toLocaleString('en-IN', { minimumFractionDigits: 2 });
      badge.style.display = 'inline-block';
    }
  };

  window.onWithdrawalGatewaySelectChange = function() {
    const sel = document.getElementById('withdrawalGatewaySelect');
    const ratesRow = document.getElementById('gatewayRatesRow');
    if (!sel || !ratesRow) return;

    if (sel.selectedIndex > 0) {
      const opt = sel.options[sel.selectedIndex];
      const debit = parseFloat(opt.dataset.debit) || 0;
      const credit = parseFloat(opt.dataset.credit) || 0;
      const prepaid = parseFloat(opt.dataset.prepaid) || 0;
      const business = parseFloat(opt.dataset.business) || 0;

      const bDebit = document.getElementById('badgeGwDebit');
      const bCredit = document.getElementById('badgeGwCredit');
      const bPrepaid = document.getElementById('badgeGwPrepaid');
      const bBusiness = document.getElementById('badgeGwBusiness');

      if (bDebit) bDebit.textContent = `Debit: ${debit.toFixed(2)}%`;
      if (bCredit) bCredit.textContent = `Credit: ${credit.toFixed(2)}%`;
      if (bPrepaid) bPrepaid.textContent = `Prepaid: ${prepaid.toFixed(2)}%`;
      if (bBusiness) bBusiness.textContent = `Business: ${business.toFixed(2)}%`;

      ratesRow.style.setProperty('display', 'flex', 'important');
    } else {
      ratesRow.style.setProperty('display', 'none', 'important');
    }
  };

  window.onSwipeSplitCheckboxToggled = function(walletId) {
    const chk = document.getElementById('swipe_split_check_' + walletId);
    const amtInput = document.getElementById('swipe_split_amt_' + walletId);
    const idInput = document.getElementById('swipe_split_id_' + walletId);
    const fillBtn = document.getElementById('btn_swipe_fill_' + walletId);

    if (chk && chk.checked) {
      if (amtInput) amtInput.disabled = false;
      if (idInput) idInput.disabled = false;
      if (fillBtn) fillBtn.disabled = false;
      if (amtInput && (!amtInput.value || parseFloat(amtInput.value) <= 0)) {
        autoFillRemainingToSwipeWallet(walletId);
        return;
      }
    } else {
      if (amtInput) { amtInput.value = ''; amtInput.disabled = true; }
      if (idInput) idInput.disabled = true;
      if (fillBtn) fillBtn.disabled = true;
    }
    recalculateSwipeSplitWalletsTotal();
  };

  window.autoFillRemainingToSwipeWallet = function(walletId) {
    const row = document.querySelector(`.swipe-split-wallet-row[data-wallet-id="${walletId}"]`);
    if (!row) return;
    const balance = parseFloat(row.dataset.balance) || 0;
    const targetAmt = parseFloat(document.getElementById('swipeAmount')?.value || 0) || 0;

    let otherSum = 0;
    document.querySelectorAll('.swipe-split-wallet-row').forEach(r => {
      const wid = r.dataset.walletId;
      if (wid != walletId) {
        const c = document.getElementById('swipe_split_check_' + wid);
        if (c && c.checked) {
          otherSum += parseFloat(document.getElementById('swipe_split_amt_' + wid)?.value || 0) || 0;
        }
      }
    });

    const needed = Math.max(0, Math.round((targetAmt - otherSum) * 100) / 100);
    const fillAmt = Math.min(needed, balance);

    const chk = document.getElementById('swipe_split_check_' + walletId);
    if (chk && !chk.checked) {
      chk.checked = true;
      const amtInput = document.getElementById('swipe_split_amt_' + walletId);
      const idInput = document.getElementById('swipe_split_id_' + walletId);
      const fillBtn = document.getElementById('btn_swipe_fill_' + walletId);
      if (amtInput) amtInput.disabled = false;
      if (idInput) idInput.disabled = false;
      if (fillBtn) fillBtn.disabled = false;
    }

    const targetInput = document.getElementById('swipe_split_amt_' + walletId);
    if (targetInput) targetInput.value = fillAmt.toFixed(2);
    recalculateSwipeSplitWalletsTotal();
  };

  window.autoDistributeSwipeSplitWallets = function() {
    const targetAmt = parseFloat(document.getElementById('swipeAmount')?.value || 0) || 0;
    let remaining = targetAmt;

    document.querySelectorAll('.swipe-split-wallet-row').forEach(r => {
      const wid = r.dataset.walletId;
      const balance = parseFloat(r.dataset.balance) || 0;
      const chk = document.getElementById('swipe_split_check_' + wid);
      const amtInput = document.getElementById('swipe_split_amt_' + wid);
      const idInput = document.getElementById('swipe_split_id_' + wid);
      const fillBtn = document.getElementById('btn_swipe_fill_' + wid);

      if (remaining > 0.001 && balance > 0) {
        const take = Math.min(remaining, balance);
        if (chk) chk.checked = true;
        if (amtInput) { amtInput.disabled = false; amtInput.value = take.toFixed(2); }
        if (idInput) idInput.disabled = false;
        if (fillBtn) fillBtn.disabled = false;
        remaining = Math.max(0, Math.round((remaining - take) * 100) / 100);
      } else {
        if (chk) chk.checked = false;
        if (amtInput) { amtInput.value = ''; amtInput.disabled = true; }
        if (idInput) idInput.disabled = true;
        if (fillBtn) fillBtn.disabled = true;
      }
    });
    recalculateSwipeSplitWalletsTotal();
  };

  window.recalculateSwipeSplitWalletsTotal = function() {
    const targetAmt = parseFloat(document.getElementById('swipeAmount')?.value || 0) || 0;
    let totalAllocated = 0;

    document.querySelectorAll('.swipe-split-wallet-row').forEach(r => {
      const wid = r.dataset.walletId;
      const chk = document.getElementById('swipe_split_check_' + wid);
      const amtInput = document.getElementById('swipe_split_amt_' + wid);

      if (chk && chk.checked && amtInput && !amtInput.disabled) {
        const amt = parseFloat(amtInput.value || 0) || 0;
        totalAllocated = Math.round((totalAllocated + amt) * 100) / 100;
      }
    });

    const diff = Math.round((targetAmt - totalAllocated) * 100) / 100;
    const reqEl = document.getElementById('swipeSplitSummaryRequired');
    const allocEl = document.getElementById('swipeSplitSummaryAllocated');
    const diffEl = document.getElementById('swipeSplitSummaryDiff');
    const alertEl = document.getElementById('swipeSplitStatusAlert');

    if (reqEl) reqEl.textContent = '₹' + targetAmt.toLocaleString('en-IN', { minimumFractionDigits: 2 });
    if (allocEl) {
      allocEl.textContent = '₹' + totalAllocated.toLocaleString('en-IN', { minimumFractionDigits: 2 });
      allocEl.className = Math.abs(diff) < 0.01 ? 'fw-bold fs-6 text-success' : 'fw-bold fs-6 text-warning';
    }
    if (diffEl) {
      diffEl.textContent = (diff >= 0 ? '₹' : '-₹') + Math.abs(diff).toLocaleString('en-IN', { minimumFractionDigits: 2 });
      diffEl.className = Math.abs(diff) < 0.01 ? 'fw-bold fs-6 text-success' : (diff > 0 ? 'fw-bold fs-6 text-warning' : 'fw-bold fs-6 text-danger');
    }
    if (alertEl) {
      if (totalAllocated === 0) {
        alertEl.className = 'small fw-semibold text-secondary';
        alertEl.textContent = 'Check wallets to allocate.';
      } else if (Math.abs(diff) < 0.01) {
        alertEl.className = 'small fw-bold text-success';
        alertEl.innerHTML = '<i class="ri-checkbox-circle-fill me-1"></i> Exact match! Ready to process swipe.';
      } else if (diff > 0) {
        alertEl.className = 'small fw-bold text-warning';
        alertEl.textContent = `₹${diff.toFixed(2)} remaining to allocate.`;
      } else {
        alertEl.className = 'small fw-bold text-danger';
        alertEl.textContent = `Over-allocated by ₹${Math.abs(diff).toFixed(2)}.`;
      }
    }
  };

  // =========================================================================
  // 2. RETURN SETTLEMENT CALCULATIONS & WALLET LOGIC (STEP 3)
  // =========================================================================
  const grossInput = document.getElementById('grossAmount');
  const pctInput = document.getElementById('returnPct');
  const returnInput = document.getElementById('returnAmount');
  const chargesSpan = document.getElementById('chargesPreview');
  const splitSection = document.getElementById('splitSection');
  const returnWalletSection = document.getElementById('returnWalletSection');
  const isSplitInput = document.getElementById('isSplitInput');
  const cardAmtInput = document.getElementById('cardAmtInput');
  const cashAmtInput = document.getElementById('cashAmtInput');
  const summaryText = document.getElementById('settlementSummaryText');
  const upiFields = document.getElementById('upiFields');
  const impsFields = document.getElementById('impsFields');

  window.setReturnPct = function(pct) {
    if (pctInput) {
      pctInput.value = pct;
      calculateAll('gross');
    }
  };

  function calculateAll(source) {
    const gross = parseFloat(grossInput?.value) || 0;
    const pct = parseFloat(pctInput?.value) || 99;

    if (source !== 'return' && source !== 'method') {
      const computedReturn = Math.round((gross * (pct / 100)) * 100) / 100;
      if (returnInput && document.activeElement !== returnInput) {
        returnInput.value = computedReturn.toFixed(2);
      }
    }

    const totalReturn = parseFloat(returnInput?.value) || 0;
    const chgAmt = Math.round((gross - totalReturn) * 100) / 100;

    if (source === 'return' && pctInput && gross > 0) {
      pctInput.value = ((totalReturn / gross) * 100).toFixed(4);
    }

    if (chargesSpan) chargesSpan.textContent = chgAmt.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const selectedMethod = document.querySelector('input[name="return_method"]:checked')?.value || 'wallet';

    // Show/hide method containers
    if (returnWalletSection) returnWalletSection.style.display = (selectedMethod === 'wallet') ? 'block' : 'none';
    if (upiFields) upiFields.style.display = (selectedMethod === 'upi') ? 'flex' : 'none';
    if (impsFields) impsFields.style.display = (selectedMethod === 'imps') ? 'flex' : 'none';

    if (selectedMethod === 'split') {
      if (splitSection) splitSection.style.display = 'block';
      if (isSplitInput) isSplitInput.value = '1';
      applySplitAmounts('total');
    } else {
      if (splitSection) splitSection.style.display = 'none';
      if (isSplitInput) isSplitInput.value = '0';

      const methodNames = {
        'wallet': 'Wallet',
        'card': 'Card',
        'upi': 'UPI',
        'imps': 'IMPS',
        'other': 'Cash'
      };
      const label = methodNames[selectedMethod] || selectedMethod.toUpperCase();

      if (selectedMethod === 'wallet') {
        const isSplitWallet = document.getElementById('returnIsSplitWallet')?.value === '1';
        if (isSplitWallet) {
          summaryText.innerHTML = `Split Wallets: <strong class="text-primary">₹${totalReturn.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</strong>`;
        } else {
          const wSelect = document.getElementById('returnSingleWalletSelect');
          const wName = (wSelect && wSelect.selectedIndex > 0) ? wSelect.options[wSelect.selectedIndex].dataset.name || 'Wallet' : 'Wallet';
          summaryText.innerHTML = `${wName}: <strong class="text-primary">₹${totalReturn.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</strong>`;
        }
      } else {
        if (summaryText) {
          summaryText.innerHTML = `${label}: <strong class="text-primary">₹${totalReturn.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</strong>`;
        }
      }
    }

    recalculateReturnSplitWalletsTotal();
  }

  function applySplitAmounts(source) {
    const totalReturn = parseFloat(returnInput?.value) || 0;
    let cardAmt = parseFloat(cardAmtInput?.value);
    let cashAmt = parseFloat(cashAmtInput?.value);

    if (source === 'card') {
      if (isNaN(cardAmt) || cardAmt < 0) cardAmt = 0;
      cardAmt = Math.min(cardAmt, totalReturn);
      cashAmt = Math.round((totalReturn - cardAmt) * 100) / 100;
      if (cashAmtInput && document.activeElement !== cashAmtInput) {
        cashAmtInput.value = cashAmt.toFixed(2);
      }
    } else if (source === 'cash') {
      if (isNaN(cashAmt) || cashAmt < 0) cashAmt = 0;
      cashAmt = Math.min(cashAmt, totalReturn);
      cardAmt = Math.round((totalReturn - cashAmt) * 100) / 100;
      if (cardAmtInput && document.activeElement !== cardAmtInput) {
        cardAmtInput.value = cardAmt.toFixed(2);
      }
    } else {
      if (isNaN(cardAmt) && isNaN(cashAmt)) {
        cardAmt = Math.round((totalReturn / 2) * 100) / 100;
      } else if (isNaN(cardAmt)) {
        cardAmt = 0;
      }
      cardAmt = Math.min(Math.max(0, cardAmt), totalReturn);
      cashAmt = Math.round((totalReturn - cardAmt) * 100) / 100;
      if (cardAmtInput && document.activeElement !== cardAmtInput) {
        cardAmtInput.value = cardAmt.toFixed(2);
      }
      if (cashAmtInput && document.activeElement !== cashAmtInput) {
        cashAmtInput.value = cashAmt.toFixed(2);
      }
    }

    const cardDisplay = parseFloat(cardAmtInput?.value) || 0;
    const cashDisplay = parseFloat(cashAmtInput?.value) || 0;
    const combined = Math.round((cardDisplay + cashDisplay) * 100) / 100;
    const hint = document.getElementById('splitAmountHint');
    if (hint) {
      const matched = Math.abs(combined - totalReturn) < 0.01;
      hint.className = matched ? 'text-success' : 'text-danger';
      hint.textContent = matched
        ? 'Card/Wallet + Cash = ₹' + combined.toLocaleString('en-IN', { minimumFractionDigits: 2 }) + ' (matches Total Return)'
        : 'Card/Wallet + Cash = ₹' + combined.toLocaleString('en-IN', { minimumFractionDigits: 2 }) + ' — must equal Total Return ₹' + totalReturn.toLocaleString('en-IN', { minimumFractionDigits: 2 });
    }
    if (summaryText) {
      summaryText.innerHTML = `Card/Wallet: <strong class="text-primary">₹${cardDisplay.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</strong> | Cash: <strong class="text-success">₹${cashDisplay.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</strong>`;
    }
  }

  if (grossInput) grossInput.addEventListener('input', function () { calculateAll('gross'); });
  if (returnInput) returnInput.addEventListener('input', function () { calculateAll('return'); });

  if (cardAmtInput) {
    cardAmtInput.addEventListener('input', function() {
      applySplitAmounts('card');
    });
  }

  if (cashAmtInput) {
    cashAmtInput.addEventListener('input', function() {
      applySplitAmounts('cash');
    });
  }

  // Return Method Radio Toggle
  const methodRadios = document.querySelectorAll('input[name="return_method"]');
  methodRadios.forEach(radio => {
    radio.addEventListener('change', function () { calculateAll('method'); });
  });

  // Step 3 Return Wallet Handlers
  window.toggleReturnSplitMode = function(isSplit) {
    const singleContainer = document.getElementById('returnSingleWalletContainer');
    const splitSection = document.getElementById('returnSplitWalletsSection');
    const isSplitInput = document.getElementById('returnIsSplitWallet');
    const singleSelect = document.getElementById('returnSingleWalletSelect');

    if (isSplit) {
      if (singleContainer) singleContainer.style.display = 'none';
      if (splitSection) splitSection.style.display = 'block';
      if (isSplitInput) isSplitInput.value = '1';
      if (singleSelect) singleSelect.removeAttribute('required');

      document.querySelectorAll('.return-split-wallet-check').forEach(chk => {
        const wid = chk.dataset.walletId;
        const amtInput = document.getElementById('return_split_amt_' + wid);
        const idInput = document.getElementById('return_split_id_' + wid);
        const fillBtn = document.getElementById('btn_return_fill_' + wid);
        if (chk.checked) {
          if (amtInput) amtInput.disabled = false;
          if (idInput) idInput.disabled = false;
          if (fillBtn) fillBtn.disabled = false;
        } else {
          if (amtInput) amtInput.disabled = true;
          if (idInput) idInput.disabled = true;
          if (fillBtn) fillBtn.disabled = true;
        }
      });
      recalculateReturnSplitWalletsTotal();
    } else {
      if (singleContainer) singleContainer.style.display = 'block';
      if (splitSection) splitSection.style.display = 'none';
      if (isSplitInput) isSplitInput.value = '0';
      onReturnSingleWalletSelectChange();
    }
  };

  window.onReturnSingleWalletSelectChange = function() {
    const select = document.getElementById('returnSingleWalletSelect');
    const hint = document.getElementById('returnSingleWalletHint');
    const badge = document.getElementById('returnSingleWalletBadge');
    if (!select || !select.value) {
      if (hint) hint.textContent = 'Select the wallet account used to settle this return';
      if (badge) badge.style.display = 'none';
      return;
    }
    const opt = select.options[select.selectedIndex];
    const balance = parseFloat(opt.dataset.balance) || 0;
    const name = opt.dataset.name || opt.text;
    if (hint) hint.textContent = name;
    if (badge) {
      badge.textContent = 'Available: ₹' + balance.toLocaleString('en-IN', { minimumFractionDigits: 2 });
      badge.style.display = 'inline-block';
    }
    calculateAll('method');
  };

  window.onReturnSettlementCompanyChange = function() {
    const companySelect = document.getElementById('returnSettlementCompanySelect');
    const gatewaySelect = document.getElementById('returnSettlementGatewaySelect');
    if (!companySelect || !gatewaySelect) return;

    const companyId = companySelect.value;
    const currentGateway = gatewaySelect.value;
    Array.from(gatewaySelect.options).forEach(function(opt) {
      if (!opt.value) {
        opt.hidden = false;
        opt.disabled = false;
        return;
      }
      const hide = companyId !== '' && String(opt.dataset.companyId) !== String(companyId);
      opt.hidden = hide;
      opt.disabled = hide;
    });
    const selected = gatewaySelect.options[gatewaySelect.selectedIndex];
    if (selected && selected.hidden) {
      gatewaySelect.value = '';
    } else if (currentGateway && selected && selected.hidden) {
      gatewaySelect.value = '';
    }
  };

  window.onReturnSettlementGatewayChange = function() {
    const companySelect = document.getElementById('returnSettlementCompanySelect');
    const gatewaySelect = document.getElementById('returnSettlementGatewaySelect');
    if (!companySelect || !gatewaySelect || !gatewaySelect.value) return;
    const selected = gatewaySelect.options[gatewaySelect.selectedIndex];
    const companyId = selected?.dataset.companyId;
    if (companyId !== undefined && companySelect.value !== String(companyId)) {
      companySelect.value = String(companyId);
      onReturnSettlementCompanyChange();
      gatewaySelect.value = selected.value;
    }
  };

  window.onReturnSplitCheckboxToggled = function(walletId) {
    const chk = document.getElementById('return_split_check_' + walletId);
    const amtInput = document.getElementById('return_split_amt_' + walletId);
    const idInput = document.getElementById('return_split_id_' + walletId);
    const fillBtn = document.getElementById('btn_return_fill_' + walletId);

    if (chk && chk.checked) {
      if (amtInput) amtInput.disabled = false;
      if (idInput) idInput.disabled = false;
      if (fillBtn) fillBtn.disabled = false;
      if (amtInput && (!amtInput.value || parseFloat(amtInput.value) <= 0)) {
        autoFillRemainingToReturnWallet(walletId);
        return;
      }
    } else {
      if (amtInput) { amtInput.value = ''; amtInput.disabled = true; }
      if (idInput) idInput.disabled = true;
      if (fillBtn) fillBtn.disabled = true;
    }
    recalculateReturnSplitWalletsTotal();
  };

  window.autoFillRemainingToReturnWallet = function(walletId) {
    const row = document.querySelector(`.return-split-wallet-row[data-wallet-id="${walletId}"]`);
    if (!row) return;
    const balance = parseFloat(row.dataset.balance) || 0;
    const targetAmt = parseFloat(document.getElementById('returnAmount')?.value || 0) || 0;

    let otherSum = 0;
    document.querySelectorAll('.return-split-wallet-row').forEach(r => {
      const wid = r.dataset.walletId;
      if (wid != walletId) {
        const c = document.getElementById('return_split_check_' + wid);
        if (c && c.checked) {
          otherSum += parseFloat(document.getElementById('return_split_amt_' + wid)?.value || 0) || 0;
        }
      }
    });

    const needed = Math.max(0, Math.round((targetAmt - otherSum) * 100) / 100);
    const fillAmt = Math.min(needed, balance);

    const chk = document.getElementById('return_split_check_' + walletId);
    if (chk && !chk.checked) {
      chk.checked = true;
      const amtInput = document.getElementById('return_split_amt_' + walletId);
      const idInput = document.getElementById('return_split_id_' + walletId);
      const fillBtn = document.getElementById('btn_return_fill_' + walletId);
      if (amtInput) amtInput.disabled = false;
      if (idInput) idInput.disabled = false;
      if (fillBtn) fillBtn.disabled = false;
    }

    const targetInput = document.getElementById('return_split_amt_' + walletId);
    if (targetInput) targetInput.value = fillAmt.toFixed(2);
    recalculateReturnSplitWalletsTotal();
  };

  window.autoDistributeReturnSplitWallets = function() {
    const targetAmt = parseFloat(document.getElementById('returnAmount')?.value || 0) || 0;
    let remaining = targetAmt;

    document.querySelectorAll('.return-split-wallet-row').forEach(r => {
      const wid = r.dataset.walletId;
      const balance = parseFloat(r.dataset.balance) || 0;
      const chk = document.getElementById('return_split_check_' + wid);
      const amtInput = document.getElementById('return_split_amt_' + wid);
      const idInput = document.getElementById('return_split_id_' + wid);
      const fillBtn = document.getElementById('btn_return_fill_' + wid);

      if (remaining > 0.001 && balance > 0) {
        const take = Math.min(remaining, balance);
        if (chk) chk.checked = true;
        if (amtInput) { amtInput.disabled = false; amtInput.value = take.toFixed(2); }
        if (idInput) idInput.disabled = false;
        if (fillBtn) fillBtn.disabled = false;
        remaining = Math.max(0, Math.round((remaining - take) * 100) / 100);
      } else {
        if (chk) chk.checked = false;
        if (amtInput) { amtInput.value = ''; amtInput.disabled = true; }
        if (idInput) idInput.disabled = true;
        if (fillBtn) fillBtn.disabled = true;
      }
    });
    recalculateReturnSplitWalletsTotal();
  };

  window.recalculateReturnSplitWalletsTotal = function() {
    const targetAmt = parseFloat(document.getElementById('returnAmount')?.value || 0) || 0;
    let totalAllocated = 0;

    document.querySelectorAll('.return-split-wallet-row').forEach(r => {
      const wid = r.dataset.walletId;
      const chk = document.getElementById('return_split_check_' + wid);
      const amtInput = document.getElementById('return_split_amt_' + wid);

      if (chk && chk.checked && amtInput && !amtInput.disabled) {
        const amt = parseFloat(amtInput.value || 0) || 0;
        totalAllocated = Math.round((totalAllocated + amt) * 100) / 100;
      }
    });

    const diff = Math.round((targetAmt - totalAllocated) * 100) / 100;
    const reqEl = document.getElementById('returnSplitSummaryRequired');
    const allocEl = document.getElementById('returnSplitSummaryAllocated');
    const diffEl = document.getElementById('returnSplitSummaryDiff');
    const alertEl = document.getElementById('returnSplitStatusAlert');

    if (reqEl) reqEl.textContent = '₹' + targetAmt.toLocaleString('en-IN', { minimumFractionDigits: 2 });
    if (allocEl) {
      allocEl.textContent = '₹' + totalAllocated.toLocaleString('en-IN', { minimumFractionDigits: 2 });
      allocEl.className = Math.abs(diff) < 0.01 ? 'fw-bold fs-6 text-success' : 'fw-bold fs-6 text-warning';
    }
    if (diffEl) {
      diffEl.textContent = (diff >= 0 ? '₹' : '-₹') + Math.abs(diff).toLocaleString('en-IN', { minimumFractionDigits: 2 });
      diffEl.className = Math.abs(diff) < 0.01 ? 'fw-bold fs-6 text-success' : (diff > 0 ? 'fw-bold fs-6 text-warning' : 'fw-bold fs-6 text-danger');
    }
    if (alertEl) {
      if (totalAllocated === 0) {
        alertEl.className = 'small fw-semibold text-secondary';
        alertEl.textContent = 'Select wallets to allocate.';
      } else if (Math.abs(diff) < 0.01) {
        alertEl.className = 'small fw-bold text-success';
        alertEl.innerHTML = '<i class="ri-checkbox-circle-fill me-1"></i> Exact match! Ready for settlement.';
      } else if (diff > 0) {
        alertEl.className = 'small fw-bold text-warning';
        alertEl.textContent = `₹${diff.toFixed(2)} remaining to allocate.`;
      } else {
        alertEl.className = 'small fw-bold text-danger';
        alertEl.textContent = `Over-allocated by ₹${Math.abs(diff).toFixed(2)}.`;
      }
    }
  };

  // =========================================================================
  // 3. STEP 2 BILL PAYMENT WALLET LOGIC
  // =========================================================================
  window.toggleSplitMode = function(isSplit) {
    const singleContainer = document.getElementById('billSingleWalletContainer');
    const splitSection = document.getElementById('billSplitWalletsSection');
    const activeNotice = document.getElementById('billSplitActiveNotice');
    const isSplitInput = document.getElementById('billIsSplitWallet');
    const singleSelect = document.getElementById('billSingleWalletSelect');
    const hiddenWalletId = document.getElementById('hiddenBillWalletId');
    const hiddenSourceId = document.getElementById('hiddenBillPaymentSourceId');

    if (isSplit) {
      if (singleContainer) singleContainer.style.display = 'none';
      if (activeNotice) activeNotice.style.display = 'flex';
      if (splitSection) splitSection.style.display = 'block';
      if (isSplitInput) isSplitInput.value = '1';
      if (singleSelect) singleSelect.removeAttribute('required');
      if (hiddenWalletId) hiddenWalletId.value = '';
      if (hiddenSourceId) hiddenSourceId.value = '';

      document.querySelectorAll('.split-wallet-check').forEach(chk => {
        const wid = chk.dataset.walletId;
        const amtInput = document.getElementById('split_amt_' + wid);
        const idInput = document.getElementById('split_id_' + wid);
        const fillBtn = document.getElementById('btn_fill_' + wid);
        if (chk.checked) {
          if (amtInput) amtInput.disabled = false;
          if (idInput) idInput.disabled = false;
          if (fillBtn) fillBtn.disabled = false;
        } else {
          if (amtInput) amtInput.disabled = true;
          if (idInput) idInput.disabled = true;
          if (fillBtn) fillBtn.disabled = true;
        }
      });
      recalculateSplitWalletsTotal();
    } else {
      if (singleContainer) singleContainer.style.display = 'block';
      if (activeNotice) activeNotice.style.display = 'none';
      if (splitSection) splitSection.style.display = 'none';
      if (isSplitInput) isSplitInput.value = '0';
      if (singleSelect) singleSelect.setAttribute('required', 'required');

      document.querySelectorAll('.split-wallet-amt').forEach(inp => inp.disabled = true);
      document.querySelectorAll('input[name^="split_wallets"][name$="[wallet_id]"]').forEach(inp => inp.disabled = true);
      onSingleWalletSelectChange();
    }
  };

  window.onSingleWalletSelectChange = function() {
    const singleSelect = document.getElementById('billSingleWalletSelect');
    const hiddenWalletId = document.getElementById('hiddenBillWalletId');
    const hiddenSourceId = document.getElementById('hiddenBillPaymentSourceId');
    const hintText = document.getElementById('singleWalletHintText');
    const badge = document.getElementById('singleWalletBadge');
    const shortfallAlert = document.getElementById('singleWalletShortfallAlert');
    const amountInput = document.getElementById('billPaymentAmount');
    const detailsBox = document.getElementById('billDebitWalletDetails');
    const detailsWallet = document.getElementById('billDebitWalletName');
    const detailsSource = document.getElementById('billDebitSourceName');

    if (!singleSelect || !singleSelect.value) {
      if (hiddenWalletId) hiddenWalletId.value = '';
      if (hiddenSourceId) hiddenSourceId.value = '';
      if (hintText) hintText.textContent = 'Select a wallet account above';
      if (badge) badge.style.display = 'none';
      if (shortfallAlert) shortfallAlert.style.display = 'none';
      if (detailsBox) detailsBox.style.display = 'none';
      return;
    }

    const val = singleSelect.value;
    const opt = singleSelect.options[singleSelect.selectedIndex];
    const sourceName = (opt.dataset.sourceName || '').trim();
    const sourceType = (opt.dataset.sourceType || '').trim();
    const sourceLabel = sourceName
      ? (sourceType ? sourceName + ' (' + sourceType + ')' : sourceName)
      : '—';

    if (val.startsWith('ext_')) {
      const extId = val.replace('ext_', '');
      if (hiddenWalletId) hiddenWalletId.value = '';
      if (hiddenSourceId) hiddenSourceId.value = extId;
      if (hintText) hintText.textContent = sourceName ? ('Bill Payment Source: ' + sourceName) : 'External gateway or bank account payout';
      if (badge) badge.style.display = 'none';
      if (shortfallAlert) shortfallAlert.style.display = 'none';
      if (detailsBox) detailsBox.style.display = 'block';
      if (detailsWallet) detailsWallet.textContent = '—';
      if (detailsSource) detailsSource.textContent = sourceLabel;
      return;
    }

    if (hiddenWalletId) hiddenWalletId.value = val;
    if (hiddenSourceId) hiddenSourceId.value = opt.dataset.sourceId || '';

    const balance = parseFloat(opt.dataset.balance) || 0;
    const name = opt.dataset.name || opt.text;
    const billAmt = parseFloat(amountInput ? amountInput.value : 0) || 0;

    if (hintText) hintText.textContent = sourceName ? (name + ' · ' + sourceName) : name;
    if (detailsBox) detailsBox.style.display = 'block';
    if (detailsWallet) detailsWallet.textContent = name;
    if (detailsSource) detailsSource.textContent = sourceLabel;
    if (badge) {
      badge.textContent = 'Available: ₹' + balance.toLocaleString('en-IN', { minimumFractionDigits: 2 });
      badge.className = balance >= billAmt ? 'badge bg-label-success fs-tiny' : 'badge bg-label-danger fs-tiny';
      badge.style.display = 'inline-block';
    }

    if (shortfallAlert) {
      shortfallAlert.style.display = (billAmt > balance) ? 'block' : 'none';
    }
  };

  window.onSplitCheckboxToggled = function(walletId) {
    const chk = document.getElementById('split_check_' + walletId);
    const amtInput = document.getElementById('split_amt_' + walletId);
    const idInput = document.getElementById('split_id_' + walletId);
    const fillBtn = document.getElementById('btn_fill_' + walletId);

    if (chk && chk.checked) {
      if (amtInput) amtInput.disabled = false;
      if (idInput) idInput.disabled = false;
      if (fillBtn) fillBtn.disabled = false;
      if (amtInput && (!amtInput.value || parseFloat(amtInput.value) <= 0)) {
        autoFillRemainingToWallet(walletId);
        return;
      }
    } else {
      if (amtInput) { amtInput.value = ''; amtInput.disabled = true; }
      if (idInput) idInput.disabled = true;
      if (fillBtn) fillBtn.disabled = true;
      const errorDiv = document.getElementById('split_error_' + walletId);
      if (errorDiv) errorDiv.style.display = 'none';
    }
    recalculateSplitWalletsTotal();
  };

  window.autoFillRemainingToWallet = function(walletId) {
    const row = document.querySelector(`.split-wallet-row[data-wallet-id="${walletId}"]`);
    if (!row) return;
    const balance = parseFloat(row.dataset.balance) || 0;
    const billAmt = parseFloat(document.getElementById('billPaymentAmount')?.value || 0) || 0;

    let otherSum = 0;
    document.querySelectorAll('.split-wallet-row').forEach(r => {
      const wid = r.dataset.walletId;
      if (wid != walletId) {
        const c = document.getElementById('split_check_' + wid);
        if (c && c.checked) {
          otherSum += parseFloat(document.getElementById('split_amt_' + wid)?.value || 0) || 0;
        }
      }
    });

    const needed = Math.max(0, Math.round((billAmt - otherSum) * 100) / 100);
    const fillAmt = Math.min(needed, balance);

    const chk = document.getElementById('split_check_' + walletId);
    if (chk && !chk.checked) {
      chk.checked = true;
      const amtInput = document.getElementById('split_amt_' + walletId);
      const idInput = document.getElementById('split_id_' + walletId);
      const fillBtn = document.getElementById('btn_fill_' + walletId);
      if (amtInput) amtInput.disabled = false;
      if (idInput) idInput.disabled = false;
      if (fillBtn) fillBtn.disabled = false;
    }

    const targetInput = document.getElementById('split_amt_' + walletId);
    if (targetInput) targetInput.value = fillAmt.toFixed(2);
    recalculateSplitWalletsTotal();
  };

  window.autoDistributeSplitWallets = function() {
    const billAmt = parseFloat(document.getElementById('billPaymentAmount')?.value || 0) || 0;
    let remaining = billAmt;

    document.querySelectorAll('.split-wallet-row').forEach(r => {
      const wid = r.dataset.walletId;
      const balance = parseFloat(r.dataset.balance) || 0;
      const chk = document.getElementById('split_check_' + wid);
      const amtInput = document.getElementById('split_amt_' + wid);
      const idInput = document.getElementById('split_id_' + wid);
      const fillBtn = document.getElementById('btn_fill_' + wid);

      if (remaining > 0.001 && balance > 0) {
        const take = Math.min(remaining, balance);
        if (chk) chk.checked = true;
        if (amtInput) { amtInput.disabled = false; amtInput.value = take.toFixed(2); }
        if (idInput) idInput.disabled = false;
        if (fillBtn) fillBtn.disabled = false;
        remaining = Math.max(0, Math.round((remaining - take) * 100) / 100);
      } else {
        if (chk) chk.checked = false;
        if (amtInput) { amtInput.value = ''; amtInput.disabled = true; }
        if (idInput) idInput.disabled = true;
        if (fillBtn) fillBtn.disabled = true;
      }
    });
    recalculateSplitWalletsTotal();
  };

  window.recalculateSplitWalletsTotal = function() {
    const billAmt = parseFloat(document.getElementById('billPaymentAmount')?.value || 0) || 0;
    let totalAllocated = 0;
    let hasOverBalanceError = false;

    document.querySelectorAll('.split-wallet-row').forEach(r => {
      const wid = r.dataset.walletId;
      const balance = parseFloat(r.dataset.balance) || 0;
      const chk = document.getElementById('split_check_' + wid);
      const amtInput = document.getElementById('split_amt_' + wid);
      const errorDiv = document.getElementById('split_error_' + wid);

      if (chk && chk.checked && amtInput && !amtInput.disabled) {
        const amt = parseFloat(amtInput.value || 0) || 0;
        totalAllocated = Math.round((totalAllocated + amt) * 100) / 100;

        if (amt > balance) {
          hasOverBalanceError = true;
          amtInput.classList.add('is-invalid');
          if (errorDiv) {
            errorDiv.textContent = `Exceeds available balance (₹${balance.toFixed(2)})`;
            errorDiv.style.display = 'block';
          }
        } else {
          amtInput.classList.remove('is-invalid');
          if (errorDiv) errorDiv.style.display = 'none';
        }
      } else {
        if (amtInput) amtInput.classList.remove('is-invalid');
        if (errorDiv) errorDiv.style.display = 'none';
      }
    });

    const diff = Math.round((billAmt - totalAllocated) * 100) / 100;
    const reqEl = document.getElementById('splitSummaryRequired');
    const allocEl = document.getElementById('splitSummaryAllocated');
    const diffEl = document.getElementById('splitSummaryDiff');
    const alertEl = document.getElementById('splitStatusAlert');

    if (reqEl) reqEl.textContent = '₹' + billAmt.toLocaleString('en-IN', { minimumFractionDigits: 2 });
    if (allocEl) {
      allocEl.textContent = '₹' + totalAllocated.toLocaleString('en-IN', { minimumFractionDigits: 2 });
      allocEl.className = (Math.abs(diff) < 0.01 && !hasOverBalanceError) ? 'fw-bold fs-6 text-success' : 'fw-bold fs-6 text-warning';
    }
    if (diffEl) {
      diffEl.textContent = (diff >= 0 ? '₹' : '-₹') + Math.abs(diff).toLocaleString('en-IN', { minimumFractionDigits: 2 });
      diffEl.className = Math.abs(diff) < 0.01 ? 'fw-bold fs-6 text-success' : (diff > 0 ? 'fw-bold fs-6 text-warning' : 'fw-bold fs-6 text-danger');
    }

    if (alertEl) {
      if (hasOverBalanceError) {
        alertEl.className = 'alert alert-danger py-2 px-3 mb-0 small d-flex align-items-center gap-2';
        alertEl.innerHTML = '<i class="ri-error-warning-fill fs-5"></i> <span><strong>Error:</strong> Allocation exceeds available wallet balance.</span>';
      } else if (totalAllocated === 0) {
        alertEl.className = 'alert alert-secondary py-2 px-3 mb-0 small';
        alertEl.textContent = 'Check wallet accounts above and allocate payment amounts.';
      } else if (Math.abs(diff) < 0.01) {
        alertEl.className = 'alert alert-success py-2 px-3 mb-0 small d-flex align-items-center gap-2';
        alertEl.innerHTML = `<i class="ri-checkbox-circle-fill fs-5"></i> <span><strong>Ready to Pay:</strong> Perfect allocation! Total ₹${billAmt.toLocaleString('en-IN', { minimumFractionDigits: 2 })} matches exactly.</span>`;
      } else if (diff > 0) {
        alertEl.className = 'alert alert-warning py-2 px-3 mb-0 small d-flex align-items-center gap-2';
        alertEl.innerHTML = `<i class="ri-alert-fill fs-5"></i> <span><strong>₹${diff.toLocaleString('en-IN', { minimumFractionDigits: 2 })} remaining</strong> to be allocated.</span>`;
      } else {
        alertEl.className = 'alert alert-danger py-2 px-3 mb-0 small d-flex align-items-center gap-2';
        alertEl.innerHTML = `<i class="ri-error-warning-fill fs-5"></i> <span><strong>Over-allocated by ₹${Math.abs(diff).toLocaleString('en-IN', { minimumFractionDigits: 2 })}!</strong> Exceeds bill amount.</span>`;
      }
    }
  };

  const billAmtInput = document.getElementById('billPaymentAmount');
  if (billAmtInput) {
    billAmtInput.addEventListener('input', function() {
      onSingleWalletSelectChange();
      recalculateSplitWalletsTotal();
    });
  }

  // =========================================================================
  // 4. CONFIRMATION MODALS (STEP 2 & STEP 3)
  // =========================================================================
  window.openConfirmBillPaymentModal = function() {
    const form = document.getElementById('billPaymentForm');
    if (!form || !form.reportValidity()) return;

    const amountInput = document.getElementById('billPaymentAmount');
    const amountVal = parseFloat(amountInput ? amountInput.value : 0) || 0;
    const formattedAmount = amountVal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const isSplit = document.getElementById('billIsSplitWallet')?.value === '1';
    let sourceText = 'Not selected';
    let splitBreakdownHtml = '';
    let showSplitRow = false;

    if (isSplit) {
      let totalAllocated = 0;
      const breakdownItems = [];
      let hasOverBalance = false;

      document.querySelectorAll('.split-wallet-row').forEach(r => {
        const wid = r.dataset.walletId;
        const balance = parseFloat(r.dataset.balance) || 0;
        const chk = document.getElementById('split_check_' + wid);
        const amtInput = document.getElementById('split_amt_' + wid);
        const wName = r.querySelector('label')?.textContent.trim() || 'Wallet';

        if (chk && chk.checked && amtInput && !amtInput.disabled) {
          const amt = parseFloat(amtInput.value || 0) || 0;
          if (amt > 0) {
            totalAllocated = Math.round((totalAllocated + amt) * 100) / 100;
            if (amt > balance) hasOverBalance = true;
            breakdownItems.push({ name: wName, amount: amt });
          }
        }
      });

      if (breakdownItems.length === 0) {
        alert('Please select and allocate amounts to at least one wallet account.');
        return;
      }
      if (hasOverBalance) {
        alert('One or more wallet allocations exceed available balance.');
        return;
      }
      if (Math.abs(amountVal - totalAllocated) > 0.01) {
        alert(`Allocated total (₹${totalAllocated.toFixed(2)}) must match bill amount (₹${amountVal.toFixed(2)}).`);
        return;
      }

      sourceText = `Split Wallets (${breakdownItems.length} accounts)`;
      showSplitRow = true;
      splitBreakdownHtml = breakdownItems.map(item => 
        `<div class="d-flex justify-content-between py-1 border-bottom border-light">
          <span><i class="ri-wallet-3-line text-primary me-1"></i> ${item.name}</span>
          <strong class="text-primary">₹${item.amount.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</strong>
        </div>`
      ).join('');
    } else {
      const walletSelect = document.getElementById('billSingleWalletSelect');
      if (!walletSelect || !walletSelect.value) {
        alert('Please select a wallet account to debit.');
        return;
      }
      const opt = walletSelect.options[walletSelect.selectedIndex];
      if (walletSelect.value.startsWith('ext_')) {
        sourceText = opt.dataset.sourceName
          ? ('Bill Payment Source: ' + opt.dataset.sourceName)
          : opt.text.trim();
      } else {
        const walletName = opt.dataset.name || opt.text;
        const sourceName = (opt.dataset.sourceName || '').trim();
        const avail = (parseFloat(opt.dataset.balance) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
        sourceText = sourceName
          ? `${walletName} · ${sourceName} (₹${avail} avail)`
          : `${walletName} (₹${avail} avail)`;
      }
    }

    const utrInput = document.getElementById('billPaymentUtr');
    const utrText = (utrInput && utrInput.value.trim()) ? utrInput.value.trim() : 'Not Specified';

    const elAmount = document.getElementById('confirmModalAmountText');
    const elSubAmount = document.getElementById('confirmModalAmountSubText');
    const elSource = document.getElementById('confirmModalSourceText');
    const elSplitRow = document.getElementById('confirmModalSplitBreakdownRow');
    const elSplitText = document.getElementById('confirmModalSplitBreakdownText');
    const elUtr = document.getElementById('confirmModalUtrText');

    if (elAmount) elAmount.textContent = formattedAmount;
    if (elSubAmount) elSubAmount.textContent = formattedAmount;
    if (elSource) elSource.textContent = sourceText;
    if (elUtr) elUtr.textContent = utrText;

    if (elSplitRow && elSplitText) {
      if (showSplitRow) {
        elSplitText.innerHTML = splitBreakdownHtml;
        elSplitRow.style.display = 'block';
      } else {
        elSplitRow.style.display = 'none';
      }
    }

    const modalEl = document.getElementById('modalConfirmBillPayment');
    if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
  };

  window.confirmAndSubmitBillPayment = function() {
    const form = document.getElementById('billPaymentForm');
    const btn = document.getElementById('btnSubmitBillPayment');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
    }
    if (form) {
      form.dataset.confirmed = 'true';
      form.submit();
    }
  };

  window.openConfirmSwipeProcessModal = function() {
    const form = document.getElementById('swipeProcessForm');
    if (!form || !form.reportValidity()) return;

    const amt = parseFloat(document.getElementById('swipeAmount')?.value || 0) || 0;
    const chg = parseFloat(document.getElementById('swipeCharges')?.value || 0) || 0;
    const pct = parseFloat(document.getElementById('swipeChargesPct')?.value || 0) || 0;
    const net = Math.max(0, Math.round((amt - chg) * 100) / 100);

    const gwSelect = document.getElementById('withdrawalGatewaySelect');
    const gwText = (gwSelect && gwSelect.selectedIndex >= 0 && gwSelect.value) ? gwSelect.options[gwSelect.selectedIndex].text.trim() : 'Not Selected';

    const isSplit = document.getElementById('swipeIsSplitWallet')?.value === '1';
    let wText = 'Not Selected';
    let splitHtml = '';
    let showSplit = false;

    if (isSplit) {
      let totalAlloc = 0;
      const items = [];
      document.querySelectorAll('.swipe-split-wallet-row').forEach(r => {
        const wid = r.dataset.walletId;
        const c = document.getElementById('swipe_split_check_' + wid);
        const a = document.getElementById('swipe_split_amt_' + wid);
        const name = r.querySelector('label')?.textContent.trim() || 'Wallet';
        if (c && c.checked && a && !a.disabled) {
          const val = parseFloat(a.value || 0) || 0;
          if (val > 0) {
            totalAlloc += val;
            items.push({ name, val });
          }
        }
      });
      if (items.length === 0) {
        alert('Please allocate swipe amounts to at least one wallet.');
        return;
      }
      if (Math.abs(amt - totalAlloc) > 0.01) {
        alert(`Total allocated (₹${totalAlloc.toFixed(2)}) must match swipe amount (₹${amt.toFixed(2)}).`);
        return;
      }
      wText = `Split Wallets (${items.length} accounts)`;
      showSplit = true;
      splitHtml = items.map(i => `<div class="d-flex justify-content-between py-1"><span>${i.name}</span><strong class="text-info">₹${i.val.toFixed(2)}</strong></div>`).join('');
    } else {
      const wSelect = document.getElementById('swipeWalletSelect');
      wText = (wSelect && wSelect.selectedIndex >= 0 && wSelect.value) ? wSelect.options[wSelect.selectedIndex].text.trim() : 'Not Selected';
    }

    const elHeader = document.getElementById('confirmSwipeAmountHeader');
    const elGross = document.getElementById('confirmSwipeGrossText');
    const elChg = document.getElementById('confirmSwipeChargesText');
    const elPctBadge = document.getElementById('confirmSwipePctBadge');
    const elNet = document.getElementById('confirmSwipeNetText');
    const elGw = document.getElementById('confirmSwipeGatewayText');
    const elW = document.getElementById('confirmSwipeWalletText');
    const elSplitRow = document.getElementById('confirmSwipeSplitBreakdownRow');
    const elSplitText = document.getElementById('confirmSwipeSplitBreakdownText');

    if (elHeader) elHeader.textContent = amt.toLocaleString('en-IN', { minimumFractionDigits: 2 });
    if (elGross) elGross.textContent = amt.toLocaleString('en-IN', { minimumFractionDigits: 2 });
    if (elChg) elChg.textContent = chg.toLocaleString('en-IN', { minimumFractionDigits: 2 });
    if (elNet) elNet.textContent = '₹' + net.toLocaleString('en-IN', { minimumFractionDigits: 2 });
    if (elGw) elGw.textContent = gwText;
    if (elW) elW.textContent = wText;

    if (elSplitRow && elSplitText) {
      if (showSplit) {
        elSplitText.innerHTML = splitHtml;
        elSplitRow.style.display = 'block';
      } else {
        elSplitRow.style.display = 'none';
      }
    }

    if (elPctBadge) {
      if (pct > 0) {
        elPctBadge.textContent = pct + '%';
        elPctBadge.style.display = 'inline-block';
      } else {
        elPctBadge.style.display = 'none';
      }
    }

    const modalEl = document.getElementById('modalConfirmSwipeProcess');
    if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
  };

  window.confirmAndSubmitSwipeProcess = function() {
    const form = document.getElementById('swipeProcessForm');
    const btn = document.getElementById('btnSubmitSwipeProcess');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing Swipe...';
    }
    if (form) {
      form.dataset.confirmed = 'true';
      form.submit();
    }
  };

  window.openConfirmReturnSettlementModal = function() {
    const form = document.getElementById('returnSettlementForm');
    if (!form || !form.reportValidity()) return;

    const grossVal = parseFloat(document.getElementById('grossAmount')?.value || 0) || 0;
    const companySelect = document.getElementById('returnSettlementCompanySelect');
    const gatewaySelect = document.getElementById('returnSettlementGatewaySelect');
    const returnVal = parseFloat(document.getElementById('returnAmount')?.value || 0) || 0;
    const chargesVal = Math.max(0, Math.round((grossVal - returnVal) * 100) / 100);

    const selectedMethod = document.querySelector('input[name="return_method"]:checked')?.value || 'wallet';

    let methodSummary = '';
    let destinationText = '';
    let showDestination = false;
    let walletText = '';
    let showWalletRow = false;
    let showSplitWalletRow = false;
    let splitWalletHtml = '';

    if (selectedMethod === 'wallet') {
      const isSplitWallet = document.getElementById('returnIsSplitWallet')?.value === '1';
      if (isSplitWallet) {
        let totalAlloc = 0;
        const items = [];
        document.querySelectorAll('.return-split-wallet-row').forEach(r => {
          const wid = r.dataset.walletId;
          const c = document.getElementById('return_split_check_' + wid);
          const a = document.getElementById('return_split_amt_' + wid);
          const name = r.querySelector('label')?.textContent.trim() || 'Wallet';
          if (c && c.checked && a && !a.disabled) {
            const val = parseFloat(a.value || 0) || 0;
            if (val > 0) {
              totalAlloc += val;
              items.push({ name, val });
            }
          }
        });
        if (items.length === 0) {
          alert('Please allocate return settlement amounts to at least one wallet.');
          return;
        }
        if (Math.abs(returnVal - totalAlloc) > 0.01) {
          alert(`Total allocated (₹${totalAlloc.toFixed(2)}) must match return amount (₹${returnVal.toFixed(2)}).`);
          return;
        }
        methodSummary = `Split Wallets (${items.length} accounts): ₹${returnVal.toLocaleString('en-IN', {minimumFractionDigits: 2})}`;
        showSplitWalletRow = true;
        splitWalletHtml = items.map(i => `<div class="d-flex justify-content-between py-1"><span>${i.name}</span><strong class="text-primary">₹${i.val.toFixed(2)}</strong></div>`).join('');
      } else {
        const wSelect = document.getElementById('returnSingleWalletSelect');
        if (!wSelect || !wSelect.value) {
          alert('Please select a wallet account for the return settlement.');
          return;
        }
        const companyName = (companySelect && companySelect.selectedIndex > 0)
          ? companySelect.options[companySelect.selectedIndex].text.trim()
          : '';
        const gatewayName = (gatewaySelect && gatewaySelect.value)
          ? (gatewaySelect.options[gatewaySelect.selectedIndex].dataset.gateway || gatewaySelect.options[gatewaySelect.selectedIndex].text.trim())
          : '';
        const walletName = wSelect.options[wSelect.selectedIndex].text.trim();
        walletText = [companyName, gatewayName, walletName].filter(Boolean).join(' – ');
        showWalletRow = true;
        methodSummary = `Wallet: ₹${returnVal.toLocaleString('en-IN', {minimumFractionDigits: 2})}`;
      }
    } else if (selectedMethod === 'split') {
      const cardAmt = parseFloat(document.getElementById('cardAmtInput')?.value || 0) || 0;
      const cashAmt = parseFloat(document.getElementById('cashAmtInput')?.value || 0) || 0;
      if (Math.abs((cardAmt + cashAmt) - returnVal) > 0.01) {
        alert('Card/Wallet and Cash amounts must add up to the total return of ₹' + returnVal.toFixed(2) + '.');
        return;
      }
      methodSummary = `Split: Card/Wallet ₹${cardAmt.toLocaleString('en-IN', {minimumFractionDigits: 2})} + Cash ₹${cashAmt.toLocaleString('en-IN', {minimumFractionDigits: 2})}`;
    } else if (selectedMethod === 'upi') {
      const upiInput = document.querySelector('input[name="upi_id"]');
      const upiVal = upiInput?.value.trim() || 'N/A';
      methodSummary = `UPI: ₹${returnVal.toLocaleString('en-IN', {minimumFractionDigits: 2})}`;
      destinationText = `VPA: ${upiVal}`;
      showDestination = true;
    } else if (selectedMethod === 'imps') {
      const accInput = document.querySelector('input[name="account_number"]');
      const bankInput = document.querySelector('input[name="bank_name"]');
      const ifscInput = document.querySelector('input[name="ifsc_code"]');
      methodSummary = `IMPS / Bank: ₹${returnVal.toLocaleString('en-IN', {minimumFractionDigits: 2})}`;
      destinationText = `${bankInput?.value || 'Bank'} A/c: ${accInput?.value || 'N/A'} (IFSC: ${ifscInput?.value || 'N/A'})`;
      showDestination = true;
    } else if (selectedMethod === 'other') {
      methodSummary = `Cash: ₹${returnVal.toLocaleString('en-IN', {minimumFractionDigits: 2})}`;
    } else {
      methodSummary = `Card: ₹${returnVal.toLocaleString('en-IN', {minimumFractionDigits: 2})}`;
    }

    const elGross = document.getElementById('confirmGrossAmountText');
    const elCharges = document.getElementById('confirmChargesText');
    const elMethod = document.getElementById('confirmMethodText');
    const elReturn = document.getElementById('confirmReturnAmountText');
    const elReturnInline = document.getElementById('confirmReturnAmountInline');
    const elDestRow = document.getElementById('confirmDestinationRow');
    const elDest = document.getElementById('confirmDestinationText');
    const elWalletRow = document.getElementById('confirmReturnWalletRow');
    const elWalletText = document.getElementById('confirmReturnWalletText');
    const elSplitRow = document.getElementById('confirmReturnSplitBreakdownRow');
    const elSplitText = document.getElementById('confirmReturnSplitBreakdownText');

    if (elGross) elGross.textContent = grossVal.toLocaleString('en-IN', {minimumFractionDigits: 2});
    if (elCharges) elCharges.textContent = chargesVal.toLocaleString('en-IN', {minimumFractionDigits: 2});
    if (elMethod) elMethod.textContent = methodSummary;
    if (elReturn) elReturn.textContent = returnVal.toLocaleString('en-IN', {minimumFractionDigits: 2});
    if (elReturnInline) elReturnInline.textContent = returnVal.toLocaleString('en-IN', {minimumFractionDigits: 2});

    if (elWalletRow && elWalletText) {
      if (showWalletRow) {
        elWalletText.textContent = walletText;
        elWalletRow.style.display = 'flex';
      } else {
        elWalletRow.style.display = 'none';
      }
    }

    if (elSplitRow && elSplitText) {
      if (showSplitWalletRow) {
        elSplitText.innerHTML = splitWalletHtml;
        elSplitRow.style.display = 'block';
      } else {
        elSplitRow.style.display = 'none';
      }
    }

    if (elDestRow && elDest) {
      if (showDestination) {
        elDest.textContent = destinationText;
        elDestRow.style.display = 'flex';
      } else {
        elDestRow.style.display = 'none';
      }
    }

    const modalEl = document.getElementById('modalConfirmReturnSettlement');
    if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
  };

  window.confirmAndSubmitReturnSettlement = function() {
    const form = document.getElementById('returnSettlementForm');
    const btn = document.getElementById('btnSubmitReturnSettlement');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Completing Lead...';
    }
    if (form) {
      form.dataset.confirmed = 'true';
      form.submit();
    }
  };

  // Activity Audit Timeline Dropdown Filter & Expand/Collapse All
  const filterActivityAction = document.getElementById('filterActivityAction');
  if (filterActivityAction) {
    filterActivityAction.addEventListener('change', function() {
      const selected = this.value;
      const items = document.querySelectorAll('.activity-log-item');
      let visibleCount = 0;

      items.forEach(item => {
        const cat = item.dataset.category || '';
        const action = item.dataset.action || '';
        let match = false;

        if (selected === 'all') match = true;
        else if (selected === 'status_changed') match = (action === 'status_changed' && cat === 'status_changed');
        else if (selected === 'payment') match = (cat === 'payment');
        else if (selected === 'settlement') match = (cat === 'settlement');
        else if (selected === 'leads') match = (cat === 'leads');

        if (match) {
          item.style.display = '';
          visibleCount++;
        } else {
          item.style.display = 'none';
        }
      });

      const noMatches = document.getElementById('noActivityMatches');
      if (noMatches) noMatches.style.display = (visibleCount === 0 && items.length > 0) ? 'block' : 'none';
    });
  }

  const btnToggleAll = document.getElementById('btnToggleAllAuditLogs');
  if (btnToggleAll) {
    let allExpanded = false;
    btnToggleAll.addEventListener('click', function() {
      const collapses = document.querySelectorAll('.log-details-collapse');
      allExpanded = !allExpanded;
      collapses.forEach(el => {
        const instance = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
        if (allExpanded) instance.show();
        else instance.hide();
      });
      this.classList.toggle('btn-primary', allExpanded);
      this.classList.toggle('btn-outline-secondary', !allExpanded);
    });
  }

  // Initial runs on load
  calculateAll();
  if (typeof onWithdrawalGatewaySelectChange === 'function') {
    onWithdrawalGatewaySelectChange();
  }
  if (typeof toggleSplitMode === 'function') {
    const isSplit = document.getElementById('toggleSplitWallets')?.checked || false;
    toggleSplitMode(isSplit);
  }
  if (typeof toggleSwipeSplitMode === 'function') {
    const isSwipeSplit = document.getElementById('toggleSwipeSplitWallets')?.checked || false;
    toggleSwipeSplitMode(isSwipeSplit);
  }
  if (typeof toggleReturnSplitMode === 'function') {
    const isReturnSplit = document.getElementById('toggleReturnSplitWallets')?.checked || false;
    toggleReturnSplitMode(isReturnSplit);
  }

  document.addEventListener('wheel', function(e) {
    if (document.activeElement && document.activeElement.type === 'number') {
      document.activeElement.blur();
    }
  }, { passive: false });
});
</script>

<style>
.audit-timeline-card-arrow, .log-chevron-icon {
  transition: transform 0.2s ease-in-out;
}
.collapsed .audit-timeline-card-arrow {
  transform: rotate(-90deg);
}
[aria-expanded="true"] > .log-chevron-icon,
[aria-expanded="true"] .log-chevron-icon {
  transform: rotate(180deg);
}
.activity-log-item .card-header:hover {
  background-color: rgba(67, 89, 113, 0.04) !important;
}
</style>
@endsection
