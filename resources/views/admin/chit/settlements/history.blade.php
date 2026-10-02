@extends('layouts/layoutMaster')
@section('title', 'Settlement History')

@section('content')
{{-- Settlement History Header --}}
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="mb-1 d-flex align-items-center gap-2">
            <i class="icon-base ri ri-history-line text-primary icon-24px"></i>
            Settlement History @if(!empty($selectedGroup))<span class="text-primary">— {{ $selectedGroup->group_code }}</span>@endif
        </h4>
        <p class="text-muted mb-0">
            @if(!empty($selectedGroup))
                Showing settlement records for Chit Group <strong class="text-dark">{{ $selectedGroup->group_code }}</strong> ({{ $selectedGroup->scheme->scheme_name ?? 'Chit Scheme' }})
            @else
                Complete record of all chit settlements and member payout transactions
            @endif
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if(!empty($selectedGroup))
            <a href="{{ route('chit.groups.show', $selectedGroup) }}" class="btn btn-primary d-inline-flex align-items-center">
                <i class="icon-base ri ri-arrow-left-line me-1"></i>Back to {{ $selectedGroup->group_code }}
            </a>
        @endif
        <a href="{{ route('chit.settlement-applications.index', !empty($selectedGroup) ? ['group_id' => $selectedGroup->id] : []) }}" class="btn btn-outline-secondary d-inline-flex align-items-center">
            <i class="icon-base ri ri-file-list-3-line me-1"></i>Settlement Applications
        </a>
        <a href="{{ route('chit.settlements.export', request()->query()) }}" class="btn btn-success d-inline-flex align-items-center">
            <i class="icon-base ri ri-download-line me-1"></i>Export CSV
        </a>
    </div>
</div>

@if(!empty($selectedGroup))
<div class="alert alert-primary d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4 py-2 px-3">
    <div class="d-flex align-items-center gap-2">
        <i class="icon-base ri ri-information-line icon-20px text-primary"></i>
        <span>
            Filtered View for Group: <strong class="text-primary fs-6">{{ $selectedGroup->group_code }}</strong>
            ({{ $selectedGroup->scheme->scheme_name ?? 'Scheme' }})
        </span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('chit.groups.show', $selectedGroup) }}" class="btn btn-xs btn-primary">
            <i class="icon-base ri ri-eye-line me-1"></i>View Group Details
        </a>
        <a href="{{ route('chit.settlements.history') }}" class="btn btn-xs btn-outline-secondary">
            <i class="icon-base ri ri-close-circle-line me-1"></i>Show All Groups History
        </a>
    </div>
</div>
@endif

{{-- Top Statistics Row --}}
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-medium text-uppercase d-block mb-1">Total Settlements</span>
                    <h4 class="mb-0 fw-bold">{{ number_format($stats['total_count'] ?? 0) }}</h4>
                </div>
                <div class="avatar avatar-md bg-label-primary rounded p-2">
                    <i class="icon-base ri ri-file-list-3-line icon-24px"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-medium text-uppercase d-block mb-1">Completed Payouts</span>
                    <h4 class="mb-0 fw-bold text-success">{{ number_format($stats['paid_count'] ?? 0) }}</h4>
                </div>
                <div class="avatar avatar-md bg-label-success rounded p-2">
                    <i class="icon-base ri ri-checkbox-circle-line icon-24px"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-medium text-uppercase d-block mb-1">Advance Settlements</span>
                    <h4 class="mb-0 fw-bold text-info">{{ number_format($stats['advance_count'] ?? 0) }}</h4>
                </div>
                <div class="avatar avatar-md bg-label-info rounded p-2">
                    <i class="icon-base ri ri-hand-coin-line icon-24px"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-medium text-uppercase d-block mb-1">Total Net Paid</span>
                    <h4 class="mb-0 fw-bold text-primary">₹{{ number_format($stats['total_net_paid'] ?? 0, 2) }}</h4>
                </div>
                <div class="avatar avatar-md bg-label-warning rounded p-2">
                    <i class="icon-base ri ri-money-rupee-circle-line icon-24px"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Main History Table Card --}}
<div class="card">
    <div class="card-header border-bottom d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-label-primary rounded-pill fw-semibold">{{ $settlements->total() }} Records</span>
            @if(!empty($selectedGroup))
                <span class="badge bg-primary text-white d-inline-flex align-items-center gap-1 ms-1">
                    <i class="icon-base ri ri-group-line me-1"></i> Group: {{ $selectedGroup->group_code }}
                    <a href="{{ route('chit.settlements.history', array_diff_key($filters, ['group_id' => ''])) }}" class="text-white ms-1 text-decoration-none fw-bold" title="Clear Group Filter">×</a>
                </span>
            @endif
        </div>
        <form method="GET" action="{{ route('chit.settlements.history') }}" class="d-flex flex-wrap align-items-center gap-2">
            <input type="text" name="search" class="form-control form-control-sm" style="min-width: 160px;"
                   placeholder="Member, group, ref..." value="{{ $filters['search'] ?? '' }}">

            <select name="payout_kind" class="form-select form-select-sm" style="min-width: 130px;" title="Payout Type">
                <option value="">All Payout Types</option>
                <option value="original" {{ ($filters['payout_kind'] ?? '') === 'original' ? 'selected' : '' }}>Original</option>
                <option value="advance" {{ ($filters['payout_kind'] ?? '') === 'advance' ? 'selected' : '' }}>Advance</option>
                <option value="rotation" {{ ($filters['payout_kind'] ?? '') === 'rotation' ? 'selected' : '' }}>Rotation</option>
                <option value="auction" {{ ($filters['payout_kind'] ?? '') === 'auction' ? 'selected' : '' }}>Auction</option>
                <option value="fixed_return" {{ ($filters['payout_kind'] ?? '') === 'fixed_return' ? 'selected' : '' }}>Fixed Return</option>
            </select>

            <select name="group_id" class="form-select form-select-sm" style="min-width: 140px;">
                <option value="">All Groups</option>
                @foreach($groups as $g)
                    <option value="{{ $g->id }}" {{ ($filters['group_id'] ?? '') == $g->id ? 'selected' : '' }}>{{ $g->group_code }}</option>
                @endforeach
            </select>

            <select name="status" class="form-select form-select-sm" style="min-width: 120px;">
                <option value="">All Statuses</option>
                @foreach(['paid', 'pending', 'processing', 'cancelled', 'failed'] as $s)
                    <option value="{{ $s }}" {{ ($filters['status'] ?? '') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>

            <input type="number" name="month_number" class="form-control form-control-sm" style="width: 80px;" min="1"
                   value="{{ $filters['month_number'] ?? '' }}" placeholder="Month #">

            @include('partials.date-range-filter', [
              'fromId' => 'settlementHistoryDateFrom',
              'toId' => 'settlementHistoryDateTo',
              'presetId' => 'settlementHistoryDatePreset',
              'fromName' => 'date_from',
              'toName' => 'date_to',
              'fromValue' => $filters['date_from'] ?? '',
              'toValue' => $filters['date_to'] ?? '',
              'presetValue' => request('date_preset'),
              'autoSubmit' => true,
            ])

            <button type="submit" class="btn btn-sm btn-primary d-inline-flex align-items-center">
                <i class="icon-base ri ri-filter-3-line me-1"></i>Filter
            </button>

            @if(!empty(array_filter($filters)))
                <a href="{{ !empty($selectedGroup) ? route('chit.settlements.history', ['group_id' => $selectedGroup->id]) : route('chit.settlements.history') }}" class="btn btn-sm btn-label-secondary d-inline-flex align-items-center">
                    <i class="icon-base ri ri-refresh-line me-1"></i>Reset
                </a>
            @endif
        </form>
    </div>

    <div class="card-datatable table-responsive">
        <table class="table table-hover align-middle text-nowrap mb-0">
            <thead class="table-light">
                <tr>
                    <th>Settlement Code</th>
                    <th>Member / Client</th>
                    <th>Chit Group</th>
                    <th class="text-center">Month</th>
                    <th>Payout Type</th>
                    <th class="text-end">Gross Payout</th>
                    <th class="text-end">Deductions</th>
                    <th class="text-end">Net Payable</th>
                    <th>Payment Date &amp; Mode</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($settlements as $settlement)
                @php
                    $totalFees = (float) ($settlement->processing_fee ?? 0)
                        + (float) ($settlement->document_charges ?? 0)
                        + (float) ($settlement->other_charges ?? 0);
                    $netAmount = $settlement->net_payout_amount ?? max(0, $settlement->payout_amount - $totalFees);
                    $clientName = $settlement->winner?->client?->client_name ?? '—';
                    $clientPhone = $settlement->winner?->client?->client_phone;
                    $memberNo = $settlement->winner?->member_number;
                    $groupCode = $settlement->group?->group_code ?? '—';
                    $schemeName = $settlement->group?->scheme?->name
                        ?? $settlement->group?->scheme?->scheme_name;

                    $modalPayload = [
                        'code' => $settlement->payout_code,
                        'kind' => $settlement->payout_kind_label,
                        'kind_badge' => $settlement->payout_kind_badge,
                        'client_name' => $clientName,
                        'client_phone' => $clientPhone ?: 'N/A',
                        'member_number' => $memberNo ?: 'N/A',
                        'group_code' => $groupCode,
                        'scheme_name' => $schemeName ?: 'N/A',
                        'month_number' => $settlement->month_number,
                        'gross_amount' => number_format((float) $settlement->payout_amount, 2),
                        'processing_fee' => number_format((float) ($settlement->processing_fee ?? 0), 2),
                        'document_charges' => number_format((float) ($settlement->document_charges ?? 0), 2),
                        'other_charges' => number_format((float) ($settlement->other_charges ?? 0), 2),
                        'banking_charges' => number_format((float) ($settlement->banking_charges ?? 0), 2),
                        'net_amount' => number_format((float) $netAmount, 2),
                        'paid_date' => $settlement->paid_date?->format('d M Y, h:i A') ?: ($settlement->paid_date?->format('d M Y') ?: '—'),
                        'payment_mode' => $settlement->payment_mode_label ?: '—',
                        'reference_no' => $settlement->reference_no ?: '—',
                        'bank_name' => $settlement->bank_name ?: '—',
                        'account_no' => $settlement->account_no ?: '—',
                        'ifsc_code' => $settlement->ifsc_code ?: '—',
                        'status' => $settlement->status_label,
                        'status_badge' => $settlement->status_badge,
                        'processed_by' => $settlement->processedBy?->name ?: '—',
                        'remarks' => $settlement->remarks ?: 'No remarks provided',
                        'promissory_note_url' => route('chit.settlements.promissory-note', $settlement),
                        'promissory_pdf_url' => route('chit.settlements.promissory-note.pdf', $settlement),
                        'is_paid' => $settlement->status === 'paid',
                    ];
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold text-primary">{{ $settlement->payout_code }}</div>
                        <small class="text-muted">{{ $settlement->created_at->format('d M Y') }}</small>
                    </td>
                    <td>
                        <div class="fw-semibold text-heading">{{ $clientName }}</div>
                        <div class="d-flex align-items-center gap-1">
                            @if($clientPhone)
                                <small class="text-muted">{{ $clientPhone }}</small>
                            @endif
                            @if($memberNo)
                                <span class="badge bg-label-secondary py-0 px-1 font-size-11">Slot #{{ $memberNo }}</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        @if($settlement->group)
                            <a href="{{ route('chit.groups.show', $settlement->group) }}" class="fw-semibold text-primary text-decoration-none">
                                {{ $groupCode }}
                            </a>
                            @if($schemeName)
                                <small class="d-block text-muted">{{ $schemeName }}</small>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @php
                            $hStartDate = $settlement->group?->start_date
                                ? \Carbon\Carbon::parse($settlement->group->start_date)
                                : ($settlement->group?->created_at ? \Carbon\Carbon::parse($settlement->group->created_at) : null);
                            $hMonthName = ($hStartDate && $settlement->month_number)
                                ? $hStartDate->copy()->addMonths($settlement->month_number - 1)->format('M Y')
                                : null;
                            $hMonthLabel = $hMonthName ? "Month {$settlement->month_number} — {$hMonthName}" : "Month {$settlement->month_number}";
                        @endphp
                        <span class="badge bg-label-primary">{{ $hMonthLabel }}</span>
                    </td>
                    <td>
                        <span class="badge bg-label-{{ $settlement->payout_kind_badge }}">{{ $settlement->payout_kind_label }}</span>
                    </td>
                    <td class="text-end fw-medium text-dark">
                        ₹{{ number_format($settlement->payout_amount, 2) }}
                    </td>
                    <td class="text-end">
                        @if($totalFees > 0)
                            <span class="text-danger fw-medium">- ₹{{ number_format($totalFees, 2) }}</span>
                            <small class="d-block text-muted" title="Proc: ₹{{ number_format($settlement->processing_fee ?? 0, 2) }} | Doc: ₹{{ number_format($settlement->document_charges ?? 0, 2) }}">
                                Fees Applied
                            </small>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end fw-bold text-success">
                        ₹{{ number_format($netAmount, 2) }}
                    </td>
                    <td>
                        <div>{{ $settlement->paid_date?->format('d M Y') ?? '—' }}</div>
                        <small class="text-muted">{{ $settlement->payment_mode_label }} @if($settlement->reference_no) · Ref: {{ $settlement->reference_no }} @endif</small>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-label-{{ $settlement->status_badge }}">{{ $settlement->status_label }}</span>
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex align-items-center gap-1">
                            <button type="button"
                                    class="btn btn-sm btn-icon btn-outline-primary js-view-settlement-detail"
                                    data-detail="{{ json_encode($modalPayload) }}"
                                    title="View Complete Settlement Details">
                                <i class="icon-base ri ri-eye-line"></i>
                            </button>
                            @if($settlement->status === 'paid')
                                <a href="{{ route('chit.settlements.promissory-note', $settlement) }}"
                                   class="btn btn-sm btn-icon btn-outline-secondary"
                                   target="_blank"
                                   title="Print Promissory Note">
                                    <i class="icon-base ri ri-printer-line"></i>
                                </a>
                                <a href="{{ route('chit.settlements.promissory-note.pdf', $settlement) }}"
                                   class="btn btn-sm btn-icon btn-outline-success"
                                   title="Download Promissory Note PDF">
                                    <i class="icon-base ri ri-file-pdf-2-line"></i>
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" class="text-center py-5 text-muted">
                        <i class="icon-base ri ri-file-list-3-line icon-32px d-block mb-2 text-secondary"></i>
                        <span class="fw-medium">No settlement records found</span>
                        <p class="small text-muted mb-0">Try clearing or adjusting your search filters above.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($settlements->hasPages())
    <div class="card-footer d-flex justify-content-between align-items-center">
        <span class="small text-muted">
            Showing {{ $settlements->firstItem() }} to {{ $settlements->lastItem() }} of {{ $settlements->total() }} settlement records
        </span>
        <div>{{ $settlements->links() }}</div>
    </div>
    @endif
</div>

{{-- Settlement Detail View Modal --}}
<div class="modal fade" id="modalSettlementDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="modal-title mb-0" id="detailModalTitle">Settlement Details</h5>
                    <span id="detailModalStatusBadge" class="badge"></span>
                    <span id="detailModalKindBadge" class="badge"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                {{-- Member & Group Info Cards --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card bg-label-secondary border-0 h-100">
                            <div class="card-body p-3">
                                <span class="text-muted small text-uppercase fw-semibold d-block mb-2">Member / Client Information</span>
                                <h6 class="mb-1 text-primary fw-bold" id="detailClientName">—</h6>
                                <div class="small text-muted mb-1">
                                    <i class="icon-base ri ri-phone-line me-1"></i><span id="detailClientPhone">—</span>
                                </div>
                                <div class="small text-muted">
                                    <i class="icon-base ri ri-user-star-line me-1"></i>Member Slot: <span id="detailMemberNumber" class="fw-semibold text-dark">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-label-secondary border-0 h-100">
                            <div class="card-body p-3">
                                <span class="text-muted small text-uppercase fw-semibold d-block mb-2">Chit Group Details</span>
                                <h6 class="mb-1 text-dark fw-bold" id="detailGroupCode">—</h6>
                                <div class="small text-muted mb-1">
                                    <i class="icon-base ri ri-layout-grid-line me-1"></i>Scheme: <span id="detailSchemeName">—</span>
                                </div>
                                <div class="small text-muted">
                                    <i class="icon-base ri ri-calendar-check-line me-1"></i>Applied Month: <span id="detailMonthNumber" class="fw-semibold text-primary">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Financial Summary Breakdown --}}
                <div class="table-responsive border rounded mb-4">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Breakdown Item</th>
                                <th class="text-end" style="width: 180px;">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="fw-medium">Gross Payout Amount</td>
                                <td class="text-end fw-semibold text-dark" id="detailGrossAmount">₹ 0.00</td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-4">Less: Processing Fee</td>
                                <td class="text-end text-danger" id="detailProcessingFee">- ₹ 0.00</td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-4">Less: Document Charges</td>
                                <td class="text-end text-danger" id="detailDocumentCharges">- ₹ 0.00</td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-4">Less: Other Deductions</td>
                                <td class="text-end text-danger" id="detailOtherCharges">- ₹ 0.00</td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-4">Bank Transfer Charges (company)</td>
                                <td class="text-end text-muted" id="detailBankingCharges">₹ 0.00</td>
                            </tr>
                            <tr class="table-success">
                                <td class="fw-bold">Net Payable Amount</td>
                                <td class="text-end fw-bold text-success fs-6" id="detailNetAmount">₹ 0.00</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Payment Details & Transaction Metadata --}}
                <div class="card border mb-0">
                    <div class="card-body p-3">
                        <h6 class="card-title text-uppercase small text-muted mb-3 fw-bold">Payment & Transaction Audit</h6>
                        <div class="row g-3">
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted small d-block">Payment Date</span>
                                <span class="fw-semibold text-dark" id="detailPaidDate">—</span>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted small d-block">Payment Mode</span>
                                <span class="fw-semibold text-dark" id="detailPaymentMode">—</span>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted small d-block">Reference No</span>
                                <span class="fw-semibold text-dark" id="detailReferenceNo">—</span>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted small d-block">Bank Name</span>
                                <span class="fw-semibold text-dark" id="detailBankName">—</span>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted small d-block">Account / Ref</span>
                                <span class="fw-semibold text-dark" id="detailAccountNo">—</span>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <span class="text-muted small d-block">Processed By</span>
                                <span class="fw-semibold text-dark" id="detailProcessedBy">—</span>
                            </div>
                            <div class="col-12 border-top pt-2 mt-2">
                                <span class="text-muted small d-block">Remarks</span>
                                <span class="text-dark small" id="detailRemarks">—</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top d-flex flex-wrap gap-2 justify-content-between">
                <div class="d-flex flex-wrap gap-2" id="detailPromissoryActions">
                    <a href="#" id="detailPromissoryPrint" class="btn btn-outline-secondary d-none" target="_blank">
                        <i class="icon-base ri ri-printer-line me-1"></i>Print Promissory Note
                    </a>
                    <a href="#" id="detailPromissoryPdf" class="btn btn-outline-success d-none">
                        <i class="icon-base ri ri-file-pdf-2-line me-1"></i>Download PDF
                    </a>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const detailModalElement = document.getElementById('modalSettlementDetail');
    if (!detailModalElement) return;

    const detailModal = new bootstrap.Modal(detailModalElement);

    document.querySelectorAll('.js-view-settlement-detail').forEach(button => {
        button.addEventListener('click', function () {
            try {
                const data = JSON.parse(this.dataset.detail || '{}');

                document.getElementById('detailModalTitle').textContent = `Settlement ${data.code || ''}`;
                
                const statusBadge = document.getElementById('detailModalStatusBadge');
                statusBadge.textContent = data.status || '';
                statusBadge.className = `badge bg-label-${data.status_badge || 'primary'}`;

                const kindBadge = document.getElementById('detailModalKindBadge');
                kindBadge.textContent = data.kind || '';
                kindBadge.className = `badge bg-label-${data.kind_badge || 'info'}`;

                document.getElementById('detailClientName').textContent = data.client_name || '—';
                document.getElementById('detailClientPhone').textContent = data.client_phone || '—';
                document.getElementById('detailMemberNumber').textContent = data.member_number ? `#${data.member_number}` : '—';

                document.getElementById('detailGroupCode').textContent = data.group_code || '—';
                document.getElementById('detailSchemeName').textContent = data.scheme_name || '—';
                document.getElementById('detailMonthNumber').textContent = data.month_number ? `Month ${data.month_number}` : '—';

                document.getElementById('detailGrossAmount').textContent = `₹ ${data.gross_amount || '0.00'}`;
                document.getElementById('detailProcessingFee').textContent = `- ₹ ${data.processing_fee || '0.00'}`;
                document.getElementById('detailDocumentCharges').textContent = `- ₹ ${data.document_charges || '0.00'}`;
                document.getElementById('detailOtherCharges').textContent = `- ₹ ${data.other_charges || '0.00'}`;
                const bankingEl = document.getElementById('detailBankingCharges');
                if (bankingEl) bankingEl.textContent = `₹ ${data.banking_charges || '0.00'}`;
                document.getElementById('detailNetAmount').textContent = `₹ ${data.net_amount || '0.00'}`;

                document.getElementById('detailPaidDate').textContent = data.paid_date || '—';
                document.getElementById('detailPaymentMode').textContent = data.payment_mode || '—';
                document.getElementById('detailReferenceNo').textContent = data.reference_no || '—';
                document.getElementById('detailBankName').textContent = data.bank_name || '—';
                document.getElementById('detailAccountNo').textContent = data.account_no || '—';
                document.getElementById('detailProcessedBy').textContent = data.processed_by || '—';
                document.getElementById('detailRemarks').textContent = data.remarks || 'No remarks provided';

                const printBtn = document.getElementById('detailPromissoryPrint');
                const pdfBtn = document.getElementById('detailPromissoryPdf');
                if (printBtn && pdfBtn) {
                    if (data.is_paid && data.promissory_note_url && data.promissory_pdf_url) {
                        printBtn.href = data.promissory_note_url;
                        pdfBtn.href = data.promissory_pdf_url;
                        printBtn.classList.remove('d-none');
                        pdfBtn.classList.remove('d-none');
                    } else {
                        printBtn.classList.add('d-none');
                        pdfBtn.classList.add('d-none');
                    }
                }

                detailModal.show();
            } catch (err) {
                console.error('Failed to parse settlement details:', err);
            }
        });
    });
});
</script>
@endsection
