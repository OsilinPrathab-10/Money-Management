@extends('layouts/layoutMaster')
@section('title', isset($mode) && $mode === 'show' ? 'Payout — '.$payout->payout_code : 'Payout Management')

@section('content')
@if(isset($mode) && $mode === 'show')
{{-- SHOW PAYOUT DETAILS VIEW --}}
<div class="row g-3 justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ $payout->payout_code }}</h5>
                <span class="badge bg-{{ $payout->status_badge }} px-3 py-2">{{ ucfirst($payout->status) }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-4 text-center">
                        <div style="background:#d1fae5;border-radius:12px;padding:20px;">
                            <div class="text-muted" style="font-size:.8rem;">Payout Amount</div>
                            <div style="font-size:1.75rem;font-weight:700;color:#065f46;">₹{{ number_format($payout->payout_amount) }}</div>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div style="background:#fef3c7;border-radius:12px;padding:20px;">
                            <div class="text-muted" style="font-size:.8rem;">Commission Deducted</div>
                            <div style="font-size:1.75rem;font-weight:700;color:#92400e;">₹{{ number_format($payout->commission_amount) }}</div>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div style="background:#e0e7ff;border-radius:12px;padding:20px;">
                            <div class="text-muted" style="font-size:.8rem;">Chit Value</div>
                            <div style="font-size:1.75rem;font-weight:700;color:#3730a3;">₹{{ number_format($payout->chit_value) }}</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-2 text-muted" style="font-size:.75rem;letter-spacing:1px;text-transform:uppercase;">Winner Details</h6>
                        <div class="border rounded p-3">
                            <div class="fw-bold">{{ $payout->winner?->client->client_name }}</div>
                            <div class="text-muted">{{ $payout->winner?->client->client_phone }}</div>
                            <div class="text-muted">Member #{{ $payout->winner?->member_number }}</div>
                            <div class="text-muted">Group: {{ $payout->group->group_code }}</div>
                            <div class="text-muted">Settlement Month: {{ $payout->month_number }}</div>
                        </div>
                    </div>
                    @if($payout->status === 'paid')
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-2 text-muted" style="font-size:.75rem;letter-spacing:1px;text-transform:uppercase;">Payment Details</h6>
                        <div class="border rounded p-3">
                            <div>Mode: <strong>{{ str_replace('_', ' ', ucfirst($payout->payment_mode)) }}</strong></div>
                            @if($payout->reference_no)<div>Ref: <code>{{ $payout->reference_no }}</code></div>@endif
                            @if($payout->bank_name)<div>Bank: {{ $payout->bank_name }}</div>@endif
                            @if($payout->account_number)<div>A/C: {{ $payout->account_number }}</div>@endif
                            @if($payout->paid_date)<div class="text-success">Paid on {{ Carbon\Carbon::parse($payout->paid_date)->format('d M Y') }}</div>@endif
                        </div>
                    </div>
                    @endif
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <h6 class="fw-bold mb-2 text-muted" style="font-size:.75rem;letter-spacing:1px;text-transform:uppercase;">Settlement Audit</h6>
                        <div class="border rounded p-3 d-flex flex-wrap gap-4">
                            <div>
                                <div class="text-muted" style="font-size:.75rem;">Initiated By</div>
                                <div class="fw-semibold">{{ $payout->initiatedBy?->name ?? '—' }}</div>
                                <small class="text-muted">{{ $payout->created_at?->format('d M Y, h:i A') }}</small>
                            </div>
                            @if($payout->processedBy)
                            <div>
                                <div class="text-muted" style="font-size:.75rem;">Processed By</div>
                                <div class="fw-semibold">{{ $payout->processedBy->name }}</div>
                                <small class="text-muted">{{ $payout->paid_date?->format('d M Y') }}</small>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if($payout->status === 'pending')
                @php
                    $defaultFees = [
                        'processing_fee' => (float) \App\Models\ChitConfiguration::get('settlement_processing_fee', 0),
                        'document_charges' => (float) \App\Models\ChitConfiguration::get('settlement_document_charges', 0),
                        'other_charges' => (float) \App\Models\ChitConfiguration::get('settlement_other_charges', 0),
                    ];
                @endphp
                <div class="border rounded p-4" style="background:#f8fafc;">
                    <h6 class="fw-bold mb-3"><i class="ri-money-rupee-circle-line me-1 text-success"></i>Release Payout</h6>
                    <p class="text-muted mb-3" style="font-size:.85rem;">Record payment details and release the settlement to the winner.</p>
                    <form method="POST" action="{{ route('chit.payouts.process', $payout) }}">
                        @csrf
                        <div class="border rounded p-3 mb-3">
                            <h6 class="fw-bold mb-3 text-uppercase small text-muted">Settlement Charges (like loan disbursement)</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Processing Fee</label>
                                    <input type="number" step="0.01" min="0" name="processing_fee" id="settlement_processing_fee"
                                        class="form-control settlement-charge-input"
                                        value="{{ old('processing_fee', number_format($defaultFees['processing_fee'], 2, '.', '')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Document Charges</label>
                                    <input type="number" step="0.01" min="0" name="document_charges" id="settlement_document_charges"
                                        class="form-control settlement-charge-input"
                                        value="{{ old('document_charges', number_format($defaultFees['document_charges'], 2, '.', '')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Other Charges</label>
                                    <input type="number" step="0.01" min="0" name="other_charges" id="settlement_other_charges"
                                        class="form-control settlement-charge-input"
                                        value="{{ old('other_charges', number_format($defaultFees['other_charges'], 2, '.', '')) }}">
                                </div>
                            </div>
                        </div>

                        <input type="hidden" id="payoutGrossAmount" value="{{ number_format($payout->payout_amount, 2, '.', '') }}">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Internal Bank Account *</label>
                                <select name="internal_bank_account_id" id="internal_bank_account_id" class="form-select" required>
                                    <option value="" disabled selected>-- Select Bank Account --</option>
                                    @foreach($bankAccounts as $account)
                                        <option value="{{ $account->id }}" {{ old('internal_bank_account_id') == $account->id ? 'selected' : '' }}>
                                            {{ $account->bank_name }} - {{ $account->account_name }} ({{ $account->account_number }}) - Bal: ₹{{ number_format($account->current_balance, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Payment Mode *</label>
                                <select name="payment_mode" class="form-select" required>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="upi">UPI</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Bank Name</label>
                                <input type="text" name="bank_name" class="form-control" placeholder="e.g., SBI">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Account Number</label>
                                <input type="text" name="account_number" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">IFSC Code</label>
                                <input type="text" name="ifsc_code" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">UPI ID</label>
                                <input type="text" name="upi_id" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Reference No.</label>
                                <input type="text" name="reference_no" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="2"></textarea>
                            </div>

                            <div class="col-12 mt-3">
                                <div class="alert alert-primary d-flex justify-content-between align-items-center py-2 mb-0">
                                    <span class="fw-semibold">Net Payable Amount:</span>
                                    <span class="fw-bold fs-5" id="payoutNetDisplay">₹{{ number_format($payout->payout_amount, 2) }}</span>
                                </div>
                            </div>

                            <div class="col-12 d-flex gap-2 mt-3">
                                <button type="submit" class="btn btn-success"
                                    onclick="return confirm('Release this payout to the member?')">
                                    <i class="ri-money-rupee-circle-line me-1"></i>Release Payout
                                </button>
                                <a href="{{ route('chit.payouts.index') }}" class="btn btn-label-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
                @else
                <div class="text-start mt-3">
                    <a href="{{ route('chit.payouts.index') }}" class="btn btn-label-secondary">Back to List</a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@else
{{-- DEFAULT INDEX LIST --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Chit Settlements</h4>
        <p class="text-muted mb-0" style="font-size:.85rem;">Manually initiate and release monthly group payouts (Admin/Staff only)</p>
    </div>
    @if($settlementGroups->isNotEmpty())
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#initiateSettlementModal">
        <i class="ri-add-circle-line me-1"></i>Initiate Settlement
    </button>
    @endif
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end ajax-filter-form">
            <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    @foreach(['pending','processing','paid','failed'] as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label form-label-sm mb-1">Group</label>
                <select name="group_id" class="form-select form-select-sm">
                    <option value="">All Groups</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" {{ request('group_id') == $g->id ? 'selected' : '' }}>{{ $g->group_code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('chit.payouts.index') }}" class="btn btn-sm btn-light ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card ajax-table-container">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Chit Settlements <span class="badge bg-primary ms-2">{{ $payouts->total() }}</span></h5>
        <small class="text-muted">Admin/Staff manual processing only</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Code</th><th>Month</th><th>Winner</th><th>Group</th><th>Chit Value</th><th>Commission</th><th>Payout Amount</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($payouts as $payout)
                    <tr>
                        <td><code>{{ $payout->payout_code }}</code></td>
                        <td><span class="badge bg-label-primary">M{{ $payout->month_number }}</span></td>
                        <td>
                            <div class="fw-semibold">{{ $payout->winner?->client->client_name ?? '—' }}</div>
                            <small class="text-muted">{{ $payout->winner?->client->client_phone }}</small>
                        </td>
                        <td>{{ $payout->group->group_code }}</td>
                        <td>₹{{ number_format($payout->chit_value) }}</td>
                        <td class="text-warning">-₹{{ number_format($payout->commission_amount) }}</td>
                        <td class="fw-bold text-success">₹{{ number_format($payout->payout_amount) }}</td>
                        <td><span class="badge bg-{{ $payout->status_badge }}">{{ ucfirst($payout->status) }}</span></td>
                        <td class="text-nowrap">
                            @if($payout->status === 'pending')
                                <a href="{{ route('chit.payouts.show', $payout) }}" class="btn btn-sm btn-success">
                                    <i class="ri-money-rupee-circle-line me-1"></i>Release Payout
                                </a>
                            @else
                                <a href="{{ route('chit.payouts.show', $payout) }}" class="btn btn-sm btn-light" title="View details">
                                    <i class="ri-eye-line"></i>
                                </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center py-5 text-muted">No settlements yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($payouts->hasPages())<div class="card-footer">{{ $payouts->links() }}</div>@endif
</div>

@if($settlementGroups->isNotEmpty())
<div class="modal fade" id="initiateSettlementModal" tabindex="-1" aria-labelledby="initiateSettlementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('chit.payouts.store') }}" id="initiateSettlementForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="initiateSettlementModalLabel">
                        <i class="ri-money-rupee-circle-line me-1 text-primary"></i>Initiate Monthly Settlement
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3" style="font-size:.85rem;">
                        Select an active group and eligible member. Only one member can receive settlement per month.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Chit Group *</label>
                        <select name="group_id" id="settlementGroupSelect" class="form-select" required>
                            <option value="">Select group...</option>
                            @foreach($settlementGroups as $sg)
                                <option value="{{ $sg['id'] }}">
                                    {{ $sg['code'] }} — Month {{ $sg['next_month'] }} ({{ $sg['scheme_type'] }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Eligible Member *</label>
                        <select name="member_id" id="settlementMemberSelect" class="form-select" required disabled>
                            <option value="">Select group first...</option>
                        </select>
                    </div>

                    <div id="settlementPreview" class="border rounded p-3 bg-light d-none">
                        <div class="row g-2" style="font-size:.85rem;">
                            <div class="col-6">
                                <span class="text-muted">Settlement Month</span>
                                <div class="fw-bold" id="previewMonth">—</div>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Payout Amount</span>
                                <div class="fw-bold text-success" id="previewAmount">—</div>
                            </div>
                            <div class="col-12">
                                <span class="text-muted">Commission</span>
                                <div class="fw-semibold text-warning" id="previewCommission">—</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="initiateSettlementBtn" disabled
                        onclick="return confirm('Initiate settlement for the selected member?')">
                        <i class="ri-check-line me-1"></i>Initiate Settlement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endif
@endif
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Release Payout Form Calculator (if elements exist)
    const grossInput = document.getElementById('payoutGrossAmount');
    const netDisplay = document.getElementById('payoutNetDisplay');
    const chargeInputs = document.querySelectorAll('.settlement-charge-input');

    function formatMoney(v) {
        return '₹' + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalcNet() {
        const gross = parseFloat(grossInput?.value || '0') || 0;
        let totalCharges = 0;
        chargeInputs.forEach(function (input) {
            totalCharges += parseFloat(input.value || '0') || 0;
        });
        const net = Math.max(0, gross - totalCharges);
        if (netDisplay) netDisplay.textContent = formatMoney(net);
    }

    if (chargeInputs.length > 0) {
        chargeInputs.forEach(function (input) {
            input.addEventListener('input', recalcNet);
        });
        recalcNet();
    }

    // 2. Initiate Settlement Modal Logic (if elements exist)
    const settlementGroups = @json($settlementGroups ?? []);
    const groupSelect = document.getElementById('settlementGroupSelect');
    const memberSelect = document.getElementById('settlementMemberSelect');
    const preview = document.getElementById('settlementPreview');
    const initiateBtn = document.getElementById('initiateSettlementBtn');

    function formatCurrency(amount) {
        return '₹' + Number(amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updatePreview(group) {
        if (!group || !preview || !initiateBtn) {
            if (preview) preview.classList.add('d-none');
            if (initiateBtn) initiateBtn.disabled = true;
            return;
        }

        const previewMonthEl = document.getElementById('previewMonth');
        const previewAmountEl = document.getElementById('previewAmount');
        const previewCommissionEl = document.getElementById('previewCommission');

        if (previewMonthEl) previewMonthEl.textContent = 'Month ' + group.next_month;
        if (previewAmountEl) previewAmountEl.textContent = formatCurrency(group.payout_amount);
        if (previewCommissionEl) previewCommissionEl.textContent = formatCurrency(group.commission);
        preview.classList.remove('d-none');
    }

    if (groupSelect) {
        groupSelect.addEventListener('change', function () {
            const group = settlementGroups.find(g => String(g.id) === String(this.value));
            memberSelect.innerHTML = '<option value="">Select member...</option>';
            memberSelect.disabled = !group;
            initiateBtn.disabled = true;

            if (!group) {
                updatePreview(null);
                return;
            }

            group.members.forEach(function (member) {
                const option = document.createElement('option');
                option.value = member.id;
                option.textContent = '#' + member.member_number + ' — ' + member.name + (member.phone ? ' (' + member.phone + ')' : '');
                memberSelect.appendChild(option);
            });

            updatePreview(group);
        });
    }

    if (memberSelect) {
        memberSelect.addEventListener('change', function () {
            const group = settlementGroups.find(g => String(g.id) === String(groupSelect.value));
            initiateBtn.disabled = !group || !this.value;
        });
    }
});
</script>
@endsection
