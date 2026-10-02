{{-- Full Payment Modal --}}
<div class="modal fade" id="chitPayModal" tabindex="-1" aria-labelledby="chitPayModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <form id="chitPayForm" method="POST" action="" class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            @csrf
            <input type="hidden" name="client_id" id="chitPayClientId">
            <input type="hidden" name="payment_type" value="full">

            {{-- Header --}}
            <div class="modal-header bg-label-primary px-3 px-sm-4 py-3 border-bottom border-primary border-opacity-10">
                <div class="d-flex align-items-center gap-2_5">
                    <div class="avatar avatar-xs bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-xs">
                        <i class="icon-base ri ri-money-dollar-circle-line"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-primary mb-0 fs-6" id="chitPayModalLabel">
                            Pay Installment <span id="chitPayPeriodLabel" class="fw-normal opacity-75"></span>
                        </h5>
                        <small class="text-primary opacity-75" style="font-size:0.75rem;">Full installment payment collection</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-3 p-sm-4">
                {{-- Client Info Card --}}
                <div class="card bg-label-secondary border-0 shadow-none mb-3">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold d-block" style="font-size:0.68rem; letter-spacing: 0.5px;">Client</small>
                                <input type="text" class="form-control-plaintext fw-bold text-dark p-0 border-0 fs-6" id="chitPayClient" readonly>
                            </div>
                            <span class="badge bg-label-primary px-2_5 py-1">Full Payment</span>
                        </div>
                    </div>
                </div>

                {{-- Multi-Seat Mode Container --}}
                <div class="mb-3 d-none" id="chitPaySeatOptionContainer">
                    <div class="card bg-label-secondary border border-secondary border-opacity-20 shadow-none">
                        <div class="card-body p-3">
                            <label class="form-label fw-semibold text-dark mb-2 small">Seat Payment Mode (Multiple Seats)</label>
                            <div class="row g-2">
                                <div class="col-12 col-sm-6">
                                    <div class="form-check bg-white rounded p-2.5 border">
                                        <input class="form-check-input chit-seat-mode-radio me-2" type="radio" name="single_seat_only" id="chitPaySeatCumulative" value="0" checked>
                                        <label class="form-check-label fw-medium text-dark small" for="chitPaySeatCumulative">
                                            <strong class="text-primary">Cumulative</strong>
                                            <span class="d-block text-muted" style="font-size:0.7rem;">All seats (<span id="chitPaySeatsListLabel"></span>)</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="form-check bg-white rounded p-2.5 border">
                                        <input class="form-check-input chit-seat-mode-radio me-2" type="radio" name="single_seat_only" id="chitPaySeatSingle" value="1">
                                        <label class="form-check-label fw-medium text-dark small" for="chitPaySeatSingle">
                                            <strong>Single Seat</strong>
                                            <span class="d-block text-muted" style="font-size:0.7rem;">Selected seat only</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Mobile Responsive Financial Breakdown Grid --}}
                <div class="row g-2 mb-3">
                    <div class="col-12 col-sm-4">
                        <div class="card bg-label-secondary border-0 shadow-none text-center h-100">
                            <div class="card-body p-2_5">
                                <small class="text-muted d-block" style="font-size:0.7rem;">Installment</small>
                                <div class="d-flex align-items-center justify-content-center text-nowrap mt-1">
                                    <span class="fw-bold text-dark fs-6 me-0_5">₹</span>
                                    <input type="text" class="form-control-plaintext fw-bold text-dark p-0 border-0 m-0 text-center w-auto fs-6" id="chitPayInstallmentAmount" readonly value="0.00" style="max-width: 140px;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="card bg-label-secondary border-0 shadow-none text-center h-100">
                            <div class="card-body p-2_5">
                                <small class="text-muted d-block" style="font-size:0.7rem;">Penalty</small>
                                <div class="d-flex align-items-center justify-content-center text-nowrap mt-1">
                                    <span class="fw-bold text-dark fs-6 me-0_5">₹</span>
                                    <input type="text" class="form-control-plaintext fw-bold text-dark p-0 border-0 m-0 text-center w-auto fs-6" id="chitPayPenaltyAmount" readonly value="0.00" style="max-width: 140px;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="card bg-label-primary border border-primary border-opacity-20 shadow-none text-center h-100">
                            <div class="card-body p-2_5">
                                <small class="text-primary fw-semibold d-block" style="font-size:0.7rem;">Total Payable</small>
                                <div class="d-flex align-items-center justify-content-center text-nowrap mt-1">
                                    <span class="fw-bold text-primary fs-6 me-0_5">₹</span>
                                    <input type="number" step="0.01" class="form-control-plaintext fw-bold text-primary p-0 border-0 m-0 text-center w-auto fs-6" id="chitPayPaidAmount" name="paid_amount" readonly required value="0.00" style="max-width: 140px;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Payment Details Section --}}
                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label for="chitPayPaidDate" class="form-label fw-medium text-dark small mb-1">Paid Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm" id="chitPayPaidDate" name="paid_date" required>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label for="chitPayPaymentMethod" class="form-label fw-medium text-dark small mb-1">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm no-search" id="chitPayPaymentMethod" name="payment_mode" required>
                            <option value="in_hand" selected>Cash in hand</option>
                            <option value="wallet">Customer Wallet</option>
                            <option value="upi">UPI / GPay / QR</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                </div>

                {{-- Wallet Balance Info --}}
                <div class="alert alert-primary bg-label-primary border-0 py-2 d-none mb-3" id="chitPayWalletInfo">
                    <div class="small text-primary">
                        <i class="icon-base ri ri-wallet-3-line me-1"></i>
                        <span>Wallet Balance: <strong id="chitPayWalletBalance">—</strong></span>
                    </div>
                </div>

                {{-- Bank Account Selector --}}
                <div class="mb-3 d-none" id="chitPayBankAccountContainer">
                    <label for="chitPayBankAccount" class="form-label fw-medium text-dark small mb-1">Collection Bank Account <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm no-search" id="chitPayBankAccount" name="internal_bank_account_id">
                        <option value="">-- Select Bank Account --</option>
                        @foreach(($bankAccounts ?? []) as $bank)
                            <option value="{{ $bank->id }}"
                                    data-bank-name="{{ $bank->bank_name }}"
                                    data-account-name="{{ $bank->account_name }}"
                                    data-account-number="{{ $bank->account_number }}"
                                    data-branch-name="{{ $bank->branch_name }}"
                                    data-account-type="{{ $bank->account_type }}"
                                    data-ifsc="{{ $bank->effective_ifsc }}"
                                    data-upi-id="{{ $bank->upi_id }}"
                                    data-qr-code="{{ $bank->qr_code ? asset('storage/' . $bank->qr_code) : '' }}">
                                {{ $bank->account_name }} — {{ $bank->bank_name }} (₹{{ number_format($bank->current_balance, 2) }})
                            </option>
                        @endforeach
                    </select>
                    @if(empty($bankAccounts) || (is_countable($bankAccounts) && count($bankAccounts) === 0))
                        <div class="form-text text-danger">No active bank accounts found. Add one under Accounts → Bank Accounts.</div>
                    @else
                        <div class="form-text text-muted">Select the company bank to collect into. UPI ID / QR for that bank will appear below.</div>
                    @endif
                </div>

                {{-- Combined bank details + QR --}}
                <div class="mb-3 d-none" id="chitPayBankDetailsCard">
                    <div class="card bg-label-secondary border-0 p-3">
                        <div class="row g-3 align-items-start">
                            <div class="col-md-7" id="chitPayBankTransferPanel">
                                <h6 class="mb-2 fw-bold text-dark small" id="chitPayQrBankName">Bank Name</h6>
                                <div id="chitPayBankTransferContent" class="small text-dark"></div>
                            </div>
                            <div class="col-md-5 text-center" id="chitPayQrContainer">
                                <p class="mb-1 small text-muted fw-semibold">Selected Bank UPI / QR</p>
                                <p class="mb-2 small text-muted">UPI ID: <span class="fw-semibold text-dark" id="chitPayQrUpiId">N/A</span></p>
                                <div id="chitPayQrImage" class="d-flex justify-content-center"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-none" id="chitPayBankTransferContainer"></div>

                {{-- Payment Reference --}}
                <div class="mb-0">
                    <label for="chitPayReference" class="form-label fw-medium text-dark small mb-1">Reference / Txn ID</label>
                    <input type="text" class="form-control form-control-sm" id="chitPayReference" name="reference_no" placeholder="e.g. UPI ID, Bank UTR">
                </div>
            </div>

            <div class="modal-footer bg-light px-3 px-sm-4 py-3 border-top">
                <button type="button" class="btn btn-sm btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary px-4 shadow-xs" id="chitPaySubmitBtn">
                    Pay Now
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Partial Payment Modal --}}
<div class="modal fade" id="chitPartialModal" tabindex="-1" aria-labelledby="chitPartialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <form id="chitPartialForm" method="POST" action="" class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            @csrf
            <input type="hidden" name="client_id" id="chitPartialClientId">
            <input type="hidden" name="payment_type" value="partial">
            <input type="hidden" name="force_frequency_partial" id="chitPartialForceFrequency" value="0">

            {{-- Header --}}
            <div class="modal-header bg-label-primary px-3 px-sm-4 py-3 border-bottom border-primary border-opacity-10">
                <div class="d-flex align-items-center gap-2_5">
                    <div class="avatar avatar-xs bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-xs">
                        <i class="icon-base ri ri-percent-line"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-primary mb-0 fs-6" id="chitPartialModalLabel">
                            Partial Payment <span id="chitPartialPeriodLabel" class="fw-normal opacity-75"></span>
                        </h5>
                        <small class="text-primary opacity-75" style="font-size:0.75rem;" id="chitPartialModalHint">Custom partial payment collection</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-3 p-sm-4">
                {{-- Client Info Card --}}
                <div class="card bg-label-secondary border-0 shadow-none mb-3">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold d-block" style="font-size:0.68rem; letter-spacing: 0.5px;">Client</small>
                                <input type="text" class="form-control-plaintext fw-bold text-dark p-0 border-0 fs-6" id="chitPartialClient" readonly>
                            </div>
                            <span class="badge bg-label-primary px-2_5 py-1">Partial Payment</span>
                        </div>
                    </div>
                </div>

                {{-- Multi-Seat Mode Container --}}
                <div class="mb-3 d-none" id="chitPartialSeatOptionContainer">
                    <div class="card bg-label-secondary border border-secondary border-opacity-20 shadow-none">
                        <div class="card-body p-3">
                            <label class="form-label fw-semibold text-dark mb-2 small">Seat Payment Mode (Multiple Seats)</label>
                            <div class="row g-2">
                                <div class="col-12 col-sm-6">
                                    <div class="form-check bg-white rounded p-2.5 border">
                                        <input class="form-check-input chit-partial-seat-mode-radio me-2" type="radio" name="single_seat_only" id="chitPartialSeatCumulative" value="0" checked>
                                        <label class="form-check-label fw-medium text-dark small" for="chitPartialSeatCumulative">
                                            <strong class="text-primary">Cumulative</strong>
                                            <span class="d-block text-muted" style="font-size:0.7rem;">All seats (<span id="chitPartialSeatsListLabel"></span>)</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="form-check bg-white rounded p-2.5 border">
                                        <input class="form-check-input chit-partial-seat-mode-radio me-2" type="radio" name="single_seat_only" id="chitPartialSeatSingle" value="1">
                                        <label class="form-check-label fw-medium text-dark small" for="chitPartialSeatSingle">
                                            <strong>Single Seat</strong>
                                            <span class="d-block text-muted" style="font-size:0.7rem;">Selected seat only</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Mobile Responsive Financial Breakdown Grid --}}
                <div class="row g-2 mb-3">
                    <div class="col-12 col-sm-4">
                        <div class="card bg-label-secondary border-0 shadow-none text-center h-100">
                            <div class="card-body p-2_5">
                                <small class="text-muted d-block" style="font-size:0.7rem;">Installment</small>
                                <div class="fw-bold text-dark fs-6 text-nowrap mt-1" id="chitPartialInstallmentDisplay">₹0.00</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="card bg-label-secondary border-0 shadow-none text-center h-100">
                            <div class="card-body p-2_5">
                                <small class="text-muted d-block" style="font-size:0.7rem;">Already Paid</small>
                                <div class="fw-bold text-dark fs-6 text-nowrap mt-1" id="chitPartialPaidDisplay">₹0.00</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="card bg-label-primary border border-primary border-opacity-20 shadow-none text-center h-100">
                            <div class="card-body p-2_5">
                                <small class="text-primary fw-semibold d-block" style="font-size:0.7rem;">Remaining</small>
                                <div class="fw-bold text-primary fs-6 text-nowrap mt-1" id="chitPartialRemainingDisplay">₹0.00</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Partial Amount Input --}}
                <div class="mb-3">
                    <label for="chitPartialAmount" class="form-label fw-medium text-dark small mb-1">
                        Partial Amount <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-label-primary text-primary fw-bold">₹</span>
                        <input type="number" step="1" class="form-control fw-bold text-dark" id="chitPartialAmount" name="paid_amount" placeholder="Enter amount" required>
                    </div>
                    <small class="text-muted d-block mt-1" id="chitPartialMinHelp" style="font-size:0.72rem;">Minimum: ₹0. Extra amount applies cumulatively.</small>
                    <div class="alert alert-info border-0 py-2 px-3 mt-2 mb-0 small d-none" id="chitPartialFrequencyHelp"></div>
                    <div class="invalid-feedback" id="chitPartialAmountError"></div>
                </div>

                {{-- Payment Details Section --}}
                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label for="chitPartialPaidDate" class="form-label fw-medium text-dark small mb-1">Paid Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm" id="chitPartialPaidDate" name="paid_date" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label for="chitPartialPaymentMethod" class="form-label fw-medium text-dark small mb-1">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm no-search" id="chitPartialPaymentMethod" name="payment_mode" required>
                            <option value="">Select Method</option>
                            <option value="in_hand">Cash in hand</option>
                            <option value="wallet">Customer Wallet</option>
                            <option value="upi">UPI / GPay / QR</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                </div>

                {{-- Wallet Balance Info --}}
                <div class="alert alert-primary bg-label-primary border-0 py-2 d-none mb-3" id="chitPartialWalletInfo">
                    <div class="small text-primary">
                        <i class="icon-base ri ri-wallet-3-line me-1"></i>
                        <span>Wallet Balance: <strong id="chitPartialWalletBalance">—</strong></span>
                    </div>
                </div>

                {{-- Bank Account Selector --}}
                <div class="mb-3 d-none" id="chitPartialBankAccountContainer">
                    <label for="chitPartialBankAccount" class="form-label fw-medium text-dark small mb-1">Collection Bank Account <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm no-search" id="chitPartialBankAccount" name="internal_bank_account_id">
                        <option value="">-- Select Bank Account --</option>
                        @foreach(($bankAccounts ?? []) as $bank)
                            <option value="{{ $bank->id }}"
                                    data-bank-name="{{ $bank->bank_name }}"
                                    data-account-name="{{ $bank->account_name }}"
                                    data-account-number="{{ $bank->account_number }}"
                                    data-branch-name="{{ $bank->branch_name }}"
                                    data-account-type="{{ $bank->account_type }}"
                                    data-ifsc="{{ $bank->effective_ifsc }}"
                                    data-upi-id="{{ $bank->upi_id }}"
                                    data-qr-code="{{ $bank->qr_code ? asset('storage/' . $bank->qr_code) : '' }}">
                                {{ $bank->account_name }} — {{ $bank->bank_name }} (₹{{ number_format($bank->current_balance, 2) }})
                            </option>
                        @endforeach
                    </select>
                    @if(empty($bankAccounts) || (is_countable($bankAccounts) && count($bankAccounts) === 0))
                        <div class="form-text text-danger">No active bank accounts found. Add one under Accounts → Bank Accounts.</div>
                    @else
                        <div class="form-text text-muted">Select the company bank to collect into. UPI ID / QR for that bank will appear below.</div>
                    @endif
                </div>

                {{-- Combined bank details + QR --}}
                <div class="mb-3 d-none" id="chitPartialBankDetailsCard">
                    <div class="card bg-label-secondary border-0 p-3">
                        <div class="row g-3 align-items-start">
                            <div class="col-md-7" id="chitPartialBankTransferPanel">
                                <h6 class="mb-2 fw-bold text-dark small" id="chitPartialQrBankName">Bank Name</h6>
                                <div id="chitPartialBankTransferContent" class="small text-dark"></div>
                            </div>
                            <div class="col-md-5 text-center" id="chitPartialQrContainer">
                                <p class="mb-1 small text-muted fw-semibold">Selected Bank UPI / QR</p>
                                <p class="mb-2 small text-muted">UPI ID: <span class="fw-semibold text-dark" id="chitPartialQrUpiId">N/A</span></p>
                                <div id="chitPartialQrImage" class="d-flex justify-content-center"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-none" id="chitPartialBankTransferContainer"></div>

                {{-- Payment Reference --}}
                <div class="mb-0">
                    <label for="chitPartialReference" class="form-label fw-medium text-dark small mb-1">Reference / Txn ID</label>
                    <input type="text" class="form-control form-control-sm" id="chitPartialReference" name="reference_no" placeholder="Transaction ID / Reference Number">
                </div>
            </div>

            <div class="modal-footer bg-light px-3 px-sm-4 py-3 border-top">
                <button type="button" class="btn btn-sm btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary px-4 shadow-xs" id="chitPartialSubmitBtn">
                    Process Payment
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Sticky Bulk Pay Bar --}}
<div id="chitBulkPayBar" class="d-none position-fixed bottom-0 start-50 translate-middle-x mb-4 shadow-lg bg-white border rounded-4 p-3"
     style="width: 92%; max-width: 720px; border-top: 4px solid #696cff !important; z-index: 1090;">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h6 class="mb-0 fw-semibold text-dark">Selected Day/Week EMI</h6>
            <small class="text-muted">
                <span id="chitBulkSelectedCount">0</span> day/week(s) ·
                EMI sum: <strong id="chitBulkSuggestedTotal" class="text-primary">₹0.00</strong> ·
                Full month(s): <strong id="chitBulkFullTotal">₹0.00</strong>
            </small>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="chitBulkClearBtn">Clear</button>
            <button type="button" class="btn btn-sm btn-primary px-4" id="chitBulkPayBtn">
                <i class="ri-money-rupee-circle-line me-1"></i>Pay Selected EMI
            </button>
        </div>
    </div>
</div>

{{-- Bulk Pay Modal --}}
<div class="modal fade" id="chitBulkPayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="chitBulkPayForm" class="modal-content border-0 shadow-lg">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="ri-money-rupee-circle-line me-2 text-primary"></i>Bulk Installment Payment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">
                    Paying <strong id="chitBulkModalCount">0</strong> selected <strong>Day/Week</strong> part(s)
                    (e.g. Day 1, Day 2, Week 1). Each credits the monthly installment as a partial.
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Total Amount</label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="text" class="form-control bg-light fw-bold" id="chitBulkModalAmount" readonly>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold" for="chitBulkPaidDate">Paid Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="chitBulkPaidDate" name="paid_date" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold" for="chitBulkPaymentMethod">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-select no-search" id="chitBulkPaymentMethod" name="payment_mode" required>
                            <option value="">Select Method</option>
                            <option value="in_hand">Cash in hand</option>
                            <option value="wallet">Customer Wallet</option>
                            <option value="upi">UPI / GPay / QR</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3 d-none" id="chitBulkBankAccountContainer">
                    <label class="form-label fw-semibold" for="chitBulkBankAccount">Collection Bank Account <span class="text-danger">*</span></label>
                    <select class="form-select no-search" id="chitBulkBankAccount" name="internal_bank_account_id">
                        <option value="">-- Select Bank Account --</option>
                        @foreach(($bankAccounts ?? []) as $bank)
                            <option value="{{ $bank->id }}"
                                    data-bank-name="{{ $bank->bank_name }}"
                                    data-account-name="{{ $bank->account_name }}"
                                    data-account-number="{{ $bank->account_number }}"
                                    data-branch-name="{{ $bank->branch_name }}"
                                    data-account-type="{{ $bank->account_type }}"
                                    data-ifsc="{{ $bank->effective_ifsc }}"
                                    data-upi-id="{{ $bank->upi_id }}"
                                    data-qr-code="{{ $bank->qr_code ? asset('storage/' . $bank->qr_code) : '' }}">
                                {{ $bank->account_name }} — {{ $bank->bank_name }} (₹{{ number_format($bank->current_balance, 2) }})
                            </option>
                        @endforeach
                    </select>
                    @if(empty($bankAccounts) || (is_countable($bankAccounts) && count($bankAccounts) === 0))
                        <div class="form-text text-danger">No active bank accounts found. Add one under Accounts → Bank Accounts.</div>
                    @else
                        <div class="form-text text-muted">Select the company bank to collect into. UPI ID / QR for that bank will appear below.</div>
                    @endif
                </div>

                <div class="mb-3 d-none" id="chitBulkBankDetailsCard">
                    <div class="card bg-label-secondary border-0 p-3">
                        <div class="row g-3 align-items-start">
                            <div class="col-md-7" id="chitBulkBankTransferPanel">
                                <h6 class="mb-2 fw-bold text-dark small" id="chitBulkQrBankName">Bank Name</h6>
                                <div id="chitBulkBankTransferContent" class="small text-dark"></div>
                            </div>
                            <div class="col-md-5 text-center" id="chitBulkQrContainer">
                                <p class="mb-1 small text-muted fw-semibold">Selected Bank UPI / QR</p>
                                <p class="mb-2 small text-muted">UPI ID: <span class="fw-semibold text-dark" id="chitBulkQrUpiId">N/A</span></p>
                                <div id="chitBulkQrImage" class="d-flex justify-content-center"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" for="chitBulkReference">Reference / Txn ID</label>
                    <input type="text" class="form-control" id="chitBulkReference" name="reference_no" placeholder="Optional">
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold" for="chitBulkRemarks">Remarks</label>
                    <textarea class="form-control" id="chitBulkRemarks" name="remarks" rows="2" placeholder="Optional notes"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="chitBulkSubmitBtn">
                    <i class="ri-check-line me-1"></i>Collect Payment
                </button>
            </div>
        </form>
    </div>
</div>
