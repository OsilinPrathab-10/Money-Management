@extends('layouts/layoutMaster')

@php
    $mode = $mode ?? 'show';
    $isForm = in_array($mode, ['create', 'edit'], true);
    $isEdit = $mode === 'edit';
    $lockFinancials = $lockFinancials ?? false;
@endphp

@section('title', $mode === 'create' ? 'Create Fixed Deposit' : ($isEdit ? 'Edit '.$deposit->fd_number : $deposit->fd_number))

@if($isForm)
@section('vendor-style')
@vite(['resources/assets/vendor/libs/select2/select2.scss'])
@endsection
@section('vendor-script')
@vite(['resources/assets/vendor/libs/select2/select2.js'])
@endsection
@endif

@section('content')
@if($isForm)
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="mb-0">{{ $isEdit ? 'Edit Fixed Deposit' : 'New Fixed Deposit' }}</h4>
        <p class="text-muted mb-0">
            @if($isEdit)
                {{ $deposit->fd_number }} · {{ $deposit->client->client_name ?? '—' }}
            @else
                Open an FD for a customer with live interest preview
            @endif
        </p>
    </div>
    <a href="{{ $isEdit ? route('fd.deposits.show', $deposit) : route('fd.deposits.index') }}" class="btn btn-label-secondary"><i class="ri-arrow-left-line me-1"></i>Back</a>
</div>
@if(session('error'))
<div class="alert alert-danger alert-dismissible mb-4">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="ri-safe-2-line text-primary ri-xl"></i>
                <h5 class="mb-0">Deposit Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ $isEdit ? route('fd.deposits.update', $deposit) : route('fd.deposits.store') }}" id="fdCreateForm">
                    @csrf
                    @if($isEdit)
                        @method('PUT')
                    @endif
                    @if($errors->any())
                    <div class="alert alert-danger mb-3"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                    @endif
                    @if($isEdit && $lockFinancials)
                    <div class="alert alert-warning mb-3">
                        Principal, scheme, dates and payment cannot be changed because this FD already has interest payouts or other transactions. Nominee and payout settings can still be updated.
                    </div>
                    @endif
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Scheme <span class="text-danger">*</span></label>
                            <select name="scheme_id" id="scheme_id" class="form-select select2" required {{ $lockFinancials ? 'disabled' : '' }}>
                                <option value="">Choose scheme…</option>
                                @foreach($schemes as $scheme)
                                    <option value="{{ $scheme->id }}" data-rate="{{ $scheme->interest_rate }}" data-tenure-type="{{ $scheme->tenure_type }}"
                                        data-min-tenure="{{ $scheme->min_tenure }}" data-max-tenure="{{ $scheme->max_tenure }}"
                                        data-payout="{{ $scheme->default_payout_option }}" data-auto-renewal="{{ $scheme->auto_renewal ? '1' : '0' }}"
                                        data-renewal-type="{{ $scheme->renewal_type }}"
                                        {{ (string) old('scheme_id', $isEdit ? $deposit->scheme_id : request('scheme_id')) === (string) $scheme->id ? 'selected' : '' }}>
                                        {{ $scheme->name }} — {{ number_format((float) $scheme->interest_rate, 2) }}% ({{ $scheme->scheme_code }})
                                    </option>
                                @endforeach
                            </select>
                            @if($lockFinancials)
                                <input type="hidden" name="scheme_id" value="{{ $deposit->scheme_id }}">
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer <span class="text-danger">*</span></label>
                            <select name="client_id" id="client_id" class="form-select select2" required {{ $isEdit ? 'disabled' : '' }}>
                                <option value="">Choose customer…</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" {{ (string) old('client_id', $isEdit ? $deposit->client_id : '') === (string) $client->id ? 'selected' : '' }}>
                                        {{ $client->client_name }} @if($client->client_phone)({{ $client->client_phone }})@endif
                                    </option>
                                @endforeach
                            </select>
                            @if($isEdit)
                                <input type="hidden" name="client_id" value="{{ $deposit->client_id }}">
                                <div class="form-text">Customer cannot be changed after the FD is created.</div>
                            @endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Deposit Amount (₹) <span class="text-danger">*</span></label>
                            <input type="number" name="deposit_amount" id="deposit_amount" class="form-control" value="{{ old('deposit_amount', $isEdit ? $deposit->deposit_amount : '') }}" required min="1" step="0.01" {{ $lockFinancials ? 'readonly' : '' }}>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Deposit Date <span class="text-danger">*</span></label>
                            <input type="date" name="deposit_date" id="deposit_date" class="form-control" value="{{ old('deposit_date', $isEdit ? optional($deposit->deposit_date)->format('Y-m-d') : today()->format('Y-m-d')) }}" required {{ $lockFinancials ? 'readonly' : '' }}>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="start_date" class="form-control" value="{{ old('start_date', $isEdit ? optional($deposit->start_date)->format('Y-m-d') : today()->format('Y-m-d')) }}" required {{ $lockFinancials ? 'readonly' : '' }}>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tenure <span class="text-danger">*</span></label>
                            <input type="number" name="tenure" id="tenure" class="form-control" value="{{ old('tenure', $isEdit ? $deposit->tenure : '') }}" required min="1" {{ $lockFinancials ? 'readonly' : '' }}>
                            <div class="form-text" id="tenureHint">Select a scheme for tenure limits</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Nominee Name</label>
                            <input type="text" name="nominee_name" class="form-control" value="{{ old('nominee_name', $isEdit ? $deposit->nominee_name : '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Nominee Relation</label>
                            <input type="text" name="nominee_relation" class="form-control" value="{{ old('nominee_relation', $isEdit ? $deposit->nominee_relation : '') }}" placeholder="e.g. Spouse, Son">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Payment Mode <span class="text-danger">*</span></label>
                            @php
                                $selectedPayMode = old('payment_mode', $isEdit ? ($creationTxn->payment_mode ?? 'cash') : 'cash');
                            @endphp
                            <select name="payment_mode" id="fd_payment_mode" class="form-select" required {{ $lockFinancials ? 'disabled' : '' }}>
                                <option value="cash" {{ $selectedPayMode === 'cash' ? 'selected' : '' }}>Cash</option>
                                <option value="upi" {{ $selectedPayMode === 'upi' ? 'selected' : '' }}>UPI</option>
                                <option value="bank_transfer" {{ $selectedPayMode === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                            </select>
                            @if($lockFinancials)
                                <input type="hidden" name="payment_mode" value="{{ $selectedPayMode }}">
                            @endif
                            <div class="form-text">Cash (no bank) credits Cash in Hand</div>
                        </div>
                        <div class="col-md-4 fd-deposit-bank">
                            <label class="form-label fw-semibold">Company Bank Account</label>
                            <select name="internal_bank_account_id" id="fd_internal_bank_account_id" class="form-select" {{ $lockFinancials ? 'disabled' : '' }}>
                                <option value="">— Select for UPI / Bank Transfer —</option>
                                @foreach(($bankAccounts ?? []) as $account)
                                    <option value="{{ $account->id }}" {{ (string) old('internal_bank_account_id', $isEdit ? $deposit->internal_bank_account_id : '') === (string) $account->id ? 'selected' : '' }}>
                                        {{ $account->bank_name }} - {{ $account->account_name }} (Bal: ₹{{ number_format((float) $account->current_balance, 2) }})
                                    </option>
                                @endforeach
                            </select>
                            @if($lockFinancials && $deposit->internal_bank_account_id)
                                <input type="hidden" name="internal_bank_account_id" value="{{ $deposit->internal_bank_account_id }}">
                            @endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Payout Option <span class="text-danger">*</span></label>
                            <select name="payout_option" id="payout_option" class="form-select" required>
                                @foreach($payoutOptions as $val => $label)
                                    <option value="{{ $val }}" {{ old('payout_option', $isEdit ? $deposit->payout_option : 'wallet') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="auto_renewal" id="auto_renewal" value="1" {{ old('auto_renewal', $isEdit ? $deposit->auto_renewal : false) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="auto_renewal">Auto Renewal</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Renewal Type</label>
                            <select name="renewal_type" id="renewal_type" class="form-select">
                                <option value="">—</option>
                                @foreach($renewalTypes as $val => $label)
                                    <option value="{{ $val }}" {{ old('renewal_type', $isEdit ? $deposit->renewal_type : '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $isEdit ? $deposit->remarks : '') }}</textarea>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i>{{ $isEdit ? 'Update Fixed Deposit' : 'Create Fixed Deposit' }}</button>
                            <a href="{{ $isEdit ? route('fd.deposits.show', $deposit) : route('fd.deposits.index') }}" class="btn btn-label-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-border-shadow-primary sticky-top" style="top: 1rem;">
            <div class="card-header"><h5 class="mb-0"><i class="ri-calculator-line me-1 text-primary"></i>Calculation Preview</h5></div>
            <div class="card-body">
                <div id="calcLoading" class="text-center text-muted py-3 d-none"><div class="spinner-border spinner-border-sm text-primary mb-2"></div><div>Calculating…</div></div>
                <div id="calcEmpty" class="text-center text-muted py-4"><i class="ri-calculator-line d-block mb-2" style="font-size:2rem;"></i>Select scheme, amount, tenure and start date</div>
                <div id="calcResult" class="d-none">
                    <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Interest Rate</span><strong id="prev_rate">—</strong></div>
                    <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Interest Type</span><strong id="prev_type">—</strong></div>
                    <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Maturity Date</span><strong id="prev_maturity_date">—</strong></div>
                    <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Interest Amount</span><strong class="text-success" id="prev_interest">—</strong></div>
                    <div class="d-flex justify-content-between py-2"><span class="text-muted">Maturity Amount</span><strong class="text-primary fs-5" id="prev_maturity_amt">—</strong></div>
                    <div class="alert alert-info py-2 mt-3 mb-0 small" id="prev_limits"></div>
                </div>
                <div id="calcError" class="alert alert-danger d-none mt-2 mb-0 small"></div>
            </div>
        </div>
    </div>
</div>
@else
@if(session('success'))
<div class="alert alert-success alert-dismissible mb-4">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible mb-4">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="mb-0">{{ $deposit->fd_number }}</h4>
            <span class="badge bg-{{ $deposit->status_badge }}">{{ $deposit->status_label }}</span>
        </div>
        <p class="text-muted mb-0">
            {{ $deposit->client->client_name ?? '—' }} · {{ $deposit->scheme->name ?? '—' }}
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('fd.deposits.certificate', $deposit) }}" class="btn btn-sm btn-outline-primary" target="_blank">
            <i class="ri-file-paper-2-line me-1"></i>Certificate
        </a>
        @if($deposit->canEdit())
        <a href="{{ route('fd.deposits.edit', $deposit) }}" class="btn btn-sm btn-primary">
            <i class="ri-edit-2-line me-1"></i>Edit
        </a>
        @endif
        @if($deposit->canProcessMaturity())
        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#maturityModal">
            <i class="ri-checkbox-circle-line me-1"></i>Process Maturity
        </button>
        @endif
        @if($deposit->status === 'active' && ($deposit->scheme->premature_withdrawal_allowed ?? false))
        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#prematureModal">
            <i class="ri-alarm-warning-line me-1"></i>Premature
        </button>
        @endif
        @if(in_array($deposit->status, ['active', 'matured'], true))
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#renewModal">
            <i class="ri-refresh-line me-1"></i>Renew
        </button>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#closeModal">
            <i class="ri-lock-line me-1"></i>Close
        </button>
        @endif
        @if($deposit->canDelete())
        <form action="{{ route('fd.deposits.destroy', $deposit) }}" method="POST" class="d-inline"
              onsubmit="return confirm('Delete this Fixed Deposit? This will reverse the deposit receipt from accounts.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="ri-delete-bin-line me-1"></i>Delete
            </button>
        </form>
        @endif
        <a href="{{ route('fd.deposits.index') }}" class="btn btn-sm btn-label-secondary">Back</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl">
        <div class="card card-border-shadow-primary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="avatar avatar-sm me-3">
                        <span class="avatar-initial rounded-3 bg-label-primary">
                            <i class="ri-money-rupee-circle-line"></i>
                        </span>
                    </div>
                    <small class="text-muted">Principal</small>
                </div>
                <h4 class="mb-0 fw-bold">₹{{ number_format((float) $deposit->deposit_amount, 2) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card card-border-shadow-success h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="avatar avatar-sm me-3">
                        <span class="avatar-initial rounded-3 bg-label-success">
                            <i class="ri-percent-line"></i>
                        </span>
                    </div>
                    <small class="text-muted">Total Interest</small>
                </div>
                <h4 class="mb-0 fw-bold text-success">₹{{ number_format((float) $deposit->interest_amount, 2) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card card-border-shadow-info h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="avatar avatar-sm me-3">
                        <span class="avatar-initial rounded-3 bg-label-info">
                            <i class="ri-wallet-3-line"></i>
                        </span>
                    </div>
                    <small class="text-muted">Interest in Wallet</small>
                </div>
                <h4 class="mb-0 fw-bold text-info">₹{{ number_format((float) ($deposit->interest_paid_to_wallet ?? 0), 2) }}</h4>
                @if($deposit->client_id)
                <a href="{{ route('fd.wallets.client', $deposit->client_id) }}" class="small">View wallet</a>
                @endif
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card card-border-shadow-warning h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="avatar avatar-sm me-3">
                        <span class="avatar-initial rounded-3 bg-label-warning">
                            <i class="ri-funds-line"></i>
                        </span>
                    </div>
                    <small class="text-muted">Maturity Amount</small>
                </div>
                <h4 class="mb-0 fw-bold text-primary">₹{{ number_format((float) $deposit->maturity_amount, 2) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card card-border-shadow-secondary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="avatar avatar-sm me-3">
                        <span class="avatar-initial rounded-3 bg-label-secondary">
                            <i class="ri-timer-line"></i>
                        </span>
                    </div>
                    <small class="text-muted">Remaining Days</small>
                </div>
                <h4 class="mb-0 fw-bold {{ $deposit->remaining_days <= 7 && $deposit->status === 'active' ? 'text-warning' : '' }}">
                    {{ $deposit->remaining_days }}
                </h4>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        @php
            $totalTenureDays = max(1, (int) $deposit->start_date->diffInDays($deposit->maturity_date));
            $elapsedDays = $deposit->status === 'active'
                ? min($totalTenureDays, max(0, (int) $deposit->start_date->diffInDays(now()->startOfDay())))
                : $totalTenureDays;
            $tenureProgress = min(100, max(0, round(($elapsedDays / $totalTenureDays) * 100)));
            $isMaturedOrPast = $deposit->maturity_date->lte(now()->startOfDay());
        @endphp

        <div class="card mb-3 overflow-hidden">
            <div class="card-header border-bottom bg-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar avatar-sm">
                            <span class="avatar-initial rounded-3 bg-label-primary">
                                <i class="ri-information-line ri-20px"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="mb-0">Deposit Information</h5>
                            <small class="text-muted">Account details, interest terms & payout configuration</small>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-label-{{ $deposit->status_badge }} rounded-pill px-3">
                            {{ $deposit->status_label }}
                        </span>
                        <span class="badge bg-label-info rounded-pill px-3" id="autoRenewalHeaderBadge" style="{{ $deposit->auto_renewal ? '' : 'display: none;' }}">
                            <i class="ri-refresh-line me-1"></i>Auto Renewal
                        </span>
                    </div>
                </div>
            </div>

            <div class="card-body">
                {{-- Tenure progress --}}
                <div class="rounded-3 p-3 mb-4 {{ $isMaturedOrPast && $deposit->status === 'active' ? 'bg-label-warning' : 'bg-label-primary' }}">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div>
                            <span class="fw-semibold d-block">Tenure Progress</span>
                            <small class="text-muted">
                                {{ $deposit->tenure }} {{ ucfirst($deposit->tenure_type) }}
                                · {{ $deposit->interest_type_label }}
                            </small>
                        </div>
                        <div class="text-end">
                            <span class="fw-bold fs-5 {{ $deposit->remaining_days <= 7 && $deposit->status === 'active' ? 'text-warning' : '' }}">
                                {{ $deposit->remaining_days }}
                            </span>
                            <small class="text-muted d-block">days remaining</small>
                        </div>
                    </div>
                    <div class="progress mb-2" style="height: 10px;">
                        <div class="progress-bar {{ $isMaturedOrPast && $deposit->status === 'active' ? 'bg-warning' : 'bg-primary' }} progress-bar-striped {{ $deposit->status === 'active' ? 'progress-bar-animated' : '' }}"
                             role="progressbar"
                             style="width: {{ $tenureProgress }}%"
                             aria-valuenow="{{ $tenureProgress }}"
                             aria-valuemin="0"
                             aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">
                            <i class="ri-play-circle-line me-1"></i>{{ optional($deposit->start_date)->format('d M Y') }}
                        </span>
                        <span class="fw-medium">{{ $tenureProgress }}% elapsed</span>
                        <span class="text-muted">
                            <i class="ri-flag-line me-1"></i>{{ optional($deposit->maturity_date)->format('d M Y') }}
                        </span>
                    </div>
                </div>

                {{-- Date timeline --}}
                <div class="fd-date-timeline mb-4">
                    <div class="fd-timeline-step {{ $deposit->deposit_date ? 'completed' : '' }}">
                        <div class="fd-timeline-icon"><i class="ri-calendar-check-line"></i></div>
                        <div class="fd-timeline-content">
                            <span class="fd-timeline-label">Deposit Date</span>
                            <span class="fd-timeline-value">{{ optional($deposit->deposit_date)->format('d M Y') ?? '—' }}</span>
                        </div>
                    </div>
                    <div class="fd-timeline-connector"></div>
                    <div class="fd-timeline-step {{ $deposit->start_date ? 'completed' : '' }}">
                        <div class="fd-timeline-icon"><i class="ri-timer-line"></i></div>
                        <div class="fd-timeline-content">
                            <span class="fd-timeline-label">Start Date</span>
                            <span class="fd-timeline-value">{{ optional($deposit->start_date)->format('d M Y') ?? '—' }}</span>
                        </div>
                    </div>
                    <div class="fd-timeline-connector {{ $isMaturedOrPast ? 'completed' : '' }}"></div>
                    <div class="fd-timeline-step {{ $isMaturedOrPast ? 'completed' : ($deposit->status === 'active' ? 'active' : '') }}">
                        <div class="fd-timeline-icon"><i class="ri-flag-2-line"></i></div>
                        <div class="fd-timeline-content">
                            <span class="fd-timeline-label">Maturity Date</span>
                            <span class="fd-timeline-value">{{ optional($deposit->maturity_date)->format('d M Y') ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Grouped details --}}
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="fd-info-group">
                            <h6 class="fd-info-group-title">
                                <i class="ri-user-3-line"></i> Customer
                            </h6>
                            <div class="fd-info-item">
                                <span class="fd-info-label">Name</span>
                                <span class="fd-info-value fw-semibold">{{ $deposit->client->client_name ?? '—' }}</span>
                            </div>
                            <div class="fd-info-item">
                                <span class="fd-info-label">Phone</span>
                                <span class="fd-info-value">
                                    @if($deposit->client?->client_phone)
                                        <a href="tel:{{ $deposit->client->client_phone }}" class="text-body">{{ $deposit->client->client_phone }}</a>
                                    @else
                                        —
                                    @endif
                                </span>
                            </div>
                            <div class="fd-info-item">
                                <span class="fd-info-label">Nominee</span>
                                <span class="fd-info-value">
                                    @if($deposit->nominee_name)
                                        {{ $deposit->nominee_name }}
                                        @if($deposit->nominee_relation)
                                            <span class="badge bg-label-secondary ms-1">{{ $deposit->nominee_relation }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted">Not specified</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="fd-info-group">
                            <h6 class="fd-info-group-title">
                                <i class="ri-safe-2-line"></i> Scheme & Interest
                            </h6>
                            <div class="fd-info-item">
                                <span class="fd-info-label">Scheme</span>
                                <span class="fd-info-value">
                                    {{ $deposit->scheme->name ?? '—' }}
                                    @if($deposit->scheme?->scheme_code)
                                        <code class="ms-1 small">{{ $deposit->scheme->scheme_code }}</code>
                                    @endif
                                </span>
                            </div>
                            <div class="fd-info-item">
                                <span class="fd-info-label">Interest Rate</span>
                                <span class="fd-info-value">
                                    <span class="badge bg-label-success">{{ number_format((float) $deposit->interest_rate, 2) }}% p.a.</span>
                                </span>
                            </div>
                            <div class="fd-info-item">
                                <span class="fd-info-label">Interest Type</span>
                                <span class="fd-info-value">{{ $deposit->interest_type_label }}</span>
                            </div>
                            <div class="fd-info-item">
                                <span class="fd-info-label">Compounding</span>
                                <span class="fd-info-value">{{ ucfirst(str_replace('_', ' ', (string) $deposit->interest_frequency)) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="fd-info-group">
                            <h6 class="fd-info-group-title">
                                <i class="ri-wallet-3-line"></i> Payout & Renewal
                            </h6>
                            <div class="fd-info-item">
                                <span class="fd-info-label">Payout Option</span>
                                <span class="fd-info-value">
                                    @php
                                        $payoutIcon = match($deposit->payout_option) {
                                            'wallet' => 'ri-wallet-3-line',
                                            'chit' => 'ri-group-line',
                                            'bank_transfer' => 'ri-bank-line',
                                            default => 'ri-money-rupee-circle-line',
                                        };
                                    @endphp
                                    <span class="badge bg-label-primary">
                                        <i class="{{ $payoutIcon }} me-1"></i>{{ $deposit->payout_option_label }}
                                    </span>
                                </span>
                            </div>
                            <div class="fd-info-item">
                                <span class="fd-info-label">Auto Renewal</span>
                                <span class="fd-info-value">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input auto-renewal-toggle-switch" type="checkbox" role="switch"
                                               id="autoRenewalToggle_{{ $deposit->id }}"
                                               data-fd-id="{{ $deposit->id }}"
                                               data-url="{{ route('fd.deposits.toggle-auto-renewal', $deposit) }}"
                                               {{ $deposit->auto_renewal ? 'checked' : '' }}
                                               @if($deposit->status !== 'active') disabled @endif>
                                        <label class="form-check-label fw-semibold ms-1" id="autoRenewalStatusText_{{ $deposit->id }}">
                                            <span class="{{ $deposit->auto_renewal ? 'text-success' : 'text-muted' }}">
                                                {{ $deposit->auto_renewal ? 'Enabled' : 'Disabled' }}
                                            </span>
                                        </label>
                                    </div>
                                    @if($deposit->renewal_type)
                                        <small class="text-muted d-block mt-1">
                                            {{ \App\Models\FixedDepositScheme::renewalTypes()[$deposit->renewal_type] ?? $deposit->renewal_type }}
                                        </small>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="fd-info-group">
                            <h6 class="fd-info-group-title">
                                <i class="ri-file-list-3-line"></i> Record
                            </h6>
                            <div class="fd-info-item">
                                <span class="fd-info-label">FD Number</span>
                                <span class="fd-info-value"><code>{{ $deposit->fd_number }}</code></span>
                            </div>
                            <div class="fd-info-item">
                                <span class="fd-info-label">Created By</span>
                                <span class="fd-info-value">{{ $deposit->creator->name ?? '—' }}</span>
                            </div>
                            <div class="fd-info-item">
                                <span class="fd-info-label">Created On</span>
                                <span class="fd-info-value">{{ optional($deposit->created_at)->format('d M Y, h:i A') }}</span>
                            </div>
                            @if($deposit->renewedFrom)
                            <div class="fd-info-item">
                                <span class="fd-info-label">Renewed From</span>
                                <span class="fd-info-value">
                                    <a href="{{ route('fd.deposits.show', $deposit->renewedFrom) }}" class="fw-medium">
                                        {{ $deposit->renewedFrom->fd_number }}
                                    </a>
                                </span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if($deposit->remarks)
                <div class="mt-2 p-3 rounded-3 border border-dashed bg-label-secondary">
                    <div class="d-flex align-items-start gap-2">
                        <i class="ri-chat-3-line text-muted mt-1"></i>
                        <div>
                            <small class="text-muted fw-semibold text-uppercase d-block mb-1" style="font-size:.7rem;letter-spacing:.04em;">Remarks</small>
                            <p class="mb-0">{{ $deposit->remarks }}</p>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        @if($deposit->status === 'premature_closed' || $deposit->closure_date)
        <div class="card mb-3 overflow-hidden">
            <div class="card-header border-bottom bg-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar avatar-sm">
                        <span class="avatar-initial rounded-3 bg-label-warning">
                            <i class="ri-lock-line ri-20px"></i>
                        </span>
                    </div>
                    <div>
                        <h5 class="mb-0">Closure Details</h5>
                        <small class="text-muted">Final settlement information</small>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach([
                        ['icon' => 'ri-calendar-line', 'label' => 'Closure Date', 'value' => optional($deposit->closure_date)->format('d M Y') ?? '—'],
                        ['icon' => 'ri-money-rupee-circle-line', 'label' => 'Closure Amount', 'value' => '₹' . number_format((float) ($deposit->closure_amount ?? 0), 2), 'highlight' => true],
                        ['icon' => 'ri-bank-card-line', 'label' => 'Payment Mode', 'value' => ucfirst(str_replace('_', ' ', (string) ($deposit->closure_payment_mode ?: '—')))],
                        ['icon' => 'ri-hashtag', 'label' => 'Reference', 'value' => $deposit->closure_transaction_ref ?: '—'],
                        ['icon' => 'ri-bank-line', 'label' => 'Customer Bank', 'value' => $deposit->customer_bank_name ?: ($deposit->bank_name ?: optional(optional($deposit->client)->kycDetail)->bank_name ?? '—')],
                        ['icon' => 'ri-user-unfollow-line', 'label' => 'Customer Account No', 'value' => $deposit->customer_account_number ?: ($deposit->account_number ?: optional(optional($deposit->client)->kycDetail)->account_number ?? '—')],
                        ['icon' => 'ri-code-box-line', 'label' => 'IFSC Code', 'value' => $deposit->customer_ifsc_code ?: ($deposit->ifsc_code ?: optional(optional($deposit->client)->kycDetail)->ifsc_code ?? '—')],
                        ['icon' => 'ri-user-line', 'label' => 'Closed By', 'value' => $deposit->closedByUser->name ?? '—'],
                    ] as $item)
                    <div class="col-sm-6">
                        <div class="fd-closure-tile {{ !empty($item['highlight']) ? 'fd-closure-tile-highlight' : '' }}">
                            <i class="{{ $item['icon'] }} fd-closure-icon"></i>
                            <div>
                                <small class="text-muted d-block">{{ $item['label'] }}</small>
                                <span class="fw-semibold {{ !empty($item['highlight']) ? 'text-warning fs-5' : '' }}">{{ $item['value'] }}</span>
                            </div>
                        </div>
                    </div>
                    @endforeach

                    @if($deposit->processing_fee > 0 || $deposit->document_charges > 0 || $deposit->other_charges > 0)
                    <div class="col-12">
                        <div class="p-3 rounded-3 border bg-label-light">
                            <small class="text-muted fw-semibold text-uppercase d-block mb-2" style="font-size:.75rem;">Settlement Charges Breakdown</small>
                            <div class="d-flex flex-wrap gap-4 text-sm">
                                <div><small class="text-muted">Processing Fee:</small> <span class="fw-bold text-danger">₹{{ number_format((float)$deposit->processing_fee, 2) }}</span></div>
                                <div><small class="text-muted">Document Charges:</small> <span class="fw-bold text-danger">₹{{ number_format((float)$deposit->document_charges, 2) }}</span></div>
                                <div><small class="text-muted">Other Charges:</small> <span class="fw-bold text-danger">₹{{ number_format((float)$deposit->other_charges, 2) }}</span></div>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($deposit->payment_proof)
                    <div class="col-12">
                        <div class="p-3 rounded-3 border bg-label-info">
                            <small class="text-muted fw-semibold text-uppercase d-block mb-2" style="font-size:.75rem;">Payment Proof / Cash Photo</small>
                            <a href="{{ asset($deposit->payment_proof) }}" target="_blank" class="d-inline-flex align-items-center gap-2 btn btn-sm btn-outline-info">
                                <i class="ri-image-line fs-5"></i> View Payment Receipt / Cash Photo
                            </a>
                        </div>
                    </div>
                    @endif

                    @if($deposit->closure_remarks)
                    <div class="col-12">
                        <div class="p-3 rounded-3 border border-dashed bg-label-secondary">
                            <small class="text-muted fw-semibold text-uppercase d-block mb-1" style="font-size:.7rem;">Closure Remarks</small>
                            <p class="mb-0">{{ $deposit->closure_remarks }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Transactions</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Date</th><th>Type</th><th class="text-end">Amount</th></tr>
                        </thead>
                        <tbody>
                            @forelse($deposit->transactions as $txn)
                            <tr>
                                <td>{{ optional($txn->created_at)->format('d M Y') }}</td>
                                <td><span class="badge bg-label-secondary">{{ $txn->transaction_type }}</span></td>
                                <td class="text-end">₹{{ number_format((float) $txn->amount, 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No transactions</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if($deposit->monthly_interest_to_wallet && $deposit->interestPayouts->count())
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">Monthly Interest Payouts</h5>
                <span class="badge bg-label-info">{{ $deposit->interestPayouts->count() }} payout(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Period</th>
                                <th>From</th>
                                <th>To</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($deposit->interestPayouts as $payout)
                            <tr>
                                <td>{{ \Carbon\Carbon::create($payout->payout_year, $payout->payout_month, 1)->format('M Y') }}</td>
                                <td>{{ optional($payout->period_from)->format('d M Y') }}</td>
                                <td>{{ optional($payout->period_to)->format('d M Y') }}</td>
                                <td class="text-end text-success fw-medium">₹{{ number_format((float) $payout->interest_amount, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="3" class="text-end fw-semibold">Total Paid to Wallet</td>
                                <td class="text-end fw-bold text-success">₹{{ number_format((float) ($deposit->interest_paid_to_wallet ?? 0), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        @endif

        @if($deposit->chitAllocations->count())
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Chit Allocations</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Group</th><th>Type</th><th class="text-end">Amount</th></tr>
                        </thead>
                        <tbody>
                            @foreach($deposit->chitAllocations as $alloc)
                            <tr>
                                <td>{{ $alloc->chitGroup->group_code ?? '—' }}</td>
                                <td>{{ $alloc->allocation_type }}</td>
                                <td class="text-end">₹{{ number_format((float) $alloc->amount, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- Maturity Modal --}}
<div class="modal fade" id="maturityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('fd.deposits.maturity', $deposit) }}" class="modal-content" enctype="multipart/form-data">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Process Maturity — {{ $deposit->fd_number }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2">
                    Payable maturity amount: <strong>₹{{ number_format((float) $deposit->maturity_amount, 2) }}</strong>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Payout Option <span class="text-danger">*</span></label>
                        <select name="payout_option" id="maturity_payout" class="form-select" required>
                            <option value="wallet">Wallet</option>
                            <option value="chit">Chit Allocation</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="col-md-6 maturity-bank">
                        <label class="form-label fw-semibold">Internal Bank Account <span class="text-danger">*</span></label>
                        <select name="internal_bank_account_id" id="maturity_internal_bank_account_id" class="form-select fd-payout-bank-select">
                            <option value="" disabled selected>-- Select Internal Bank Account --</option>
                            @foreach($bankAccounts as $account)
                                <option value="{{ $account->id }}">
                                    {{ $account->bank_name }} - {{ $account->account_name }} ({{ $account->account_number }}) - Bal: ₹{{ number_format($account->current_balance, 2) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-12 maturity-bank">
                        <div class="card bg-label-secondary border p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-bold text-dark"><i class="ri-bank-line me-1"></i> Customer Bank Details (Verified KYC)</span>
                                @if(optional($deposit->client)->kycDetail && optional($deposit->client->kycDetail)->account_number)
                                    <span class="badge bg-success">Verified KYC Bank</span>
                                @else
                                    <span class="badge bg-warning">Manual Entry</span>
                                @endif
                            </div>
                            <div class="row g-2">
                                <div class="col-md-12 mb-2">
                                    <label class="form-label small fw-semibold text-muted">Select Customer Bank Account</label>
                                    <select id="maturity_customer_bank_select" class="form-select form-select-sm fd-customer-bank-picker"
                                            data-bank-target="#maturity_bank_name"
                                            data-account-target="#maturity_account_number"
                                            data-ifsc-target="#maturity_ifsc_code">
                                        @if(optional($deposit->client)->kycDetail && optional($deposit->client->kycDetail)->account_number)
                                            <option value="kyc" selected
                                                    data-bank="{{ $deposit->client->kycDetail->bank_name }}" 
                                                    data-account="{{ $deposit->client->kycDetail->account_number }}" 
                                                    data-ifsc="{{ $deposit->client->kycDetail->ifsc_code }}">
                                                {{ $deposit->client->kycDetail->bank_name }} - {{ $deposit->client->kycDetail->account_number }} (IFSC: {{ $deposit->client->kycDetail->ifsc_code }}) [KYC Account]
                                            </option>
                                        @endif
                                        <option value="custom" {{ !optional($deposit->client)->kycDetail ? 'selected' : '' }}>Enter Custom Bank Details</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Bank Name</label>
                                    <input type="text" name="bank_name" id="maturity_bank_name" class="form-control form-control-sm"
                                           value="{{ optional(optional($deposit->client)->kycDetail)->bank_name }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Account Number</label>
                                    <input type="text" name="account_number" id="maturity_account_number" class="form-control form-control-sm"
                                           value="{{ optional(optional($deposit->client)->kycDetail)->account_number }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">IFSC Code</label>
                                    <input type="text" name="ifsc_code" id="maturity_ifsc_code" class="form-control form-control-sm"
                                           value="{{ optional(optional($deposit->client)->kycDetail)->ifsc_code }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 maturity-bank">
                        <label class="form-label fw-semibold">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="{{ today()->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-6 maturity-bank">
                        <label class="form-label fw-semibold">UTR / Reference</label>
                        <input type="text" name="utr_reference" class="form-control">
                    </div>

                    <div class="col-md-12 maturity-cash">
                        <div class="alert alert-warning py-2 mb-0 d-flex align-items-center gap-2">
                            <i class="ri-camera-line fs-4"></i>
                            <div>
                                <span class="fw-bold d-block">Cash in Hand Photo Upload Required</span>
                                <small>Please capture or upload photo of cash voucher / cash handover receipt.</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-12 maturity-proof">
                        <label class="form-label fw-semibold">Payment Proof / Photo Upload</label>
                        <input type="file" name="payment_proof" class="form-control" accept="image/*,.pdf">
                        <small class="text-muted">Upload cash voucher, receipt photo, or bank transfer screenshot.</small>
                    </div>

                    <div class="col-md-6 maturity-chit">
                        <label class="form-label fw-semibold">Chit Group</label>
                        <select name="chit_group_id" class="form-select">
                            <option value="">Select group…</option>
                            @foreach($chitGroups as $group)
                                <option value="{{ $group->id }}">{{ $group->group_code ?? ('Group #'.$group->id) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 maturity-chit">
                        <label class="form-label fw-semibold">Allocation Type</label>
                        <select name="allocation_type" class="form-select">
                            <option value="pending_installment">Pending Installment</option>
                            <option value="advance">Advance</option>
                            <option value="settlement">Settlement</option>
                            <option value="join_new">Join New</option>
                            <option value="chit_wallet">Chit Wallet</option>
                        </select>
                    </div>
                    <div class="col-md-6 maturity-chit">
                        <label class="form-label fw-semibold">Allocation Amount</label>
                        <input type="number" name="allocation_amount" class="form-control" step="0.01" min="0"
                               value="{{ $deposit->maturity_amount }}">
                    </div>

                    <div class="col-12 border rounded p-3 my-2" style="background: #f8fafc;">
                        <h6 class="fw-bold mb-3 text-uppercase small text-muted">Settlement Charges (like loan disbursement)</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Processing Fee</label>
                                <input type="number" step="0.01" min="0" name="processing_fee" id="maturity_processing_fee"
                                    class="form-control fd-charge-input" value="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Document Charges</label>
                                <input type="number" step="0.01" min="0" name="document_charges" id="maturity_document_charges"
                                    class="form-control fd-charge-input" value="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Other Charges</label>
                                <input type="number" step="0.01" min="0" name="other_charges" id="maturity_other_charges"
                                    class="form-control fd-charge-input" value="0.00">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Bank Transfer Charges (company)</label>
                                <input type="number" step="0.01" min="0" name="banking_charges" id="maturity_banking_charges"
                                    class="form-control" value="0.00">
                                <small class="text-muted">Paid by company. Posted to bank transactions and expenses. Not deducted from client payout.</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 my-2">
                        <div class="alert alert-primary d-flex justify-content-between align-items-center py-2 mb-0">
                            <span class="fw-semibold">Net Payout Amount:</span>
                            <span class="fw-bold fs-5" id="maturityNetDisplay" data-gross="{{ $deposit->maturity_amount }}">₹{{ number_format($deposit->maturity_amount, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Process Maturity</button>
            </div>
        </form>
    </div>
</div>

{{-- Premature Modal --}}
<div class="modal fade" id="prematureModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('fd.deposits.premature', $deposit) }}" class="modal-content" enctype="multipart/form-data">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Premature Withdrawal — {{ $deposit->fd_number }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Withdrawal Date</label>
                        <input type="date" name="withdrawal_date" class="form-control" value="{{ today()->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Payout Option <span class="text-danger">*</span></label>
                        <select name="payout_option" id="premature_payout" class="form-select" required>
                            <option value="wallet">Wallet</option>
                            <option value="chit">Chit Allocation</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="col-md-6 premature-bank">
                        <label class="form-label fw-semibold">Internal Bank Account <span class="text-danger">*</span></label>
                        <select name="internal_bank_account_id" id="premature_internal_bank_account_id" class="form-select fd-payout-bank-select">
                            <option value="" disabled selected>-- Select Internal Bank Account --</option>
                            @foreach($bankAccounts as $account)
                                <option value="{{ $account->id }}">
                                    {{ $account->bank_name }} - {{ $account->account_name }} ({{ $account->account_number }}) - Bal: ₹{{ number_format($account->current_balance, 2) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-12 premature-bank">
                        <div class="card bg-label-secondary border p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-bold text-dark"><i class="ri-bank-line me-1"></i> Customer Bank Details (Verified KYC)</span>
                                @if(optional($deposit->client)->kycDetail && optional($deposit->client->kycDetail)->account_number)
                                    <span class="badge bg-success">Verified KYC Bank</span>
                                @else
                                    <span class="badge bg-warning">Manual Entry</span>
                                @endif
                            </div>
                            <div class="row g-2">
                                <div class="col-md-12 mb-2">
                                    <label class="form-label small fw-semibold text-muted">Select Customer Bank Account</label>
                                    <select id="premature_customer_bank_select" class="form-select form-select-sm fd-customer-bank-picker"
                                            data-bank-target="#premature_bank_name"
                                            data-account-target="#premature_account_number"
                                            data-ifsc-target="#premature_ifsc_code">
                                        @if(optional($deposit->client)->kycDetail && optional($deposit->client->kycDetail)->account_number)
                                            <option value="kyc" selected
                                                    data-bank="{{ $deposit->client->kycDetail->bank_name }}" 
                                                    data-account="{{ $deposit->client->kycDetail->account_number }}" 
                                                    data-ifsc="{{ $deposit->client->kycDetail->ifsc_code }}">
                                                {{ $deposit->client->kycDetail->bank_name }} - {{ $deposit->client->kycDetail->account_number }} (IFSC: {{ $deposit->client->kycDetail->ifsc_code }}) [KYC Account]
                                            </option>
                                        @endif
                                        <option value="custom" {{ !optional($deposit->client)->kycDetail ? 'selected' : '' }}>Enter Custom Bank Details</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Bank Name</label>
                                    <input type="text" name="bank_name" id="premature_bank_name" class="form-control form-control-sm"
                                           value="{{ optional(optional($deposit->client)->kycDetail)->bank_name }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Account Number</label>
                                    <input type="text" name="account_number" id="premature_account_number" class="form-control form-control-sm"
                                           value="{{ optional(optional($deposit->client)->kycDetail)->account_number }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">IFSC Code</label>
                                    <input type="text" name="ifsc_code" id="premature_ifsc_code" class="form-control form-control-sm"
                                           value="{{ optional(optional($deposit->client)->kycDetail)->ifsc_code }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-12 premature-bank">
                        <label class="form-label fw-semibold">UTR / Reference</label>
                        <input type="text" name="utr_reference" class="form-control">
                    </div>

                    <div class="col-md-12 premature-cash">
                        <div class="alert alert-warning py-2 mb-0 d-flex align-items-center gap-2">
                            <i class="ri-camera-line fs-4"></i>
                            <div>
                                <span class="fw-bold d-block">Cash in Hand Photo Upload Required</span>
                                <small>Please capture or upload photo of cash voucher / cash handover receipt.</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-12 premature-proof">
                        <label class="form-label fw-semibold">Payment Proof / Photo Upload</label>
                        <input type="file" name="payment_proof" class="form-control" accept="image/*,.pdf">
                        <small class="text-muted">Upload cash voucher, receipt photo, or bank transfer screenshot.</small>
                    </div>

                    <div class="col-md-6 premature-chit">
                        <label class="form-label fw-semibold">Chit Group</label>
                        <select name="chit_group_id" class="form-select">
                            <option value="">Select group…</option>
                            @foreach($chitGroups as $group)
                                <option value="{{ $group->id }}">{{ $group->group_code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 premature-chit">
                        <label class="form-label fw-semibold">Allocation Type</label>
                        <select name="allocation_type" class="form-select">
                            <option value="pending_installment">Pending Installment</option>
                            <option value="advance">Advance</option>
                            <option value="settlement">Settlement</option>
                            <option value="join_new">Join New</option>
                            <option value="chit_wallet">Chit Wallet</option>
                        </select>
                    </div>
                    <div class="col-md-6 premature-chit">
                        <label class="form-label fw-semibold">Allocation Amount</label>
                        <input type="number" name="allocation_amount" class="form-control" step="0.01" min="0">
                    </div>

                    <div class="col-12 border rounded p-3 my-2" style="background: #f8fafc;">
                        <h6 class="fw-bold mb-3 text-uppercase small text-muted">Settlement Charges (like loan disbursement)</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Processing Fee</label>
                                <input type="number" step="0.01" min="0" name="processing_fee" id="premature_processing_fee"
                                    class="form-control fd-charge-input" value="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Document Charges</label>
                                <input type="number" step="0.01" min="0" name="document_charges" id="premature_document_charges"
                                    class="form-control fd-charge-input" value="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Other Charges</label>
                                <input type="number" step="0.01" min="0" name="other_charges" id="premature_other_charges"
                                    class="form-control fd-charge-input" value="0.00">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Bank Transfer Charges (company)</label>
                                <input type="number" step="0.01" min="0" name="banking_charges" id="premature_banking_charges"
                                    class="form-control" value="0.00">
                                <small class="text-muted">Paid by company. Posted to bank transactions and expenses. Not deducted from client payout.</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning">Confirm Premature Withdrawal</button>
            </div>
        </form>
    </div>
</div>

{{-- Renew Modal --}}
<div class="modal fade" id="renewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('fd.deposits.renew', $deposit) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Renew Fixed Deposit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-semibold">Renewal Type <span class="text-danger">*</span></label>
                <select name="renewal_type" class="form-select" required>
                    <option value="principal_only">Principal Only</option>
                    <option value="principal_interest" selected>Principal + Interest</option>
                </select>
                <p class="text-muted small mt-2 mb-0">A new FD will be created and this deposit will be marked as renewed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Renew FD</button>
            </div>
        </form>
    </div>
</div>

{{-- Close Modal --}}
<div class="modal fade" id="closeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('fd.deposits.close', $deposit) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Close Fixed Deposit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Closure Date <span class="text-danger">*</span></label>
                        <input type="date" name="closure_date" class="form-control" value="{{ today()->format('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Closure Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" name="closure_amount" id="closeModalAmountInput" class="form-control" step="0.01" min="0"
                               value="{{ $deposit->maturity_amount }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Payment Mode <span class="text-danger">*</span></label>
                        <select name="payment_mode" class="form-select" required>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="upi">UPI</option>
                            <option value="wallet">Wallet</option>
                        </select>
                    </div>
                    <div class="col-md-6 close-bank">
                        <label class="form-label fw-semibold">Internal Bank Account <span class="text-danger">*</span></label>
                        <select name="internal_bank_account_id" id="close_internal_bank_account_id" class="form-select fd-payout-bank-select">
                            <option value="" disabled selected>-- Select Bank Account --</option>
                            @foreach($bankAccounts as $account)
                                <option value="{{ $account->id }}">
                                    {{ $account->bank_name }} - {{ $account->account_name }} ({{ $account->account_number }}) - Bal: ₹{{ number_format($account->current_balance, 2) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Transaction Reference</label>
                        <input type="text" name="transaction_reference" class="form-control">
                    </div>
                    
                    <div class="col-12 border rounded p-3 my-2" style="background: #f8fafc;">
                        <h6 class="fw-bold mb-3 text-uppercase small text-muted">Settlement Charges (like loan disbursement)</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Processing Fee</label>
                                <input type="number" step="0.01" min="0" name="processing_fee" id="close_processing_fee"
                                    class="form-control fd-charge-input" value="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Document Charges</label>
                                <input type="number" step="0.01" min="0" name="document_charges" id="close_document_charges"
                                    class="form-control fd-charge-input" value="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Other Charges</label>
                                <input type="number" step="0.01" min="0" name="other_charges" id="close_other_charges"
                                    class="form-control fd-charge-input" value="0.00">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Bank Transfer Charges (company)</label>
                                <input type="number" step="0.01" min="0" name="banking_charges" id="close_banking_charges"
                                    class="form-control" value="0.00">
                                <small class="text-muted">Paid by company. Posted to bank transactions and expenses. Not deducted from client payout.</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 my-2">
                        <div class="alert alert-primary d-flex justify-content-between align-items-center py-2 mb-0">
                            <span class="fw-semibold">Net Payout Amount:</span>
                            <span class="fw-bold fs-5" id="closeNetDisplay">₹{{ number_format($deposit->maturity_amount, 2) }}</span>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-secondary">Close FD</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@if($isForm)
@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery().select2) jQuery('.select2').select2({ width: '100%' });
    const schemeSelect = document.getElementById('scheme_id');
    const amountInput = document.getElementById('deposit_amount');
    const tenureInput = document.getElementById('tenure');
    const startDateInput = document.getElementById('start_date');
    const payoutSelect = document.getElementById('payout_option');
    const autoRenewal = document.getElementById('auto_renewal');
    const renewalType = document.getElementById('renewal_type');
    const tenureHint = document.getElementById('tenureHint');
    const calcUrl = @json(url('/admin/fd/deposits/calculate'));
    let timer = null;
    let applySchemeDefaults = @json(!$isEdit);
    function money(n) { return '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function onSchemeChange() {
        const opt = schemeSelect.options[schemeSelect.selectedIndex];
        if (!opt || !opt.value) return;
        tenureHint.textContent = 'Allowed: ' + opt.dataset.minTenure + '–' + opt.dataset.maxTenure + ' ' + (opt.dataset.tenureType || 'months');
        if (applySchemeDefaults) {
            if (!tenureInput.value) tenureInput.value = opt.dataset.minTenure;
            if (opt.dataset.payout) payoutSelect.value = opt.dataset.payout;
            autoRenewal.checked = opt.dataset.autoRenewal === '1';
            if (opt.dataset.renewalType) renewalType.value = opt.dataset.renewalType;
        }
        applySchemeDefaults = true;
        scheduleCalc();
    }
    function scheduleCalc() { clearTimeout(timer); timer = setTimeout(runCalc, 350); }
    function runCalc() {
        const schemeId = schemeSelect.value, amount = amountInput.value, tenure = tenureInput.value, startDate = startDateInput.value;
        document.getElementById('calcError').classList.add('d-none');
        if (!schemeId || !amount || !tenure || !startDate) {
            document.getElementById('calcResult').classList.add('d-none');
            document.getElementById('calcEmpty').classList.remove('d-none');
            return;
        }
        document.getElementById('calcEmpty').classList.add('d-none');
        document.getElementById('calcLoading').classList.remove('d-none');
        document.getElementById('calcResult').classList.add('d-none');
        fetch(calcUrl + '?' + new URLSearchParams({ scheme_id: schemeId, deposit_amount: amount, tenure: tenure, start_date: startDate }), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(async res => { const data = await res.json(); if (!res.ok) throw new Error(data.message || 'Calculation failed'); return data; })
        .then(data => {
            document.getElementById('prev_rate').textContent = Number(data.interest_rate).toFixed(2) + '%';
            document.getElementById('prev_type').textContent = data.interest_type_label || data.interest_type;
            document.getElementById('prev_maturity_date').textContent = data.maturity_date_formatted || data.maturity_date;
            document.getElementById('prev_interest').textContent = money(data.interest_amount);
            document.getElementById('prev_maturity_amt').textContent = money(data.maturity_amount);
            document.getElementById('prev_limits').textContent = 'Deposit ₹' + Number(data.min_deposit).toLocaleString('en-IN') + '–₹' + Number(data.max_deposit).toLocaleString('en-IN') + ' · Tenure ' + data.min_tenure + '–' + data.max_tenure + ' ' + data.tenure_type;
            document.getElementById('calcResult').classList.remove('d-none');
        }).catch(err => {
            const el = document.getElementById('calcError'); el.textContent = err.message || 'Unable to calculate'; el.classList.remove('d-none');
            document.getElementById('calcEmpty').classList.remove('d-none');
        }).finally(() => document.getElementById('calcLoading').classList.add('d-none'));
    }
    schemeSelect.addEventListener('change', onSchemeChange);
    [amountInput, tenureInput, startDateInput].forEach(el => { el.addEventListener('input', scheduleCalc); el.addEventListener('change', scheduleCalc); });
    if (window.jQuery) jQuery('#scheme_id').on('change', onSchemeChange);
    if (schemeSelect.value) onSchemeChange();
});
</script>
@endsection
@else
@section('page-style')
<style>
.fd-date-timeline {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0;
    padding: 0 .25rem;
}
.fd-timeline-step {
    flex: 1;
    text-align: center;
    min-width: 0;
}
.fd-timeline-icon {
    width: 42px;
    height: 42px;
    margin: 0 auto .5rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bs-gray-100);
    color: var(--bs-secondary-color);
    font-size: 1.1rem;
    border: 2px solid var(--bs-border-color);
    transition: all .2s ease;
}
.fd-timeline-step.completed .fd-timeline-icon {
    background: rgba(var(--bs-primary-rgb), .12);
    color: var(--bs-primary);
    border-color: var(--bs-primary);
}
.fd-timeline-step.active .fd-timeline-icon {
    background: var(--bs-primary);
    color: #fff;
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 4px rgba(var(--bs-primary-rgb), .2);
}
.fd-timeline-connector {
    flex: 0 0 40px;
    height: 2px;
    background: var(--bs-border-color);
    margin-top: 20px;
}
.fd-timeline-connector.completed {
    background: var(--bs-primary);
}
.fd-timeline-label {
    display: block;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--bs-secondary-color);
    margin-bottom: .15rem;
}
.fd-timeline-value {
    display: block;
    font-size: .85rem;
    font-weight: 600;
}
.fd-info-group {
    background: var(--bs-gray-50);
    border-radius: .5rem;
    padding: 1rem 1.1rem;
    height: 100%;
    border: 1px solid var(--bs-border-color-translucent);
}
.fd-info-group-title {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--bs-secondary-color);
    font-weight: 600;
    margin-bottom: .85rem;
    padding-bottom: .5rem;
    border-bottom: 1px solid var(--bs-border-color-translucent);
}
.fd-info-group-title i {
    margin-right: .35rem;
    color: var(--bs-primary);
}
.fd-info-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    padding: .45rem 0;
}
.fd-info-item + .fd-info-item {
    border-top: 1px dashed var(--bs-border-color-translucent);
}
.fd-info-label {
    font-size: .8rem;
    color: var(--bs-secondary-color);
    flex-shrink: 0;
}
.fd-info-value {
    font-size: .85rem;
    text-align: right;
}
.fd-closure-tile {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    padding: .85rem 1rem;
    border-radius: .5rem;
    background: var(--bs-gray-50);
    border: 1px solid var(--bs-border-color-translucent);
    height: 100%;
}
.fd-closure-tile-highlight {
    background: rgba(var(--bs-warning-rgb), .08);
    border-color: rgba(var(--bs-warning-rgb), .25);
}
.fd-closure-icon {
    font-size: 1.25rem;
    color: var(--bs-secondary-color);
    margin-top: .15rem;
}
.fd-closure-tile-highlight .fd-closure-icon {
    color: var(--bs-warning);
}
@media (max-width: 575.98px) {
    .fd-date-timeline {
        flex-direction: column;
        align-items: stretch;
        gap: .5rem;
    }
    .fd-timeline-step {
        display: flex;
        align-items: center;
        gap: .75rem;
        text-align: left;
    }
    .fd-timeline-icon {
        margin: 0;
        flex-shrink: 0;
        width: 36px;
        height: 36px;
        font-size: 1rem;
    }
    .fd-timeline-connector {
        display: none;
    }
    .fd-info-value {
        text-align: right;
        max-width: 60%;
    }
}
</style>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function togglePayout(selectId, bankCls, chitCls, cashCls, proofCls, bankSelectId) {
        const sel = document.getElementById(selectId);
        if (!sel) return;
        const sync = () => {
            const v = sel.value;
            document.querySelectorAll('.' + bankCls).forEach(el => el.style.display = (v === 'bank_transfer') ? '' : 'none');
            document.querySelectorAll('.' + chitCls).forEach(el => el.style.display = (v === 'chit') ? '' : 'none');
            if (cashCls) {
                document.querySelectorAll('.' + cashCls).forEach(el => el.style.display = (v === 'cash') ? '' : 'none');
            }
            if (proofCls) {
                document.querySelectorAll('.' + proofCls).forEach(el => el.style.display = (v === 'cash' || v === 'bank_transfer') ? '' : 'none');
            }
            const bankSelect = document.getElementById(bankSelectId);
            if (bankSelect) {
                bankSelect.required = (v === 'bank_transfer');
            }
        };
        sel.addEventListener('change', sync);
        sync();
    }
    togglePayout('maturity_payout', 'maturity-bank', 'maturity-chit', 'maturity-cash', 'maturity-proof', 'maturity_internal_bank_account_id');
    togglePayout('premature_payout', 'premature-bank', 'premature-chit', 'premature-cash', 'premature-proof', 'premature_internal_bank_account_id');

    // Customer Bank Account Picker Handler
    document.querySelectorAll('.fd-customer-bank-picker').forEach(function(picker) {
        picker.addEventListener('change', function() {
            const selectedOpt = this.options[this.selectedIndex];
            const bankTarget = document.querySelector(this.getAttribute('data-bank-target'));
            const accountTarget = document.querySelector(this.getAttribute('data-account-target'));
            const ifscTarget = document.querySelector(this.getAttribute('data-ifsc-target'));

            if (selectedOpt && selectedOpt.value === 'kyc') {
                if (bankTarget) bankTarget.value = selectedOpt.getAttribute('data-bank') || '';
                if (accountTarget) accountTarget.value = selectedOpt.getAttribute('data-account') || '';
                if (ifscTarget) ifscTarget.value = selectedOpt.getAttribute('data-ifsc') || '';
            } else if (selectedOpt && selectedOpt.value === 'custom') {
                if (bankTarget) bankTarget.value = '';
                if (accountTarget) accountTarget.value = '';
                if (ifscTarget) ifscTarget.value = '';
            }
        });
    });

    // Close modal payment mode toggler
    const closePaymentMode = document.querySelector('#closeModal select[name="payment_mode"]');
    function syncCloseBank() {
        if (!closePaymentMode) return;
        const v = closePaymentMode.value;
        const isBank = (v === 'bank_transfer' || v === 'upi');
        document.querySelectorAll('.close-bank').forEach(el => el.style.display = isBank ? '' : 'none');
        const bankSelect = document.getElementById('close_internal_bank_account_id');
        if (bankSelect) {
            bankSelect.required = isBank;
        }
    }
    closePaymentMode?.addEventListener('change', syncCloseBank);
    syncCloseBank();

    const fdPayMode = document.getElementById('fd_payment_mode');
    function syncFdDepositBank() {
        if (!fdPayMode) return;
        const v = fdPayMode.value;
        const needBank = (v === 'upi' || v === 'bank_transfer');
        document.querySelectorAll('.fd-deposit-bank').forEach(el => el.style.display = needBank ? '' : 'none');
        const bankSelect = document.getElementById('fd_internal_bank_account_id');
        if (bankSelect) {
            bankSelect.required = needBank;
        }
    }
    fdPayMode?.addEventListener('change', syncFdDepositBank);
    syncFdDepositBank();

    // Dynamic Net Calculator for Modals
    function setupModalNetCalculator(grossInputId, displayId, chargeSelector) {
        const grossInput = document.getElementById(grossInputId);
        const displayEl = document.getElementById(displayId);
        const chargeInputs = document.querySelectorAll(chargeSelector);

        function recalc() {
            let gross = 0;
            if (grossInput) {
                if (grossInput.tagName === 'INPUT') {
                    gross = parseFloat(grossInput.value) || 0;
                } else {
                    gross = parseFloat(grossInput.getAttribute('data-gross')) || 0;
                }
            }
            let totalCharges = 0;
            chargeInputs.forEach(input => {
                totalCharges += parseFloat(input.value) || 0;
            });
            const net = Math.max(0, gross - totalCharges);
            if (displayEl) {
                displayEl.textContent = '₹' + net.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }

        if (grossInput) {
            if (grossInput.tagName === 'INPUT') {
                grossInput.addEventListener('input', recalc);
            }
        }
        chargeInputs.forEach(input => {
            input.addEventListener('input', recalc);
        });
        recalc();
    }

    setupModalNetCalculator('maturityNetDisplay', 'maturityNetDisplay', '#maturityModal .fd-charge-input');
    setupModalNetCalculator('closeModalAmountInput', 'closeNetDisplay', '#closeModal .fd-charge-input');

    // Auto Renewal Toggle AJAX Handler
    document.querySelectorAll('.auto-renewal-toggle-switch').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            const url = this.getAttribute('data-url');
            const isChecked = this.checked;
            const fdId = this.getAttribute('data-fd-id');
            const statusTextEl = document.getElementById('autoRenewalStatusText_' + fdId);
            const badgeEl = document.getElementById('autoRenewalHeaderBadge');
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '{{ csrf_token() }}';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    auto_renewal: isChecked
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (statusTextEl) {
                        statusTextEl.innerHTML = `<span class="${isChecked ? 'text-success' : 'text-muted'}">${isChecked ? 'Enabled' : 'Disabled'}</span>`;
                    }
                    if (badgeEl) {
                        badgeEl.style.display = isChecked ? 'inline-block' : 'none';
                    }
                } else {
                    alert(data.message || 'Failed to update auto renewal state.');
                    this.checked = !isChecked;
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred while updating auto renewal.');
                this.checked = !isChecked;
            });
        });
    });
});
</script>
@endsection
@endif
