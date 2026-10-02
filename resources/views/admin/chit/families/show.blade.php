@extends('layouts/layoutMaster')
@section('title', $family->name . ' — Family')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/select2/select2.scss', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

@section('vendor-script')
@vite(['resources/assets/vendor/libs/select2/select2.js', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.js', 'resources/assets/custom-js/bank-payment-fields.js'])
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/chit-families.js'])
<script>
    window.chitFamilyConfig = {
        bulkCollectUrl: @json(route('chit.families.bulk-collect', $family)),
        familyName: @json($family->name),
    };
    window._bankPaymentGroupsQueue = window._bankPaymentGroupsQueue || [];
    window._bankPaymentGroupsQueue.push({
        methodSelectId: 'bulkPayMethod',
        bankSelectId: 'familyBulkBankAccount',
        bankContainerId: 'familyBulkBankWrap',
        bankDetailsCardId: 'familyBulkBankDetailsCard',
        qrContainerId: 'familyBulkQrContainer',
        qrBankNameId: 'familyBulkQrBankName',
        qrUpiIdId: 'familyBulkQrUpiId',
        qrImageWrapperId: 'familyBulkQrImageWrapper',
        bankTransferContainerId: 'familyBulkBankTransferContainer',
        bankTransferContentId: 'familyBulkBankTransferContent',
    });
</script>
@endsection

@section('content')
{{-- SHOW — aligned with Loan Accounts UI --}}
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-6">
    <div>
        <h4 class="mb-1">{{ $family->name }}</h4>
        <p class="text-heading mb-0">{{ $family->notes ?: 'Family chit members & bulk dues' }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('chit.families.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center">
            <i class="icon-base ri ri-arrow-left-line me-1"></i>Back to Families
        </a>
        <button type="button" class="btn btn-label-primary" data-bs-toggle="modal" data-bs-target="#editFamilyModal">
            <i class="icon-base ri ri-pencil-line me-1"></i>Edit
        </button>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
            <i class="icon-base ri ri-user-add-line me-1"></i>Add Member
        </button>
        <form method="POST" action="{{ route('chit.families.destroy', $family) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this family? This will unassign all members.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-label-danger d-inline-flex align-items-center">
                <i class="icon-base ri ri-delete-bin-line me-1"></i>Delete Family
            </button>
        </form>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible mb-4">
    <i class="icon-base ri ri-checkbox-circle-line me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible mb-4">
    <i class="icon-base ri ri-error-warning-line me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Summary cards like Loan Accounts --}}
<div class="row g-6 mb-6">
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-heading">Family Members</span>
                        <div class="d-flex align-items-center my-1">
                            <h4 class="mb-0 me-2">{{ $family->familyMembers->count() }}</h4>
                        </div>
                        <small class="mb-0">In this family</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="icon-base ri ri-group-line icon-26px"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-heading">Active Chits</span>
                        <div class="d-flex align-items-center my-1">
                            <h4 class="mb-0 me-2">{{ $family->active_chits_count }}</h4>
                        </div>
                        <small class="mb-0">Active enrollments</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="icon-base ri ri-checkbox-circle-line icon-26px"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-heading">Current Dues</span>
                        <div class="d-flex align-items-center my-1">
                            <h4 class="mb-0 me-2">{{ $currentDues->count() }}</h4>
                        </div>
                        <small class="mb-0">Pending installments</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-warning">
                            <i class="icon-base ri ri-time-line icon-26px"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-heading">Total Due Amount</span>
                        <div class="d-flex align-items-center my-1">
                            <h4 class="mb-0 me-2 text-danger">₹{{ number_format($family->total_current_due, 2) }}</h4>
                        </div>
                        <small class="mb-0">Current balance</small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-danger">
                            <i class="icon-base ri ri-money-rupee-circle-line icon-26px"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Family Members & Their Groups --}}
<div class="card mb-6">
    <div class="card-header border-bottom">
        <h5 class="mb-0">
            <i class="icon-base ri ri-group-line me-2 text-primary"></i>Family Members & Chit Groups
        </h5>
    </div>
    <div class="card-datatable table-responsive">
        <table class="table text-nowrap mb-0">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Relationship</th>
                    <th>Chit Groups</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($family->familyMembers as $fm)
                @php $groups = $memberGroups->get($fm->client_id, collect()); @endphp
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar avatar-sm">
                                <span class="avatar-initial rounded-circle bg-label-primary">
                                    {{ strtoupper(substr($fm->client->client_name ?? 'NA', 0, 2)) }}
                                </span>
                            </div>
                            <div>
                                <div class="fw-semibold text-heading">{{ $fm->client->client_name ?? '—' }}</div>
                                @if($fm->is_primary || $family->primary_client_id == $fm->client_id)
                                <span class="badge bg-label-info" style="font-size:.65rem;">Primary</span>
                                @endif
                                <small class="text-muted d-block">{{ $fm->client->client_phone ?? '' }}</small>
                            </div>
                        </div>
                    </td>
                    <td>{{ $fm->relationship ?: '—' }}</td>
                    <td>
                        @forelse($groups as $gm)
                        <span class="badge bg-label-secondary me-1 mb-1">
                            {{ $gm->group->group_code ?? '—' }}
                            @if($gm->group?->scheme) ({{ $gm->group->scheme->name }}) @endif
                        </span>
                        @empty
                        <span class="text-muted small">No active chit enrollment</span>
                        @endforelse
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-1">
                            @if(!empty($fm->client->client_phone))
                                @php
                                    $fmCleanPhone = preg_replace('/\D/', '', $fm->client->client_phone);
                                    if (strlen($fmCleanPhone) === 10) { $fmCleanPhone = '91' . $fmCleanPhone; }
                                @endphp
                                @if($fmCleanPhone)
                                    <a href="https://wa.me/{{ $fmCleanPhone }}" target="_blank" class="btn btn-sm btn-icon btn-text-secondary rounded-pill text-success" title="WhatsApp Member">
                                        <i class="icon-base ri ri-whatsapp-line icon-20px"></i>
                                    </a>
                                    <a href="sms:+{{ $fmCleanPhone }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill text-info" title="SMS Member">
                                        <i class="icon-base ri ri-message-3-line icon-20px"></i>
                                    </a>
                                @endif
                                <a href="tel:{{ preg_replace('/\s+/', '', $fm->client->client_phone) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="Call Member">
                                    <i class="icon-base ri ri-phone-line icon-20px"></i>
                                </a>
                            @endif
                            <form method="POST" action="{{ route('chit.families.members.destroy', [$family, $fm]) }}" class="d-inline" onsubmit="return confirm('Remove this member from the family?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Remove Member">
                                    <i class="icon-base ri ri-user-unfollow-line icon-22px"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center text-muted py-5">
                        <i class="icon-base ri ri-user-unfollow-line icon-32px d-block mb-2"></i>
                        No members in this family yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Current Month Dues — Bulk Pay --}}
<div class="card">
    <div class="card-header border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">
            <i class="icon-base ri ri-bank-card-line me-2 text-primary"></i>Current Chit Dues — Pay in Single Shot
        </h5>
        @if($currentDues->isNotEmpty())
        <button type="button" class="btn btn-success btn-sm" id="btnBulkPayAll" disabled>
            <i class="icon-base ri ri-money-rupee-circle-line me-1"></i>Pay Selected (<span id="selectedCount">0</span>)
        </button>
        @endif
    </div>
    <div class="card-datatable table-responsive">
        <table class="table text-nowrap mb-0" id="familyDuesTable">
            <thead>
                <tr>
                    @if($currentDues->isNotEmpty())
                    <th style="width:40px"><input type="checkbox" class="form-check-input" id="selectAllDues" checked></th>
                    @endif
                    <th>Client</th>
                    <th>Chit Group</th>
                    <th>Month</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th class="text-end">Balance Due</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($currentDues as $due)
                @php
                    $cPhone = $due['client']->client_phone ?? '';
                    $cleanPhone = preg_replace('/\D/', '', $cPhone);
                    if (strlen($cleanPhone) === 10) {
                        $cleanPhone = '91' . $cleanPhone;
                    }
                    $waMsg = '';
                    $smsMsg = '';
                    $btnTitleWa = "Send WhatsApp Reminder";
                    $btnTitleSms = "Send SMS Reminder";
                    if ($cleanPhone) {
                        $pubToken = $due['client']?->id ? \App\Support\HashId::encode($due['client']->id) : null;
                        $pubLink = $pubToken ? route('public.view-chit-schedule', $pubToken) : '';
                        $msgData = [
                            'client_name' => $due['client']->client_name ?? 'Client',
                            'mobile_no' => $cleanPhone,
                            'group_name' => $due['group']->group_code ?? '',
                            'month_number' => $due['month_number'],
                            'amount_due' => $due['balance'],
                            'due_date' => $due['due_date']?->format('d-m-Y') ?? '',
                            'public_link' => $pubLink,
                        ];
                        if ($due['installment']->status === 'overdue') {
                            $msgRes = \App\Helpers\NotificationTemplateHelper::getChitOverdueReminderMessages($msgData);
                            $btnTitleWa = "Send Overdue Reminder via WhatsApp";
                            $btnTitleSms = "Send Overdue Reminder via SMS";
                        } else {
                            $msgRes = \App\Helpers\NotificationTemplateHelper::getChitPendingReminderMessages($msgData);
                            $btnTitleWa = "Send Pending Reminder via WhatsApp";
                            $btnTitleSms = "Send Pending Reminder via SMS";
                        }
                        $waMsg = $msgRes['whatsapp_message'] ?? '';
                        $smsMsg = $msgRes['sms_message'] ?? '';
                    }
                @endphp
                <tr data-installment-id="{{ $due['installment_id'] }}" data-balance="{{ $due['balance'] }}">
                    <td>
                        <input type="checkbox" class="form-check-input due-checkbox" value="{{ $due['installment_id'] }}" data-balance="{{ $due['balance'] }}" checked>
                    </td>
                    <td class="fw-semibold text-heading">{{ $due['client']->client_name ?? '—' }}</td>
                    <td>
                        <span class="fw-medium text-primary">{{ $due['group']->group_code ?? '—' }}</span>
                        @if($due['group']?->scheme)
                        <br><small class="text-muted">{{ $due['group']->scheme->name }}</small>
                        @endif
                    </td>
                    <td>Month {{ $due['month_number'] }}</td>
                    <td>{{ $due['due_date']?->format('d M Y') ?? '—' }}</td>
                    <td><span class="badge bg-label-{{ $due['installment']->status_badge }}">{{ ucfirst($due['installment']->status) }}</span></td>
                    <td class="text-end fw-semibold">₹{{ number_format($due['balance'], 2) }}</td>
                    <td class="text-center">
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            @if($cleanPhone)
                                <a href="https://wa.me/{{ $cleanPhone }}?text={{ rawurlencode($waMsg) }}" target="_blank" class="btn btn-sm btn-icon btn-text-secondary rounded-pill text-success" title="{{ $btnTitleWa }}">
                                    <i class="icon-base ri ri-whatsapp-line icon-20px"></i>
                                </a>
                                <a href="sms:+{{ $cleanPhone }}?body={{ rawurlencode($smsMsg) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill text-info" title="{{ $btnTitleSms }}">
                                    <i class="icon-base ri ri-message-3-line icon-20px"></i>
                                </a>
                                <a href="tel:{{ preg_replace('/\s+/', '', $cPhone) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="Call Client">
                                    <i class="icon-base ri ri-phone-line icon-20px"></i>
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="icon-base ri ri-checkbox-circle-line icon-32px text-success d-block mb-2"></i>
                        All current chit dues for this family are cleared!
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if($currentDues->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="6" class="text-end fw-semibold text-heading">Selected Total:</td>
                    <td class="text-end fw-bold text-danger" id="selectedTotal">₹{{ number_format($family->total_current_due, 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- Sticky bulk pay bar --}}
@if($currentDues->isNotEmpty())
<div class="position-fixed bottom-0 start-50 translate-middle-x mb-4 d-none" id="bulkPayBar" style="z-index:1050; min-width:320px;">
    <div class="card shadow-lg border-success">
        <div class="card-body py-2 px-4 d-flex align-items-center gap-3">
            <div>
                <small class="text-muted d-block">Selected total</small>
                <strong class="text-success fs-5" id="barTotalAmount">₹0.00</strong>
            </div>
            <button type="button" class="btn btn-success ms-auto" id="btnOpenBulkPayModal">
                <i class="icon-base ri ri-money-rupee-circle-line me-1"></i>Pay All Selected
            </button>
        </div>
    </div>
</div>
@endif

{{-- Family Bulk Pay Modal --}}
<div class="modal fade" id="bulkPayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <form id="bulkPayForm" class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            @csrf
            <div class="modal-header bg-label-primary px-3 px-sm-4 py-3 border-bottom border-primary border-opacity-10">
                <div class="d-flex align-items-center gap-2_5">
                    <div class="avatar avatar-xs bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-xs">
                        <i class="ri-checkbox-multiple-line"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-primary mb-0 fs-6">Family Bulk Payment</h5>
                        <small class="text-primary opacity-75" style="font-size:0.75rem;">Collect installment payments for <strong>{{ $family->name }}</strong> members</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-3 p-sm-4">
                {{-- Payment Type / Mode Selection --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold text-dark small mb-1">Payment Mode</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="collection_type" id="payTypeFull" value="full" checked>
                            <label class="form-check-label fw-medium text-dark small" for="payTypeFull">
                                Full Pay (Pay Total Selected Dues)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="collection_type" id="payTypePartial" value="partial">
                            <label class="form-check-label fw-medium text-dark small" for="payTypePartial">
                                Partial Pay (Custom Amount Allocation)
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Selected Summary --}}
                <div class="alert alert-info py-2 px-3 small mb-3 border-0 rounded-3">
                    Paying <strong id="bulkPayCount">0</strong> selected installment(s) for <strong>{{ $family->name }}</strong>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6" id="selectedTotalWrapper">
                        <label class="form-label fw-medium text-dark small mb-1" id="totalAmountLabel" for="bulkPayAmount">Total Amount (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-label-primary text-primary fw-bold">₹</span>
                            <input type="text" id="bulkPayAmount" class="form-control fw-bold text-dark" readonly>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6" id="totalAmountPaidWrapper" style="display: none;">
                        <label class="form-label fw-medium text-dark small mb-1" for="totalAmountPaidInput">Amount to Pay (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-label-primary text-primary fw-bold">₹</span>
                            <input type="number" step="0.01" min="0.01" name="total_amount_paid" class="form-control fw-bold text-dark" id="totalAmountPaidInput" placeholder="Enter amount">
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-medium text-dark small mb-1" for="bulkPayDate">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" name="paid_date" id="bulkPayDate" class="form-control form-control-sm" required value="{{ today()->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-medium text-dark small mb-1" for="bulkPayMethod">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_mode" id="bulkPayMethod" class="form-select form-select-sm no-search" required>
                            <option value="in_hand" selected>Cash in hand</option>
                            <option value="wallet">Customer Wallet</option>
                            <option value="upi">UPI / GPay / QR</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-medium text-dark small mb-1" for="bulkPayReference">Reference No.</label>
                        <input type="text" name="reference_no" id="bulkPayReference" class="form-control form-control-sm" placeholder="Optional receipt / UTR number">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium text-dark small mb-1" for="bulkPayRemarks">Remarks</label>
                    <input type="text" name="remarks" id="bulkPayRemarks" class="form-control form-control-sm" placeholder="Family bulk payment notes...">
                </div>

                <div class="mb-0">
                    @include('admin.partials.bank-collection-fields', [
                        'bankAccounts' => $bankAccounts ?? [],
                        'bankContainerId' => 'familyBulkBankWrap',
                        'bankSelectId' => 'familyBulkBankAccount',
                        'bankSelectName' => 'internal_bank_account_id',
                        'bankDetailsCardId' => 'familyBulkBankDetailsCard',
                        'qrContainerId' => 'familyBulkQrContainer',
                        'qrBankNameId' => 'familyBulkQrBankName',
                        'qrUpiIdId' => 'familyBulkQrUpiId',
                        'qrImageWrapperId' => 'familyBulkQrImageWrapper',
                        'bankTransferContainerId' => 'familyBulkBankTransferContainer',
                        'bankTransferContentId' => 'familyBulkBankTransferContent',
                        'wrapperClass' => 'mb-3',
                    ])
                </div>
            </div>

            <div class="modal-footer bg-light px-3 px-sm-4 py-3 border-top">
                <button type="button" class="btn btn-sm btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-success px-4 shadow-xs" id="bulkPaySubmitBtn">
                    <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                    Confirm Bulk Payment
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Family Modal --}}
<div class="modal fade" id="editFamilyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('chit.families.update', $family) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="icon-base ri ri-pencil-line me-2 text-primary"></i>Edit Family
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Family Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ $family->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Primary Contact</label>
                        <select name="primary_client_id" class="form-select select2">
                            <option value="">None</option>
                            @foreach($family->familyMembers as $fm)
                            <option value="{{ $fm->client_id }}" {{ $family->primary_client_id == $fm->client_id ? 'selected' : '' }}>
                                {{ $fm->client->client_name ?? '—' }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ $family->notes }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="icon-base ri ri-save-line me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Add Member Modal --}}
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('chit.families.members.store', $family) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="icon-base ri ri-user-add-line me-2 text-primary"></i>Add Family Member
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Client <span class="text-danger">*</span></label>
                        <select name="client_id" class="form-select select2" required>
                            <option value="">Select client...</option>
                            @foreach(\App\Models\Client::where('status','active')->whereDoesntHave('chitFamilyMember')->withCount(['groupMembers' => fn($q) => $q->whereIn('status', ['active','approved','applied'])])->orderBy('client_name')->get() as $c)
                            <option value="{{ $c->id }}">{{ $c->client_name }} — {{ $c->client_phone }} ({{ $c->group_members_count }} {{ \Illuminate\Support\Str::plural('group', $c->group_members_count) }})</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Only clients not already in another family are shown.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Relationship <span class="text-muted fw-normal">(optional)</span></label>
                        <select name="relationship" class="form-select">
                            <option value="">— None —</option>
                            @foreach(['Husband','Father','Mother','Son','Daughter','Spouse','Brother','Sister','Other'] as $rel)
                            <option value="{{ $rel }}">{{ $rel }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="icon-base ri ri-user-add-line me-1"></i>Add Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
