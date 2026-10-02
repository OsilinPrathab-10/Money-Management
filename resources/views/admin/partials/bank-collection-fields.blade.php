@php
    $bankContainerId = $bankContainerId ?? 'bankAccountContainer';
    $bankSelectId = $bankSelectId ?? 'internal_bank_account_id';
    $bankSelectName = $bankSelectName ?? 'internal_bank_account_id';
    $qrContainerId = $qrContainerId ?? 'qrCodeDisplayContainer';
    $qrBankNameId = $qrBankNameId ?? 'qrBankName';
    $qrUpiIdId = $qrUpiIdId ?? 'qrUpiId';
    $qrImageWrapperId = $qrImageWrapperId ?? 'qrCodeImageWrapper';
    $bankTransferContainerId = $bankTransferContainerId ?? 'bankTransferDetailsContainer';
    $bankTransferContentId = $bankTransferContentId ?? 'bankTransferDetailsContent';
    $bankDetailsCardId = $bankDetailsCardId ?? ($bankContainerId . 'DetailsCard');
    $wrapperClass = $wrapperClass ?? 'mb-3';
@endphp

<div class="{{ $wrapperClass }} d-none" id="{{ $bankContainerId }}">
    <label class="form-label" for="{{ $bankSelectId }}">
        Collection Bank Account <span class="text-danger">*</span>
    </label>
    <select class="form-select no-search" id="{{ $bankSelectId }}" name="{{ $bankSelectName }}">
        <option value="">-- Select Bank Account --</option>
        @foreach($bankAccounts as $bank)
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
    <small class="text-muted">Payment will be credited to this account for maintaining records.</small>
</div>

{{-- Combined account details + QR for UPI/GPay and bank transfer --}}
<div class="{{ $wrapperClass }} d-none" id="{{ $bankDetailsCardId }}">
    <div class="card bg-lighter border shadow-none">
        <div class="card-body p-3">
            <div class="row g-3 align-items-start">
                <div class="col-md-7">
                    <h6 class="mb-2 fw-semibold text-heading" id="{{ $qrBankNameId }}">Bank Name</h6>
                    <div id="{{ $bankTransferContentId }}" class="small text-dark">
                        {{-- Filled by JS: A/C no, IFSC, UPI, branch --}}
                    </div>
                </div>
                <div class="col-md-5 text-center" id="{{ $qrContainerId }}">
                    <p class="mb-1 small text-muted">UPI / GPay QR</p>
                    <p class="mb-2 small">UPI ID: <span class="fw-bold text-dark" id="{{ $qrUpiIdId }}">N/A</span></p>
                    <div id="{{ $qrImageWrapperId }}"></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Legacy ids kept empty/hidden for older JS hooks --}}
<div class="d-none" id="{{ $bankTransferContainerId }}"></div>
