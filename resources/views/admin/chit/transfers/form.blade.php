@extends('layouts/layoutMaster')

@php $mode = $mode ?? 'index'; @endphp

@section('title', match($mode) {
    'create' => 'Transfer Member — ' . ($member->group->group_code ?? '') . ' #' . ($member->member_number ?? ''),
    'show' => 'Transfer ' . ($transfer->transfer_code ?? ''),
    default => 'Member Transfers',
})

@section('content')
@if($mode === 'index')
{{-- INDEX — aligned with Loan Accounts UI --}}
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="mb-1">Member Transfers</h4>
        <p class="text-muted mb-0">Same-group ticket takeovers and member moves between groups</p>
    </div>
</div>

<div class="card">
    <div class="card-header border-bottom d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
        <h5 class="mb-0">
            All Transfers
            <span class="badge bg-label-primary ms-2">{{ $transfers->total() }}</span>
        </h5>
        <form method="GET" class="d-flex flex-wrap align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-nowrap fw-medium">Search:</label>
                <input type="text" name="search" class="form-control form-control-sm" style="min-width: 180px;"
                       placeholder="Code, member, group..." value="{{ request('search') }}">
            </div>
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-nowrap fw-medium">Group:</label>
                <select name="group_id" class="form-select form-select-sm" style="min-width: 140px;">
                    <option value="">All Groups</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" {{ request('group_id') == $g->id ? 'selected' : '' }}>{{ $g->group_code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="icon-base ri ri-filter-3-line me-1"></i>Filter
                </button>
                <a href="{{ route('chit.transfers.index') }}" class="btn btn-sm btn-label-secondary">
                    <i class="icon-base ri ri-refresh-line me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
    <div class="card-datatable table-responsive">
        <table class="table text-nowrap mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>From / To Group</th>
                    <th>Ticket</th>
                    <th>Member</th>
                    <th>Same-group Incoming</th>
                    <th>Credit / Takeover</th>
                    <th>Settlement</th>
                    <th>Rounds</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $transfer)
                @php
                    $memberClient = $transfer->isCrossGroup()
                        ? ($transfer->incomingMember?->client ?? $transfer->outgoingMember?->client)
                        : $transfer->outgoingMember?->client;
                @endphp
                <tr>
                    <td><span class="fw-medium text-primary">{{ $transfer->transfer_code }}</span></td>
                    <td>
                        @if($transfer->isCrossGroup())
                            <span class="badge bg-label-info">Another Group</span>
                        @else
                            <span class="badge bg-label-secondary">Same Group</span>
                        @endif
                    </td>
                    <td>{{ $transfer->transfer_date->format('d M Y') }}</td>
                    <td>
                        @if($transfer->group)
                            <a href="{{ route('chit.groups.show', $transfer->group) }}" class="fw-semibold text-primary text-decoration-none">
                                {{ $transfer->group->group_code }}
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                        @if($transfer->isCrossGroup())
                            <div class="small text-muted">
                                →
                                @if($transfer->destinationGroup)
                                    <a href="{{ route('chit.groups.show', $transfer->destinationGroup) }}" class="text-decoration-none">
                                        {{ $transfer->destinationGroup->group_code }}
                                    </a>
                                @else
                                    —
                                @endif
                            </div>
                        @endif
                    </td>
                    <td><span class="badge bg-label-primary">#{{ $transfer->ticket_number }}</span></td>
                    <td>
                        <div class="fw-semibold text-heading">{{ $memberClient?->client_name ?? '—' }}</div>
                        <small class="text-muted">Paid ₹{{ number_format($transfer->paid_installments_total, 2) }}</small>
                    </td>
                    <td>
                        @if($transfer->isSameGroup())
                            <div class="fw-semibold text-heading">{{ $transfer->incomingMember?->client?->client_name ?? '—' }}</div>
                            <small class="text-muted">{{ $transfer->remaining_installments }} installments left</small>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="fw-semibold text-primary">
                        @if($transfer->isCrossGroup())
                            ₹{{ number_format($transfer->paid_installments_total, 2) }}
                            <div class="small text-muted">EMI credit</div>
                        @else
                            ₹{{ number_format($transfer->takeover_amount, 2) }}
                        @endif
                    </td>
                    <td class="fw-semibold text-success">
                        @php
                            $estSettle = (float) ($transfer->outgoing_settlement_amount ?? 0);
                            $settlePaid = $transfer->isOutgoingSettlementPaid()
                                || ($transfer->outgoingMember && \App\Models\Payout::where('winner_member_id', $transfer->outgoing_member_id)->where('status', 'paid')->exists());
                        @endphp
                        @if($estSettle > 0.009)
                            ₹{{ number_format($estSettle, 2) }}
                            <div class="small text-muted">Paid − foreman</div>
                            @if($settlePaid)
                                <div class="small text-success">Settled</div>
                            @else
                                <div class="small text-warning">Via Settlement Applications</div>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td><span class="badge bg-label-secondary">{{ $transfer->completed_rounds }} done</span></td>
                    <td>
                        <a href="{{ route('chit.transfers.show', $transfer) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="View">
                            <i class="icon-base ri ri-eye-line icon-22px"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" class="text-center py-5 text-muted">
                        <i class="icon-base ri ri-exchange-line icon-32px d-block mb-2"></i>
                        No transfer records found
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transfers->hasPages())
    <div class="card-footer">{{ $transfers->links() }}</div>
    @endif
</div>

@elseif($mode === 'create')
{{-- CREATE TRANSFER — aligned with Loan Accounts UI --}}
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-6">
    <div>
        <h4 class="mb-1">Transfer Member</h4>
        <p class="text-heading mb-0">
            {{ $member->group->group_code ?? '' }} · Ticket #{{ $member->member_number }} · {{ $member->client->client_name ?? '' }}
        </p>
    </div>
    <a href="{{ route('chit.groups.show', $member->group) }}" class="btn btn-outline-secondary d-inline-flex align-items-center">
        <i class="icon-base ri ri-arrow-left-line me-1"></i>Back to Group
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0 transfer-mode-same">
                    <i class="icon-base ri ri-user-unfollow-line me-2 text-danger"></i>Outgoing Member Summary
                </h5>
                <h5 class="mb-0 transfer-mode-cross d-none">
                    <i class="icon-base ri ri-user-line me-2 text-primary"></i>Member Summary
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Customer</small>
                        <h6 class="mb-0 fw-semibold text-heading">{{ $member->client->client_name ?? '—' }}</h6>
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Ticket Number</small>
                        <h6 class="mb-0 fw-semibold">#{{ $member->member_number }}</h6>
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Completed Rounds</small>
                        <h6 class="mb-0 fw-semibold text-success">{{ $preview['completed_rounds'] }}</h6>
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Remaining Installments</small>
                        <h6 class="mb-0 fw-semibold text-warning">{{ $preview['remaining_installments'] }}</h6>
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Total Paid</small>
                        <h6 class="mb-0 fw-semibold">₹{{ number_format($preview['paid_installments_total'], 2) }}</h6>
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Outstanding</small>
                        <h6 class="mb-0 fw-semibold text-danger">₹{{ number_format($preview['outstanding_at_transfer'], 2) }}</h6>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="icon-base ri ri-calculator-line me-2 text-primary"></i>Amount Calculation
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-heading">Paid installments (total)</span>
                    <span class="fw-semibold">₹{{ number_format($preview['paid_installments_total'], 2) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2 transfer-mode-same">
                    <span class="text-heading">Outstanding (waived on outgoing; new schedule for incoming)</span>
                    <span class="fw-semibold text-danger">₹{{ number_format($preview['outstanding_at_transfer'], 2) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2 transfer-mode-cross d-none">
                    <span class="text-heading">Outstanding (waived in this group)</span>
                    <span class="fw-semibold text-danger">₹{{ number_format($preview['outstanding_at_transfer'], 2) }}</span>
                </div>
                @if(($preview['transfer_fee'] ?? 0) > 0)
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-heading">Transfer fee</span>
                    <span class="fw-semibold">₹{{ number_format($preview['transfer_fee'], 2) }}</span>
                </div>
                @endif
                <hr>
                <div class="d-flex justify-content-between mb-2 transfer-mode-same">
                    <span class="fw-semibold text-heading">Outgoing paid total (for their settlement)</span>
                    <span class="fw-bold text-primary fs-5">₹{{ number_format($preview['paid_installments_total'], 2) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2 transfer-mode-cross d-none">
                    <span class="fw-semibold text-heading">Credit to new group EMIs</span>
                    <span class="fw-bold text-primary fs-5">₹{{ number_format($preview['paid_installments_total'], 2) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2 transfer-mode-same">
                    <span class="fw-semibold text-heading">Outgoing Settlement (paid − foreman)</span>
                    <span class="fw-bold text-success fs-5">₹{{ number_format($preview['outgoing_settlement_amount'], 2) }}</span>
                </div>
                <div id="crossCreditPreview" class="transfer-mode-cross d-none mt-3 small text-muted"></div>
                <small class="text-muted d-block mt-2 transfer-mode-same">
                    Incoming gets a fresh schedule (same EMI amounts). Optionally collect now — amount splits FIFO (full months, then partial).
                    Outgoing can apply settlement for paid amount minus foreman commission.
                </small>
                <small class="text-muted d-block mt-2 transfer-mode-cross d-none">
                    Paid total from this group is applied to the destination schedule (full months first, then partial).
                    No wallet settlement on the transfer — use <strong>Settlement Applications</strong> for paid − foreman on this source group.
                </small>
            </div>
        </div>

        @if(!empty($preview['remaining_month_numbers']))
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="icon-base ri ri-calendar-schedule-line me-2 text-secondary"></i>Remaining Chit Periods
                </h6>
            </div>
            <div class="card-body py-2">
                @foreach($preview['remaining_month_numbers'] as $monthNum)
                    <span class="badge bg-label-secondary me-1 mb-1">M{{ $monthNum }}</span>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="icon-base ri ri-exchange-line me-2 text-primary"></i>Transfer Details
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('chit.members.transfer.store', $member) }}" id="transferMemberForm">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Transfer Type <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check custom-option custom-option-basic">
                                    <label class="form-check-label custom-option-content" for="transferTypeSame">
                                        <input class="form-check-input" type="radio" name="transfer_type" id="transferTypeSame"
                                               value="same_group" {{ old('transfer_type', 'same_group') === 'same_group' ? 'checked' : '' }}>
                                        <span class="custom-option-header">
                                            <span class="fw-semibold">Same Group</span>
                                        </span>
                                        <span class="custom-option-body">
                                            <small class="text-muted">Replace this ticket with a different customer (takeover).</small>
                                        </span>
                                    </label>
                                </div>
                                <div class="form-check custom-option custom-option-basic">
                                    <label class="form-check-label custom-option-content" for="transferTypeCross">
                                        <input class="form-check-input" type="radio" name="transfer_type" id="transferTypeCross"
                                               value="cross_group" {{ old('transfer_type') === 'cross_group' ? 'checked' : '' }}>
                                        <span class="custom-option-header">
                                            <span class="fw-semibold">Another Group</span>
                                        </span>
                                        <span class="custom-option-body">
                                            <small class="text-muted">Move {{ $member->client->client_name ?? 'this member' }} into another active group.</small>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            @error('transfer_type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 transfer-mode-same">
                            <label class="form-label fw-semibold">Incoming Member <span class="text-danger">*</span></label>
                            <select name="incoming_client_id" id="incomingClientSelect" class="form-select select2">
                                <option value="">Select customer to take over this ticket...</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}" {{ old('incoming_client_id') == $c->id ? 'selected' : '' }}>
                                        {{ $c->client_name }} ({{ $c->client_phone }})
                                    </option>
                                @endforeach
                            </select>
                            @error('incoming_client_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 transfer-mode-cross d-none">
                            <label class="form-label fw-semibold">Destination Group <span class="text-danger">*</span></label>
                            <select name="destination_group_id" id="destinationGroupSelect" class="form-select select2">
                                <option value="">Select group with an open seat...</option>
                                @forelse(($destinationGroups ?? collect()) as $dg)
                                    <option value="{{ $dg->id }}" {{ old('destination_group_id') == $dg->id ? 'selected' : '' }}>
                                        {{ $dg->group_code }}
                                        — {{ $dg->scheme->name ?? 'Scheme' }}
                                        ({{ $dg->seatsFillLabel() }} seats)
                                    </option>
                                @empty
                                    <option value="" disabled>No active groups with open seats</option>
                                @endforelse
                            </select>
                            <small class="text-muted">Same customer will be enrolled in the selected group with a new installment schedule.</small>
                            @error('destination_group_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Referred By</label>
                            <select name="referred_by" class="form-select select2">
                                <option value="">Select Referrer...</option>
                                <optgroup label="Agents">
                                    @foreach($agents as $a)
                                        <option value="agent:{{ $a->id }}" {{ old('referred_by') == 'agent:'.$a->id ? 'selected' : '' }}>Agent: {{ $a->agent_name }}</option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Clients">
                                    @foreach($clients as $c)
                                        <option value="client:{{ $c->id }}" {{ old('referred_by') == 'client:'.$c->id ? 'selected' : '' }}>Client: {{ $c->client_name }}</option>
                                    @endforeach
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Transfer Date</label>
                            <input type="date" name="transfer_date" class="form-control" value="{{ old('transfer_date', date('Y-m-d')) }}">
                        </div>

                        <div class="col-12 transfer-mode-same">
                            <div class="border rounded-3 p-3 bg-label-light">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0 fw-semibold">
                                        <i class="icon-base ri ri-money-rupee-circle-line me-1 text-success"></i>
                                        Collect from incoming now (optional)
                                    </h6>
                                    <small class="text-muted">Leave blank to start fully unpaid</small>
                                </div>
                                <p class="small text-muted mb-3">
                                    Enter any amount to split across installments in order
                                    (e.g. EMI ₹{{ number_format($preview['same_group_installment_sample'] ?? 0, 0) }} —
                                    ₹3,200 closes 6 months fully and leaves month 7 partial).
                                </p>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold" for="incomingPaymentAmount">Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" min="0" name="incoming_payment_amount"
                                                   id="incomingPaymentAmount" class="form-control"
                                                   value="{{ old('incoming_payment_amount') }}"
                                                   placeholder="0.00"
                                                   data-emi="{{ (float) ($preview['same_group_installment_sample'] ?? 0) }}"
                                                   data-months="{{ (int) ($preview['same_group_total_months'] ?? 0) }}">
                                        </div>
                                        @error('incoming_payment_amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold" for="incomingPaymentMode">Payment Mode</label>
                                        <select name="incoming_payment_mode" id="incomingPaymentMode" class="form-select">
                                            <option value="">Select if collecting</option>
                                            <option value="cash" {{ old('incoming_payment_mode') === 'cash' ? 'selected' : '' }}>Cash in hand</option>
                                            <option value="upi" {{ old('incoming_payment_mode') === 'upi' ? 'selected' : '' }}>UPI / GPay / QR</option>
                                            <option value="bank_transfer" {{ old('incoming_payment_mode') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                            <option value="wallet" {{ old('incoming_payment_mode') === 'wallet' ? 'selected' : '' }}>Customer Wallet</option>
                                        </select>
                                        @error('incoming_payment_mode')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold" for="incomingPaymentRef">Reference No.</label>
                                        <input type="text" name="incoming_payment_reference_no" id="incomingPaymentRef"
                                               class="form-control" value="{{ old('incoming_payment_reference_no') }}"
                                               placeholder="Txn / UTR (optional)">
                                    </div>
                                    <div class="col-12">
                                        @include('admin.partials.bank-collection-fields', [
                                            'bankAccounts' => $bankAccounts ?? collect(),
                                            'bankContainerId' => 'incomingBankAccountContainer',
                                            'bankSelectId' => 'incomingBankAccount',
                                            'bankSelectName' => 'internal_bank_account_id',
                                            'bankDetailsCardId' => 'incomingBankDetailsCard',
                                            'qrContainerId' => 'incomingQrContainer',
                                            'qrBankNameId' => 'incomingQrBankName',
                                            'qrUpiIdId' => 'incomingQrUpiId',
                                            'qrImageWrapperId' => 'incomingQrImageWrapper',
                                            'bankTransferContainerId' => 'incomingBankTransferContainer',
                                            'bankTransferContentId' => 'incomingBankTransferContent',
                                            'wrapperClass' => 'mb-0',
                                        ])
                                        @error('internal_bank_account_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <div id="incomingPaymentPreview" class="small text-muted">Enter an amount to preview how it splits across months.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="confirm_second_seat" id="confirmSecondSeat" value="0">
                        <div class="col-12">
                            <div class="alert alert-warning py-2 mb-0 d-flex align-items-start transfer-mode-same">
                                <i class="icon-base ri ri-alert-line me-2 mt-1"></i>
                                <div>
                                    <strong>{{ $member->client->client_name ?? 'Outgoing member' }}</strong> leaves this ticket.
                                    Incoming gets a <strong>fresh installment schedule</strong> (same EMI amounts).
                                    Leave collection blank to start unpaid from scratch, or collect now to close months FIFO (full then partial).
                                    Outgoing settlement = paid − foreman (Settlement Applications).
                                </div>
                            </div>
                            <div class="alert alert-info py-2 mb-0 d-flex align-items-start transfer-mode-cross d-none">
                                <i class="icon-base ri ri-information-line me-2 mt-1"></i>
                                <div>
                                    <strong>{{ $member->client->client_name ?? 'This member' }}</strong> leaves
                                    <strong>{{ $member->group->group_code ?? 'this group' }}</strong>
                                    (source seat is removed from that group).
                                    Paid total is credited onto the new group’s EMIs (full months, then partial).
                                    If already a member of the destination, you can confirm to add a second seat as
                                    <strong>{{ $member->client->client_name ?? 'Client' }} B</strong>.
                                    After transfer, apply settlement from <strong>Settlement Applications</strong> (paid − foreman) — not from this transfer screen.
                                </div>
                            </div>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="confirmTransferBtn">
                                <i class="icon-base ri ri-exchange-line me-1"></i>Confirm Transfer
                            </button>
                            <a href="{{ route('chit.groups.show', $member->group) }}" class="btn btn-label-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
                <script>
                    (function () {
                        const previewUrl = @json(route('chit.members.transfer.preview', $member));
                        const paidTotal = {{ (float) ($preview['paid_installments_total'] ?? 0) }};
                        const outgoingName = @json($member->client->client_name ?? 'this member');
                        let destinationPreview = null;

                        function formatCreditPreview(data) {
                            destinationPreview = data || null;
                            const box = document.getElementById('crossCreditPreview');
                            if (!box) return;
                            const alloc = data?.credit_allocation;
                            if (!alloc) {
                                box.innerHTML = 'Select a destination group to preview how paid credit applies to its EMI schedule.';
                                return;
                            }
                            const parts = [];
                            if (alloc.months_fully_covered > 0) {
                                parts.push(alloc.months_fully_covered + ' full month(s)');
                            }
                            if (alloc.partial_month) {
                                parts.push('M' + alloc.partial_month + ' partial ₹' + Number(alloc.partial_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 }));
                            }
                            const destCode = data.destination_group_code || 'destination';
                            const sample = data.destination_installment_sample != null
                                ? ' (≈ ₹' + Number(data.destination_installment_sample).toLocaleString('en-IN', { minimumFractionDigits: 2 }) + '/mo)'
                                : '';
                            let html = '<strong>Credit on ' + destCode + sample + ':</strong> '
                                + '₹' + Number(alloc.credit || paidTotal).toLocaleString('en-IN', { minimumFractionDigits: 2 })
                                + ' → ' + (parts.join(', ') || 'no EMIs to apply')
                                + (alloc.leftover_credit > 0.009 ? ' · leftover ₹' + Number(alloc.leftover_credit).toLocaleString('en-IN', { minimumFractionDigits: 2 }) : '');
                            if (data.already_active_in_destination) {
                                html += '<div class="text-warning mt-2"><i class="ri-alert-line"></i> Already a member of '
                                    + destCode + '. Confirming will add a second seat as <strong>'
                                    + (data.second_seat_label || (outgoingName + ' B'))
                                    + '</strong>.</div>';
                            }
                            box.innerHTML = html;
                        }

                        async function refreshCrossCreditPreview() {
                            const dest = document.getElementById('destinationGroupSelect');
                            const destId = dest?.value;
                            const confirmInput = document.getElementById('confirmSecondSeat');
                            if (confirmInput) confirmInput.value = '0';
                            if (!destId) {
                                formatCreditPreview(null);
                                return;
                            }
                            try {
                                const res = await fetch(previewUrl + '?destination_group_id=' + encodeURIComponent(destId), {
                                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                                });
                                const json = await res.json();
                                if (json.success) formatCreditPreview(json.data);
                            } catch (e) {
                                formatCreditPreview(null);
                            }
                        }

                        function syncTransferMode() {
                            const type = document.querySelector('input[name="transfer_type"]:checked')?.value || 'same_group';
                            const same = type === 'same_group';
                            document.querySelectorAll('.transfer-mode-same').forEach(el => el.classList.toggle('d-none', !same));
                            document.querySelectorAll('.transfer-mode-cross').forEach(el => el.classList.toggle('d-none', same));
                            const incoming = document.getElementById('incomingClientSelect');
                            const dest = document.getElementById('destinationGroupSelect');
                            if (incoming) {
                                incoming.required = same;
                                incoming.disabled = !same;
                                if (!same) {
                                    incoming.value = '';
                                    if (window.jQuery && window.jQuery(incoming).data('select2')) {
                                        window.jQuery(incoming).val(null).trigger('change');
                                    }
                                }
                            }
                            if (dest) {
                                dest.required = !same;
                                dest.disabled = same;
                                if (same) {
                                    dest.value = '';
                                    if (window.jQuery && window.jQuery(dest).data('select2')) {
                                        window.jQuery(dest).val(null).trigger('change');
                                    }
                                }
                            }
                            if (!same) refreshCrossCreditPreview();
                        }
                        document.querySelectorAll('input[name="transfer_type"]').forEach(el => {
                            el.addEventListener('change', syncTransferMode);
                        });
                        document.getElementById('destinationGroupSelect')?.addEventListener('change', refreshCrossCreditPreview);
                        syncTransferMode();

                        function updateIncomingPaymentPreview() {
                            const input = document.getElementById('incomingPaymentAmount');
                            const box = document.getElementById('incomingPaymentPreview');
                            if (!input || !box) return;
                            const amount = Math.max(0, parseFloat(input.value || '0') || 0);
                            const emi = Math.max(0, parseFloat(input.dataset.emi || '0') || 0);
                            const months = Math.max(0, parseInt(input.dataset.months || '0', 10) || 0);
                            if (amount <= 0.009) {
                                box.textContent = 'Leave blank to start fully unpaid from scratch.';
                                return;
                            }
                            if (emi <= 0.009) {
                                box.textContent = '₹' + amount.toLocaleString('en-IN', { minimumFractionDigits: 2 }) + ' will be applied FIFO across installments.';
                                return;
                            }
                            let remaining = amount;
                            let full = 0;
                            let partialMonth = null;
                            let partialAmt = 0;
                            const max = months > 0 ? months : 120;
                            for (let m = 1; m <= max && remaining > 0.009; m++) {
                                if (remaining >= emi - 0.01) {
                                    full++;
                                    remaining = Math.round((remaining - emi) * 100) / 100;
                                } else {
                                    partialMonth = m;
                                    partialAmt = remaining;
                                    remaining = 0;
                                }
                            }
                            let text = '₹' + amount.toLocaleString('en-IN', { minimumFractionDigits: 2 })
                                + ' → ' + full + ' full installment(s)';
                            if (partialMonth) {
                                text += ' + month ' + partialMonth + ' partial ₹'
                                    + partialAmt.toLocaleString('en-IN', { minimumFractionDigits: 2 });
                            }
                            if (remaining > 0.009) {
                                text += ' · leftover ₹' + remaining.toLocaleString('en-IN', { minimumFractionDigits: 2 });
                            }
                            box.innerHTML = '<span class="text-success fw-semibold">' + text + '</span>';
                        }

                        document.getElementById('incomingPaymentAmount')?.addEventListener('input', updateIncomingPaymentPreview);
                        updateIncomingPaymentPreview();

                        const form = document.getElementById('transferMemberForm');
                        if (!form) return;

                        let transferConfirmed = false;
                        form.addEventListener('submit', function (e) {
                            if (transferConfirmed) return;

                            e.preventDefault();

                            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                                form.reportValidity();
                                return;
                            }

                            const collectAmount = Math.max(0, parseFloat(document.getElementById('incomingPaymentAmount')?.value || '0') || 0);
                            const modeSelect = document.getElementById('incomingPaymentMode');
                            const bankSelect = document.getElementById('incomingBankAccount');
                            if (collectAmount > 0.009) {
                                if (!modeSelect || !modeSelect.value) {
                                    const msg = 'Select a payment mode for the incoming collection amount.';
                                    if (typeof Swal !== 'undefined') {
                                        Swal.fire({ icon: 'warning', title: 'Payment mode required', text: msg });
                                    } else {
                                        alert(msg);
                                    }
                                    return;
                                }
                                if (modeSelect.value === 'upi' || modeSelect.value === 'bank_transfer') {
                                    const bankError = window.BankPaymentFields
                                        ? window.BankPaymentFields.validateBankPayment(modeSelect, bankSelect, form)
                                        : ((!bankSelect || !bankSelect.value) ? 'Select the company bank account for UPI / bank transfer.' : null);
                                    if (bankError) {
                                        if (typeof Swal !== 'undefined') {
                                            Swal.fire({ icon: 'warning', title: 'Bank account required', text: bankError });
                                        } else {
                                            alert(bankError);
                                        }
                                        return;
                                    }
                                }
                            }

                            const type = document.querySelector('input[name="transfer_type"]:checked')?.value || 'same_group';
                            const isCross = type === 'cross_group';
                            const groupCode = @json($member->group->group_code ?? 'this group');
                            const ticketNo = @json((string) ($member->member_number ?? ''));
                            const confirmInput = document.getElementById('confirmSecondSeat');

                            let title = 'Confirm ticket takeover?';
                            let html = `<p class="mb-2">Replace the ticket in <strong>${groupCode}</strong>`
                                + (ticketNo ? ` (#${ticketNo})` : '')
                                + ` with the selected incoming member.</p>`
                                + `<p class="text-danger mb-0 fw-semibold">This cannot be undone.</p>`;
                            let confirmText = 'Yes, Confirm Takeover';

                            if (isCross) {
                                const destSelect = document.getElementById('destinationGroupSelect');
                                const destLabel = destSelect?.selectedOptions?.[0]?.text?.trim() || 'the selected group';
                                const destCode = destinationPreview?.destination_group_code || destLabel;
                                const needsSecondSeat = !!destinationPreview?.already_active_in_destination;
                                const seatB = destinationPreview?.second_seat_label || (outgoingName + ' B');

                                if (needsSecondSeat) {
                                    title = 'Add second seat in destination?';
                                    html = `<p class="mb-2"><strong>${outgoingName}</strong> is already an active member of <strong>${destCode}</strong>.</p>`
                                        + `<p class="mb-2">Add a second seat as <strong>${seatB}</strong> and remove this membership from <strong>${groupCode}</strong>?</p>`
                                        + `<p class="text-danger mb-0 fw-semibold">Source group data for this seat will be removed. This cannot be undone.</p>`;
                                    confirmText = 'Yes, Add as ' + seatB;
                                    if (confirmInput) confirmInput.value = '1';
                                } else {
                                    title = 'Move member to another group?';
                                    html = `<p class="mb-2">Move <strong>${outgoingName}</strong> from <strong>${groupCode}</strong> to <strong>${destLabel}</strong>.</p>`
                                        + `<p class="mb-2">This seat will be removed from <strong>${groupCode}</strong>.</p>`
                                        + `<p class="text-danger mb-0 fw-semibold">This cannot be undone.</p>`;
                                    confirmText = 'Yes, Move Member';
                                    if (confirmInput) confirmInput.value = '0';
                                }
                            } else if (confirmInput) {
                                confirmInput.value = '0';
                            }

                            const runConfirm = () => {
                                if (typeof Swal === 'undefined') {
                                    const ok = window.confirm(
                                        isCross && destinationPreview?.already_active_in_destination
                                            ? ('Already a member of destination. Add second seat as '
                                                + (destinationPreview?.second_seat_label || (outgoingName + ' B'))
                                                + ' and remove from ' + groupCode + '?')
                                            : (isCross
                                                ? 'Move this member to another group? Source seat will be removed. This cannot be undone.'
                                                : 'Confirm same-group ticket takeover? This cannot be undone.')
                                    );
                                    if (!ok) {
                                        if (confirmInput) confirmInput.value = '0';
                                        return;
                                    }
                                    transferConfirmed = true;
                                    form.submit();
                                    return;
                                }

                                Swal.fire({
                                    icon: 'warning',
                                    title: title,
                                    html: html,
                                    showCancelButton: true,
                                    confirmButtonText: confirmText,
                                    cancelButtonText: 'Cancel',
                                    reverseButtons: true,
                                    focusCancel: true,
                                    customClass: {
                                        confirmButton: 'btn btn-primary me-2',
                                        cancelButton: 'btn btn-label-secondary'
                                    },
                                    buttonsStyling: false
                                }).then((result) => {
                                    if (!result.isConfirmed) {
                                        if (confirmInput) confirmInput.value = '0';
                                        return;
                                    }
                                    transferConfirmed = true;
                                    const btn = document.getElementById('confirmTransferBtn');
                                    if (btn) {
                                        btn.disabled = true;
                                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
                                    }
                                    form.submit();
                                });
                            };

                            runConfirm();
                        });
                    })();
                </script>
            </div>
        </div>
    </div>
</div>

@else
{{-- SHOW / RECEIPT — aligned with Loan Accounts UI --}}
@php
    $isCross = $transfer->isCrossGroup();
    $receiptClient = $isCross
        ? ($transfer->incomingMember?->client ?? $transfer->outgoingMember?->client)
        : null;
@endphp
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-6">
    <div>
        <h4 class="mb-1">Transfer Receipt — {{ $transfer->transfer_code }}</h4>
        <p class="text-heading mb-0">
            @if($isCross)
                <span class="badge bg-label-info me-1">Another Group</span>
                {{ $transfer->group->group_code ?? '' }} → {{ $transfer->destinationGroup->group_code ?? '—' }}
            @else
                <span class="badge bg-label-secondary me-1">Same Group</span>
                {{ $transfer->group->group_code ?? '' }} · Ticket #{{ $transfer->ticket_number }}
            @endif
            · {{ $transfer->transfer_date->format('d M Y') }}
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('chit.transfers.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center">
            <i class="icon-base ri ri-arrow-left-line me-1"></i>All Transfers
        </a>
        @if($transfer->incomingMember)
        <a href="{{ route('chit.accounts.show', $transfer->incomingMember) }}" class="btn btn-primary d-inline-flex align-items-center">
            <i class="icon-base ri ri-eye-line me-1"></i>{{ $isCross ? 'View Member Account' : 'View Incoming Account' }}
        </a>
        @endif
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible">
    <i class="icon-base ri ri-checkbox-circle-line me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if($isCross)
{{-- Cross-group: one member moved between groups (no separate outgoing/incoming people) --}}
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="icon-base ri ri-user-line me-2 text-primary"></i>Member
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <small class="text-muted text-uppercase d-block mb-1">Customer</small>
                    <h6 class="mb-0 fw-semibold text-heading fs-5">{{ $receiptClient?->client_name ?? '—' }}</h6>
                    <small class="text-muted">{{ $receiptClient?->client_phone }}</small>
                </div>
                <div class="row g-4">
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">From Group</small>
                        <h6 class="mb-0 fw-semibold">
                            @if($transfer->group)
                                <a href="{{ route('chit.groups.show', $transfer->group) }}" class="text-decoration-none">{{ $transfer->group->group_code }}</a>
                            @else
                                —
                            @endif
                        </h6>
                        <small class="text-muted">Ticket #{{ $transfer->ticket_number }} (removed)</small>
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">To Group</small>
                        <h6 class="mb-0 fw-semibold">
                            @if($transfer->destinationGroup)
                                <a href="{{ route('chit.groups.show', $transfer->destinationGroup) }}" class="text-decoration-none">{{ $transfer->destinationGroup->group_code }}</a>
                            @else
                                —
                            @endif
                        </h6>
                        @if($transfer->incomingMember)
                            <small class="text-muted">Ticket #{{ $transfer->incomingMember->member_number }}</small>
                        @endif
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Paid in Source</small>
                        <h6 class="mb-0 fw-semibold">₹{{ number_format($transfer->paid_installments_total, 2) }}</h6>
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Completed Rounds</small>
                        <h6 class="mb-0 fw-semibold">{{ $transfer->completed_rounds }}</h6>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="icon-base ri ri-exchange-dollar-line me-2 text-primary"></i>New Group Credit
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">EMI Credit Applied</small>
                        <h6 class="mb-0 fw-bold text-primary fs-5">₹{{ number_format($transfer->paid_installments_total, 2) }}</h6>
                        <small class="text-muted">From source paid total onto new schedule</small>
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Completed Rounds (source)</small>
                        <h6 class="mb-0 fw-semibold">{{ $transfer->completed_rounds }}</h6>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info py-2 mb-0">
                            <i class="icon-base ri ri-information-line me-1"></i>
                            Group-to-group transfer has <strong>no wallet settlement on this receipt</strong>.
                            The member can apply <strong>Settlement Applications</strong> on the source group for paid amount minus foreman commission.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="icon-base ri ri-file-list-3-line me-2 text-primary"></i>Transfer Details
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-3 col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Transfer Code</small>
                        <h6 class="mb-0 text-primary fw-bold">{{ $transfer->transfer_code }}</h6>
                    </div>
                    <div class="col-md-3 col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Processed By</small>
                        <h6 class="mb-0">{{ $transfer->processedBy?->name ?? '—' }}</h6>
                    </div>
                    @if($transfer->remarks)
                    <div class="col-12">
                        <small class="text-muted text-uppercase d-block mb-1">Remarks</small>
                        <p class="mb-0 bg-light p-3 rounded-3 text-heading">{{ $transfer->remarks }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@else
{{-- Same-group: outgoing + incoming members --}}
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="icon-base ri ri-user-unfollow-line me-2 text-danger"></i>Outgoing Member
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <small class="text-muted text-uppercase d-block mb-1">Customer</small>
                    <h6 class="mb-0 fw-semibold text-heading fs-5">{{ $transfer->outgoingMember?->client?->client_name ?? '—' }}</h6>
                    <small class="text-muted">{{ $transfer->outgoingMember?->client?->client_phone }}</small>
                </div>
                <div class="row g-4">
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Completed Rounds</small>
                        <h6 class="mb-0 fw-semibold">{{ $transfer->completed_rounds }}</h6>
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Paid Total</small>
                        <h6 class="mb-0 fw-semibold">₹{{ number_format($transfer->paid_installments_total, 2) }}</h6>
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Settlement Amount</small>
                        <h6 class="mb-0 fw-bold text-success">₹{{ number_format($outgoingSettlementAmount ?? $transfer->outgoing_settlement_amount, 2) }}</h6>
                        <div class="small text-muted">Paid months − foreman commission</div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Settlement Path</small>
                        <span class="badge bg-label-primary">Settlement Applications</span>
                    </div>
                    <div class="col-12">
                        <small class="text-muted text-uppercase d-block mb-1">Settlement Status</small>
                        @if(!empty($outgoingHasPaidSettlement))
                            <span class="badge bg-label-success">Settled</span>
                            @if($transfer->outgoing_settlement_paid_at)
                                <div class="small text-muted mt-1">{{ $transfer->outgoing_settlement_paid_at->format('d M Y, h:i A') }}</div>
                            @endif
                        @else
                            <span class="badge bg-label-warning">Pending application</span>
                            <div class="small text-muted mt-1">
                                Outgoing client applies for settlement of paid installments minus the foreman commission month.
                            </div>
                            <a href="{{ $outgoingSettlementUrl ?? route('chit.settlement-applications.index', ['group_id' => $transfer->group_id]) }}"
                               class="btn btn-sm btn-outline-primary mt-2">
                                <i class="icon-base ri ri-hand-coin-line me-1"></i>Open Settlement Applications
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="icon-base ri ri-user-add-line me-2 text-success"></i>Incoming Member
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <small class="text-muted text-uppercase d-block mb-1">Customer</small>
                    <h6 class="mb-0 fw-semibold text-heading fs-5">{{ $transfer->incomingMember?->client?->client_name ?? '—' }}</h6>
                    <small class="text-muted">{{ $transfer->incomingMember?->client?->client_phone }}</small>
                </div>
                <div class="row g-4">
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Installments Still Due</small>
                        <h6 class="mb-0 fw-semibold">{{ $transfer->remaining_installments }}</h6>
                        <div class="small text-muted">After any transfer collection</div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Collected at Transfer</small>
                        <h6 class="mb-0 fw-semibold">₹{{ number_format($transfer->takeover_amount, 2) }}</h6>
                        @if($transfer->takeover_payment_mode)
                            <div class="small text-muted">{{ $transfer->takeover_payment_mode_label }} · split across EMIs</div>
                        @else
                            <div class="small text-muted">None — started from scratch</div>
                        @endif
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Ticket Number</small>
                        <h6 class="mb-0 fw-semibold">#{{ $transfer->ticket_number }}</h6>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="icon-base ri ri-file-list-3-line me-2 text-primary"></i>Transfer Details
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-3 col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Transfer Code</small>
                        <h6 class="mb-0 text-primary fw-bold">{{ $transfer->transfer_code }}</h6>
                    </div>
                    <div class="col-md-3 col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Chit Group</small>
                        <a href="{{ route('chit.groups.show', $transfer->group) }}" class="fw-semibold text-primary text-decoration-none">
                            {{ $transfer->group->group_code ?? '—' }}
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Ticket Number</small>
                        <h6 class="mb-0 fw-semibold">#{{ $transfer->ticket_number }}</h6>
                    </div>
                    <div class="col-md-3 col-6">
                        <small class="text-muted text-uppercase d-block mb-1">Processed By</small>
                        <h6 class="mb-0">{{ $transfer->processedBy?->name ?? '—' }}</h6>
                    </div>
                    @if($transfer->remarks)
                    <div class="col-12">
                        <small class="text-muted text-uppercase d-block mb-1">Remarks</small>
                        <p class="mb-0 bg-light p-3 rounded-3 text-heading">{{ $transfer->remarks }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endif
@endsection

@if($mode === 'create')
@section('page-script')
<script>
  window._bankPaymentGroupsQueue = [{
    methodSelectId: 'incomingPaymentMode',
    bankSelectId: 'incomingBankAccount',
    bankContainerId: 'incomingBankAccountContainer',
    bankDetailsCardId: 'incomingBankDetailsCard',
    qrContainerId: 'incomingQrContainer',
    qrBankNameId: 'incomingQrBankName',
    qrUpiIdId: 'incomingQrUpiId',
    qrImageWrapperId: 'incomingQrImageWrapper',
    bankTransferContainerId: 'incomingBankTransferContainer',
    bankTransferContentId: 'incomingBankTransferContent'
  }];
</script>
@vite([
    'resources/assets/custom-js/bank-payment-fields.js',
    'resources/assets/custom-js/chit-need-month.js'
])
@endsection
@endif
