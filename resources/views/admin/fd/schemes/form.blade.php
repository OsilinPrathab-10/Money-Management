@extends('layouts/layoutMaster')

@php
    $mode = $mode ?? 'show';
    $isCreate = $mode === 'create';
    $isEdit = $mode === 'edit';
    $isShow = $mode === 'show';
    $isForm = $isCreate || $isEdit;
    $schemeObj = $scheme ?? null;
    $val = function ($field, $default = '') use ($isForm, $schemeObj) {
        if (!$isForm) {
            return $default;
        }
        return old($field, $schemeObj ? ($schemeObj->{$field} ?? $default) : $default);
    };
    $checked = function ($field) use ($isForm, $schemeObj) {
        if (!$isForm) {
            return false;
        }
        return (bool) old($field, $schemeObj ? $schemeObj->{$field} : false);
    };
@endphp

@section('title', $isCreate ? 'Create FD Scheme' : ($isEdit ? 'Edit FD Scheme' : ($scheme->name ?? 'FD Scheme')))

@section('content')
@if(session('success'))
<div class="alert alert-success alert-dismissible mb-4">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible mb-4">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if($isForm)
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="ri-{{ $isEdit ? 'edit-2' : 'safe-2' }}-line text-primary ri-xl"></i>
                    <h5 class="mb-0">{{ $isEdit ? 'Edit: ' . ($scheme->name ?? '') : 'New Fixed Deposit Scheme' }}</h5>
                </div>
                <a href="{{ $isEdit ? route('fd.schemes.show', $scheme) : route('fd.schemes.index') }}" class="btn btn-sm btn-label-secondary">Back</a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ $isEdit ? route('fd.schemes.update', $scheme) : route('fd.schemes.store') }}" id="fdSchemeForm">
                    @csrf
                    @if($isEdit) @method('PUT') @endif

                    @if($errors->any())
                    <div class="alert alert-danger mb-3">
                        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                    @endif

                    <div class="row g-3">
                        @if($isEdit)
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Scheme Code</label>
                            <input type="text" class="form-control bg-light" value="{{ $scheme->scheme_code }}" readonly>
                        </div>
                        <div class="col-md-8">
                        @else
                        <div class="col-md-8">
                        @endif
                            <label class="form-label fw-semibold">Scheme Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ $val('name') }}" required
                                   placeholder="{{ $isCreate ? 'e.g. Regular FD 12 Months' : '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Deposit Type <span class="text-danger">*</span></label>
                            <select name="deposit_type" class="form-select" required>
                                @foreach($depositTypes as $v => $label)
                                    <option value="{{ $v }}" {{ $val('deposit_type', 'simple_interest') === $v ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Min Deposit (₹) <span class="text-danger">*</span></label>
                            <input type="number" name="min_deposit_amount" class="form-control" value="{{ $val('min_deposit_amount', $isCreate ? 1000 : '') }}" required min="1" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Max Deposit (₹) <span class="text-danger">*</span></label>
                            <input type="number" name="max_deposit_amount" class="form-control" value="{{ $val('max_deposit_amount') }}" required min="1" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Interest Rate (%) <span class="text-danger">*</span></label>
                            <input type="number" name="interest_rate" class="form-control" value="{{ $val('interest_rate') }}" required min="0" max="100" step="0.01">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Interest Frequency <span class="text-danger">*</span></label>
                            <select name="interest_frequency" class="form-select" required>
                                @foreach($frequencies as $v => $label)
                                    <option value="{{ $v }}" {{ $val('interest_frequency', 'yearly') === $v ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Min Tenure <span class="text-danger">*</span></label>
                            <input type="number" name="min_tenure" class="form-control" value="{{ $val('min_tenure', $isCreate ? 12 : '') }}" required min="1">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Max Tenure <span class="text-danger">*</span></label>
                            <input type="number" name="max_tenure" class="form-control" value="{{ $val('max_tenure', $isCreate ? 60 : '') }}" required min="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tenure Type <span class="text-danger">*</span></label>
                            <select name="tenure_type" class="form-select" required>
                                @foreach($tenureTypes as $v => $label)
                                    <option value="{{ $v }}" {{ $val('tenure_type', 'months') === $v ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="fw-semibold mb-0"><i class="ri-error-warning-line me-1 text-warning"></i>Premature Withdrawal</h6></div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="premature_withdrawal_allowed" id="prematureAllowed" value="1" {{ $checked('premature_withdrawal_allowed') ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="prematureAllowed">Allow Premature Withdrawal</label>
                            </div>
                        </div>
                        <div class="col-md-4 premature-fields">
                            <label class="form-label fw-semibold">Penalty Type</label>
                            <select name="premature_penalty_type" class="form-select">
                                <option value="">—</option>
                                <option value="percentage" {{ $val('premature_penalty_type') === 'percentage' ? 'selected' : '' }}>Percentage</option>
                                <option value="fixed" {{ $val('premature_penalty_type') === 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                            </select>
                        </div>
                        <div class="col-md-4 premature-fields">
                            <label class="form-label fw-semibold">Penalty Value</label>
                            <input type="number" name="premature_penalty_value" class="form-control" value="{{ $val('premature_penalty_value') }}" min="0" step="0.01">
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="fw-semibold mb-0"><i class="ri-refresh-line me-1 text-primary"></i>Renewal & Payout</h6></div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="auto_renewal" id="autoRenewal" value="1" {{ $checked('auto_renewal') ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="autoRenewal">Auto Renewal</label>
                            </div>
                        </div>
                        <div class="col-md-4 renewal-fields">
                            <label class="form-label fw-semibold">Renewal Type</label>
                            <select name="renewal_type" class="form-select">
                                <option value="">—</option>
                                @foreach($renewalTypes as $v => $label)
                                    <option value="{{ $v }}" {{ $val('renewal_type') === $v ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Default Payout <span class="text-danger">*</span></label>
                            <select name="default_payout_option" class="form-select" required>
                                @foreach($payoutOptions as $v => $label)
                                    <option value="{{ $v }}" {{ $val('default_payout_option', 'wallet') === $v ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="active" {{ $val('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $val('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="{{ $isCreate ? 'Optional scheme details…' : '' }}">{{ $val('description') }}</textarea>
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-save-line me-1"></i>{{ $isEdit ? 'Update Scheme' : 'Create Scheme' }}
                            </button>
                            <a href="{{ $isEdit ? route('fd.schemes.show', $scheme) : route('fd.schemes.index') }}" class="btn btn-label-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@else
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-2">
        <h4 class="mb-0">{{ $scheme->name }}</h4>
        <span class="badge bg-{{ $scheme->status_badge }}">{{ ucfirst($scheme->status) }}</span>
        <code class="ms-1">{{ $scheme->scheme_code }}</code>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('fd.schemes.edit', $scheme) }}" class="btn btn-sm btn-primary"><i class="ri-edit-2-line me-1"></i>Edit</a>
        <form action="{{ route('fd.schemes.destroy', $scheme) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this scheme?');">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line me-1"></i>Delete</button>
        </form>
        <a href="{{ route('fd.schemes.index') }}" class="btn btn-sm btn-label-secondary">Back</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Scheme Details</h5></div>
            <div class="card-body p-0">
                @php
                    $rows = [
                        ['Code', $scheme->scheme_code],
                        ['Deposit Type', $scheme->deposit_type_label],
                        ['Interest Rate', number_format((float) $scheme->interest_rate, 2) . '%'],
                        ['Interest Frequency', $scheme->interest_frequency_label],
                        ['Min Deposit', '₹' . number_format((float) $scheme->min_deposit_amount, 2)],
                        ['Max Deposit', '₹' . number_format((float) $scheme->max_deposit_amount, 2)],
                        ['Tenure Range', $scheme->min_tenure . '–' . $scheme->max_tenure . ' ' . ucfirst($scheme->tenure_type)],
                        ['Premature Allowed', $scheme->premature_withdrawal_allowed ? 'Yes' : 'No'],
                        ['Penalty', $scheme->premature_withdrawal_allowed ? ucfirst((string) $scheme->premature_penalty_type) . ' — ' . number_format((float) $scheme->premature_penalty_value, 2) : 'N/A'],
                        ['Auto Renewal', $scheme->auto_renewal ? 'Yes' : 'No'],
                        ['Renewal Type', $scheme->renewal_type ? (\App\Models\FixedDepositScheme::renewalTypes()[$scheme->renewal_type] ?? $scheme->renewal_type) : 'N/A'],
                        ['Default Payout', \App\Models\FixedDepositScheme::payoutOptions()[$scheme->default_payout_option] ?? $scheme->default_payout_option],
                        ['Status', ucfirst($scheme->status)],
                        ['Linked Deposits', $scheme->deposits_count ?? 0],
                        ['Created', optional($scheme->created_at)->format('d M Y H:i')],
                    ];
                @endphp
                @foreach($rows as [$k, $v])
                <div class="d-flex justify-content-between px-4 py-2 border-bottom">
                    <span class="text-muted" style="font-size:.82rem;">{{ $k }}</span>
                    <span style="font-size:.85rem;font-weight:500;">{{ $v }}</span>
                </div>
                @endforeach
                @if($scheme->description)
                <div class="px-4 py-3">
                    <small class="text-muted d-block mb-1">Description</small>
                    <p class="mb-0">{{ $scheme->description }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card card-border-shadow-primary">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="avatar me-3">
                        <span class="avatar-initial rounded-3 bg-label-primary"><i class="ri-safe-2-line ri-24px"></i></span>
                    </div>
                    <div>
                        <h5 class="mb-0">Quick Actions</h5>
                        <small class="text-muted">Create deposits under this scheme</small>
                    </div>
                </div>
                <a href="{{ route('fd.deposits.create', ['scheme_id' => $scheme->id]) }}" class="btn btn-primary w-100 mb-2"><i class="ri-add-line me-1"></i>New Deposit</a>
                <a href="{{ route('fd.deposits.index', ['scheme_id' => $scheme->id]) }}" class="btn btn-outline-secondary w-100"><i class="ri-list-check me-1"></i>View Deposits</a>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@if($isForm)
@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const premature = document.getElementById('prematureAllowed');
    const autoRenewal = document.getElementById('autoRenewal');
    function toggleFields() {
        document.querySelectorAll('.premature-fields').forEach(el => {
            el.style.opacity = premature.checked ? '1' : '0.5';
            el.querySelectorAll('input, select').forEach(i => i.disabled = !premature.checked);
        });
        document.querySelectorAll('.renewal-fields').forEach(el => {
            el.style.opacity = autoRenewal.checked ? '1' : '0.5';
            el.querySelectorAll('input, select').forEach(i => i.disabled = !autoRenewal.checked);
        });
    }
    premature.addEventListener('change', toggleFields);
    autoRenewal.addEventListener('change', toggleFields);
    toggleFields();
});
</script>
@endsection
@endif
