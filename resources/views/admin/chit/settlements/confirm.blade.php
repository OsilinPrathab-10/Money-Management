@extends('layouts/layoutMaster')
@section('title', 'Confirm Settlement')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@php
    $isAdvance = ($payoutKind ?? 'original') === 'advance';
    $isOutgoingRelease = ($pendingSettlement?->isOutgoingRelease() ?? false)
        || (! $isAdvance && app(\App\Services\ChitPayoutService::class)->isOutgoingTransferredMember($member));
    $isCancelSettlement = ($pendingSettlement?->isContributionSettlement() ?? false)
        && ! $isOutgoingRelease
        && app(\App\Services\ChitPayoutService::class)->isCancelledWithdrawnMember($member);
    $settlementTypeLabel = $pendingSettlement?->payout_kind_label
        ?? ($isAdvance ? 'Advance Amount' : ($isOutgoingRelease ? 'Outgoing Release' : ($isCancelSettlement ? 'Cancel Settlement' : 'Original Payout')));
    $settlementTypeBadge = $pendingSettlement?->payout_kind_badge
        ?? ($isAdvance ? 'info' : ($isOutgoingRelease ? 'warning' : ($isCancelSettlement ? 'secondary' : 'primary')));
    $periodNumber = max(1, (int) ($context['month_number'] ?? $overview['current_month'] ?? $group->current_month ?? 1));
    $grossAmount = (float) ($grossPayout ?? ($pendingSettlement?->payout_amount ?? ($amounts['payout_amount'] ?? 0)));
    $processingFee = (float) old('processing_fee', $defaultFees['processing_fee'] ?? 0);
    $documentCharges = (float) old('document_charges', $defaultFees['document_charges'] ?? 0);
    $otherCharges = (float) old('other_charges', $defaultFees['other_charges'] ?? 0);
    $bankingCharges = (float) old('banking_charges', $defaultFees['banking_charges'] ?? 0);
    // Banking charges are company bank cost — not deducted from member net.
    $netPayable = max(0, $grossAmount - $processingFee - $documentCharges - $otherCharges);
    $sharePct = (float) ($pendingSettlement?->share_percentage
        ?? $member->effective_share_percentage
        ?? 100);
    $shareRatio = max(0, $sharePct) / 100.0;
    $fullCommission = (float) ($amounts['commission'] ?? 0);
    $commission = round($fullCommission * $shareRatio, 2);
    $chitValue = round((float) ($group->chit_value ?? 0) * $shareRatio, 2);
    $fullMonthPayout = $shareRatio > 0 ? round($grossAmount / $shareRatio, 2) : $grossAmount;
@endphp

@section('content')
<div class="row g-3 justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow-sm border-0">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">
                    <i class="icon-base ri ri-shield-check-line text-primary me-1"></i>
                    Settlement Confirmation
                </h5>
                <a href="{{ route('chit.settlement-applications.index', ['group_id' => $group->id]) }}" class="btn btn-sm btn-label-secondary d-inline-flex align-items-center">
                    <i class="icon-base ri ri-arrow-left-line me-1"></i>Back
                </a>
            </div>
            <div class="card-body">
                <div class="alert alert-info py-2 mb-4" style="font-size:.85rem;">
                    @if($isAdvance)
                        Verify member, amount, and payment details before releasing the <strong>Advance Amount</strong> payout.
                    @else
                        Verify member, amount, and payment details before releasing the <strong>original settlement</strong> payout.
                    @endif
                </div>

                {{-- Member & Group Details --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100 bg-light">
                            <h6 class="text-muted text-uppercase mb-2" style="font-size:.7rem;letter-spacing:1px;">Member Details</h6>
                            <div class="fw-bold fs-5 text-dark">{{ $member->client?->client_name ?? '—' }}</div>
                            <div class="text-muted">{{ $member->client?->client_phone ?? '—' }}</div>
                            <div class="text-muted">Member #{{ $member->member_number }}</div>
                            @if(abs($sharePct - 100) > 0.001)
                                <div class="mt-1">
                                    <span class="badge bg-label-info">{{ rtrim(rtrim(number_format($sharePct, 2), '0'), '.') }}% Independent Share{{ $sharePct > 100.001 ? ' (' . rtrim(rtrim(number_format($sharePct / 100, 2), '0'), '.') . ' seats)' : '' }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100 bg-light">
                            <h6 class="text-muted text-uppercase mb-2" style="font-size:.7rem;letter-spacing:1px;">Chit Group Details</h6>
                            <div class="fw-bold fs-5 text-dark">{{ $group->scheme->name ?? $group->group_code }}</div>
                            <div class="text-muted">{{ $group->group_code }}</div>
                            @php
                                $groupStartDate = $group->start_date
                                    ? \Carbon\Carbon::parse($group->start_date)
                                    : ($group->created_at ? \Carbon\Carbon::parse($group->created_at) : now());
                                $monthDateName = $groupStartDate->copy()->addMonths($periodNumber - 1)->format('M Y');
                            @endphp
                            <div class="text-muted">Settlement Month: <strong>Month {{ $periodNumber }} - {{ $monthDateName }}</strong></div>
                            <div class="mt-1">
                                <span class="badge bg-label-{{ $settlementTypeBadge }}">
                                    {{ $settlementTypeLabel }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Amount & Settlement Details --}}
                <div class="card border mb-4">
                    <div class="card-header bg-label-success py-3">
                        <h6 class="mb-0 text-success">
                            <i class="icon-base ri ri-money-rupee-circle-line me-1"></i>
                            Settlement Amount &amp; Details
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-center">
                            <div class="col-lg-5">
                                <div class="text-center p-4 rounded" style="background:#d1fae5;">
                                    <div class="text-muted mb-1">
                                        {{ $isAdvance ? 'Advance Amount' : 'Gross Settlement Amount' }}
                                        (Month {{ $periodNumber }})
                                    </div>
                                    <div class="fw-bold text-success" style="font-size:2rem;" id="settlementGrossDisplay">
                                        ₹{{ number_format($grossAmount, 2) }}
                                    </div>
                                    <div class="fw-bold text-primary mt-2" style="font-size:1.25rem;">
                                        Net Payable:
                                        <span id="settlementNetDisplay">₹{{ number_format($netPayable, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-7">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            @if($sharePct < 99.999)
                                            <tr>
                                                <td class="text-muted">Independent Share</td>
                                                <td class="text-end fw-semibold text-info">{{ rtrim(rtrim(number_format($sharePct, 2), '0'), '.') }}%</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Full Month Payout</td>
                                                <td class="text-end fw-semibold">₹{{ number_format($fullMonthPayout, 2) }}</td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <td class="text-muted">{{ $sharePct < 99.999 ? 'Share of Chit Value' : 'Chit Value' }}</td>
                                                <td class="text-end fw-semibold">₹{{ number_format($chitValue, 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Foreman Commission{{ $sharePct < 99.999 ? ' (share)' : '' }}</td>
                                                <td class="text-end fw-semibold text-danger">- ₹{{ number_format($commission, 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Gross Payout{{ $sharePct < 99.999 ? ' (share)' : '' }}</td>
                                                <td class="text-end fw-semibold text-success">₹{{ number_format($grossAmount, 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Processing Fee</td>
                                                <td class="text-end text-danger" id="detailProcessingFee">- ₹{{ number_format($processingFee, 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Document Charges</td>
                                                <td class="text-end text-danger" id="detailDocumentCharges">- ₹{{ number_format($documentCharges, 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Other Charges</td>
                                                <td class="text-end text-danger" id="detailOtherCharges">- ₹{{ number_format($otherCharges, 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Bank Transfer Charges (company)</td>
                                                <td class="text-end text-muted" id="detailBankingCharges">₹{{ number_format($bankingCharges, 2) }}</td>
                                            </tr>
                                            <tr class="table-light">
                                                <td class="fw-bold text-dark">Net Payable to Member</td>
                                                <td class="text-end fw-bold text-primary fs-5" id="detailNetPayable">₹{{ number_format($netPayable, 2) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <small class="text-muted">
                                    @if($group->getScheduledPayoutAmountForMonth($periodNumber))
                                        Amount taken from scheme payout schedule for Period {{ $periodNumber }}.
                                    @else
                                        Gross payout = Chit value − foreman commission (or schedule amount).
                                    @endif
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="settlementGrossAmount" value="{{ number_format($grossAmount, 2, '.', '') }}">

                <form method="POST" action="{{ route('chit.settlements.process', [$group, $member]) }}" id="settlementReleaseForm" enctype="multipart/form-data">
                    @csrf
                    @if($pendingSettlement)
                        <input type="hidden" name="pending_id" value="{{ $pendingSettlement->id }}">
                    @endif
                    <input type="hidden" name="payout_kind" value="{{ $payoutKind ?? 'original' }}">

                    <div class="border rounded p-3 mb-3">
                        <h6 class="fw-bold mb-3 text-uppercase small text-muted">Settlement Charges</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Processing Fee</label>
                                <input type="number" step="0.01" min="0" name="processing_fee" id="settlement_processing_fee"
                                    class="form-control settlement-charge-input"
                                    value="{{ number_format($processingFee, 2, '.', '') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Document Charges</label>
                                <input type="number" step="0.01" min="0" name="document_charges" id="settlement_document_charges"
                                    class="form-control settlement-charge-input"
                                    value="{{ number_format($documentCharges, 2, '.', '') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Other Charges</label>
                                <input type="number" step="0.01" min="0" name="other_charges" id="settlement_other_charges"
                                    class="form-control settlement-charge-input"
                                    value="{{ number_format($otherCharges, 2, '.', '') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Bank Transfer Charges</label>
                                <input type="number" step="0.01" min="0" name="banking_charges" id="settlement_banking_charges"
                                    class="form-control settlement-charge-input"
                                    value="{{ number_format($bankingCharges, 2, '.', '') }}">
                                <small class="text-muted">Company bank debit only — not deducted from member.</small>
                            </div>
                        </div>
                    </div>

                    <div class="border rounded p-3 mb-4">
                        <h6 class="fw-bold mb-3 text-uppercase small text-muted">Payment Details</h6>
                        <div class="row g-3">
                            @if(isset($clientKyc) && ($clientKyc->bank_name || $clientKyc->account_number))
                            <div class="col-12">
                                <div class="alert alert-info py-2 px-3 mb-1 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div>
                                        <i class="icon-base ri ri-bank-line me-1"></i>
                                        <strong>Auto-fetched Client Bank Details (from KYC):</strong>
                                        <span class="ms-1">
                                            {{ $clientKyc->bank_name ?? 'Bank' }} &bull; A/C: <code>{{ $clientKyc->account_number ?? 'N/A' }}</code> &bull; IFSC: <code>{{ $clientKyc->ifsc_code ?? 'N/A' }}</code>
                                            @if($clientKyc->account_holder_name) &bull; Holder: {{ $clientKyc->account_holder_name }} @endif
                                        </span>
                                    </div>
                                    <span class="badge bg-label-success"><i class="icon-base ri ri-checkbox-circle-line me-1"></i>KYC Auto-filled</span>
                                </div>
                            </div>
                            @endif
                            <div class="col-md-6" id="settlementInternalBankWrap">
                                <label class="form-label fw-semibold" id="settlementInternalBankLabel">Disburse From (Company Bank) *</label>
                                <select name="internal_bank_account_id" id="internal_bank_account_id" class="form-select" required>
                                    <option value="" disabled {{ old('internal_bank_account_id') ? '' : 'selected' }}>-- Select Bank Account --</option>
                                    @foreach($bankAccounts as $account)
                                        <option value="{{ $account->id }}" {{ (string) old('internal_bank_account_id') === (string) $account->id ? 'selected' : '' }}>
                                            {{ $account->bank_name }} - {{ $account->account_name }} ({{ $account->account_number }}) - Bal: ₹{{ number_format($account->current_balance, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted" id="settlementCashHint" style="display:none;">Cash payouts are booked to the company <strong>Cash in Hand</strong> account automatically.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Payment Date *</label>
                                <input type="date" name="paid_date" class="form-control" required
                                    value="{{ old('paid_date', today()->format('Y-m-d')) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Payment Mode *</label>
                                <select name="payment_mode" id="settlement_payment_mode" class="form-select" required>
                                    @foreach(['cash' => 'Cash in hand', 'bank_transfer' => 'Bank Transfer', 'upi' => 'UPI / GPay / QR', 'other' => 'Other'] as $val => $label)
                                        <option value="{{ $val }}" {{ old('payment_mode', 'cash') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Transaction Reference</label>
                                <input type="text" name="reference_no" class="form-control" placeholder="Optional"
                                    value="{{ old('reference_no') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Bank Name</label>
                                <input type="text" name="bank_name" class="form-control" value="{{ old('bank_name', $pendingSettlement?->bank_name ?? $clientKyc?->bank_name ?? '') }}" placeholder="e.g. State Bank of India">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Account Number</label>
                                <input type="text" name="account_number" class="form-control" value="{{ old('account_number', $pendingSettlement?->account_number ?? $clientKyc?->account_number ?? '') }}" placeholder="e.g. 1234567890">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">IFSC Code</label>
                                <input type="text" name="ifsc_code" class="form-control" value="{{ old('ifsc_code', $pendingSettlement?->ifsc_code ?? $clientKyc?->ifsc_code ?? '') }}" placeholder="e.g. SBIN0001234">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">UPI ID</label>
                                <input type="text" name="upi_id" class="form-control" value="{{ old('upi_id') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="2">{{ old('remarks') }}</textarea>
                            </div>
                        </div>
                    </div>

                    {{-- Settlement & Collateral Documents (Optional) --}}
                    <div class="border rounded p-3 mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                            <h6 class="fw-bold mb-0 text-uppercase small text-muted">
                                <i class="icon-base ri ri-folder-open-line me-1 text-primary"></i>
                                Settlement & Collateral Documents (Optional)
                            </h6>
                            <span class="badge bg-label-info">Non-Mandatory / Optional</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Settlement Deed / Release Document</label>
                                <input type="file" name="settlement_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                <small class="text-muted d-block mt-1">Upload settlement agreement, NOC, or release form</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Collateral & Other Document</label>
                                <input type="file" name="other_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                <small class="text-muted d-block mt-1">Upload any collateral papers or additional proof document</small>
                            </div>
                        </div>
                    </div>

                    {{-- Confirm & Release Settlement --}}
                    <div class="card border-success mb-0">
                        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div>
                                <h6 class="mb-1 text-success">
                                    <i class="icon-base ri ri-checkbox-circle-line me-1"></i>
                                    Confirm &amp; Release Settlement
                                </h6>
                                <p class="text-muted mb-0 small">
                                    Net payable to <strong>{{ $member->client?->client_name ?? 'member' }}</strong>:
                                    <strong class="text-primary" id="footerNetPayable">₹{{ number_format($netPayable, 2) }}</strong>
                                </p>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('chit.settlement-applications.index', ['group_id' => $group->id]) }}" class="btn btn-label-secondary btn-lg">
                                    Cancel
                                </a>
                                <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#releaseSettlementModal">
                                    <i class="icon-base ri ri-money-rupee-circle-line me-1"></i>
                                    {{ $isAdvance ? 'Confirm & Release Advance Amount' : 'Confirm & Release Settlement' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="releaseSettlementModal" tabindex="-1" aria-labelledby="releaseSettlementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="releaseSettlementModalLabel">
                    <i class="icon-base ri ri-shield-check-line text-success me-1"></i>
                    {{ $isAdvance ? 'Release Advance Amount?' : 'Release Settlement Payout?' }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="text-muted mb-3">
                    Please confirm you want to release this
                    {{ $isAdvance ? 'advance amount payout' : 'original settlement payout' }}.
                </p>
                <div class="border rounded p-3 bg-light">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Member</span>
                        <span class="fw-semibold">{{ $member->client?->client_name ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Chit Group</span>
                        <span class="fw-semibold">{{ $group->group_code }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Chit Period</span>
                        <span class="fw-semibold">Period {{ $periodNumber }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Settlement Type</span>
                        <span class="fw-semibold">{{ $settlementTypeLabel }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Gross Amount</span>
                        <span class="fw-semibold text-success">₹{{ number_format($grossAmount, 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-2 mt-1">
                        <span class="text-muted">Net Payable</span>
                        <span class="fw-bold text-primary" id="modalNetPayout">₹{{ number_format($netPayable, 2) }}</span>
                    </div>
                </div>
                <p class="text-warning small mt-3 mb-0">
                    <i class="icon-base ri ri-error-warning-line me-1"></i>This action cannot be undone once released.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmReleaseSettlementBtn">
                    <i class="icon-base ri ri-check-line me-1"></i>Yes, Confirm &amp; Release Settlement
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="postPayoutInstallmentModal" tabindex="-1" aria-labelledby="postPayoutInstallmentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('settlementReleaseForm');
    const confirmBtn = document.getElementById('confirmReleaseSettlementBtn');
    const releaseModalEl = document.getElementById('releaseSettlementModal');
    const grossInput = document.getElementById('settlementGrossAmount');
    const netDisplay = document.getElementById('settlementNetDisplay');
    const detailNet = document.getElementById('detailNetPayable');
    const footerNet = document.getElementById('footerNetPayable');
    const modalNet = document.getElementById('modalNetPayout');
    const detailProcessing = document.getElementById('detailProcessingFee');
    const detailDocument = document.getElementById('detailDocumentCharges');
    const detailOther = document.getElementById('detailOtherCharges');
    const detailBanking = document.getElementById('detailBankingCharges');
    const chargeInputs = document.querySelectorAll('.settlement-charge-input');
    const defaultConfirmHtml = confirmBtn ? confirmBtn.innerHTML : '';

    function formatMoney(v) {
        return '₹' + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalcNet() {
        const gross = parseFloat(grossInput?.value || '0') || 0;
        const processing = parseFloat(document.getElementById('settlement_processing_fee')?.value || '0') || 0;
        const documentFee = parseFloat(document.getElementById('settlement_document_charges')?.value || '0') || 0;
        const other = parseFloat(document.getElementById('settlement_other_charges')?.value || '0') || 0;
        const banking = parseFloat(document.getElementById('settlement_banking_charges')?.value || '0') || 0;
        // Banking charges are company bank cost — not deducted from member net.
        const net = Math.max(0, gross - processing - documentFee - other);

        if (netDisplay) netDisplay.textContent = formatMoney(net);
        if (detailNet) detailNet.textContent = formatMoney(net);
        if (footerNet) footerNet.textContent = formatMoney(net);
        if (modalNet) modalNet.textContent = formatMoney(net);
        if (detailProcessing) detailProcessing.textContent = '- ' + formatMoney(processing);
        if (detailDocument) detailDocument.textContent = '- ' + formatMoney(documentFee);
        if (detailOther) detailOther.textContent = '- ' + formatMoney(other);
        if (detailBanking) detailBanking.textContent = formatMoney(banking);
    }

    chargeInputs.forEach(function (input) {
        input.addEventListener('input', recalcNet);
    });
    recalcNet();

    const paymentModeSelect = document.getElementById('settlement_payment_mode');
    const bankSelect = document.getElementById('internal_bank_account_id');
    const bankLabel = document.getElementById('settlementInternalBankLabel');
    const cashHint = document.getElementById('settlementCashHint');

    function syncSettlementBankRequirement() {
        const mode = (paymentModeSelect?.value || 'cash').toLowerCase();
        const needsBank = mode === 'upi' || mode === 'bank_transfer' || mode === 'other';
        if (!bankSelect) return;
        bankSelect.required = needsBank;
        if (bankLabel) {
            bankLabel.textContent = needsBank
                ? 'Disburse From (Company Bank) *'
                : 'Disburse From (optional — Cash in Hand used)';
        }
        if (cashHint) {
            cashHint.style.display = needsBank ? 'none' : 'block';
        }
        if (!needsBank) {
            bankSelect.value = '';
        }
    }
    paymentModeSelect?.addEventListener('change', syncSettlementBankRequirement);
    syncSettlementBankRequirement();

    function showError(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Settlement Failed',
                text: message || 'Settlement payout failed.',
                confirmButtonText: 'OK',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false
            });
            return;
        }
        alert(message || 'Settlement payout failed.');
    }

    function showFinalSuccess(data) {
        const message = (data && data.message)
            ? data.message
            : 'Settlement released successfully.';
        const historyUrl = (data && data.redirect_url)
            ? data.redirect_url
            : @json(route('chit.settlements.history', ['group_id' => $group->id]));
        const noteUrl = data && data.promissory_note_url ? data.promissory_note_url : null;
        const pdfUrl = data && data.promissory_pdf_url ? data.promissory_pdf_url : null;

        if (typeof Swal === 'undefined') {
            alert(message);
            window.location.href = noteUrl || historyUrl;
            return;
        }

        const actions = [];
        if (noteUrl) {
            actions.push('<a href="' + noteUrl + '" class="btn btn-primary me-2"><i class="ri-file-text-line me-1"></i>View Promissory Note</a>');
        }
        if (pdfUrl) {
            actions.push('<a href="' + pdfUrl + '" class="btn btn-outline-success me-2" target="_blank"><i class="ri-download-line me-1"></i>Download PDF</a>');
        }
        actions.push('<a href="' + historyUrl + '" class="btn btn-outline-secondary"><i class="ri-history-line me-1"></i>Settlement History</a>');

        Swal.fire({
            icon: 'success',
            title: 'Settlement Released',
            html: '<p class="mb-3">' + message + '</p><div class="d-flex flex-wrap justify-content-center gap-2">' + actions.join('') + '</div>',
            showConfirmButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            width: 560
        });
    }

    function handleSuccessFlow(data) {
        showFinalSuccess(data);
    }

    confirmBtn?.addEventListener('click', function () {
        if (!form || !form.reportValidity()) {
            return;
        }

        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';

        const modalInstance = releaseModalEl && typeof bootstrap !== 'undefined'
            ? bootstrap.Modal.getInstance(releaseModalEl)
            : null;
        if (modalInstance) {
            modalInstance.hide();
        }

        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
            .then(async function (response) {
                const data = await response.json().catch(function () { return {}; });
                if (!response.ok) {
                    let message = data.message || 'Settlement payout failed.';
                    if (data.errors) {
                        message = Object.values(data.errors).flat().join(' ');
                    }
                    throw new Error(message);
                }
                return data;
            })
            .then(function (data) {
                if (data.success === false) {
                    throw new Error(data.message || 'Settlement payout failed.');
                }
                handleSuccessFlow(data);
            })
            .catch(function (err) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = defaultConfirmHtml;
                showError(err.message);
            });
    });
});
</script>
@endsection
