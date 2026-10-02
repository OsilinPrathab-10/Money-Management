@extends('layouts/layoutMaster')
@section('title', isset($mode) && $mode === 'show' ? 'Group — ' . $group->group_code : (isset($mode) && $mode === 'create' ? 'Create Chit Group' : (isset($mode) && $mode === 'edit' ? 'Edit Chit Group' : 'Chit Groups')))

@section('content')
@if(isset($mode) && $mode === 'create')
{{-- CREATE GROUP FORM --}}
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="icon-base ri ri-pages-line text-primary ri-xl"></i>
                <div>
                    <h5 class="mb-0">New Chit Group</h5>
                    @if(!empty($lockedScheme))
                        <small class="text-muted">Scheme: <strong>{{ $lockedScheme->name }}</strong></small>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('chit.groups.store') }}">
                    @csrf

                    @if($errors->any())
                    <div class="alert alert-danger mb-3">
                        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                    @endif

                    <div class="row g-3">
                        {{-- Scheme --}}
                        @php
                            $selectedSchemeId = old('scheme_id', $preselectedSchemeId ?? request('scheme_id'));
                            $isSchemeLocked = !empty($lockedScheme) && (string) $lockedScheme->id === (string) $selectedSchemeId;
                        @endphp
                        <div class="col-12">
                            <label class="form-label fw-semibold">Chit Scheme <span class="text-danger">*</span></label>
                            @if($isSchemeLocked)
                                <input type="hidden" name="scheme_id" id="schemeSelectHidden" value="{{ $lockedScheme->id }}">
                                <select id="schemeSelect" class="form-select" disabled>
                                    <option value="{{ $lockedScheme->id }}" selected
                                        data-value="{{ $lockedScheme->chit_value }}"
                                        data-members="{{ $lockedScheme->total_members }}"
                                        data-installment="{{ $lockedScheme->installment_amount }}"
                                        data-commission="{{ $lockedScheme->commission_pct }}"
                                        data-type="{{ $lockedScheme->scheme_type }}"
                                        data-type-label="{{ $lockedScheme->scheme_type_label }}"
                                        data-type-color="{{ $lockedScheme->scheme_type_badge_color }}"
                                        data-frequency="{{ $lockedScheme->installment_frequency ?? 'monthly' }}"
                                        data-private="{{ $lockedScheme->is_private ? '1' : '0' }}">
                                        {{ $lockedScheme->name }} — ₹{{ number_format($lockedScheme->chit_value) }} / {{ $lockedScheme->total_members }} members ({{ $lockedScheme->scheme_type_label }})
                                    </option>
                                </select>
                                <div class="form-text text-muted small">Scheme is fixed from the scheme page and cannot be changed here.</div>
                            @else
                                <select name="scheme_id" class="form-select select2" required id="schemeSelect">
                                    <option value="">Choose a scheme…</option>
                                    @foreach($schemes as $scheme)
                                        <option value="{{ $scheme->id }}"
                                            data-value="{{ $scheme->chit_value }}"
                                            data-members="{{ $scheme->total_members }}"
                                            data-installment="{{ $scheme->installment_amount }}"
                                            data-commission="{{ $scheme->commission_pct }}"
                                            data-type="{{ $scheme->scheme_type }}"
                                            data-type-label="{{ $scheme->scheme_type_label }}"
                                            data-type-color="{{ $scheme->scheme_type_badge_color }}"
                                            data-frequency="{{ $scheme->installment_frequency ?? 'monthly' }}"
                                            data-private="{{ $scheme->is_private ? '1' : '0' }}"
                                            {{ (string) $selectedSchemeId === (string) $scheme->id ? 'selected' : '' }}>
                                            {{ $scheme->name }} — ₹{{ number_format($scheme->chit_value) }} / {{ $scheme->total_members }} members ({{ $scheme->scheme_type_label }})
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        {{-- Scheme Preview Card --}}
                        <div id="schemePreview" class="col-12" style="display:none;">
                            <div class="border rounded p-3 bg-light">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span id="prev-type-badge" class="badge"></span>
                                    <span id="prev-private-badge" class="badge bg-dark" style="display:none;">
                                        <i class="icon-base ri ri-lock-line" style="font-size:.75rem;"></i> Private
                                    </span>
                                </div>
                                <div class="row g-2">
                                    <div class="col-4">
                                        <div class="border rounded p-2 text-center bg-white">
                                            <div class="text-muted" style="font-size:.72rem;">Chit Value</div>
                                            <div class="fw-bold text-primary" id="prev-value">—</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border rounded p-2 text-center bg-white">
                                            <div class="text-muted" style="font-size:.72rem;">Installment</div>
                                            <div class="fw-bold text-primary" id="prev-install">—</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border rounded p-2 text-center bg-white">
                                            <div class="text-muted" style="font-size:.72rem;">Members / Frequency</div>
                                            <div class="fw-bold" id="prev-members">—</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Start Date --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control"
                                   value="{{ old('start_date', today()->format('Y-m-d')) }}" required>
                            <small class="text-muted">Past dates are allowed for backdated or already-started groups.</small>
                        </div>

                        {{-- Auction Type (hidden default) --}}
                        <input type="hidden" name="auction_type" value="open">

                        {{-- Group Leader (only for group_based) --}}
                        <div class="col-md-6" id="groupLeaderRow" style="display:none;">
                            <label class="form-label fw-semibold">
                                <i class="icon-base ri ri-star-line text-warning me-1"></i>Group Leader
                            </label>
                            <select name="group_leader_id" class="form-select select2" id="groupLeaderSelect">
                                <option value="">— Select Leader —</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}" {{ old('group_leader_id') == $c->id ? 'selected' : '' }}>
                                        {{ $c->client_name }} ({{ $c->client_phone }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text text-muted">Leader can manage members for this private group.</div>
                        </div>

                        {{-- Branch --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Branch</label>
                            <select name="branch_id" class="form-select select2">
                                <option value="">All Branches</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" {{ old('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Referral Commission --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Referral Commission (%)</label>
                            <input type="number" name="referral_commission_pct" class="form-control"
                                   value="{{ old('referral_commission_pct') }}" step="0.1" min="0" max="100" placeholder="Defaults to Scheme setting / global default">
                        </div>

                        {{-- Group Registration --}}
                        @include('admin.chit.groups.partials.registration-fields')

                        {{-- Remarks --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2">{{ old('remarks') }}</textarea>
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="icon-base ri ri-save-line me-1"></i>Create Group
                            </button>
                            <a href="{{ route('chit.groups.index') }}" class="btn btn-label-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@elseif(isset($mode) && $mode === 'edit')
{{-- EDIT GROUP FORM --}}
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="icon-base ri ri-edit-2-line text-primary ri-xl"></i>
                    <h5 class="mb-0">Edit Group: {{ $group->group_code }}</h5>
                    <span class="badge bg-{{ $group->scheme_type_badge_color }}">{{ $group->scheme_type_label }}</span>
                    @if($group->is_private)
                        <span class="badge bg-dark"><i class="icon-base ri ri-lock-line" style="font-size:.75rem;"></i> Private</span>
                    @endif
                </div>
                <a href="{{ route('chit.groups.show', $group) }}" class="btn btn-sm btn-label-secondary">Back</a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('chit.groups.update', $group) }}">
                    @csrf
                    @method('PUT')

                    @if($errors->any())
                    <div class="alert alert-danger mb-3">
                        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                    @endif

                    <div class="row g-3">
                        {{-- Status --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                @foreach(['forming','active','completed','terminated'] as $s)
                                    <option value="{{ $s }}" {{ old('status', $group->status) === $s ? 'selected' : '' }}>
                                        {{ ucfirst($s) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Referral Commission (%)</label>
                            <input type="number" name="referral_commission_pct" class="form-control"
                                   value="{{ old('referral_commission_pct', $group->referral_commission_pct) }}" step="0.1" min="0" max="100" placeholder="Defaults to Scheme setting / global default">
                        </div>

                        {{-- Group Leader (for group_based) --}}
                        @if($group->scheme_type === 'group_based')
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="icon-base ri ri-star-line text-warning me-1"></i>Group Leader
                            </label>
                            <select name="group_leader_id" class="form-select select2">
                                <option value="">— No Leader —</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}"
                                        {{ old('group_leader_id', $group->group_leader_id) == $c->id ? 'selected' : '' }}>
                                        {{ $c->client_name }} ({{ $c->client_phone }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text text-muted">Only for Group-Based (Private) chit type.</div>
                        </div>
                        @else
                        <input type="hidden" name="group_leader_id" value="{{ $group->group_leader_id }}">
                        @endif

                        {{-- Group Registration --}}
                        @include('admin.chit.groups.partials.registration-fields', ['group' => $group])

                        {{-- Remarks --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="3">{{ old('remarks', $group->remarks) }}</textarea>
                        </div>

                        {{-- Info: read-only inherited fields --}}
                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-0" style="font-size:.83rem;">
                                <i class="icon-base ri ri-information-line me-1"></i>
                                Scheme type, chit value, installment amount, and frequency are inherited from the scheme
                                and cannot be changed after group creation.
                            </div>
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="icon-base ri ri-save-line me-1"></i>Update Group
                            </button>
                            <a href="{{ route('chit.groups.show', $group) }}" class="btn btn-label-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@elseif(isset($mode) && $mode === 'show')
{{-- SHOW GROUP DETAILS — AJAX-reloadable root (modals stay outside) --}}
<div id="group-show-ajax-root"
     data-reload-url="{{ route('chit.groups.show', $group) }}{{ request()->filled('progress_month') ? '?progress_month='.(int) request('progress_month') : '' }}">
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-6">
    <div>
        <h4 class="mb-1">{{ $group->group_code }}</h4>
        <p class="text-heading mb-0">
            {{ $group->scheme->name ?? 'Chit Group' }}
            · Month {{ $operatingMonth ?? $group->current_month }}/{{ $group->total_months }}
            @if(!empty($operatingMonth))
                <span class="text-primary fw-semibold">({{ $group->periodCalendarLabel((int) $operatingMonth) }})</span>
            @endif
            · {{ ucfirst($group->installment_frequency ?? 'Monthly') }}
        </p>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
            <span class="badge bg-label-{{ $group->status_badge }}">{{ ucfirst($group->status) }}</span>
            <span class="badge bg-label-{{ $group->scheme_type_badge_color }}">{{ $group->scheme_type_label }}</span>
            @if($group->is_private)
                <span class="badge bg-label-dark"><i class="icon-base ri ri-lock-line me-1"></i>Private</span>
            @endif
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if($group->status === 'active')
            <form id="close-group-form" method="POST" action="{{ route('chit.groups.close', $group) }}" style="display:none;">
                @csrf
            </form>
            <button type="button"
                class="btn btn-sm btn-primary d-inline-flex align-items-center"
                id="btn-close-group"
                data-fully-settled="{{ $group->isFullySettled() ? '1' : '0' }}"
                data-progress="{{ $group->closeProgressLabel() }}">
                <i class="icon-base ri ri-checkbox-circle-line me-1"></i>Close Group
            </button>
        @elseif($group->status === 'completed' || $group->status === 'closed')
            <span class="badge bg-label-primary align-self-center px-3 py-2 me-1">
                <i class="icon-base ri ri-lock-line me-1"></i>Closed
            </span>
            <form action="{{ route('chit.groups.reopen', $group) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to reopen this group?');">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-success d-inline-flex align-items-center">
                    <i class="icon-base ri ri-arrow-go-back-line me-1"></i>Reopen
                </button>
            </form>
        @elseif($group->status === 'terminated')
            <span class="badge bg-label-danger align-self-center px-3 py-2 me-1">
                <i class="icon-base ri ri-close-circle-line me-1"></i>Terminated (Accounts Frozen)
            </span>
            <form action="{{ route('chit.groups.cancel-termination', $group) }}" method="POST" class="d-inline" id="cancel-termination-form">
                @csrf
            </form>
            <button type="button" id="btn-cancel-termination" class="btn btn-sm btn-outline-success d-inline-flex align-items-center">
                <i class="icon-base ri ri-refresh-line me-1"></i>Cancel Termination / Un-freeze
            </button>
        @endif
        <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#editGroupModal">
            <i class="icon-base ri ri-pencil-line me-1"></i>Edit
        </button>
        <form action="{{ route('chit.groups.destroy', $group) }}" method="POST" class="d-inline delete-group-form">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger btn-delete-group d-inline-flex align-items-center">
                <i class="icon-base ri ri-delete-bin-line me-1"></i>Delete
            </button>
        </form>
        <a href="{{ route('chit.groups.index') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center">
            <i class="icon-base ri ri-arrow-left-line me-1"></i>Back
        </a>
    </div>
</div>

<div class="alert alert-{{ $group->scheme_type_badge_color === 'warning' ? 'warning' : ($group->scheme_type_badge_color === 'danger' ? 'danger' : 'info') }} mb-4 py-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="text-dark flex-grow-1">
            @switch($group->scheme_type)
                @case('fixed')       Fixed installments by rotation — no auction required. @break
                @case('auction')     Lowest bid wins each month. Dividend distributed to all members. @break
                @case('flexible')    Variable installment &amp; dividend set per period. @break
                @case('fixed_return') Each member receives ₹{{ number_format($group->fixed_return_amount ?? 0) }} guaranteed on their turn. @break
                @case('daily_weekly') Installments collected <strong>{{ ucfirst($group->installment_frequency ?? 'weekly') }}</strong>. @break
                @case('group_based') Invite-only private group.
                    @if($group->groupLeader)
                        Leader: <strong>{{ $group->groupLeader->client_name }}</strong>
                    @endif
                    @break
            @endswitch
        </span>
        <span class="text-muted small">Scheme: <strong>{{ $group->scheme->name ?? '—' }}</strong></span>
    </div>
</div>

@php
    $foremanMonthNumber = $group->scheme?->foremanCommissionMonth() ?? 1;
@endphp
<div class="row g-6 mb-6">
    <div class="col-sm-6 col-xl-2">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left min-w-0">
                        <span class="text-heading">Chit Value &amp; Members</span>
                        <div class="d-flex align-items-center my-1">
                            <h4 class="mb-0 me-2">₹{{ number_format($group->chit_value) }}</h4>
                        </div>
                        <small class="mb-0 text-muted fw-semibold d-block text-truncate">
                            <i class="icon-base ri ri-group-line me-1 text-primary"></i>{{ $group->seatsFillLabel() }} Seats
                            @if($group->hasMultiSeatMembers() || $group->valid_members_count != (int) round($group->occupiedSeats()))
                                <span class="text-muted small">({{ $group->valid_members_count }} {{ \Illuminate\Support\Str::plural('member', $group->valid_members_count) }})</span>
                            @endif
                        </small>
                    </div>
                    <div class="avatar flex-shrink-0">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="icon-base ri ri-money-rupee-circle-line icon-26px"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-heading">Foreman Commission</span>
                        <div class="d-flex align-items-center my-1">
                            <h4 class="mb-0 me-2 text-warning fw-bold">₹{{ number_format($foremanCommissionSummary['amount'], 2) }}</h4>
                        </div>
                        <small class="mb-0 text-muted">
                            @if(!empty($foremanCommissionSummary['client_wise']))
                                Collected after client-wise FC extra is paid
                                @if(!empty($foremanCommissionSummary['month']))
                                    (month {{ $foremanCommissionSummary['month'] }})
                                @endif
                                · expected ₹{{ number_format($foremanCommissionSummary['expected_amount'] ?? 0, 2) }}
                                (₹{{ number_format($foremanCommissionSummary['per_member'], 2) }} × {{ $foremanCommissionSummary['member_count'] }} members)
                                @if(($foremanCommissionSummary['pending_amount'] ?? 0) > 0.009)
                                    · pending ₹{{ number_format($foremanCommissionSummary['pending_amount'], 2) }}
                                @endif
                            @elseif(!empty($foremanCommissionSummary['uses_share_scaling']))
                                Share-wise total · Month {{ $foremanCommissionSummary['month'] }} · {{ $foremanCommissionSummary['member_count'] }} members
                            @else
                                ₹{{ number_format($foremanCommissionSummary['installment'], 2) }} × {{ $foremanCommissionSummary['member_count'] }} members
                            @endif
                        </small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-warning">
                            <i class="icon-base ri ri-percent-line icon-26px"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card h-100 border-success border-opacity-25 shadow-xs">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-heading text-success fw-semibold" style="font-size: 0.85rem;">Collected Amount</span>
                        <div class="d-flex align-items-center my-1">
                            <h4 class="mb-0 me-2 text-success fw-bold">₹{{ number_format($groupCollections, 0) }}</h4>
                        </div>
                        <small class="text-muted d-block" style="font-size: 0.72rem;">
                            Client installment collections
                        </small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="icon-base ri ri-exchange-funds-line icon-26px"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card h-100 border-info border-opacity-25 shadow-xs">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-heading text-info fw-semibold" style="font-size: 0.85rem;">Settled Amount</span>
                        <div class="d-flex align-items-center my-1">
                            <h4 class="mb-0 me-2 text-info fw-bold">₹{{ number_format($groupSettlements, 0) }}</h4>
                        </div>
                        <small class="text-muted d-block" style="font-size: 0.72rem;">
                            Paid payout settlements
                        </small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-info">
                            <i class="icon-base ri ri-hand-coin-line icon-26px"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card h-100 border-success border-opacity-25 shadow-xs">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-heading text-success fw-semibold" style="font-size: 0.85rem;">Dividend Pool</span>
                        <div class="d-flex align-items-center my-1">
                            <h4 class="mb-0 me-2 text-success fw-bold">₹{{ number_format($dividendPool ?? $group->dividend_pool_balance ?? 0, 0) }}</h4>
                        </div>
                        <small class="text-muted d-block" style="font-size: 0.72rem;">
                            Surplus for dividend distribution
                            <a href="{{ route('chit.dividends.pool', $group) }}" class="text-success text-decoration-none ms-1">Ledger</a>
                        </small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="icon-base ri ri-funds-box-line icon-26px"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card h-100 border-primary border-opacity-25 shadow-xs">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-heading text-primary fw-semibold" style="font-size: 0.85rem;">Group Balance</span>
                        <div class="d-flex align-items-center my-1">
                            <h4 class="mb-0 me-2 {{ ($groupBalance ?? 0) < 0 ? 'text-danger' : 'text-primary' }} fw-bold">₹{{ number_format($groupBalance ?? 0, 0) }}</h4>
                        </div>
                        <small class="text-muted d-block" style="font-size: 0.72rem;">
                            Collected − Settled − Foreman Comm.
                        </small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="icon-base ri ri-scales-3-line icon-26px"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card h-100 border-primary border-opacity-25 shadow-xs">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-heading text-primary fw-semibold" style="font-size: 0.85rem;">Available Group Funds</span>
                        <div class="d-flex align-items-center my-1">
                            <h4 class="mb-0 me-2 {{ ($availableGroupFunds ?? 0) < 0 ? 'text-danger' : 'text-primary' }} fw-bold">₹{{ number_format($availableGroupFunds ?? 0, 0) }}</h4>
                        </div>
                        <small class="text-muted d-block" style="font-size: 0.72rem;">
                            Group Balance + Dividend Pool
                        </small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="icon-base ri ri-wallet-3-line icon-26px"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card h-100 border-danger border-opacity-25 shadow-xs">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span class="text-heading text-danger fw-semibold" style="font-size: 0.85rem;">Uncollected Amount</span>
                        <div class="d-flex align-items-center my-1">
                            <h4 class="mb-0 me-2 text-danger fw-bold">₹{{ number_format($uncollectedAmount ?? 0, 0) }}</h4>
                        </div>
                        <small class="text-muted d-block" style="font-size: 0.72rem;">
                            Overdue ₹{{ number_format($overdueUncollectedAmount ?? 0, 0) }}
                            · Current ₹{{ number_format($currentMonthUncollectedAmount ?? 0, 0) }}
                            @if(!empty($calendarMonth))
                                <span class="d-block mt-1">{{ $group->periodCalendarLabel((int) $calendarMonth) }}</span>
                            @endif
                        </small>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-danger">
                            <i class="icon-base ri ri-error-warning-line icon-26px"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Collection Progress Card (month filter) --}}
@if(isset($currentMonthSummary))
<div class="card mb-4 shadow-xs">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 pb-3 border-bottom">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar avatar-md bg-label-primary rounded d-flex align-items-center justify-content-center fw-bold fs-4">
                    <i class="icon-base ri ri-pie-chart-2-line text-primary"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                        <h6 class="mb-0 text-dark fw-bold">{{ $currentMonthSummary['month_title'] }} Collection Progress</h6>
                        <span class="badge bg-primary">{{ $currentMonthSummary['progress_pct'] }}% Collected</span>
                        @if(!empty($currentMonthSummary['is_overdue_period']))
                            <span class="badge bg-danger">Overdue</span>
                        @elseif(!empty($currentMonthSummary['is_operating']))
                            <span class="badge bg-label-success">Current</span>
                        @endif
                    </div>
                    <small class="text-muted">
                        @if(!empty($currentMonthSummary['is_operating']))
                            Current collection month
                        @else
                            Viewing selected month
                        @endif
                        &nbsp;·&nbsp;
                        <strong class="text-dark">{{ $currentMonthSummary['paid_count'] }} of {{ $currentMonthSummary['total_count'] }}</strong> members paid
                        @if(($currentMonthSummary['overdue_count'] ?? 0) > 0)
                            &nbsp;·&nbsp;<span class="text-danger fw-semibold">{{ $currentMonthSummary['overdue_count'] }} overdue</span>
                        @endif
                    </small>
                </div>
            </div>
            <form method="GET" action="{{ route('chit.groups.show', $group) }}" class="d-flex align-items-center gap-2 group-progress-month-form">
                <label class="form-label mb-0 text-nowrap fw-semibold small"><i class="ri-filter-3-line me-1"></i>Month filter</label>
                <select name="progress_month" class="form-select form-select-sm group-progress-month-select" style="min-width: 220px;" onchange="this.form.submit()">
                    @foreach(($periodOptions ?? []) as $opt)
                        <option value="{{ $opt['month'] }}" @selected((int) $opt['month'] === (int) $currentMonthSummary['month'])>
                            {{ $opt['label'] }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="row g-3 my-1">
            <div class="col-12 col-sm-4">
                <div class="p-2 px-3 rounded bg-label-primary border border-primary-subtle d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-muted d-block fw-medium">Collected ({{ $currentMonthSummary['month_label'] }})</small>
                        <span class="fw-bold text-primary fs-6">₹{{ number_format($currentMonthSummary['collected'], 2) }}</span>
                    </div>
                    <i class="ri-checkbox-circle-line fs-3 text-primary opacity-75"></i>
                </div>
            </div>
            <div class="col-12 col-sm-4">
                <div class="p-2 px-3 rounded bg-label-danger border border-danger-subtle d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-muted d-block fw-medium">Pending ({{ $currentMonthSummary['month_label'] }})</small>
                        <span class="fw-bold text-danger fs-6">₹{{ number_format($currentMonthSummary['pending'], 2) }}</span>
                    </div>
                    <i class="ri-time-line fs-3 text-danger opacity-75"></i>
                </div>
            </div>
            <div class="col-12 col-sm-4">
                <div class="p-2 px-3 rounded bg-label-secondary border border-secondary-subtle d-flex align-items-center justify-content-between" title="Sum of installments for {{ $currentMonthSummary['month_title'] }} (including penalty)">
                    <div>
                        <small class="text-muted d-block fw-medium">Total Monthly Target</small>
                        <span class="fw-bold text-dark fs-6">₹{{ number_format($currentMonthSummary['expected'], 2) }}</span>
                    </div>
                    <i class="ri-flag-2-line fs-3 text-secondary opacity-75"></i>
                </div>
            </div>
        </div>

        <div class="progress mt-3" style="height: 8px;">
            <div class="progress-bar {{ !empty($currentMonthSummary['is_overdue_period']) ? 'bg-danger' : 'bg-primary' }} progress-bar-striped progress-bar-animated"
                 role="progressbar"
                 style="width: {{ $currentMonthSummary['progress_pct'] }}%"
                 aria-valuenow="{{ $currentMonthSummary['progress_pct'] }}"
                 aria-valuemin="0"
                 aria-valuemax="100">
            </div>
        </div>
    </div>
</div>
@endif

@if($group->usesRotationPayout() || $group->supportsAuction())
@php
    $settlementService = app(\App\Services\ChitPayoutService::class);
@endphp
<div class="card mb-4 border-primary shadow-xs">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 pb-3 border-bottom">
            <div>
                <h6 class="mb-1"><i class="icon-base ri ri-shield-check-line text-primary me-1"></i>Monthly Settlement Status</h6>
                @php
                    $nextSettlementMonthName = $group->periodCalendarLabel((int) $nextSettlementMonth);
                @endphp
                <p class="text-muted mb-0" style="font-size:.85rem;">
                    Next settlement month: <strong>Month {{ $nextSettlementMonth }} — {{ $nextSettlementMonthName }}</strong>
                    @if($currentMonthSettlement)
                        — <span class="badge bg-{{ $currentMonthSettlement->status_badge }}">{{ ucfirst($currentMonthSettlement->status) }}</span>
                        for {{ $currentMonthSettlement->winner?->client->client_name }}
                    @elseif($canInitiateSettlement)
                        — <span class="badge bg-label-info">Ready for settlement</span>
                    @elseif($group->current_month >= $group->total_months)
                        — <span class="badge bg-label-primary">All settlements completed</span>
                    @else
                        — <span class="badge bg-label-warning">Awaiting action</span>
                    @endif
                    @if(($allowsAdvancePayouts ?? false) && ($advancePeriod ?? 0) > 0 && (($paidOriginalForAdvance?->status ?? null) === 'paid'))
                        <br>
                        <span class="text-info">
                            Same-month Advance Amount open for Month {{ $advancePeriod }}
                            @if(($advanceEligibleMembers ?? collect())->isNotEmpty())
                                — {{ $advanceEligibleMembers->count() }} eligible member(s)
                            @endif
                            @if(($advanceSettlements ?? collect())->isNotEmpty())
                                ({{ $advanceSettlements->count() }} advance already recorded)
                            @endif
                        </span>
                    @endif
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @if($currentMonthSettlement)
                    <a href="{{ route('chit.settlements.confirm', [$group, $currentMonthSettlement->winner]) }}" class="btn btn-sm btn-primary">
                        <i class="icon-base ri ri-eye-line me-1"></i>View Settlement
                    </a>
                @endif
                
                <a href="{{ route('chit.settlements.history', ['group_id' => $group->id]) }}" class="btn btn-sm btn-outline-primary">
                    <i class="icon-base ri ri-history-line me-1"></i>Settlement History
                </a>
                     <a href="{{ route('chit.installments.show', [$group->id, max(1, $group->current_month)]) }}" class="btn btn-sm btn-outline-primary">
                <i class="icon-base ri ri-bank-card-line me-1"></i>View Installments
            </a>

                <a href="{{ route('chit.settlement-applications.index', ['group_id' => $group->id]) }}" class="btn btn-sm btn-outline-primary">
                      <i class="icon-base ri ri-file-list-3-line me-1"></i>Settlement Applications
                </a>

            </div>
        </div>

        <!-- {{-- Horizontal Quick Actions Bar --}}
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="text-muted fw-semibold me-2"><i class="icon-base ri ri-flashlight-line me-1 text-primary"></i>Quick Actions:</span>
           
            @if($group->supportsAuction())
                <a href="{{ route('chit.auctions.create') }}" class="btn btn-sm btn-outline-warning">
                    <i class="icon-base ri ri-auction-line me-1"></i>Schedule Auction
                </a>
            @else
                <button class="btn btn-sm btn-outline-secondary" disabled title="Auctions not applicable for {{ $group->scheme_type_label }}">
                    <i class="icon-base ri ri-auction-line me-1"></i>Auction N/A
                </button>
            @endif

            <a href="{{ route('chit.installments.show', [$group->id, max(1, $group->current_month)]) }}" class="btn btn-sm btn-outline-info">
                <i class="icon-base ri ri-bank-card-line me-1"></i>Collect / View Installments
            </a>

            <a href="{{ route('chit.settlement-applications.index', ['group_id' => $group->id]) }}" class="btn btn-sm btn-outline-info">
                <i class="icon-base ri ri-file-list-3-line me-1"></i>Settlement Applications
            </a>

            @if($group->usesRotationPayout())
                <a href="{{ route('chit.settlement-applications.index', ['group_id' => $group->id]) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="icon-base ri ri-money-rupee-circle-line me-1"></i>Settlement Overview
                </a>
            @endif

            @if($group->status === 'active' && $group->canBeClosed())
                <button type="button" class="btn btn-sm btn-danger shadow-xs" onclick="document.getElementById('btn-close-group')?.click()">
                    <i class="icon-base ri ri-checkbox-circle-line me-1"></i>Close / Complete Group
                </button>
            @elseif($group->status === 'completed')
                <button type="button" class="btn btn-sm btn-label-info" disabled>
                    <i class="icon-base ri ri-checkbox-circle-line me-1"></i>Group Closed
                </button>
            @elseif($group->status === 'terminated')
                <button type="button" class="btn btn-sm btn-label-danger" disabled>
                    <i class="icon-base ri ri-close-circle-line me-1"></i>Group Terminated
                </button>
            @endif
        </div> -->
    </div>
</div>
@endif

{{-- Full-width Banner 2: Group Details Card (Rectangle Shape UI) --}}
<div class="card mb-4 shadow-xs">
    <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="icon-base ri ri-information-line me-1 text-primary"></i>Group Details</h5>
        @if($group->status === 'forming')
            <form id="activate-form" method="POST" action="{{ route('chit.groups.activate', $group) }}" style="display:none;">@csrf</form>
            <button type="button" class="btn btn-sm btn-primary" id="btn-activate-group">
                <i class="icon-base ri ri-flashlight-line me-1"></i>Activate Group
            </button>
        @elseif($group->status === 'completed')
            <span class="badge bg-label-primary">Closed</span>
        @endif
    </div>
    <div class="card-body py-3">
        <div class="row g-3">
            <div class="col-md-3 col-6">
                <small class="text-muted d-block">Group Code</small>
                <span class="fw-bold text-dark fs-6">{{ $group->group_code }}</span>
            </div>
            <div class="col-md-3 col-6">
                <small class="text-muted d-block">Scheme Name</small>
                <span class="fw-semibold text-dark">{{ $group->scheme->name ?? '—' }}</span>
            </div>
            <div class="col-md-3 col-6">
                <small class="text-muted d-block">Group Type</small>
                <span class="badge bg-label-info">{{ $group->scheme_type_label }}</span>
            </div>
            <div class="col-md-3 col-6">
                <small class="text-muted d-block">Installment</small>
                <span class="fw-semibold text-dark">₹{{ number_format($group->installment_amount) }} ({{ ucfirst($group->installment_frequency ?? 'Monthly') }})</span>
            </div>
            <div class="col-md-3 col-6">
                <small class="text-muted d-block">Foreman Commission</small>
                <span class="badge bg-label-warning text-dark fw-bold fs-6">
                    <i class="icon-base ri ri-percent-line me-1"></i>
                    ₹{{ number_format($foremanCommissionSummary['amount'], 2) }}
                </span>
                <small class="text-muted d-block mt-1">
                    @if(!empty($foremanCommissionSummary['client_wise']))
                        Updates after client-wise FC extra is collected
                        @if(!empty($foremanCommissionSummary['month']))
                            (month {{ $foremanCommissionSummary['month'] }})
                        @endif
                        · expected ₹{{ number_format($foremanCommissionSummary['expected_amount'] ?? 0, 2) }}
                        (₹{{ number_format($foremanCommissionSummary['per_member'], 2) }}
                        × {{ $foremanCommissionSummary['member_count'] }} members)
                    @elseif(!empty($foremanCommissionSummary['uses_share_scaling']))
                        Sum of each member’s share installment (Month {{ $foremanCommissionSummary['month'] }})
                    @else
                        ₹{{ number_format($foremanCommissionSummary['installment'], 2) }}
                        × {{ $foremanCommissionSummary['member_count'] }} active members
                    @endif
                </small>
            </div>
            <div class="col-md-3 col-6">
                <small class="text-muted d-block">Referral Commission</small>
                <span class="fw-semibold text-dark">{{ $group->referral_commission_pct ? $group->referral_commission_pct.'%' : 'Global / Scheme Default' }}</span>
            </div>
            <div class="col-md-3 col-6">
                <small class="text-muted d-block">Start Date</small>
                <span class="fw-semibold text-dark">{{ Carbon\Carbon::parse($group->start_date)->format('d M Y') }}</span>
                <small class="text-primary d-block fw-medium">{{ $group->start_month_label }}</small>
            </div>
            <div class="col-md-3 col-6">
                <small class="text-muted d-block">End Date</small>
                <span class="fw-semibold text-dark">{{ $group->end_date ? Carbon\Carbon::parse($group->end_date)->format('d M Y') : '—' }}</span>
                <small class="text-primary d-block fw-medium">{{ $group->end_month_label }}</small>
            </div>
            @if($group->scheme_type === 'fixed_return')
            <div class="col-md-6 col-12">
                <small class="text-muted d-block">Fixed Return Amount</small>
                <span class="fw-bold text-primary">₹{{ number_format($group->fixed_return_amount ?? ($group->chit_value - ($group->chit_value * $group->commission_pct / 100))) }} per turn</span>
            </div>
            @endif
            @if($group->groupLeader)
            <div class="col-md-6 col-12">
                <small class="text-muted d-block">Group Leader</small>
                <span class="fw-semibold text-dark"><i class="icon-base ri ri-star-line text-warning me-1"></i>{{ $group->groupLeader->client_name }} ({{ $group->groupLeader->client_phone }})</span>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Full-width Members (6) & Payout Rotation Schedule Table --}}
<div class="row g-3">
    <div class="col-12">
        <div class="card mb-4 shadow-xs">
            <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">
                    <i class="icon-base ri ri-group-line me-2 text-primary"></i>
                    Members ({{ $group->valid_members_count }}) — {{ $group->seatsFillLabel() }} seats — Payout Rotation Schedule
                    @php
                        $pendingApprovalMembers = ($memberDisplayRows ?? collect())
                            ->pluck('member')
                            ->unique('id')
                            ->filter(fn ($m) => ($m->status ?? '') === 'applied')
                            ->values();
                        $pendingApprovalCount = $pendingApprovalMembers->count();
                    @endphp
                    @if($pendingApprovalCount > 0)
                        <span class="badge bg-warning text-dark ms-2" style="font-size:.7rem;">
                            <i class="icon-base ri ri-time-line me-1"></i>{{ $pendingApprovalCount }} Pending Approval
                        </span>
                    @endif
                    <small class="text-muted fw-normal d-block mt-1" style="font-size:.75rem;">Ordered by applied settlement month (descending)</small>
                    @if($group->is_private)
                        <span class="badge bg-label-dark ms-2" style="font-size:.65rem;"><i class="icon-base ri ri-lock-line"></i> Private Group</span>
                    @endif
                </h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="d-flex align-items-center gap-1">
                        <label for="rotationMemberSearch" class="form-label mb-0 small text-muted text-nowrap fw-semibold">Search:</label>
                        <input type="text" id="rotationMemberSearch" class="form-control form-control-sm" style="width: 160px;"
                               placeholder="Name / phone / #…">
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <label for="rotationMonthFilter" class="form-label mb-0 small text-muted text-nowrap fw-semibold">Filter Month:</label>
                        <select id="rotationMonthFilter" class="form-select form-select-sm no-search" style="width: auto;">
                            <option value="all">All Months</option>
                            @for($m = 1; $m <= ($group->total_months ?: 12); $m++)
                                @php
                                    $mDateLabel = $group->start_date ? \Carbon\Carbon::parse($group->start_date)->addMonths($m - 1)->format('M Y') : null;
                                @endphp
                                <option value="{{ $m }}">Month {{ $m }}{{ $mDateLabel ? ' ('.$mDateLabel.')' : '' }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <label for="memberSortOption" class="form-label mb-0 small text-muted text-nowrap fw-semibold">Sort:</label>
                        <select id="memberSortOption" class="form-select form-select-sm no-search" style="width: auto;" onchange="window.location.href='?sort='+this.value">
                            <option value="default" {{ request('sort') == 'default' ? 'selected' : '' }}>Applied Month</option>
                            <option value="sl_asc" {{ request('sort') == 'sl_asc' ? 'selected' : '' }}>SL No (Ascending)</option>
                            <option value="sl_desc" {{ request('sort') == 'sl_desc' ? 'selected' : '' }}>SL No (Descending)</option>
                        </select>
                    </div>
                    @if(!$groupIsFull)
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                        <i class="icon-base ri ri-add-line me-1"></i>@if($group->is_private) Invite Member @else Add Member @endif
                    </button>
                    @endif
                </div>
            </div>
            <div class="card-body p-0">
                @if(($pendingApprovalCount ?? 0) > 0)
                    <div class="alert alert-warning border-0 rounded-0 mb-0 d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2">
                        <div class="small mb-0">
                            <i class="icon-base ri ri-error-warning-line me-1"></i>
                            <strong>{{ $pendingApprovalCount }} newly added member{{ $pendingApprovalCount > 1 ? 's' : '' }} awaiting approval.</strong>
                            Use Actions → <strong>Approve</strong> or <strong>Reject</strong> for each.
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($pendingApprovalMembers as $pendingMember)
                                <span class="badge bg-label-warning">
                                    #{{ $pendingMember->member_number }}
                                    {{ $pendingMember->client->client_name ?? 'Member' }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Turn</th>
                                <th>Client Details</th>
                                <th>Phone / Contact</th>
                                <th>Frequency</th>
                                <th>Settlement Month</th>
                                <th>Settlement Amount</th>
                                @if($group->scheme_type === 'group_based')<th>Role</th>@endif
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($memberDisplayRows ?? collect()) as $row)
                            @php
                                $member = $row->member;
                                $rowClient = $row->client;
                                $rowClientId = (int) $row->client_id;
                                $rowMemberNo = $row->member_number_label;
                                $rowDisplayName = $row->display_name
                                    ?? $member->displayClientName($rowClient)
                                    ?? ($rowClient->client_name ?? 'Client');
                                $rowEnrollmentSuffix = $row->enrollment_suffix ?? $member->enrollmentSuffix();
                                $rowSingleSeatOnly = !empty($row->single_seat_only) || $rowEnrollmentSuffix !== null;
                                $detailsId = 'member-details-' . $row->row_key;
                                $rowAgentName = $rowClient?->agent?->agent_name
                                    ?? ($member->assigned_agent_name !== '—' ? $member->assigned_agent_name : null);

                                $preferredNeedMonth = $member->preferredChitNeedPeriod();
                                // Show Settlement Month only while applied / approved. Hide after reject so they can re-apply.
                                $settleMonthNum = $member->displaySettlementMonthNumber();
                                $settleMonthFormatted = $member->displaySettlementMonthFormatted();

                                $payoutForAmountMonth = $settleMonthNum
                                    ?? (int) ($nextSettlementMonth ?? max(1, (int) $group->current_month + 1));

                                // One consistent share % for this row (independent seat × co-ownership if shared).
                                $independentSharePct = (float) ($member->effective_share_percentage ?? 100);
                                $ownershipSharePct = !empty($row->is_share_row)
                                    ? (float) ($row->ownership_percentage ?? 100)
                                    : 100.0;
                                $rowSharePct = round($independentSharePct * ($ownershipSharePct / 100.0), 2);
                                $rowSharePctLabel = rtrim(rtrim(number_format($rowSharePct, 2), '0'), '.');

                                // Prefer applied payout (already share-scaled); else calculate with Independent Sharing %.
                                $appliedPayoutForAmt = $group->payouts
                                    ->where('winner_member_id', $member->id)
                                    ->whereIn('status', ['pending', 'processing', 'paid'])
                                    ->sortByDesc('id')
                                    ->first();
                                $seatSettleAmt = $appliedPayoutForAmt
                                    ? (float) $appliedPayoutForAmt->payout_amount
                                    : $member->seatSettlementAmount((int) $payoutForAmountMonth);
                                $memberSettleAmt = !empty($row->is_share_row)
                                    ? $member->amountForClient($seatSettleAmt, $rowClientId)
                                    : round($seatSettleAmt, 2);

                                $payout = $appliedPayoutForAmt;
                                $needMatchesNextSettlement = $preferredNeedMonth
                                    && (int) $preferredNeedMonth === (int) $nextSettlementMonth;
                                $memberCanApplySettlement = ! $payout
                                    && ! $member->has_won_auction
                                    && in_array($member->status, ['active', 'approved'], true)
                                    && $group->status === 'active'
                                    && $needMatchesNextSettlement
                                    && ($canInitiateSettlement ?? false);
                                $memberCanApplyAdvance = ! $payout
                                    && ! $member->has_won_auction
                                    && in_array($member->status, ['active', 'approved'], true)
                                    && $group->status === 'active'
                                    && ($allowsAdvancePayouts ?? false)
                                    && ($advancePeriod ?? 0) > 0
                                    && ($paidOriginalForAdvance?->status === 'paid')
                                    && ($advanceEligibleMembers ?? collect())->contains('id', $member->id);
                            @endphp
                            <tr class="rotation-row {{ ($member->status ?? '') === 'applied' ? 'table-warning' : '' }}"
                                data-settlement-month="{{ $settleMonthNum ?? '' }}"
                                data-member-search="{{ strtolower(trim(($rowDisplayName ?? '') . ' ' . ($rowClient->client_phone ?? '') . ' ' . ($rowMemberNo ?? '') . ' ' . ($member->client->client_name ?? '') . ' ' . ($member->owners_display ?? ''))) }}"
                                @if(($member->status ?? '') === 'applied') title="Newly added — awaiting approval" @endif>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <button type="button"
                                            class="btn btn-icon btn-xs btn-label-primary rounded-circle member-emi-toggle"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#{{ $detailsId }}"
                                            aria-expanded="false"
                                            aria-controls="{{ $detailsId }}"
                                            title="View client installment schedule">
                                            <i class="icon-base ri ri-arrow-down-s-line"></i>
                                        </button>
                                        <span class="badge bg-light text-dark fw-bold">#{{ $rowMemberNo }}</span>
                                        @if($settleMonthNum)
                                            <span class="badge bg-label-primary" style="font-size:.65rem;" title="Applied settlement turn">M{{ $settleMonthNum }}</span>
                                        @endif
                                        @if(($member->status ?? '') === 'applied')
                                            <span class="badge bg-warning text-dark" style="font-size:.65rem;" title="Newly added member — needs approval">
                                                <i class="ri-time-line"></i> Pending Approval
                                            </span>
                                        @elseif(($member->status ?? '') === 'approved')
                                            <span class="badge bg-label-info" style="font-size:.65rem;" title="Approved — will activate with group">Approved</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-sm d-flex align-items-center justify-content-center bg-label-primary text-primary rounded-circle fw-bold" style="width:34px; height:34px; font-size:.85rem;">
                                            {{ substr($rowDisplayName ?? 'NA', 0, 2) }}
                                        </div>
                                        <div>
                                            @if($rowClient)
                                                <a href="{{ route('client-view-account', $rowClient->id) }}" class="fw-semibold text-primary text-decoration-none">
                                                    {{ $rowDisplayName }}
                                                </a>
                                                <div class="d-flex flex-wrap gap-1 mt-1">
                                                    @if(($member->status ?? '') === 'applied')
                                                        <span class="badge bg-warning text-dark" style="font-size:.65rem;">
                                                            <i class="ri-time-line me-1"></i>Awaiting Approval
                                                        </span>
                                                    @endif
                                                    @if($rowEnrollmentSuffix)
                                                        <span class="badge bg-label-warning" style="font-size:.65rem;" title="Same client holds multiple seats in this group">Seat {{ $rowEnrollmentSuffix }}</span>
                                                    @endif
                                                    @if($row->is_share_row)
                                                        <span class="badge bg-label-primary" style="font-size:.65rem;">Shared</span>
                                                    @endif
                                                    @if(abs($rowSharePct - 100) > 0.001)
                                                        <span class="badge bg-label-info" style="font-size:.65rem;"
                                                              title="@if($row->is_share_row && abs($independentSharePct - 100) > 0.001)Seat {{ rtrim(rtrim(number_format($independentSharePct, 2), '0'), '.') }}% × co-owner {{ rtrim(rtrim(number_format($ownershipSharePct, 2), '0'), '.') }}%@elseif($row->is_share_row)Co-owner share@elseif($independentSharePct > 100.001)Multi-seat cumulative share@else Independent share @endif">
                                                            {{ $rowSharePctLabel }}% Share
                                                            @if($rowSharePct > 100.001)
                                                                ({{ rtrim(rtrim(number_format($rowSharePct / 100, 2), '0'), '.') }} seats)
                                                            @endif
                                                        </span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-muted fst-italic">Client Deleted</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($rowClient)
                                        {{ $rowClient->client_phone }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $rowFreq = $member->collection_frequency ?? 'monthly';
                                        $rowFreqLabel = $member->collection_frequency_label ?? 'Monthly';
                                        $rowMonthlyAmt = (float) ($member->share_installment ?? $member->seatInstallmentAmount($group));
                                        $rowSplitAmt = $member->collectionSplitAmount($rowMonthlyAmt);
                                    @endphp
                                    <span class="badge bg-label-{{ $rowFreq === 'daily' ? 'warning' : ($rowFreq === 'weekly' ? 'info' : 'secondary') }}"
                                          title="Collection frequency{{ $rowFreq !== 'monthly' ? ' — ₹' . number_format($rowSplitAmt, 2) . ' per ' . ($rowFreq === 'daily' ? 'day' : 'week') : '' }}">
                                        {{ $rowFreqLabel }}
                                    </span>
                                    @if($rowFreq === 'daily' || $rowFreq === 'weekly')
                                        <div class="text-muted small" style="font-size:.7rem;">₹{{ number_format($rowSplitAmt, 2) }}/{{ $rowFreq === 'daily' ? 'day' : 'wk' }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($settleMonthFormatted !== '—')
                                        <span class="badge bg-label-primary font-monospace" title="Settlement Month">
                                            <i class="icon-base ri ri-calendar-event-line me-1"></i>{{ $settleMonthFormatted }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($appliedPayoutForAmt)
                                        <strong class="text-success" title="Applied Settlement Amount">₹{{ number_format($memberSettleAmt, 2) }}</strong>
                                        @if(abs($rowSharePct - 100) > 0.001)
                                            <div class="text-muted small" style="font-size:.7rem;">({{ $rowSharePctLabel }}% share)</div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                @if($group->scheme_type === 'group_based')
                                <td>
                                    @if($group->group_leader_id && $rowClientId == $group->group_leader_id)
                                        <span class="badge bg-warning text-dark"><i class="icon-base ri ri-star-line" style="font-size:.65rem;"></i> Leader</span>
                                    @else
                                        <span class="badge bg-secondary" style="font-size:.65rem;">Member</span>
                                    @endif
                                </td>
                                @endif

                                <td class="text-nowrap text-center">
                                    @php
                                        $memberPublicToken = \App\Support\HashId::encode($rowClientId);
                                        $memberPublicLink = route('public.view-chit-schedule', $memberPublicToken);
                                        $nextPayableInst = $member->installments
                                            ? $member->installments->where('group_id', $group->id)->whereIn('status', ['pending', 'overdue', 'partial'])->sortBy('month_number')->first()
                                            : \App\Models\Installment::where('group_id', $group->id)->where('member_id', $member->id)->whereIn('status', ['pending', 'overdue', 'partial'])->orderBy('month_number')->first();
                                        if ($nextPayableInst) {
                                            $nextPayableInst->setRelation('member', $member);
                                            $nextPayableInst->setRelation('group', $group);
                                            $nextShareBalance = $row->is_share_row
                                                ? $nextPayableInst->clientBalanceShare($rowClientId)
                                                : (float) $nextPayableInst->balance;
                                            $displayNextAmt = $member->displayAmountForInstallment(
                                                $nextPayableInst,
                                                $row->is_share_row ? $rowClientId : null
                                            );
                                            if (
                                                ! $row->is_share_row
                                                && abs((float) $nextPayableInst->amount - $displayNextAmt) > 0.05
                                                && (float) $nextPayableInst->paid_amount < 0.01
                                            ) {
                                                $nextShareBalance = $displayNextAmt;
                                            }
                                        } else {
                                            $nextShareBalance = 0;
                                        }
                                    @endphp
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        @if($nextPayableInst && $nextPayableInst->isCollectible() && $nextShareBalance > 0.009)
                                            @php
                                                $cName = $rowDisplayName;
                                                $pLabel = match($group->installment_frequency ?? 'monthly') {
                                                    'daily'  => 'Day ' . $nextPayableInst->month_number,
                                                    'weekly' => 'Week ' . $nextPayableInst->month_number,
                                                    default  => 'Month ' . $nextPayableInst->month_number,
                                                };
                                                $bal = round($nextShareBalance, 2);
                                                $amt = $member->displayAmountForInstallment(
                                                    $nextPayableInst,
                                                    $row->is_share_row ? $rowClientId : null
                                                );
                                                $paidAmt = $row->is_share_row
                                                    ? $nextPayableInst->clientPaidShare($rowClientId)
                                                    : (float) $nextPayableInst->paid_amount;
                                                $isCons = !empty($nextPayableInst->is_consolidated);
                                                $memNums = $rowMemberNo;
                                                $cumAmt = $amt;
                                                $seatInstallment = $member->seatInstallmentAmount($group, (int) $nextPayableInst->month_number);
                                                $singleAmt = $isCons && $seatInstallment > 0
                                                    ? ($row->is_share_row
                                                        ? $member->amountForClient($seatInstallment, $rowClientId)
                                                        : $seatInstallment)
                                                    : $amt;
                                                $partialRulesUrl = route('chit.installments.partial-rules', $nextPayableInst)
                                                    . ($rowClientId ? ('?client_id=' . $rowClientId) : '');
                                            @endphp
                                        @endif
                                         <div class="dropdown">
                                             <button type="button" class="btn btn-xs btn-icon btn-text-secondary rounded-pill dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
                                                 <i class="icon-base ri ri-more-2-fill fs-5"></i>
                                             </button>
                                             <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                 @if($nextPayableInst && $nextPayableInst->isCollectible() && $nextShareBalance > 0.009)
                                                     @php
                                                         $actionFreq = $member->collection_frequency ?? 'monthly';
                                                         $groupSchedFreq = $group->installment_frequency ?? 'monthly';
                                                         $actionDueDate = $nextPayableInst->due_date;
                                                         $actionSuggested = $member->suggestedCollectionAmount((float) $amt, (float) $bal, $actionDueDate);
                                                         $actionSplit = $member->collectionSplitAmount((float) $amt, $actionDueDate);
                                                         $actionSplitCount = $member->collectionSplitCount($actionDueDate);
                                                         $freqPayLabel = (($groupSchedFreq === 'monthly' || $groupSchedFreq === '')
                                                             && ($actionFreq === 'daily' || $actionFreq === 'weekly'))
                                                             ? ($actionFreq === 'daily' ? 'Pay Next Day' : 'Pay Next Week')
                                                             : null;
                                                     @endphp
                                                     @if($freqPayLabel)
                                                     <li>
                                                         <a href="javascript:void(0);"
                                                            class="dropdown-item chit-partial-btn text-primary"
                                                            data-installment-id="{{ $nextPayableInst->id }}"
                                                            data-collect-url="{{ route('chit.installments.collect', $nextPayableInst) }}"
                                                            data-partial-rules-url="{{ $partialRulesUrl }}"
                                                            data-client="{{ $cName }}"
                                                            data-client-id="{{ $rowClientId }}"
                                                            data-single-seat-only="1"
                                                            data-period="{{ $pLabel }}"
                                                            data-amount="{{ $amt }}"
                                                            data-penalty="{{ $row->is_share_row ? $member->amountForClient((float)$nextPayableInst->penalty_amount, $rowClientId) : (float)$nextPayableInst->penalty_amount }}"
                                                            data-paid="{{ $paidAmt }}"
                                                            data-balance="{{ $bal }}"
                                                            data-is-consolidated="0"
                                                            data-member-numbers="{{ $memNums }}"
                                                            data-single-amount="{{ $singleAmt }}"
                                                            data-cumulative-amount="{{ $cumAmt }}"
                                                            data-collection-frequency="{{ $actionFreq }}"
                                                            data-suggested-amount="{{ $actionSuggested }}"
                                                            data-split-amount="{{ $actionSplit }}"
                                                            data-split-count="{{ $actionSplitCount }}"
                                                            data-force-frequency-partial="1"
                                                            data-min-percentage="1"
                                                            data-penalty-method="{{ $partialPaymentConfig['penalty_calculation_method'] ?? 'emi_amount' }}">
                                                             <i class="icon-base ri ri-calendar-check-line me-2 text-primary"></i>{{ $freqPayLabel }} (₹{{ number_format($actionSuggested, 2) }})
                                                         </a>
                                                     </li>
                                                     @endif
                                                     <li>
                                                         <a href="javascript:void(0);"
                                                            class="dropdown-item chit-pay-btn text-success"
                                                            data-installment-id="{{ $nextPayableInst->id }}"
                                                            data-collect-url="{{ route('chit.installments.collect', $nextPayableInst) }}"
                                                            data-partial-rules-url="{{ $partialRulesUrl }}"
                                                            data-client="{{ $cName }}"
                                                            data-client-id="{{ $rowClientId }}"
                                                            data-single-seat-only="1"
                                                            data-period="{{ $pLabel }}"
                                                            data-amount="{{ $amt }}"
                                                            data-penalty="{{ $row->is_share_row ? $member->amountForClient((float)$nextPayableInst->penalty_amount, $rowClientId) : (float)$nextPayableInst->penalty_amount }}"
                                                            data-paid="{{ $paidAmt }}"
                                                            data-balance="{{ $bal }}"
                                                            data-is-consolidated="0"
                                                            data-member-numbers="{{ $memNums }}"
                                                            data-single-amount="{{ $singleAmt }}"
                                                            data-cumulative-amount="{{ $cumAmt }}"
                                                            data-collection-frequency="{{ $actionFreq }}"
                                                            data-due-date="{{ $nextPayableInst->due_date?->format('Y-m-d') }}">
                                                             <i class="icon-base ri ri-money-dollar-circle-line me-2 text-success"></i>Pay Full {{ $pLabel }}
                                                         </a>
                                                     </li>
                                                     @if($partialPaymentConfig['is_active'] ?? false)
                                                         <li>
                                                             <a href="javascript:void(0);"
                                                                class="dropdown-item chit-partial-btn text-info"
                                                                data-installment-id="{{ $nextPayableInst->id }}"
                                                                data-collect-url="{{ route('chit.installments.collect', $nextPayableInst) }}"
                                                                data-partial-rules-url="{{ $partialRulesUrl }}"
                                                                data-client="{{ $cName }}"
                                                                data-client-id="{{ $rowClientId }}"
                                                                data-single-seat-only="1"
                                                                data-period="{{ $pLabel }}"
                                                                data-amount="{{ $amt }}"
                                                                data-penalty="{{ $row->is_share_row ? $member->amountForClient((float)$nextPayableInst->penalty_amount, $rowClientId) : (float)$nextPayableInst->penalty_amount }}"
                                                                data-paid="{{ $paidAmt }}"
                                                                data-balance="{{ $bal }}"
                                                                data-is-consolidated="0"
                                                                data-member-numbers="{{ $memNums }}"
                                                                data-single-amount="{{ $singleAmt }}"
                                                                data-cumulative-amount="{{ $cumAmt }}"
                                                                data-collection-frequency="{{ $actionFreq }}"
                                                                data-suggested-amount="{{ $actionSuggested }}"
                                                                data-split-amount="{{ $actionSplit }}"
                                                                data-split-count="{{ $actionSplitCount }}"
                                                                data-min-percentage="{{ $partialPaymentConfig['minimum_partial_percentage'] ?? 10 }}"
                                                                data-penalty-method="{{ $partialPaymentConfig['penalty_calculation_method'] ?? 'emi_amount' }}">
                                                                 <i class="icon-base ri ri-percent-line me-2 text-info"></i>Custom Partial ({{ $pLabel }})
                                                             </a>
                                                         </li>
                                                     @endif
                                                     <li><hr class="dropdown-divider"></li>
                                                 @endif

                                                @if($payout)
                                                    @if($payout->status === 'pending')
                                                        <li>
                                                            <a href="{{ route('chit.settlements.confirm', [$group, $member]) }}?payout_id={{ $payout->id }}{{ ($payout->payout_kind ?? '') === 'advance' ? '&kind=advance' : '' }}" class="dropdown-item text-warning">
                                                                <i class="icon-base ri ri-time-line me-2 text-warning"></i>Confirm {{ ($payout->payout_kind ?? '') === 'advance' ? 'Advance' : 'Settlement' }}
                                                            </a>
                                                        </li>
                                                    @elseif($payout->status === 'processing')
                                                        <li>
                                                            <a href="{{ route('chit.settlement-applications.show', $payout) }}" class="dropdown-item text-info">
                                                                <i class="icon-base ri ri-refresh-line me-2 text-info"></i>View Settlement Application
                                                            </a>
                                                        </li>
                                                    @endif
                                                @elseif($memberCanApplyAdvance)
                                                    <li>
                                                        <a href="{{ route('chit.settlements.confirm', [$group, $member]) }}?kind=advance" class="dropdown-item text-info">
                                                            <i class="icon-base ri ri-flashlight-line me-2 text-info"></i>Apply Advance
                                                        </a>
                                                    </li>
                                                @elseif($memberCanApplySettlement)
                                                    <li>
                                                        <a href="{{ route('chit.settlement-applications.index', ['group_id' => $group->id, 'member_id' => $member->id, 'month_number' => $preferredNeedMonth]) }}" class="dropdown-item text-primary">
                                                            <i class="icon-base ri ri-send-plane-line me-2 text-primary"></i>Apply Settlement
                                                        </a>
                                                    </li>
                                                @endif
                                                @if($rowClientId)
                                                    <li>
                                                        <a href="{{ route('client-view-chits', $rowClientId) }}" class="dropdown-item">
                                                            <i class="icon-base ri ri-user-3-line me-2 text-primary"></i>Client Chits Profile
                                                        </a>
                                                    </li>
                                                @endif
                                                <li>
                                                    <a href="{{ $memberPublicLink }}" target="_blank" class="dropdown-item">
                                                        <i class="icon-base ri ri-share-line me-2 text-secondary"></i>Public Chit Schedule
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('chit.members.edit', $member) }}" class="dropdown-item">
                                                        <i class="icon-base ri ri-pencil-line me-2 text-warning"></i>Edit Member
                                                    </a>
                                                </li>
                                                @php
                                                    $canTransfer = $member->canBeTransferred($group);
                                                    $transferBlockedReason = $member->transferBlockReason($group);
                                                @endphp
                                                @if(!in_array($member->status, \App\Models\GroupMember::INACTIVE_STATUSES, true))
                                                    <li>
                                                        @if($canTransfer)
                                                            <a href="{{ route('chit.members.transfer.create', $member) }}" class="dropdown-item text-info">
                                                                <i class="icon-base ri ri-exchange-line me-2 text-info"></i>Transfer Member
                                                            </a>
                                                        @else
                                                            <span class="dropdown-item disabled text-muted" title="{{ $transferBlockedReason }}">
                                                                <i class="icon-base ri ri-exchange-line me-2"></i>Transfer Member
                                                                <small class="d-block text-muted ps-4" style="font-size:.7rem;">{{ $transferBlockedReason }}</small>
                                                            </span>
                                                        @endif
                                                    </li>
                                                @endif
                                                @if($member->status === 'applied')
                                                    <li>
                                                        <form method="POST" action="{{ route('chit.members.approve', $member) }}" class="group-ajax-form" data-success-title="Approved">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item text-success">
                                                                <i class="icon-base ri ri-checkbox-circle-line me-2 text-success"></i>Approve
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST" action="{{ route('chit.members.reject', $member) }}" class="group-ajax-form" data-confirm="Reject this member?" data-success-title="Rejected">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="icon-base ri ri-close-circle-line me-2 text-danger"></i>Reject
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endif
                                                @php
                                                    $canDeleteMember = $member->canDeleteFromGroup();
                                                    $paidSettlementBlocksCancel = $member->has_won_auction
                                                        || ($payout && $payout->status === 'paid');
                                                    // Until first month is paid → Delete. After payment → Cancel Chit.
                                                    $canCancelMember = ! $canDeleteMember
                                                        && in_array($member->status, ['active', 'approved', 'defaulted', 'frozen'], true);
                                                @endphp
                                                @if($canDeleteMember)
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" action="{{ route('chit.members.destroy', $member) }}" class="group-ajax-form"
                                                              data-confirm="Permanently delete this client from the group? Available until the first month installment is paid."
                                                              data-success-title="Deleted">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="icon-base ri ri-delete-bin-line me-2 text-danger"></i>Delete Client
                                                                <small class="d-block text-muted ps-4" style="font-size:.7rem;">Until first month paid</small>
                                                            </button>
                                                        </form>
                                                    </li>
                                                @elseif($canCancelMember)
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        @if($paidSettlementBlocksCancel)
                                                            <span class="dropdown-item disabled text-muted" title="Paid settlement exists — cancel not allowed">
                                                                <i class="icon-base ri ri-close-circle-line me-2"></i>Cancel Chit
                                                                <small class="d-block text-muted ps-4" style="font-size:.7rem;">Paid settlement already released</small>
                                                            </span>
                                                        @else
                                                            <form method="POST" action="{{ route('chit.members.cancel', $member) }}" class="group-ajax-form"
                                                                  data-confirm="Cancel this client's chit? Seat freed. Settlement = paid months − foreman commission month (apply separately)."
                                                                  data-success-title="Chit Cancelled">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item text-warning">
                                                                    <i class="icon-base ri ri-prohibited-line me-2 text-warning"></i>Cancel Chit
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </li>
                                                @endif
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            {{-- Client-wise installment schedule (below member / share-owner row) --}}
                            <tr class="member-details-row">
                                <td colspan="{{ $group->scheme_type === 'group_based' ? 8 : 7 }}" class="p-0 border-0">
                                    <div class="collapse" id="{{ $detailsId }}">
                                    @php
                                        $clientLabel = $rowDisplayName;
                                        $mPayout = $group->payouts->where('winner_member_id', $member->id)->where('status', '!=', 'cancelled')->sortByDesc('id')->first();
                                        $mInstallments = ($member->relationLoaded('installments')
                                            ? $member->installments
                                            : \App\Models\Installment::where('group_id', $group->id)->where('member_id', $member->id)->with('sharePayments')->get()
                                        )->sortBy('month_number')->values();
                                        $mInstallments->each(function ($inst) use ($group, $member) {
                                            $inst->setRelation('group', $group);
                                            $inst->setRelation('member', $member);
                                        });
                                        $seatEmi = $member->seatInstallmentAmount($group, 1);
                                        $ownerEmi = $member->installmentAmountForClient(
                                            $row->is_share_row ? $rowClientId : null,
                                            $group,
                                            1
                                        );
                                        $detailSharePct = $rowSharePct;
                                        $detailSharePctLabel = $rowSharePctLabel;
                                        $mPaidSum = 0.0;
                                        $mPaidCount = 0;
                                        foreach ($mInstallments as $inst) {
                                            $paidShare = $row->is_share_row
                                                ? $inst->clientPaidShare($rowClientId)
                                                : (float) $inst->paid_amount;
                                            $balShare = $row->is_share_row
                                                ? $inst->clientBalanceShare($rowClientId)
                                                : (float) $inst->balance;
                                            $mPaidSum += $paidShare;
                                            if ($balShare <= 0.009 && $paidShare > 0.009) {
                                                $mPaidCount++;
                                            }
                                        }
                                        $mTotalDue = (float) $group->total_months * $ownerEmi;
                                        $settleBaseMonth = $settleMonthNum
                                            ?? (int) ($nextSettlementMonth ?? max(1, (int) $group->current_month + 1));
                                        // Same source as Settlement Amount column (Independent Sharing %).
                                        $mSettlementAmt = $mPayout
                                            ? ($row->is_share_row
                                                ? $member->amountForClient((float) $mPayout->payout_amount, $rowClientId)
                                                : (float) $mPayout->payout_amount)
                                            : ($row->is_share_row
                                                ? $member->settlementAmountForClient($rowClientId, $settleBaseMonth)
                                                : $member->seatSettlementAmount($settleBaseMonth));
                                    @endphp
                                    <div class="bg-light border-top border-bottom p-3">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                            <h6 class="mb-0 text-primary">
                                                <i class="icon-base ri ri-user-3-line me-1"></i>#{{ $rowMemberNo }} {{ $clientLabel }}
                                                <span class="text-muted fw-normal">— Client Installment Schedule</span>
                                                @if($row->is_share_row)
                                                    <span class="badge bg-label-primary ms-1" style="font-size:.65rem;">Shared</span>
                                                @endif
                                                @if(abs($detailSharePct - 100) > 0.001)
                                                    <span class="badge bg-label-info ms-1" style="font-size:.65rem;">{{ $detailSharePctLabel }}% Share</span>
                                                @endif
                                                <span class="badge bg-label-{{ ($member->collection_frequency ?? 'monthly') === 'daily' ? 'warning' : (($member->collection_frequency ?? 'monthly') === 'weekly' ? 'info' : 'secondary') }} ms-1" style="font-size:.65rem;">
                                                    {{ $member->collection_frequency_label ?? 'Monthly' }}
                                                </span>
                                            </h6>
                                            <div class="d-flex flex-wrap gap-2">
                                                <span class="badge bg-label-info">{{ $mPaidCount }}/{{ $group->total_months }} Paid</span>
                                                <span class="badge bg-label-primary">Collected ₹{{ number_format($mPaidSum, 2) }}</span>
                                                @if($mPayout)
                                                    <span class="badge bg-{{ $mPayout->status_badge }}">Settlement: {{ $mPayout->status === 'paid' ? 'Prised' : $mPayout->status_label }}</span>
                                                @else
                                                    <span class="badge bg-label-secondary">Settlement: Not Settled</span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-md-4 col-6">
                                                <small class="text-muted d-block">{{ $mPayout ? 'Settlement Amount' : 'Est. Payout' }}{{ $row->is_share_row ? ' (share)' : '' }}</small>
                                                @if($mPayout)
                                                    <span class="fw-bold text-primary">₹{{ number_format($mSettlementAmt, 2) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </div>
                                            <div class="col-md-4 col-6">
                                                <small class="text-muted d-block">
                                                    {{ $row->is_share_row ? 'Share EMI' : 'Monthly EMI' }}
                                                    @if(abs($detailSharePct - 100) > 0.001)
                                                        <span class="text-info">({{ $detailSharePctLabel }}%)</span>
                                                    @endif
                                                </small>
                                                <span class="fw-semibold">₹{{ number_format($ownerEmi, 2) }}</span>
                                            </div>
                                            <div class="col-md-4 col-6">
                                                <small class="text-muted d-block">Payout Ref</small>
                                                <span class="fw-semibold">{{ $mPayout->payout_code ?? '—' }}</span>
                                            </div>
                                        </div>

                                         <div class="card border shadow-xs mb-0">
                                             <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                 <span class="fw-semibold text-dark">
                                                     <i class="icon-base ri ri-calendar-schedule-line me-1 text-primary"></i>Installment Schedule
                                                     @if(($member->collection_frequency ?? 'monthly') === 'daily')
                                                         <span class="badge bg-label-warning ms-1" style="font-size:.65rem;">Daily parts under each month</span>
                                                     @elseif(($member->collection_frequency ?? 'monthly') === 'weekly')
                                                         <span class="badge bg-label-info ms-1" style="font-size:.65rem;">Weekly parts under each month</span>
                                                     @endif
                                                 </span>
                                                 <div class="d-flex align-items-center gap-2 flex-wrap">
                                                     @php
                                                         $schedMemberFreq = $member->collection_frequency ?? 'monthly';
                                                         $isFreqMember = in_array($schedMemberFreq, ['daily', 'weekly'], true)
                                                             && (($group->installment_frequency ?? 'monthly') === 'monthly' || ($group->installment_frequency ?? '') === '');
                                                         $defaultMonthFilter = 'all';
                                                         if ($isFreqMember) {
                                                             $firstUnpaidMonth = $mInstallments
                                                                 ->first(function ($fi) use ($member, $row, $rowClientId) {
                                                                     $bal = $row->is_share_row
                                                                         ? $fi->clientBalanceShare($rowClientId)
                                                                         : (float) $fi->balance;
                                                                     return $fi->isCollectible() && $bal > 0.009;
                                                                 });
                                                             $defaultMonthFilter = $firstUnpaidMonth
                                                                 ? 'month-' . $firstUnpaidMonth->month_number
                                                                 : 'month-' . ($mInstallments->first()->month_number ?? 1);
                                                         }
                                                     @endphp
                                                     @if($isFreqMember)
                                                     <label class="form-label mb-0 text-nowrap small fw-semibold text-muted">
                                                         <i class="ri-filter-3-line me-1"></i>
                                                         {{ $schedMemberFreq === 'daily' ? 'Daily' : 'Weekly' }} by Month:
                                                     </label>
                                                     <select class="form-select form-select-sm member-inst-filter no-search py-1 px-2"
                                                             style="min-width: 200px;"
                                                             data-table-id="inst-table-{{ $detailsId }}"
                                                             data-default-month="{{ $defaultMonthFilter }}">
                                                         <option value="all" @selected($defaultMonthFilter === 'all')>All Months ({{ $mInstallments->count() }})</option>
                                                         <option value="unpaid">Pending &amp; Overdue</option>
                                                         <option value="paid">Paid</option>
                                                         @foreach($mInstallments as $filterInst)
                                                             @php
                                                                 $filterDays = $filterInst->due_date
                                                                     ? (int) $filterInst->due_date->daysInMonth
                                                                     : 30;
                                                                 $filterLabel = 'Month ' . $filterInst->month_number;
                                                                 if ($filterInst->due_date) {
                                                                     $filterLabel .= ' — ' . $filterInst->due_date->format('M Y');
                                                                 }
                                                                 if ($schedMemberFreq === 'daily') {
                                                                     $filterLabel .= ' (' . $filterDays . ' days)';
                                                                 } else {
                                                                     $filterLabel .= ' (4 weeks)';
                                                                 }
                                                                 $filterBal = $row->is_share_row
                                                                     ? $filterInst->clientBalanceShare($rowClientId)
                                                                     : (float) $filterInst->balance;
                                                                 $filterPaidAmt = $row->is_share_row
                                                                     ? $filterInst->clientPaidShare($rowClientId)
                                                                     : (float) $filterInst->paid_amount;
                                                                 if ($filterBal <= 0.009 && $filterPaidAmt > 0.009) {
                                                                     $filterLabel .= ' ✓ Paid';
                                                                 }
                                                             @endphp
                                                             <option value="month-{{ $filterInst->month_number }}"
                                                                     @selected($defaultMonthFilter === 'month-'.$filterInst->month_number)>
                                                                 {{ $filterLabel }}
                                                             </option>
                                                         @endforeach
                                                     </select>
                                                     @endif
                                                 </div>
                                             </div>
                                           
                                             <div class="table-responsive">
                                                 <table class="table table-sm table-bordered table-hover align-middle mb-0" id="inst-table-{{ $detailsId }}">
                                                     <thead class="table-light">
                                                         <tr>
                                                             @if($isFreqMember)
                                                             <th class="text-center" style="width:36px;">
                                                                 <input type="checkbox" class="form-check-input chit-bulk-select-all" data-table-id="inst-table-{{ $detailsId }}" title="Select days/weeks in order">
                                                             </th>
                                                             @endif
                                                             <th class="text-center">Month #</th>
                                                             <th>Due Date</th>
                                                             <th class="text-end">Amount Due</th>
                                                             <th>Paid Date</th>
                                                             <th class="text-end">Paid Amount</th>
                                                             <th class="text-end">Penalty</th>
                                                             <th class="text-end">Balance</th>
                                                             <th class="text-end">Dividend</th>
                                                             <th>Reference No</th>
                                                             <th class="text-center">Status</th>
                                                             <th class="text-center">Action</th>
                                                         </tr>
                                                     </thead>
                                                     <tbody>
                                                         @forelse($mInstallments as $inst)
                                                             @php
                                                                 $subAmount = $member->displayAmountForInstallment(
                                                                     $inst,
                                                                     $row->is_share_row ? $rowClientId : null
                                                                 );
                                                                 $subPaid = $row->is_share_row
                                                                     ? $inst->clientPaidShare($rowClientId)
                                                                     : (float) $inst->paid_amount;
                                                                 $subPenalty = $row->is_share_row
                                                                     ? $member->amountForClient((float) $inst->penalty_amount, $rowClientId)
                                                                     : (float) $inst->penalty_amount;
                                                                 $subBalance = $row->is_share_row
                                                                     ? $inst->clientBalanceShare($rowClientId)
                                                                     : (float) $inst->balance;
                                                                 $subDividend = $group->getMonthlyDividendAmount(
                                                                     (int) $inst->month_number,
                                                                     $member,
                                                                     $row->is_share_row ? $rowClientId : null
                                                                 );
                                                                 // Align unpaid balance to ownership / independent-share scaled amount.
                                                                 if (abs((float) $inst->amount - $subAmount) > 0.05 && (float) $inst->paid_amount < 0.01) {
                                                                     $subBalance = max(0, round($subAmount + $subPenalty - $subPaid, 2));
                                                                 }
                                                                 if ($subBalance <= 0.009 && $subPaid > 0.009) {
                                                                     $ownerStatus = 'paid';
                                                                 } elseif ($subPaid > 0.009 && $subBalance > 0.009) {
                                                                     $ownerStatus = 'partial';
                                                                 } else {
                                                                     $ownerStatus = $inst->status;
                                                                 }
                                                                 $statusColor = match ($ownerStatus) {
                                                                     'paid' => 'success',
                                                                     'partial' => 'info',
                                                                     'overdue' => 'danger',
                                                                     'waived' => 'secondary',
                                                                     default => 'warning',
                                                                 };
                                                                 $undoFormId = 'undo-form-inst-' . $inst->id . '-' . $rowClientId;
                                                                 $memberFreq = $member->collection_frequency ?? 'monthly';
                                                                 $groupSchedFreq = $group->installment_frequency ?? 'monthly';
                                                                 $freqPeriods = ($groupSchedFreq === 'monthly' || $groupSchedFreq === '')
                                                                     && ($memberFreq === 'daily' || $memberFreq === 'weekly')
                                                                     ? $member->collectionPeriodSchedule($inst->due_date, (float) $subAmount, (float) $subPaid)
                                                                     : [];
                                                                 $freqSplitCount = count($freqPeriods);
                                                                 $subClientName = $clientLabel;
                                                                 $subPeriodLabel = match($group->installment_frequency ?? 'monthly') {
                                                                     'daily'  => 'Day ' . $inst->month_number,
                                                                     'weekly' => 'Week ' . $inst->month_number,
                                                                     default  => 'Month ' . $inst->month_number,
                                                                 };
                                                                 $instPartialRulesUrl = route('chit.installments.partial-rules', $inst)
                                                                     . ($rowClientId ? ('?client_id=' . $rowClientId) : '');
                                                                 $freqUnitLabel = $memberFreq === 'daily' ? 'Day' : ($memberFreq === 'weekly' ? 'Week' : 'Month');
                                                                 $nextFreqPeriod = collect($freqPeriods)->firstWhere('is_next', true);
                                                                 $monthSplitAmt = $member->collectionSplitAmount((float) $subAmount, $inst->due_date);
                                                                 $monthSplitCount = $member->collectionSplitCount($inst->due_date);
                                                             @endphp
                                                             @php
                                                                 $instRowHidden = $isFreqMember
                                                                     && $defaultMonthFilter !== 'all'
                                                                     && $defaultMonthFilter !== ('month-' . $inst->month_number);
                                                                 $groupCanBulk = $isFreqMember
                                                                     && $inst->isCollectible()
                                                                     && $subBalance > 0.009;
                                                                 $groupSuggested = $nextFreqPeriod
                                                                     ? (float) $nextFreqPeriod['balance']
                                                                     : $member->suggestedCollectionAmount((float) $subAmount, (float) $subBalance, $inst->due_date);
                                                             @endphp
                                                             <tr class="inst-row"
                                                                 data-month="month-{{ $inst->month_number }}"
                                                                 data-status="{{ $ownerStatus }}"
                                                                 data-inst-id="{{ $inst->id }}"
                                                                 @if($instRowHidden) style="display:none"@endif>
                                                                 @if($isFreqMember)
                                                                 <td class="text-center"></td>
                                                                 @endif
                                                                 <td class="text-center fw-semibold">
                                                                     {{ $inst->isForemanCommission() ? \App\Models\ChitScheme::FOREMAN_COMMISSION_LABEL : 'Month '.$inst->month_number }}
                                                                     @if($group->usesClientWiseForemanCommission() && (int) $inst->month_number === $group->scheme->clientWiseForemanCollectionMonth())
                                                                         @php $cwFc = $group->clientWiseForemanExtraForShare((int) $inst->month_number, (float) ($inst->share_percentage ?? $member->effective_share_percentage ?? 100)); @endphp
                                                                         @if($cwFc > 0)
                                                                             <div class="text-warning small fw-normal" style="font-size:.65rem;">includes ₹{{ number_format($cwFc, 2) }} client-wise FC</div>
                                                                         @endif
                                                                     @endif
                                                                     @if($freqSplitCount > 0)
                                                                         <div class="text-muted small fw-normal" style="font-size:.65rem;">
                                                                             {{ $freqSplitCount }} {{ strtolower($freqUnitLabel) }}{{ $freqSplitCount === 1 ? '' : 's' }}
                                                                             @if($memberFreq === 'daily' && $inst->due_date)
                                                                                 ({{ $inst->due_date->format('M Y') }})
                                                                             @endif
                                                                         </div>
                                                                     @endif
                                                                 </td>
                                                                 <td>{{ $inst->due_date ? $inst->due_date->format('d M Y') : '—' }}</td>
                                                                 <td class="text-end">₹{{ number_format($subAmount, 2) }}</td>
                                                                 <td>{{ $inst->paid_date && $subPaid > 0.009 ? $inst->paid_date->format('d M Y') : '—' }}</td>
                                                                 <td class="text-end text-success fw-bold">₹{{ number_format($subPaid, 2) }}</td>
                                                                 <td class="text-end text-warning">₹{{ number_format($subPenalty, 2) }}</td>
                                                                 <td class="text-end fw-semibold {{ $subBalance > 0.009 ? 'text-danger' : 'text-muted' }}">
                                                                     {{ $subBalance > 0.009 ? '₹' . number_format($subBalance, 2) : '—' }}
                                                                 </td>
                                                                 <td class="text-end fw-semibold {{ $subDividend > 0.009 ? 'text-success' : 'text-muted' }}">
                                                                     {{ $subDividend > 0.009 ? '₹' . number_format($subDividend, 2) : '—' }}
                                                                 </td>
                                                                 <td><code>{{ $inst->reference_no ?? '—' }}</code></td>
                                                                 <td class="text-center">
                                                                     <span class="badge bg-label-{{ $statusColor }} text-capitalize">{{ $ownerStatus }}</span>
                                                                 </td>
                                                                 <td class="text-center">
                                                                     @if($inst->isCollectible() && $subBalance > 0.009)
                                                                         <div class="dropdown d-inline-block">
                                                                            <button type="button" class="btn btn-xs btn-icon btn-text-secondary rounded-pill dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
                                                                                <i class="icon-base ri ri-more-2-fill fs-5"></i>
                                                                            </button>
                                                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                                                @if($nextFreqPeriod)
                                                                                    <li>
                                                                                        <a href="javascript:void(0);"
                                                                                           class="dropdown-item chit-partial-btn text-primary"
                                                                                           data-installment-id="{{ $inst->id }}"
                                                                                           data-collect-url="{{ route('chit.installments.collect', $inst) }}"
                                                                                           data-partial-rules-url="{{ $instPartialRulesUrl }}"
                                                                                           data-client="{{ $subClientName }}"
                                                                                           data-client-id="{{ $rowClientId }}"
                                                                                           data-single-seat-only="1"
                                                                                           data-period="{{ $subPeriodLabel }} — {{ $nextFreqPeriod['label'] }}"
                                                                                           data-amount="{{ $subAmount }}"
                                                                                           data-penalty="{{ $subPenalty }}"
                                                                                           data-paid="{{ $subPaid }}"
                                                                                           data-balance="{{ $subBalance }}"
                                                                                           data-is-consolidated="0"
                                                                                           
                                                                                           data-member-numbers="{{ $rowMemberNo }}"
                                                                                           data-single-amount="{{ $subAmount }}"
                                                                                           data-cumulative-amount="{{ $subAmount }}"
                                                                                           data-collection-frequency="{{ $memberFreq }}"
                                                                                           data-suggested-amount="{{ $nextFreqPeriod['balance'] }}"
                                                                                           data-split-amount="{{ $nextFreqPeriod['amount'] }}"
                                                                                           data-split-count="{{ $freqSplitCount }}"
                                                                                           data-force-frequency-partial="1"
                                                                                           data-min-percentage="1"
                                                                                           data-penalty-method="{{ $partialPaymentConfig['penalty_calculation_method'] ?? 'emi_amount' }}">
                                                                                            <i class="icon-base ri ri-calendar-check-line me-2 text-primary"></i>Pay {{ $nextFreqPeriod['label'] }} (₹{{ number_format($nextFreqPeriod['balance'], 2) }})
                                                                                        </a>
                                                                                    </li>
                                                                                @endif
                                                                                <li>
                                                                                    <a href="javascript:void(0);"
                                                                                       class="dropdown-item chit-pay-btn text-success"
                                                                                       data-installment-id="{{ $inst->id }}"
                                                                                       data-collect-url="{{ route('chit.installments.collect', $inst) }}"
                                                                                       data-partial-rules-url="{{ $instPartialRulesUrl }}"
                                                                                       data-client="{{ $subClientName }}"
                                                                                       data-client-id="{{ $rowClientId }}"
                                                                                       data-single-seat-only="1"
                                                                                       data-period="{{ $subPeriodLabel }}"
                                                                                       data-amount="{{ $subAmount }}"
                                                                                       data-penalty="{{ $subPenalty }}"
                                                                                       data-paid="{{ $subPaid }}"
                                                                                       data-balance="{{ $subBalance }}"
                                                                                       data-is-consolidated="0"
                                                                                       data-member-numbers="{{ $rowMemberNo }}"
                                                                                       data-single-amount="{{ $subAmount }}"
                                                                                       data-cumulative-amount="{{ $subAmount }}"
                                                                                       data-collection-frequency="{{ $memberFreq }}"
                                                                                       data-due-date="{{ $inst->due_date?->format('Y-m-d') }}">
                                                                                        <i class="icon-base ri ri-money-dollar-circle-line me-2 text-success"></i>Pay Full Month
                                                                                    </a>
                                                                                </li>
                                                                                @if($partialPaymentConfig['is_active'] ?? false)
                                                                                    <li>
                                                                                        <a href="javascript:void(0);"
                                                                                           class="dropdown-item chit-partial-btn text-info"
                                                                                           data-installment-id="{{ $inst->id }}"
                                                                                           data-collect-url="{{ route('chit.installments.collect', $inst) }}"
                                                                                           data-partial-rules-url="{{ $instPartialRulesUrl }}"
                                                                                           data-client="{{ $subClientName }}"
                                                                                           data-client-id="{{ $rowClientId }}"
                                                                                           data-single-seat-only="1"
                                                                                           data-period="{{ $subPeriodLabel }}"
                                                                                           data-amount="{{ $subAmount }}"
                                                                                           data-penalty="{{ $subPenalty }}"
                                                                                           data-paid="{{ $subPaid }}"
                                                                                           data-balance="{{ $subBalance }}"
                                                                                           data-is-consolidated="0"
                                                                                           data-member-numbers="{{ $rowMemberNo }}"
                                                                                           data-single-amount="{{ $subAmount }}"
                                                                                           data-cumulative-amount="{{ $subAmount }}"
                                                                                           data-collection-frequency="{{ $memberFreq }}"
                                                                                           data-suggested-amount="{{ $member->suggestedCollectionAmount((float) $subAmount, (float) $subBalance, $inst->due_date) }}"
                                                                                           data-split-amount="{{ $monthSplitAmt }}"
                                                                                           data-split-count="{{ $monthSplitCount }}"
                                                                                           data-min-percentage="{{ $partialPaymentConfig['minimum_partial_percentage'] ?? 10 }}"
                                                                                           data-penalty-method="{{ $partialPaymentConfig['penalty_calculation_method'] ?? 'emi_amount' }}">
                                                                                            <i class="icon-base ri ri-percent-line me-2 text-info"></i>Custom Partial
                                                                                        </a>
                                                                                    </li>
                                                                                @endif
                                                                            </ul>
                                                                        </div>
                                                                     @elseif($subPaid > 0.009)
                                                                         <div class="d-flex align-items-center justify-content-center gap-1">
                                                                             @if($inst->isUndoable($rowClientId))
                                                                                 <button type="button" class="btn btn-xs btn-outline-danger d-inline-flex align-items-center gap-1 py-1 px-2" onclick="confirmChitUndoPayment('{{ $undoFormId }}')" title="Undo Payment">
                                                                                     <i class="icon-base ri ri-history-line me-1"></i>Undo
                                                                                 </button>
                                                                                 <form id="{{ $undoFormId }}" action="{{ route('chit.installments.undo', $inst) }}" method="POST" class="d-none">
                                                                                     @csrf
                                                                                     <input type="hidden" name="client_id" value="{{ $rowClientId }}">
                                                                                 </form>
                                                                             @else
                                                                                 <span class="badge bg-label-success">Paid</span>
                                                                             @endif
                                                                         </div>
                                                                     @else
                                                                         <span class="text-muted">—</span>
                                                                     @endif
                                                                 </td>
                                                            </tr>
                                                            @foreach($freqPeriods as $period)
                                                                @php
                                                                    $pStatusColor = match ($period['status']) {
                                                                        'paid' => 'success',
                                                                        'partial' => 'info',
                                                                        'overdue' => 'danger',
                                                                        default => 'warning',
                                                                    };
                                                                @endphp
                                                                <tr class="inst-row inst-freq-row bg-light"
                                                                    data-month="month-{{ $inst->month_number }}"
                                                                    data-status="{{ $period['status'] }}"
                                                                    data-period-index="{{ $period['index'] }}"
                                                                    data-parent-inst="{{ $inst->id }}"
                                                                    @if($instRowHidden) style="display:none"@endif>
                                                                    @if($isFreqMember)
                                                                    <td class="text-center">
                                                                        @if($inst->isCollectible() && $subBalance > 0.009 && $period['balance'] > 0.009)
                                                                            <input type="checkbox"
                                                                                   class="form-check-input chit-bulk-cb"
                                                                                   value="{{ $inst->id }}"
                                                                                   data-client-id="{{ $rowClientId }}"
                                                                                   data-balance="{{ $subBalance }}"
                                                                                   data-suggested="{{ $period['balance'] }}"
                                                                                   data-period-amount="{{ $period['balance'] }}"
                                                                                   data-period-index="{{ $period['index'] }}"
                                                                                   data-period-label="{{ $period['label'] }}"
                                                                                   data-month-number="{{ $inst->month_number }}"
                                                                                   data-is-next="{{ !empty($period['is_next']) ? '1' : '0' }}"
                                                                                   data-is-freq="1">
                                                                        @endif
                                                                    </td>
                                                                    @endif
                                                                    <td class="text-center text-muted small ps-4">
                                                                        <i class="ri-corner-down-right-line me-1"></i>{{ $period['label'] }}
                                                                    </td>
                                                                    <td class="small">{{ $period['due_date'] ? $period['due_date']->format('d M Y') : '—' }}</td>
                                                                    <td class="text-end small">₹{{ number_format($period['amount'], 2) }}</td>
                                                                    <td class="small">—</td>
                                                                    <td class="text-end text-success small">₹{{ number_format($period['paid'], 2) }}</td>
                                                                    <td class="text-end small text-muted">—</td>
                                                                    <td class="text-end fw-semibold small {{ $period['balance'] > 0.009 ? 'text-danger' : 'text-muted' }}">
                                                                        {{ $period['balance'] > 0.009 ? '₹' . number_format($period['balance'], 2) : '—' }}
                                                                    </td>
                                                                    <td class="small text-muted">—</td>
                                                                    <td class="text-center">
                                                                        <span class="badge bg-label-{{ $pStatusColor }} text-capitalize" style="font-size:.65rem;">{{ $period['status'] }}</span>
                                                                    </td>
                                                                    <td class="text-center text-nowrap">
                                                                        @if($inst->isCollectible() && $subBalance > 0.009 && $period['balance'] > 0.009)
                                                                            <a href="javascript:void(0);"
                                                                               class="btn btn-xs btn-primary chit-partial-btn"
                                                                               data-installment-id="{{ $inst->id }}"
                                                                               data-collect-url="{{ route('chit.installments.collect', $inst) }}"
                                                                               data-partial-rules-url="{{ $instPartialRulesUrl }}"
                                                                               data-client="{{ $subClientName }}"
                                                                               data-client-id="{{ $rowClientId }}"
                                                                               data-single-seat-only="1"
                                                                               data-period="{{ $subPeriodLabel }} — {{ $period['label'] }}"
                                                                               data-amount="{{ $subAmount }}"
                                                                               data-penalty="0"
                                                                               data-paid="{{ $subPaid }}"
                                                                               data-balance="{{ $subBalance }}"
                                                                               data-is-consolidated="0"
                                                                               data-member-numbers="{{ $rowMemberNo }}"
                                                                               data-single-amount="{{ $subAmount }}"
                                                                               data-cumulative-amount="{{ $subAmount }}"
                                                                               data-collection-frequency="{{ $memberFreq }}"
                                                                               data-suggested-amount="{{ $period['balance'] }}"
                                                                               data-split-amount="{{ $period['amount'] }}"
                                                                               data-split-count="{{ $freqSplitCount }}"
                                                                               data-force-frequency-partial="1"
                                                                               data-min-percentage="1"
                                                                               data-penalty-method="{{ $partialPaymentConfig['penalty_calculation_method'] ?? 'emi_amount' }}">
                                                                                <i class="ri-money-dollar-circle-line me-1"></i>Pay {{ $period['label'] }}
                                                                            </a>
                                                                        @elseif($period['status'] === 'paid')
                                                                            <span class="badge bg-label-success" style="font-size:.65rem;">Paid</span>
                                                                        @else
                                                                            <span class="text-muted">—</span>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        @empty
                                                            <tr>
                                                                <td colspan="10" class="text-center text-muted py-4">
                                                                    No installments generated for this client in this group yet.
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
                                            @if($isFreqMember)
                                            <button type="button"
                                                    class="btn btn-xs btn-primary chit-inst-bulk-pay-btn"
                                                    data-table-id="inst-table-{{ $detailsId }}"
                                                    disabled>
                                                <i class="icon-base ri ri-money-rupee-circle-line me-1"></i>Pay Selected {{ $schedMemberFreq === 'weekly' ? 'Weekly' : 'Daily' }} Parts
                                            </button>
                                            @endif
                                            <a href="{{ route('chit.installments.show', [$group->id, max(1, $group->current_month)]) }}" class="btn btn-xs btn-outline-info">
                                                <i class="icon-base ri ri-bank-card-line me-1"></i>Collect Group Installments
                                            </a>
                                            @if($rowClientId)
                                                <a href="{{ route('client-view-chits', $rowClientId) }}" class="btn btn-xs btn-outline-primary">
                                                    <i class="icon-base ri ri-user-3-line me-1"></i>Client Profile
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $group->scheme_type === 'group_based' ? 8 : 7 }}" class="text-center py-4 text-muted">
                                    @if($group->is_private)
                                        No members invited yet. Use <strong>Invite</strong> to add members.
                                    @else
                                        No members enrolled yet.
                                    @endif
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Auctions Section (If applicable) --}}
@if($group->supportsAuction())
<div class="row g-3">
    <div class="col-12">
        <div class="card mb-4 shadow-xs">
            <div class="card-header py-3">
                <h5 class="mb-0 text-dark"><i class="icon-base ri ri-auction-line me-2 text-warning"></i>Auctions</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Month</th><th>Date</th><th>Winner</th>
                                <th>Winning Bid</th><th>Status</th><th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($group->auctions as $auction)
                            <tr>
                                <td>Month {{ $auction->month_number }}</td>
                                <td>{{ Carbon\Carbon::parse($auction->auction_date)->format('d M Y') }}</td>
                                <td>{{ $auction->winner?->client->client_name ?? '—' }}</td>
                                <td>{{ $auction->winning_bid ? '₹'.number_format($auction->winning_bid) : '—' }}</td>
                                <td><span class="badge bg-{{ $auction->status_badge }}">{{ ucfirst($auction->status) }}</span></td>
                                <td class="text-center">
                                    <a href="{{ route('chit.auctions.show', $auction) }}" class="btn btn-xs btn-outline-primary">
                                        <i class="icon-base ri ri-eye-line me-1"></i>View
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center py-4 text-muted">No auctions conducted yet</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

</div>{{-- /#group-show-ajax-root --}}

{{-- Edit Group Modal --}}
<div class="modal fade" id="editGroupModal" tabindex="-1" aria-labelledby="editGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editGroupModalLabel">
                    <i class="icon-base ri ri-edit-2-line me-1 text-primary"></i>Edit Group: {{ $group->group_code }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('chit.groups.update', $group) }}">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    @if($errors->any() && old('_modal') === 'edit')
                    <div class="alert alert-danger mb-3">
                        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                @foreach(['forming','active','completed','terminated'] as $s)
                                    <option value="{{ $s }}" {{ old('status', $group->status) === $s ? 'selected' : '' }}>
                                        {{ ucfirst($s) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Referral Commission (%)</label>
                            <input type="number" name="referral_commission_pct" class="form-control"
                                   value="{{ old('referral_commission_pct', $group->referral_commission_pct) }}" step="0.1" min="0" max="100" placeholder="Defaults to Scheme setting / global default">
                        </div>

                        @if($group->scheme_type === 'group_based')
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="icon-base ri ri-star-line text-warning me-1"></i>Group Leader
                            </label>
                            <select name="group_leader_id" class="form-select">
                                <option value="">— No Leader —</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}"
                                        {{ old('group_leader_id', $group->group_leader_id) == $c->id ? 'selected' : '' }}>
                                        {{ $c->client_name }} ({{ $c->client_phone }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @else
                        <input type="hidden" name="group_leader_id" value="{{ $group->group_leader_id }}">
                        @endif

                        <div class="col-12">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="3">{{ old('remarks', $group->remarks) }}</textarea>
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-0" style="font-size:.83rem;">
                                <i class="icon-base ri ri-information-line me-1"></i>
                                Scheme type, chit value, and installment settings cannot be changed after creation.
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="_modal" value="edit">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="icon-base ri ri-save-line me-1"></i>Update Group
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Add Member Modal --}}
@if(!$groupIsFull)
<div class="modal fade" id="addMemberModal" tabindex="-1" aria-labelledby="addMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMemberModalLabel">
                    <i class="icon-base ri ri-user-add-line me-1 text-primary"></i>
                    @if($group->is_private) Invite Member @else Add Member @endif
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('chit.members.store') }}">
                @csrf
                <input type="hidden" name="group_id" value="{{ $group->id }}">
                <div class="modal-body">
                    @if($errors->any() && old('_modal') === 'add_member')
                    <div class="alert alert-danger mb-3">
                        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Group</label>
                        <input type="text" class="form-control" value="{{ $group->group_code }} — {{ $group->seatsFillLabel() }} seats · {{ $group->valid_members_count }} {{ \Illuminate\Support\Str::plural('member', $group->valid_members_count) }} · Chit ₹{{ number_format($group->chit_value) }}" readonly>
                    </div>

                    @include('admin.chit.members.partials.shared-membership-fields', [
                        'clients' => $availableClients->isNotEmpty() ? $availableClients : $clients,
                        'group' => $group,
                        'prefix' => 'groupAdd',
                    ])

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Agent</label>
                            <select name="agent_id" class="form-select">
                                <option value="">Select Agent...</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->agent_name }} ({{ $agent->agent_code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Referred By</label>
                            <select name="referred_by" class="form-select">
                                <option value="">Select Referrer...</option>
                                <optgroup label="Agents">
                                    @foreach($agents as $agent)
                                        <option value="agent:{{ $agent->id }}">{{ $agent->agent_name }} ({{ $agent->agent_code }})</option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Clients">
                                    @foreach($clients as $client)
                                        <option value="client:{{ $client->id }}">{{ $client->client_name }} ({{ $client->client_phone }})</option>
                                    @endforeach
                                </optgroup>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="joined_date" value="{{ date('Y-m-d') }}">
                    <input type="hidden" name="_modal" value="add_member">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" @if($availableClients->isEmpty() && $clients->isEmpty()) disabled @endif>
                        <i class="icon-base ri ri-user-add-line me-1"></i>
                        @if($group->is_private) Invite @else Enroll @endif
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@include('admin.chit.installments.partials.collect-modals')
<script>
  window.chitBulkCollectUrl = @json(route('chit.installments.bulk-collect'));

  // Instant EMI count + enable Pay Selected (works even before Vite bundle refresh).
  (function () {
    function money(v) {
      return '₹' + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function syncSummaryForTable(table) {
      if (!table || !table.id) return;
      let count = 0;
      let sum = 0;
      table.querySelectorAll('.chit-bulk-cb[data-is-freq="1"]:checked').forEach(function (cb) {
        var row = cb.closest('tr');
        if (row && row.style.display === 'none') return;
        count += 1;
        sum += parseFloat(cb.getAttribute('data-period-amount') || cb.getAttribute('data-suggested') || '0') || 0;
      });
      document.querySelectorAll('.chit-inst-bulk-summary[data-table-id="' + table.id + '"]').forEach(function (summary) {
        var c = summary.querySelector('.chit-inst-bulk-count');
        var s = summary.querySelector('.chit-inst-bulk-sum');
        if (c) c.textContent = String(count);
        if (s) s.textContent = money(sum);
        summary.querySelectorAll('.chit-inst-bulk-pay-btn, .chit-inst-bulk-clear-btn').forEach(function (btn) {
          btn.disabled = count === 0;
        });
      });
      document.querySelectorAll('.chit-inst-bulk-pay-btn[data-table-id="' + table.id + '"]').forEach(function (btn) {
        btn.disabled = count === 0;
      });
    }
    document.addEventListener('change', function (e) {
      var t = e.target;
      if (!t) return;
      if (t.classList && (t.classList.contains('chit-bulk-cb') || t.classList.contains('chit-bulk-select-all'))) {
        var table = t.closest('table');
        if (!table && t.classList.contains('chit-bulk-select-all')) {
          var tid = t.getAttribute('data-table-id');
          table = tid ? document.getElementById(tid) : null;
        }
        if (table) {
          // Let other handlers run first (order auto-select), then recount.
          setTimeout(function () { syncSummaryForTable(table); }, 0);
        }
      }
    });
  })();
</script>
@vite([
    'resources/assets/custom-js/chit-group-view.js',
    'resources/assets/custom-js/bank-payment-fields.js',
    'resources/assets/custom-js/chit-installments.js'
])

@else
{{-- DEFAULT INDEX LIST — aligned with Loan Accounts UI --}}
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="mb-1">
            @if(isset($mode) && $mode === 'trash')
                <i class="icon-base ri ri-delete-bin-line text-danger me-1"></i>Recycle Bin
            @else
                Chit Groups
            @endif
        </h4>
        <p class="text-muted mb-0">
            @if(isset($mode) && $mode === 'trash')
                Restore or permanently delete soft-deleted chit groups
            @else
                Manage active and forming chit groups
            @endif
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if(isset($mode) && $mode === 'trash')
            <a href="{{ route('chit.groups.index') }}" class="btn btn-label-secondary d-inline-flex align-items-center">
                <i class="icon-base ri ri-arrow-left-line me-1"></i>Back to Groups
            </a>
        @else
            <a href="{{ route('chit.groups.index', ['trash' => 'true']) }}" class="btn btn-label-danger d-inline-flex align-items-center" title="Recycle Bin">
                <i class="icon-base ri ri-delete-bin-line me-1"></i>Recycle Bin
                <!-- @if(!empty($trashedGroupsCount) && $trashedGroupsCount > 0)
                    <span class="badge bg-danger ms-1.5">{{ $trashedGroupsCount }}</span>
                @endif -->
            </a>
            <a href="{{ route('chit.groups.create') }}" class="btn btn-primary d-inline-flex align-items-center">
                <i class="icon-base ri ri-add-line me-1"></i>New Group
            </a>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header border-bottom d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
        <h5 class="mb-0">
            @if(isset($mode) && $mode === 'trash')
                Deleted Groups
            @else
                All Groups
            @endif
            <span class="badge bg-label-primary ms-2">{{ $groups->total() }}</span>
        </h5>
        <div class="d-flex flex-column align-items-stretch align-items-md-end gap-2">
        <form method="GET" class="d-flex flex-wrap align-items-center gap-3 ajax-filter-form">
            @if(isset($mode) && $mode === 'trash')
                <input type="hidden" name="trash" value="true">
            @endif
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-nowrap fw-medium">Search:</label>
                <input type="text" name="search" class="form-control form-control-sm" style="min-width: 160px;"
                       placeholder="Group code / member…" value="{{ request('search') }}">
            </div>
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-nowrap fw-medium">Member:</label>
                <select name="client_id" id="chitGroupMemberFilter" class="form-select form-select-sm select2" style="min-width: 220px;" data-placeholder="All members">
                    <option value="">All Members</option>
                    @foreach(($filterClients ?? collect()) as $fc)
                        <option value="{{ $fc->id }}" @selected((string) request('client_id') === (string) $fc->id)>
                            {{ $fc->client_name }}@if($fc->client_phone) ({{ $fc->client_phone }})@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-nowrap fw-medium">Type:</label>
                <select name="scheme_type" class="form-select form-select-sm" style="min-width: 140px;">
                    <option value="">All Types</option>
                    @foreach(\App\Models\ChitScheme::schemeTypes() as $val => $label)
                        <option value="{{ $val }}" {{ request('scheme_type') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-nowrap fw-medium">Status:</label>
                <select name="status" class="form-select form-select-sm" style="width: 140px;">
                    <option value="">All Statuses</option>
                    @foreach(['forming','active','completed','terminated'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="icon-base ri ri-filter-3-line me-1"></i>Filter
                </button>
                <a href="{{ route('chit.groups.index', isset($mode) && $mode === 'trash' ? ['trash' => 'true'] : []) }}" class="btn btn-sm btn-label-secondary">
                    <i class="icon-base ri ri-refresh-line me-1"></i>Reset
                </a>
            </div>
        </form>
        @if(!empty($selectedClient))
            <span class="badge bg-label-info align-self-start">
                <i class="icon-base ri ri-user-line me-1"></i>
                Showing groups for: <strong>{{ $selectedClient->client_name }}</strong>
                @if($selectedClient->client_phone) ({{ $selectedClient->client_phone }}) @endif
            </span>
        @endif
        </div>
    </div>
    @php
        $currentSort = request('sort', $sortField ?? 'id');
        $currentDir  = request('direction', $sortDir ?? 'desc');

        $sortUrl = function($field) use ($currentSort, $currentDir) {
            $nextDir = ($currentSort === $field && $currentDir === 'asc') ? 'desc' : 'asc';
            return request()->fullUrlWithQuery(['sort' => $field, 'direction' => $nextDir]);
        };

        $sortIcon = function($field) use ($currentSort, $currentDir) {
            if ($currentSort === $field) {
                return $currentDir === 'asc'
                    ? '<i class="icon-base ri ri-arrow-up-s-line text-primary ms-1"></i>'
                    : '<i class="icon-base ri ri-arrow-down-s-line text-primary ms-1"></i>';
            }
            return '<i class="icon-base ri ri-arrow-up-down-line text-muted opacity-50 ms-1"></i>';
        };
    @endphp
    <div class="card-datatable table-responsive ajax-table-container">
        <table class="table text-nowrap mb-0">
            <thead>
                <tr>
                    <th><a href="{{ $sortUrl('group_code') }}" class="text-body text-decoration-none d-inline-flex align-items-center">Group Code {!! $sortIcon('group_code') !!}</a></th>
                    <th><a href="{{ $sortUrl('scheme') }}" class="text-body text-decoration-none d-inline-flex align-items-center">Scheme {!! $sortIcon('scheme') !!}</a></th>
                    <th><a href="{{ $sortUrl('scheme_type') }}" class="text-body text-decoration-none d-inline-flex align-items-center">Type {!! $sortIcon('scheme_type') !!}</a></th>
                    <th><a href="{{ $sortUrl('chit_value') }}" class="text-body text-decoration-none d-inline-flex align-items-center">Chit Value {!! $sortIcon('chit_value') !!}</a></th>
                    <th><a href="{{ $sortUrl('total_members') }}" class="text-body text-decoration-none d-inline-flex align-items-center">Members {!! $sortIcon('total_members') !!}</a></th>
                    <th><a href="{{ $sortUrl('installment_frequency') }}" class="text-body text-decoration-none d-inline-flex align-items-center">Frequency {!! $sortIcon('installment_frequency') !!}</a></th>
                    <th><a href="{{ $sortUrl('start_date') }}" class="text-body text-decoration-none d-inline-flex align-items-center">Start Date {!! $sortIcon('start_date') !!}</a></th>
                    <th><a href="{{ $sortUrl('end_date') }}" class="text-body text-decoration-none d-inline-flex align-items-center">End Date {!! $sortIcon('end_date') !!}</a></th>
                    <th><a href="{{ $sortUrl('current_month') }}" class="text-body text-decoration-none d-inline-flex align-items-center">Month {!! $sortIcon('current_month') !!}</a></th>
                    <th><a href="{{ $sortUrl('status') }}" class="text-body text-decoration-none d-inline-flex align-items-center">Status {!! $sortIcon('status') !!}</a></th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($groups as $group)
                <tr>
                    <td>
                        @if(isset($mode) && $mode === 'trash')
                            <span class="fw-semibold text-heading">{{ $group->group_code }}</span>
                        @else
                            <a href="{{ route('chit.groups.show', $group) }}" class="fw-semibold text-primary text-decoration-none">{{ $group->group_code }}</a>
                        @endif
                        @if($group->is_private)
                            <span class="ms-1 badge bg-label-dark" style="font-size:.6rem;" title="Private group">
                                <i class="icon-base ri ri-lock-line"></i>
                            </span>
                        @endif
                    </td>
                    <td>{{ $group->scheme->name ?? '—' }}</td>
                    <td>
                        <span class="badge bg-label-{{ $group->scheme_type_badge_color }}">
                            {{ $group->scheme_type_label }}
                        </span>
                    </td>
                    <td>₹{{ number_format($group->chit_value) }}</td>
                    <td>
                        <div class="progress progress-thin mb-1" style="height:5px;">
                            @php $pct = $group->seatsFillPercent(); @endphp
                            <div class="progress-bar bg-primary" style="width:{{ $pct }}%"></div>
                        </div>
                        <small>{{ $group->seatsFillLabel() }} seats</small>
                        @if($group->hasMultiSeatMembers())
                            <small class="text-muted d-block">{{ $group->valid_members_count }} {{ \Illuminate\Support\Str::plural('member', $group->valid_members_count) }}</small>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-label-secondary">
                            {{ ucfirst($group->installment_frequency ?? 'monthly') }}
                        </span>
                    </td>
                    <td>
                        {{ $group->start_date->format('d M Y') }}
                        <small class="text-muted d-block">{{ $group->start_month_label }}</small>
                    </td>
                    <td>
                        {{ $group->end_date ? $group->end_date->format('d M Y') : '—' }}
                        @if($group->end_date)
                            <small class="text-muted d-block">{{ $group->end_month_label }}</small>
                        @endif
                    </td>
                    <td>{{ $group->current_month }}/{{ $group->total_months }}</td>
                    <td><span class="badge bg-label-{{ $group->status_badge }}">{{ ucfirst($group->status) }}</span></td>
                    <td>
                        <div class="d-flex align-items-center">
                            @if(isset($mode) && $mode === 'trash')
                                <form action="{{ route('chit.groups.restore', $group->id) }}" method="POST" class="d-inline restore-group-form">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-icon btn-text-success rounded-pill btn-restore-group" title="Restore Group">
                                        <i class="icon-base ri ri-refresh-line icon-22px"></i>
                                    </button>
                                </form>
                                <form action="{{ route('chit.groups.force-delete', $group->id) }}" method="POST" class="d-inline force-delete-group-form">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill btn-force-delete-group" title="Delete Permanently">
                                        <i class="icon-base ri ri-delete-bin-line icon-22px"></i>
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('chit.groups.show', $group) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="View">
                                    <i class="icon-base ri ri-eye-line icon-22px"></i>
                                </a>
                                @if(in_array($group->status, ['completed', 'terminated', 'closed']))
                                <form action="{{ route('chit.groups.reopen', $group) }}" method="POST" class="d-inline reopen-group-form" onsubmit="return confirm('Are you sure you want to reopen this group?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-icon btn-text-success rounded-pill btn-reopen-group-list" title="Reopen Group">
                                        <i class="icon-base ri ri-arrow-go-back-line icon-22px"></i>
                                    </button>
                                </form>
                                @endif
                                <form action="{{ route('chit.groups.destroy', $group) }}" method="POST" class="d-inline delete-group-form">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill btn-delete-group" title="Move to Recycle Bin">
                                        <i class="icon-base ri ri-delete-bin-line icon-22px"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" class="text-center py-5 text-muted">
                        <i class="icon-base ri ri-pages-line icon-32px d-block mb-2"></i>
                        No groups found
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($groups->hasPages())
        <div class="card-footer">{{ $groups->links() }}</div>
    @endif
</div>
@endif
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/chit-shared-membership.js', 'resources/assets/custom-js/chit-need-month.js'])
<script>
window.confirmChitUndoPayment = function (formId) {
  var form = document.getElementById(formId);
  if (!form) return;

  var doSubmit = function () {
    if (typeof window.reloadChitGroupView === 'function' && document.getElementById('group-show-ajax-root')) {
      form.classList.add('group-ajax-form');
      // Trigger delegated AJAX submit handler
      var evt = new Event('submit', { bubbles: true, cancelable: true });
      form.dispatchEvent(evt);
      return;
    }
    form.submit();
  };

  if (typeof Swal !== 'undefined') {
    Swal.fire({
      title: 'Undo this payment?',
      text: 'This will reverse the payment and restore the installment balance.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, undo payment',
      cancelButtonText: 'Cancel',
      customClass: {
        confirmButton: 'btn btn-danger me-2',
        cancelButton: 'btn btn-label-secondary'
      },
      buttonsStyling: false
    }).then(function (result) {
      if (result.isConfirmed) doSubmit();
    });
  } else if (confirm('Are you sure you want to undo this payment?')) {
    doSubmit();
  }
};
</script>
@if(isset($mode) && $mode === 'create')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const schemeSelect = document.getElementById('schemeSelect');
    const schemePreview = document.getElementById('schemePreview');
    const auctionTypeRow = document.getElementById('auctionTypeRow');
    const groupLeaderRow = document.getElementById('groupLeaderRow');

    const prevTypeBadge = document.getElementById('prev-type-badge');
    const prevPrivateBadge = document.getElementById('prev-private-badge');
    const prevValue = document.getElementById('prev-value');
    const prevInstall = document.getElementById('prev-install');
    const prevMembers = document.getElementById('prev-members');

    function updatePreview() {
        const opt = schemeSelect.options[schemeSelect.selectedIndex];
        if (!opt || !opt.value) {
            schemePreview.style.display = 'none';
            auctionTypeRow.style.display = 'block';
            groupLeaderRow.style.display = 'none';
            return;
        }

        const type = opt.getAttribute('data-type');
        const label = opt.getAttribute('data-type-label');
        const color = opt.getAttribute('data-type-color');
        const freq = opt.getAttribute('data-frequency');
        const isPrivate = opt.getAttribute('data-private');

        prevTypeBadge.innerText = label;
        prevTypeBadge.className = 'badge bg-' + color;

        if (isPrivate === '1') {
            prevPrivateBadge.style.display = 'inline-block';
        } else {
            prevPrivateBadge.style.display = 'none';
        }

        prevValue.innerText = '₹' + parseFloat(opt.getAttribute('data-value')).toLocaleString('en-IN');
        prevInstall.innerText = '₹' + parseFloat(opt.getAttribute('data-installment')).toLocaleString('en-IN');
        prevMembers.innerText = opt.getAttribute('data-members') + ' / ' + freq;

        schemePreview.style.display = 'block';

        const showAuction = (type === 'auction' || type === 'flexible');
        auctionTypeRow.style.display = showAuction ? 'block' : 'none';
        groupLeaderRow.style.display = (type === 'group_based') ? 'block' : 'none';
    }

    schemeSelect.addEventListener('change', updatePreview);

    if (schemeSelect.value) {
        updatePreview();
    }
});
</script>
@endif

@if(isset($mode) && $mode === 'show')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Activate / close / cancel-termination are handled by chit-group-view.js (AJAX).
        // Keep only modal helpers + EMI toggle bind for first paint.

        @if($errors->any() && old('_modal') === 'edit')
        const editModal = document.getElementById('editGroupModal');
        if (editModal) new bootstrap.Modal(editModal).show();
        @endif

        @if($errors->any() && old('_modal') === 'add_member')
        const addModal = document.getElementById('addMemberModal');
        if (addModal) new bootstrap.Modal(addModal).show();
        @endif

        if (typeof window.initSearchableSelects === 'function') {
            document.getElementById('editGroupModal')?.addEventListener('shown.bs.modal', function (e) {
                window.initSearchableSelects(e.target);
            });
            document.getElementById('addMemberModal')?.addEventListener('shown.bs.modal', function (e) {
                window.initSearchableSelects(e.target);
            });
        }

        document.querySelectorAll('.member-emi-toggle').forEach(function (btn) {
            const targetSelector = btn.getAttribute('data-bs-target');
            const panel = targetSelector ? document.querySelector(targetSelector) : null;
            if (!panel) return;

            panel.addEventListener('show.bs.collapse', function () {
                btn.setAttribute('aria-expanded', 'true');
                btn.querySelector('i')?.classList.replace('ri-arrow-down-s-line', 'ri-arrow-up-s-line');
            });
            panel.addEventListener('hide.bs.collapse', function () {
                btn.setAttribute('aria-expanded', 'false');
                btn.querySelector('i')?.classList.replace('ri-arrow-up-s-line', 'ri-arrow-down-s-line');
            });
        });
    });
</script>
@endif

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Delete Confirmation (Soft Delete) — also handle list close forms with progress-aware confirm
        document.querySelectorAll('.close-group-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Close Chit Group?',
                    html: "Close this active group now? If settlements are incomplete it will be <strong>terminated</strong>; otherwise it will be <strong>completed</strong>.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#7367f0',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Close Group'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });

        // Delete Confirmation (Soft Delete)
        const deleteForms = document.querySelectorAll('.delete-group-form');
        deleteForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Delete Group?',
                    text: "This will soft-delete the group and move it to the Recycle Bin. Related installments and records are kept and can be restored later.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ff3e1d',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });

        // Restore Confirmation
        const restoreForms = document.querySelectorAll('.restore-group-form');
        restoreForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Restore Group?',
                    text: "Are you sure you want to restore this Chit group to the active lists?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#03C95A',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, restore it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });

        // Force Delete Confirmation
        const forceDeleteForms = document.querySelectorAll('.force-delete-group-form');
        forceDeleteForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Permanently Delete Group?',
                    text: "Are you sure you want to permanently delete this Chit group? This action CANNOT be undone and will delete all references from the database.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ff3e1d',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, permanently delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });

        // Rotation Month + Search filter (delegated — survives AJAX group-view reload)
        function applyRotationFilters() {
            const filterEl = document.getElementById('rotationMonthFilter');
            const searchEl = document.getElementById('rotationMemberSearch');
            const selectedMonth = filterEl ? filterEl.value : 'all';
            const needle = searchEl ? String(searchEl.value || '').trim().toLowerCase() : '';

            document.querySelectorAll('.rotation-row').forEach(function (row) {
                const rowMonth = row.getAttribute('data-settlement-month');
                const haystack = row.getAttribute('data-member-search') || '';
                const monthOk = selectedMonth === 'all' || String(rowMonth) === String(selectedMonth);
                const searchOk = !needle || haystack.indexOf(needle) !== -1;
                const show = monthOk && searchOk;

                row.style.display = show ? '' : 'none';
                const details = row.nextElementSibling;
                if (details && details.classList.contains('member-details-row')) {
                    details.style.display = show ? '' : 'none';
                    if (!show) {
                        const open = details.querySelector('.collapse.show');
                        if (open && typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                            const inst = bootstrap.Collapse.getInstance(open);
                            if (inst) inst.hide();
                        }
                    }
                }
            });
        }

        document.addEventListener('change', function (e) {
            if (e.target && e.target.id === 'rotationMonthFilter') {
                applyRotationFilters();
            }
        });
        document.addEventListener('input', function (e) {
            if (e.target && e.target.id === 'rotationMemberSearch') {
                applyRotationFilters();
            }
        });
        applyRotationFilters();

        // Member collapse installment month filter (Daily/Weekly by Month)
        function applyMemberInstFilter(select) {
            if (!select) return;
            const tableId = select.getAttribute('data-table-id');
            let table = tableId ? document.getElementById(tableId) : null;
            if (!table) {
                table = select.closest('.card')?.querySelector('table[id^="inst-table-"]')
                    || select.closest('.collapse')?.querySelector('table[id^="inst-table-"]');
            }
            if (!table) return;

            const filterVal = String(select.value || 'all');
            const rows = Array.from(table.querySelectorAll('tbody tr.inst-row'));

            rows.forEach(function (row) {
                if (row.classList.contains('inst-freq-row')) return;

                const st = row.getAttribute('data-status') || '';
                const month = row.getAttribute('data-month') || '';
                let showParent = true;

                if (filterVal === 'all') {
                    showParent = true;
                } else if (filterVal === 'unpaid') {
                    showParent = st === 'pending' || st === 'overdue' || st === 'partial';
                } else if (filterVal === 'paid') {
                    showParent = st === 'paid';
                } else if (filterVal.indexOf('month-') === 0) {
                    showParent = month === filterVal;
                }

                row.style.display = showParent ? '' : 'none';

                let next = row.nextElementSibling;
                while (next && next.classList.contains('inst-freq-row')) {
                    next.style.display = showParent ? '' : 'none';
                    next = next.nextElementSibling;
                }
            });
        }

        document.addEventListener('change', function (e) {
            const select = e.target && e.target.closest
                ? e.target.closest('select.member-inst-filter')
                : (e.target && e.target.classList && e.target.classList.contains('member-inst-filter') ? e.target : null);
            if (select) applyMemberInstFilter(select);
        });

        function refreshMemberInstFilters(scope) {
            (scope || document).querySelectorAll('select.member-inst-filter').forEach(applyMemberInstFilter);
        }

        refreshMemberInstFilters();

        document.addEventListener('shown.bs.collapse', function (e) {
            const panel = e.target;
            if (!panel || !panel.querySelector) return;
            const filter = panel.querySelector('select.member-inst-filter');
            if (filter) applyMemberInstFilter(filter);
        });

        document.addEventListener('chit-group-view:reloaded', function () {
            try {
                const raw = sessionStorage.getItem('chitInstFilterState');
                if (raw) {
                    const filters = JSON.parse(raw);
                    sessionStorage.removeItem('chitInstFilterState');
                    document.querySelectorAll('select.member-inst-filter').forEach(function (sel, idx) {
                        const key = sel.getAttribute('data-table-id') || ('idx-' + idx);
                        if (filters[key] && Array.from(sel.options).some(function (o) { return o.value === filters[key]; })) {
                            sel.value = filters[key];
                        }
                    });
                }
            } catch (e) { /* ignore */ }
            refreshMemberInstFilters();
            applyRotationFilters();

            document.querySelectorAll('.member-emi-toggle').forEach(function (btn) {
                const targetSelector = btn.getAttribute('data-bs-target');
                const panel = targetSelector ? document.querySelector(targetSelector) : null;
                if (!panel || panel.dataset.emiToggleBound) return;
                panel.dataset.emiToggleBound = '1';
                panel.addEventListener('show.bs.collapse', function () {
                    btn.setAttribute('aria-expanded', 'true');
                    btn.querySelector('i')?.classList.replace('ri-arrow-down-s-line', 'ri-arrow-up-s-line');
                });
                panel.addEventListener('hide.bs.collapse', function () {
                    btn.setAttribute('aria-expanded', 'false');
                    btn.querySelector('i')?.classList.replace('ri-arrow-up-s-line', 'ri-arrow-down-s-line');
                });
            });
        });
    });
</script>
@endsection
