@extends('layouts/layoutMaster')
@section('title', isset($mode) && $mode === 'show' ? $scheme->name : (isset($mode) && $mode === 'create' ? 'Create Chit Scheme' : (isset($mode) && $mode === 'edit' ? 'Edit Chit Scheme' : (isset($mode) && $mode === 'trash' ? 'Scheme Recycle Bin' : 'Chit Schemes'))))

@section('content')
@if(isset($mode) && $mode === 'create')
{{-- CREATE SCHEME FORM --}}
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="ri-file-text-line text-primary ri-xl"></i>
                <h5 class="mb-0">New Chit Scheme</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('chit.schemes.store') }}" id="schemeForm" enctype="multipart/form-data">
                    @csrf

                    @if($errors->any())
                    <div class="alert alert-danger mb-3">
                        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                    @endif

                    <div class="row g-3">
                        {{-- Scheme Name --}}
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Scheme Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required
                                   placeholder="e.g. 1 Lakh Monthly Gold Chit">
                        </div>

                        {{-- Scheme Type --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Scheme Type <span class="text-danger">*</span></label>
                            <select name="scheme_type" id="schemeType" class="form-select" required>
                                @foreach($schemeTypes as $val => $label)
                                    @if(!in_array($val, ['flexible', 'fixed_return', 'daily_weekly']))
                                        <option value="{{ $val }}" {{ old('scheme_type', 'fixed') === $val ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        {{-- Type Description Card (dynamic) --}}
                        <div class="col-12">
                            <div id="typeHint" class="alert alert-info py-2 mb-0" style="font-size:.85rem;"></div>
                        </div>

                        {{-- Chit Value --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Chit Value (₹) <span class="text-danger">*</span></label>
                            <input type="number" name="chit_value" id="chitValue" class="form-control"
                                   value="{{ old('chit_value') }}" required min="1000" step="1000">
                        </div>

                        {{-- Members --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Number of Members <span class="text-danger">*</span></label>
                            <input type="number" name="total_members" id="totalMembers" class="form-control"
                                   value="{{ old('total_members') }}" required min="2" max="100">
                        </div>

                        {{-- Commission --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Commission (%)</label>
                            <input type="number" name="commission_pct" id="commissionPct" class="form-control"
                                   value="{{ old('commission_pct', 5) }}" step="0.5" min="0" max="30">
                        </div>

                        <!-- {{-- Referral Commission --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Referral Commission (%)</label>
                            <input type="number" name="referral_commission_pct" id="referralCommissionPct" class="form-control"
                                   value="{{ old('referral_commission_pct') }}" step="0.1" min="0" max="100" placeholder="Global default">
                        </div> -->

                        {{-- Installment Frequency --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Installment Frequency</label>
                            <select name="installment_frequency" id="installmentFrequency" class="form-select">
                                @foreach($frequencies as $val => $label)
                                    <option value="{{ $val }}" {{ old('installment_frequency', 'monthly') === $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text text-muted" id="freqHint">Monthly = standard chit</div>
                        </div>

                        {{-- Auction Type: only for Auction-Based schemes --}}
                        <div class="col-md-4" id="auctionTypeRow" style="display:none;">
                            <label class="form-label fw-semibold">Auction Type <span class="text-danger">*</span></label>
                            <select name="auction_type" id="auctionType" class="form-select">
                                <option value="open" {{ old('auction_type', 'open') === 'open' ? 'selected' : '' }}>Open Bid</option>
                                <option value="closed" {{ old('auction_type') === 'closed' ? 'selected' : '' }}>Closed Bid</option> 
                            </select>
                            <div class="form-text text-muted">Shown only for Auction-Based Chit schemes.</div>
                        </div>

                        {{-- Fixed Return Amount (shown only for fixed_return) --}}
                        <div class="col-md-4" id="fixedReturnRow" style="display:none;">
                            <label class="form-label fw-semibold">Fixed Return Amount (₹)</label>
                            <input type="number" name="fixed_return_amount" id="fixedReturnAmount" class="form-control"
                                   value="{{ old('fixed_return_amount') }}" min="0" step="100"
                                   placeholder="Amount each member receives">
                        </div>

                        {{-- Scheme installment is taken from the payout schedule (first filled month). --}}
                        <input type="hidden" name="installment_amount" id="installmentAmount" value="{{ old('installment_amount') }}">

                        <div class="col-md-4">
                            <label class="form-label fw-semibold js-duration-label">Duration (Months) <span class="text-danger">*</span></label>
                            <input type="number" name="duration_months" id="durationMonths" class="form-control"
                                   value="{{ old('duration_months') }}" required min="1" max="120" placeholder="e.g. 20">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold js-foreman-label">Foreman Commission Month <span class="text-danger">*</span></label>
                            <input type="number" name="foreman_commission_month" id="foremanCommissionMonth" class="form-control"
                                   value="{{ old('foreman_commission_month', 1) }}" required min="0" max="120">
                            <div class="form-text text-muted" id="foremanCommissionHint">
                                Enter <strong>0</strong> to collect a one-time client-wise amount instead of taking a full month’s collection as commission.
                            </div>
                        </div>

                        <div class="col-12" id="clientWiseForemanFields" style="display:none;">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Client Wise Foreman Commission (₹) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" name="client_wise_foreman_commission" id="clientWiseForemanCommission" class="form-control"
                                               value="{{ old('client_wise_foreman_commission') }}" min="0.01" step="0.01" placeholder="e.g. 1000">
                                    </div>
                                    <div class="form-text text-muted">Collected once from each member added to the group. Installment stays the same.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold js-client-wise-month-label">Client Wise Collection Month <span class="text-danger">*</span></label>
                                    <input type="number" name="client_wise_foreman_collection_month" id="clientWiseForemanCollectionMonth" class="form-control"
                                           value="{{ old('client_wise_foreman_collection_month') }}" min="1" max="120" placeholder="e.g. 1">
                                    <div class="form-text text-muted js-client-wise-month-hint">This amount is added to that month’s due, one time only.</div>
                                </div>
                            </div>
                        </div>

                        {{-- Post-Payout Monthly Installment Adjustment (Optional) --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Post-Payout Monthly Installment Adjustment (Optional)</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0" name="post_payout_installment_adjustment" id="postPayoutInstallmentAdjustment" class="form-control"
                                       value="{{ old('post_payout_installment_adjustment') }}" placeholder="e.g. 1000 or 6000">
                            </div>
                            <div class="form-text text-muted">Increase or update monthly installment for this payout client starting next month onwards until chit closure. Entered amount is applied after settlement payout.</div>
                        </div>

                        {{-- Private Group Toggle (shown for group_based) --}}
                        <div class="col-md-6" id="privateRow" style="display:none;">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="is_private" id="isPrivate"
                                       value="1" {{ old('is_private') ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="isPrivate">
                                    <i class="ri-lock-line me-1"></i> Private Group (Invite Only)
                                </label>
                            </div>
                            <div class="form-text text-muted">Members can only join through direct invitation.</div>
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

                        <!-- Payout Schedule Section -->
                        <div class="col-12 mt-4" id="payoutScheduleSection">
                            <div class="card border">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
                                    <h6 class="mb-0 fw-semibold js-payout-title"><i class="ri-table-line me-1 text-primary"></i>Payout Schedule (Month-wise)</h6>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <small class="text-muted">Rows follow duration — enter installment &amp; payout manually</small>
                                        <div class="btn-group">
                                            <button type="button" id="formDownloadTemplate" class="btn btn-sm btn-outline-secondary">
                                                <i class="ri-file-pdf-2-line me-1"></i>Download Template (PDF)
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split"
                                                    data-bs-toggle="dropdown" aria-expanded="false">
                                                <span class="visually-hidden">More formats</span>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0);" id="formDownloadCSV">
                                                        <i class="ri-file-excel-2-line me-2"></i>Download as CSV
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 12%;">No. <span class="text-muted fw-normal js-payout-col-unit">(Month)</span></th>
                                                    <th>Installment Amount (₹)</th>
                                                    <th>Payout / Disbursement (₹)</th>
                                                </tr>
                                            </thead>
                                            <tbody id="payoutScheduleTableBody">
                                                <!-- Dynamically filled -->
                                            </tbody>
                                            <tfoot class="table-light text-center">
                                                <tr class="fw-bold">
                                                    <td>Total</td>
                                                    <td id="tfootTotalInstallment">₹0.00</td>
                                                    <td id="tfootTotalPayout">₹0.00</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Description --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="2"
                                      placeholder="Optional scheme details…">{{ old('description') }}</textarea>
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-save-line me-1"></i>Create Scheme
                            </button>
                            <a href="{{ route('chit.schemes.index') }}" class="btn btn-label-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@elseif(isset($mode) && $mode === 'edit')
{{-- EDIT SCHEME FORM --}}
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="ri-edit-2-line text-primary ri-xl"></i>
                    <h5 class="mb-0">Edit: {{ $scheme->name }}</h5>
                </div>
                <a href="{{ route('chit.schemes.index') }}" class="btn btn-sm btn-label-secondary">Back</a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('chit.schemes.update', $scheme) }}" id="schemeForm" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    @if($errors->any())
                    <div class="alert alert-danger mb-3">
                        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Scheme Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   value="{{ old('name', $scheme->name) }}" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Scheme Type <span class="text-danger">*</span></label>
                            <select name="scheme_type" id="schemeType" class="form-select" required>
                                @foreach($schemeTypes as $val => $label)
                                    @if(!in_array($val, ['flexible', 'fixed_return', 'daily_weekly']))
                                        <option value="{{ $val }}" {{ old('scheme_type', $scheme->scheme_type) === $val ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <div id="typeHint" class="alert alert-info py-2 mb-0" style="font-size:.85rem;"></div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Chit Value (₹) <span class="text-danger">*</span></label>
                            <input type="number" name="chit_value" id="chitValue" class="form-control"
                                   value="{{ old('chit_value', $scheme->chit_value) }}" required min="1000" step="1000">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Number of Members <span class="text-danger">*</span></label>
                            <input type="number" name="total_members" id="totalMembers" class="form-control"
                                   value="{{ old('total_members', $scheme->total_members) }}" required min="2" max="100">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Commission (%)</label>
                            <input type="number" name="commission_pct" id="commissionPct" class="form-control"
                                   value="{{ old('commission_pct', $scheme->commission_pct) }}" step="0.5" min="0" max="30">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Referral Commission (%)</label>
                            <input type="number" name="referral_commission_pct" id="referralCommissionPct" class="form-control"
                                   value="{{ old('referral_commission_pct', $scheme->referral_commission_pct) }}" step="0.1" min="0" max="100" placeholder="Global default">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Installment Frequency</label>
                            <select name="installment_frequency" id="installmentFrequency" class="form-select">
                                @foreach($frequencies as $val => $label)
                                    <option value="{{ $val }}"
                                        {{ old('installment_frequency', $scheme->installment_frequency ?? 'monthly') === $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                                @if (($scheme->installment_frequency ?? '') === 'daily')
                                    <option value="daily" selected>Daily</option>
                                @endif
                            </select>
                            <div class="form-text text-muted" id="freqHint"></div>
                        </div>

                        <div class="col-md-4" id="auctionTypeRow" style="display:none;">
                            <label class="form-label fw-semibold">Auction Type <span class="text-danger">*</span></label>
                            <select name="auction_type" id="auctionType" class="form-select">
                                <option value="open" {{ old('auction_type', $scheme->auction_type) === 'open' ? 'selected' : '' }}>Open Bid</option>
                                <option value="closed" {{ old('auction_type', $scheme->auction_type) === 'closed' ? 'selected' : '' }}>Closed Bid</option>
                            </select>
                            <div class="form-text text-muted">Shown only for Auction-Based Chit schemes.</div>
                        </div>

                        <div class="col-md-4" id="fixedReturnRow" style="display:none;">
                            <label class="form-label fw-semibold">Fixed Return Amount (₹)</label>
                            <input type="number" name="fixed_return_amount" id="fixedReturnAmount" class="form-control"
                                   value="{{ old('fixed_return_amount', $scheme->fixed_return_amount) }}" min="0" step="100">
                        </div>

                        {{-- Scheme installment is taken from the payout schedule (first filled month). --}}
                        <input type="hidden" name="installment_amount" id="installmentAmount"
                               value="{{ old('installment_amount', $scheme->installment_amount) }}">

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Commission Amount (Auto)</label>
                            <input type="text" id="commissionPreview" class="form-control bg-light" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold js-duration-label">Duration (Months) <span class="text-danger">*</span></label>
                            <input type="number" name="duration_months" id="durationMonths" class="form-control"
                                   value="{{ old('duration_months', $scheme->duration_months) }}" required min="1" max="120">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold js-foreman-label">Foreman Commission Month <span class="text-danger">*</span></label>
                            <input type="number" name="foreman_commission_month" id="foremanCommissionMonth" class="form-control"
                                   value="{{ old('foreman_commission_month', $scheme->foreman_commission_month ?? 1) }}" required min="0" max="120">
                            <div class="form-text text-muted" id="foremanCommissionHint">
                                Clients still pay the normal installment that month; the full collection (installment × members) is company/foreman commission — not a member payout.
                            </div>
                        </div>

                        <div class="col-12" id="clientWiseForemanFields" style="{{ (int) old('foreman_commission_month', $scheme->foreman_commission_month ?? 1) === 0 ? '' : 'display:none;' }}">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Client Wise Foreman Commission (₹) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" name="client_wise_foreman_commission" id="clientWiseForemanCommission" class="form-control"
                                               value="{{ old('client_wise_foreman_commission', $scheme->client_wise_foreman_commission) }}" min="0.01" step="0.01" placeholder="e.g. 1000">
                                    </div>
                                    <div class="form-text text-muted">Collected once from each member added to the group. Installment stays the same.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold js-client-wise-month-label">Client Wise Collection Month <span class="text-danger">*</span></label>
                                    <input type="number" name="client_wise_foreman_collection_month" id="clientWiseForemanCollectionMonth" class="form-control"
                                           value="{{ old('client_wise_foreman_collection_month', $scheme->client_wise_foreman_collection_month) }}" min="1" max="120" placeholder="e.g. 1">
                                    <div class="form-text text-muted js-client-wise-month-hint">This amount is added to that month’s due, one time only.</div>
                                </div>
                            </div>
                        </div>

                        {{-- Post-Payout Monthly Installment Adjustment (Optional) --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Post-Payout Monthly Installment Adjustment (Optional)</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0" name="post_payout_installment_adjustment" id="postPayoutInstallmentAdjustment" class="form-control"
                                       value="{{ old('post_payout_installment_adjustment', $scheme->post_payout_installment_adjustment ?? '') }}" placeholder="e.g. 1000 or 6000">
                            </div>
                            <div class="form-text text-muted">Increase or update monthly installment for this payout client starting next month onwards until chit closure. Entered amount is applied after settlement payout.</div>
                        </div>

                        <div class="col-md-6" id="privateRow" style="display:none;">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="is_private" id="isPrivate"
                                       value="1" {{ old('is_private', $scheme->is_private) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="isPrivate">
                                    <i class="ri-lock-line me-1"></i> Private Group (Invite Only)
                                </label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" {{ old('status', $scheme->status) === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status', $scheme->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Branch</label>
                            <select name="branch_id" class="form-select select2">
                                <option value="">All Branches</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" {{ old('branch_id', $scheme->branch_id) == $b->id ? 'selected' : '' }}>
                                        {{ $b->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Payout Schedule Section -->
                        <div class="col-12 mt-4" id="payoutScheduleSection">
                            <div class="card border">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
                                    <h6 class="mb-0 fw-semibold js-payout-title"><i class="ri-table-line me-1 text-primary"></i>Payout Schedule (Month-wise)</h6>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <small class="text-muted">Rows follow duration — enter installment &amp; payout manually</small>
                                        <div class="btn-group">
                                            <button type="button" id="formDownloadTemplate" class="btn btn-sm btn-outline-secondary">
                                                <i class="ri-file-pdf-2-line me-1"></i>Download Template (PDF)
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split"
                                                    data-bs-toggle="dropdown" aria-expanded="false">
                                                <span class="visually-hidden">More formats</span>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0);" id="formDownloadCSV">
                                                        <i class="ri-file-excel-2-line me-2"></i>Download as CSV
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 12%;">No. <span class="text-muted fw-normal js-payout-col-unit">(Month)</span></th>
                                                    <th>Installment Amount (₹)</th>
                                                    <th>Payout / Disbursement (₹)</th>
                                                </tr>
                                            </thead>
                                            <tbody id="payoutScheduleTableBody">
                                                <!-- Dynamically filled -->
                                            </tbody>
                                            <tfoot class="table-light text-center">
                                                <tr class="fw-bold">
                                                    <td>Total</td>
                                                    <td id="tfootTotalInstallment">₹0.00</td>
                                                    <td id="tfootTotalPayout">₹0.00</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description', $scheme->description) }}</textarea>
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-save-line me-1"></i>Update Scheme
                            </button>
                            <a href="{{ route('chit.schemes.index') }}" class="btn btn-label-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@elseif(isset($mode) && $mode === 'show')
{{-- SHOW SCHEME DETAILS --}}
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="mb-0">{{ $scheme->name }}</h4>
            <span class="badge bg-label-secondary font-monospace">{{ $scheme->scheme_code }}</span>
            <span class="badge bg-{{ $scheme->scheme_type_badge_color }}">{{ $scheme->scheme_type_label }}</span>
            @if($scheme->is_private)
                <span class="badge bg-dark"><i class="ri-lock-line me-1"></i>Private</span>
            @endif
            <span class="badge bg-{{ $scheme->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($scheme->status) }}</span>
        </div>
        <p class="text-muted mb-0">
            Chit plan specifications, associated groups, and month-wise payout schedule.
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('chit.schemes.edit', $scheme) }}" class="btn btn-primary d-inline-flex align-items-center">
            <i class="ri-edit-2-line me-1"></i>Edit Scheme
        </a>
        <a href="{{ route('chit.groups.create', ['scheme_id' => $scheme->id]) }}" class="btn btn-outline-primary d-inline-flex align-items-center">
            <i class="ri-add-line me-1"></i>New Group
        </a>
        <a href="{{ route('chit.schemes.flyer', [$scheme, 'download' => 1]) }}" target="_blank" class="btn btn-outline-info d-inline-flex align-items-center">
            <i class="ri-printer-line me-1"></i>Print Flyer
        </a>
        @if(($scheme->chit_groups_count ?? 0) === 0)
        <form action="{{ route('chit.schemes.destroy', $scheme) }}" method="POST" class="d-inline delete-scheme-form">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger d-inline-flex align-items-center">
                <i class="ri-delete-bin-line me-1"></i>Delete
            </button>
        </form>
        @endif
        <a href="{{ route('chit.schemes.index') }}" class="btn btn-label-secondary d-inline-flex align-items-center">
            <i class="ri-arrow-left-line me-1"></i>Back
        </a>
    </div>
</div>
 
{{-- Metric Cards Strip --}}
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card card-border-shadow-success h-100">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar avatar-md bg-label-success rounded">
                        <i class="ri-money-rupee-circle-line ri-22px"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Chit Value</small>
                        <h5 class="mb-0 fw-bold text-success">₹{{ number_format($scheme->chit_value) }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card card-border-shadow-primary h-100">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar avatar-md bg-label-primary rounded">
                        <i class="ri-wallet-3-line ri-22px"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Monthly Installment</small>
                        <h5 class="mb-0 fw-bold text-primary">₹{{ number_format($scheme->installment_amount) }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card card-border-shadow-info h-100">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar avatar-md bg-label-info rounded">
                        <i class="ri-calendar-event-line ri-22px"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Duration</small>
                        <h5 class="mb-0 fw-bold">{{ $scheme->duration_months }} {{ $scheme->periodUnitLabelPlural() }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card card-border-shadow-warning h-100">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar avatar-md bg-label-warning rounded">
                        <i class="ri-group-line ri-22px"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Total Members</small>
                        <h5 class="mb-0 fw-bold">{{ $scheme->total_members }} Members</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Left Column: Scheme Specifications --}}
    <div class="col-lg-5">
        <div class="card shadow-xs mb-4">
            <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="ri-file-text-line me-2 text-primary"></i>Scheme Specifications</h5>
                <span class="badge bg-label-primary">{{ $scheme->scheme_code }}</span>
            </div>
            <div class="card-body p-0">
                @php
                    $unit = $scheme->periodUnitLabel();
                    $foremanSpec = $scheme->usesClientWiseForemanCommission()
                        ? 'Client-wise ₹'.number_format($scheme->clientWiseForemanAmount(), 2).' · collected once in '.$unit.' '.$scheme->clientWiseForemanCollectionMonth()
                        : (($scheme->foremanCommissionMonth() === 0)
                            ? $unit.' 0 — client-wise (amount not set)'
                            : $unit.' '.$scheme->foremanCommissionMonth().' — '.\App\Models\ChitScheme::FOREMAN_COMMISSION_LABEL);
                    $specifications = [
                        ['Code',          $scheme->scheme_code, 'ri-barcode-line'],
                        ['Scheme Type',   $scheme->scheme_type_label, 'ri-stack-line'],
                        ['Chit Value',    '₹'.number_format($scheme->chit_value), 'ri-money-rupee-circle-line text-success fw-bold'],
                        ['Total Members', $scheme->total_members.' Members', 'ri-group-line'],
                        ['Duration',      $scheme->duration_months.' '.$scheme->periodUnitLabelPlural(), 'ri-calendar-event-line'],
                        ['Foreman Commission '.$unit, $foremanSpec, 'ri-user-star-line text-warning'],
                        ['Installment',   '₹'.number_format($scheme->installment_amount).' / '.ucfirst($scheme->installment_frequency ?? 'monthly'), 'ri-wallet-3-line'],
                        ['Commission',    $scheme->commission_pct.'% (₹'.number_format($scheme->commission_amount).')', 'ri-percent-line'],
                        ['Referral Commission', $scheme->referral_commission_pct ? $scheme->referral_commission_pct.'%' : 'Global Default', 'ri-gift-line'],
                        ['Auction Type',  $scheme->isAuctionBased() ? ucfirst($scheme->auction_type) : 'N/A', 'ri-auction-line'],
                        ['Private Group', $scheme->is_private ? 'Yes (Invite Only)' : 'No (Public)', 'ri-lock-line'],
                        ['Status',        ucfirst($scheme->status), 'ri-checkbox-circle-line'],
                        ['Branch',        $scheme->branch->name ?? 'All Branches', 'ri-building-line'],
                    ];
                    if ($scheme->post_payout_installment_adjustment) {
                        $specifications[] = ['Post-Payout Installment Adjustment', '₹'.number_format($scheme->post_payout_installment_adjustment, 2).' (applied starting next month after payout)', 'ri-edit-box-line text-warning'];
                    }
                    if ($scheme->fixed_return_amount) {
                        $specifications[] = ['Fixed Return Amount', '₹'.number_format($scheme->fixed_return_amount), 'ri-hand-coin-line'];
                    }
                @endphp
                @foreach($specifications as [$k, $v, $icon])
                <div class="d-flex justify-content-between align-items-center px-4 py-2_5 border-bottom">
                    <span class="text-muted d-flex align-items-center gap-2 style="font-size:.85rem;">
                        <i class="{{ $icon }} text-muted"></i> {{ $k }}
                    </span>
                    <span class="fw-medium text-heading style="font-size:.875rem;">
                        @if(str_starts_with($k, 'Foreman Commission'))
                            <span class="badge bg-label-warning px-2 py-1"><i class="ri-award-line me-1"></i>{{ $v }}</span>
                        @elseif($k === 'Status')
                            <span class="badge bg-label-{{ $scheme->status === 'active' ? 'success' : 'secondary' }}">{{ $v }}</span>
                        @else
                            {{ $v }}
                        @endif
                    </span>
                </div>
                @endforeach
                @if($scheme->description)
                <div class="px-4 py-3 bg-lighter">
                    <small class="text-muted d-block mb-1 fw-semibold"><i class="ri-information-line me-1"></i>Description</small>
                    <p class="mb-0 text-secondary small">{{ $scheme->description }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Right Column: Associated Chit Groups --}}
    <div class="col-lg-7">
        <div class="card shadow-xs mb-4">
            <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="ri-group-line me-2 text-primary"></i>Chit Groups (<span class="text-primary">{{ $scheme->chitGroups->count() }}</span>)</h5>
                <a href="{{ route('chit.groups.create', ['scheme_id' => $scheme->id]) }}" class="btn btn-sm btn-primary d-inline-flex align-items-center">
                    <i class="ri-add-line me-1"></i>New Group
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Group Code</th>
                                <th>Member Filling</th>
                                <th>Start Date</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($scheme->chitGroups as $group)
                            @php
                                $filled = $group->occupiedSeats();
                                $total = (int) $group->total_members;
                                $pct = $group->seatsFillPercent();
                                $filledLabel = abs($filled - round($filled)) < 0.001
                                    ? (string) (int) round($filled)
                                    : rtrim(rtrim(number_format($filled, 2, '.', ''), '0'), '.');
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('chit.groups.show', $group) }}" class="fw-semibold text-primary">
                                        {{ $group->group_code }}
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px; min-width: 80px;">
                                            <div class="progress-bar bg-{{ $pct >= 100 ? 'success' : 'primary' }}" style="width: {{ $pct }}%"></div>
                                        </div>
                                        <small class="fw-medium text-nowrap">{{ $filledLabel }}/{{ $total }}</small>
                                    </div>
                                </td>
                                <td><small class="text-muted">{{ $group->start_date->format('d M Y') }}</small></td>
                                <td><span class="badge bg-{{ $group->status_badge }}">{{ ucfirst($group->status) }}</span></td>
                                <td class="text-center">
                                    <a href="{{ route('chit.groups.show', $group) }}" class="btn btn-sm btn-icon btn-label-primary rounded-circle" title="View Group">
                                        <i class="ri-eye-line"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="ri-group-line ri-2x d-block mb-2 text-secondary"></i>
                                    <div>No active chit groups created under this scheme yet.</div>
                                    <a href="{{ route('chit.groups.create', ['scheme_id' => $scheme->id]) }}" class="btn btn-sm btn-outline-primary mt-2">
                                        <i class="ri-add-line me-1"></i>Create First Group
                                    </a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Scheme Flyer & Payout Schedule --}}
    @if($scheme->payout_schedule && count($scheme->payout_schedule) > 0)
    <div class="col-12">
        <div class="card shadow-xs border">
            <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="ri-file-list-3-line text-primary ri-xl"></i>
                    <h5 class="mb-0">Scheme Flyer & Payout Schedule</h5>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" onclick="downloadCSV()" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center">
                        <i class="ri-download-line me-1"></i>Export CSV
                    </button>
                    <a href="{{ route('chit.schemes.flyer', $scheme) }}" target="_blank" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center">
                        <i class="ri-external-link-line me-1"></i>Full Page View
                    </a>
                    <a href="{{ route('chit.schemes.flyer', [$scheme, 'download' => 1]) }}" target="_blank" class="btn btn-sm btn-primary d-inline-flex align-items-center">
                        <i class="ri-file-pdf-2-line me-1"></i>View PDF
                    </a>
                </div>
            </div>
            <div class="card-body bg-light p-4 d-flex justify-content-center">
                @include('admin.chit.schemes.partials.scheme-flyer', [
                    'flyerId' => 'flyerContainer',
                    'chitValue' => $scheme->chit_value,
                    'durationMonths' => $scheme->duration_months,
                    'totalMembers' => $scheme->total_members,
                    'installmentAmount' => $scheme->installment_amount,
                    'installmentFrequency' => $scheme->installment_frequency ?? 'monthly',
                    'commissionPct' => $scheme->commission_pct,
                    'payoutSchedule' => $scheme->payout_schedule,
                    'foremanCommissionMonth' => $scheme->foremanCommissionMonth(),
                    'clientWiseForemanAmount' => $scheme->clientWiseForemanAmount(),
                    'clientWiseForemanCollectionMonth' => $scheme->clientWiseForemanCollectionMonth(),
                ])
            </div>
        </div>
    </div>
    @endif
</div>

@else
{{-- DEFAULT INDEX LIST / RECYCLE BIN — aligned with Loan Accounts UI --}}
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="mb-1">
            @if(isset($mode) && $mode === 'trash')
                <i class="icon-base ri ri-delete-bin-line text-danger me-1"></i>Recycle Bin
            @else
                Chit Schemes
            @endif
        </h4>
        <p class="text-muted mb-0">
            @if(isset($mode) && $mode === 'trash')
                Restore or permanently delete soft-deleted chit schemes
            @else
                Define chit plans with value, members, commission and type
            @endif
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if(isset($mode) && $mode === 'trash')
            <a href="{{ route('chit.schemes.index') }}" class="btn btn-label-secondary">
                <i class="icon-base ri ri-arrow-left-line me-1"></i>Back to Schemes
            </a>
        @else
            <a href="{{ route('chit.schemes.index', ['trash' => 'true']) }}" class="btn btn-label-danger" title="Recycle Bin">
                <i class="icon-base ri ri-delete-bin-line me-1"></i>Recycle Bin
            </a>
            <a href="{{ route('chit.schemes.create') }}" class="btn btn-primary">
                <i class="icon-base ri ri-add-line me-1"></i>New Scheme
            </a>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header border-bottom d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
        <h5 class="mb-0">
            @if(isset($mode) && $mode === 'trash')
                Deleted Schemes
            @else
                All Schemes
            @endif
            <span class="badge bg-label-primary ms-2">{{ $schemes->total() }}</span>
        </h5>
        <form method="GET" class="d-flex flex-wrap align-items-center gap-3 ajax-filter-form">
            @if(isset($mode) && $mode === 'trash')
                <input type="hidden" name="trash" value="true">
            @endif
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-nowrap fw-medium">Search:</label>
                <input type="text" name="search" class="form-control form-control-sm" style="min-width: 160px;"
                       placeholder="Name or code…" value="{{ request('search') }}">
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
            @if(!isset($mode) || $mode !== 'trash')
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-nowrap fw-medium">Status:</label>
                <select name="status" class="form-select form-select-sm" style="width: 140px;">
                    <option value="">All Statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            @endif
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="icon-base ri ri-filter-3-line me-1"></i>Filter
                </button>
                <a href="{{ route('chit.schemes.index', isset($mode) && $mode === 'trash' ? ['trash' => 'true'] : []) }}" class="btn btn-sm btn-label-secondary">
                    <i class="icon-base ri ri-refresh-line me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
    <div class="card-datatable table-responsive ajax-table-container">
        <table class="table text-nowrap mb-0">
            <thead>
                <tr>
                    <th>Scheme</th>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Chit Value</th>
                    <th>Members</th>
                    <th>Installment</th>
                    <th>Frequency</th>
                    <th>Commission</th>
                    <th>Groups</th>
                    <th>Status</th>
                    @if(isset($mode) && $mode === 'trash')
                        <th>Deleted</th>
                    @endif
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($schemes as $scheme)
                <tr>
                    <td>
                        <div>
                            @if(isset($mode) && $mode === 'trash')
                                <span class="fw-semibold text-heading">{{ $scheme->name }}</span>
                            @else
                                <a href="{{ route('chit.schemes.show', $scheme) }}" class="fw-semibold text-primary text-decoration-none">{{ $scheme->name }}</a>
                            @endif
                        </div>
                        @if($scheme->is_private)
                            <span class="badge bg-label-dark" style="font-size:.65rem;">
                                <i class="icon-base ri ri-lock-line"></i> Private
                            </span>
                        @endif
                    </td>
                    <td><span class="fw-medium">{{ $scheme->scheme_code }}</span></td>
                    <td>
                        <span class="badge bg-label-{{ $scheme->scheme_type_badge_color }}">
                            {{ $scheme->scheme_type_label }}
                        </span>
                    </td>
                    <td>₹{{ number_format($scheme->chit_value) }}</td>
                    <td>{{ $scheme->total_members }}</td>
                    <td>₹{{ number_format($scheme->installment_amount) }}</td>
                    <td>
                        <span class="badge bg-label-secondary">
                            {{ ucfirst($scheme->installment_frequency ?? 'monthly') }}
                        </span>
                    </td>
                    <td>{{ $scheme->commission_pct }}% (₹{{ number_format($scheme->commission_amount) }})</td>
                    <td><span class="badge bg-label-info">{{ $scheme->chit_groups_count ?? $scheme->chitGroups->count() }}</span></td>
                    <td>
                        <form action="{{ route('chit.schemes.toggle-status', $scheme) }}" method="POST" class="d-inline">
                            @csrf
                            @method('PATCH')
                            <div class="form-check form-switch mb-0" style="min-height: auto;">
                                <input class="form-check-input cursor-pointer" type="checkbox" onchange="this.form.submit()" {{ $scheme->status === 'active' ? 'checked' : '' }} title="Toggle Status">
                            </div>
                        </form>
                    </td>
                    @if(isset($mode) && $mode === 'trash')
                        <td><small class="text-muted">{{ optional($scheme->deleted_at)->format('d M Y H:i') }}</small></td>
                    @endif
                    <td>
                        <div class="d-flex align-items-center">
                            @if(isset($mode) && $mode === 'trash')
                                <form action="{{ route('chit.schemes.restore', $scheme->id) }}" method="POST" class="d-inline restore-scheme-form">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-icon btn-text-success rounded-pill" title="Restore Scheme">
                                        <i class="icon-base ri ri-refresh-line icon-22px"></i>
                                    </button>
                                </form>
                                <form action="{{ route('chit.schemes.force-delete', $scheme->id) }}" method="POST" class="d-inline force-delete-scheme-form">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Delete Permanently">
                                        <i class="icon-base ri ri-delete-bin-line icon-22px"></i>
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('chit.schemes.show', $scheme) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="View">
                                    <i class="icon-base ri ri-eye-line icon-22px"></i>
                                </a>
                                @if($scheme->payout_schedule && count($scheme->payout_schedule) > 0)
                                <a href="{{ route('chit.schemes.flyer', $scheme) }}" target="_blank" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="View / Print Flyer">
                                    <i class="icon-base ri ri-printer-line icon-22px"></i>
                                </a>
                                @endif
                                <a href="{{ route('chit.schemes.edit', $scheme) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="Edit">
                                    <i class="icon-base ri ri-pencil-line icon-22px"></i>
                                </a>

                                @if(($scheme->chit_groups_count ?? 0) === 0)
                                <form action="{{ route('chit.schemes.destroy', $scheme) }}" method="POST" class="d-inline delete-scheme-form">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Move to Recycle Bin">
                                        <i class="icon-base ri ri-delete-bin-line icon-22px"></i>
                                    </button>
                                </form>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ isset($mode) && $mode === 'trash' ? 12 : 11 }}" class="text-center py-5 text-muted">
                        <i class="icon-base ri ri-inbox-line icon-32px d-block mb-2"></i>
                        @if(isset($mode) && $mode === 'trash')
                            Recycle bin is empty
                        @else
                            No schemes created yet
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($schemes->hasPages())
        <div class="card-footer">{{ $schemes->links() }}</div>
    @endif
</div>
@endif
@endsection

@section('page-script')
@if(isset($mode) && in_array($mode, ['create', 'edit']))
<script>
window.existingPayoutSchedule = @json(
    old('payout_schedule', isset($scheme) && $scheme->payout_schedule ? $scheme->payout_schedule : null)
);

document.addEventListener('DOMContentLoaded', function() {
    const typeHints = {
        fixed:        '📌 <strong>Fixed Chit:</strong> Fixed installment every period. Payout by rotation — no auction needed.',
        auction:      '🔨 <strong>Auction-Based Chit:</strong> Members bid each period; lowest bid wins the chit amount.',
        group_based:  '🔒 <strong>Group-Based (Private) Chit:</strong> Invite-only with an assigned Group Leader.'
    };

    const schemeType = document.getElementById('schemeType');
    const typeHint = document.getElementById('typeHint');
    const auctionTypeRow = document.getElementById('auctionTypeRow');
    const fixedReturnRow = document.getElementById('fixedReturnRow');
    const privateRow = document.getElementById('privateRow');
    const installmentFrequency = document.getElementById('installmentFrequency');

    const chitValue = document.getElementById('chitValue');
    const totalMembers = document.getElementById('totalMembers');
    const commissionPct = document.getElementById('commissionPct');

    const installmentAmount = document.getElementById('installmentAmount');
    const btnAutoFillInstallment = document.getElementById('btnAutoFillInstallment');
    const commissionPreview = document.getElementById('commissionPreview');
    const durationMonths = document.getElementById('durationMonths');
    const freqHint = document.getElementById('freqHint');
    const foremanCommissionMonth = document.getElementById('foremanCommissionMonth');
    const FOREMAN_LABEL = @json(\App\Models\ChitScheme::FOREMAN_COMMISSION_LABEL);
    // Never overwrite a typed installment unless the user clicks Auto Fill (edit form).
    let installmentWasManuallyEdited = true;

    function parseForemanMonth() {
        if (!foremanCommissionMonth) return 1;
        const raw = parseInt(foremanCommissionMonth.value, 10);
        return Number.isNaN(raw) ? 1 : raw;
    }

    function currentPeriodUnit() {
        const freq = installmentFrequency?.value || 'monthly';
        if (freq === 'weekly') return { singular: 'Week', plural: 'Weeks', lower: 'week' };
        if (freq === 'daily') return { singular: 'Day', plural: 'Days', lower: 'day' };
        return { singular: 'Month', plural: 'Months', lower: 'month' };
    }

    function updateFrequencyLabels() {
        const u = currentPeriodUnit();
        document.querySelectorAll('.js-duration-label').forEach(el => {
            el.innerHTML = `Duration (${u.plural}) <span class="text-danger">*</span>`;
        });
        document.querySelectorAll('.js-foreman-label').forEach(el => {
            el.innerHTML = `Foreman Commission ${u.singular} <span class="text-danger">*</span>`;
        });
        document.querySelectorAll('.js-client-wise-month-label').forEach(el => {
            el.innerHTML = `Client Wise Collection ${u.singular} <span class="text-danger">*</span>`;
        });
        document.querySelectorAll('.js-client-wise-month-hint').forEach(el => {
            el.textContent = `This amount is added to that ${u.lower}'s due, one time only.`;
        });
        document.querySelectorAll('.js-payout-title').forEach(el => {
            el.innerHTML = `<i class="ri-table-line me-1 text-primary"></i>Payout Schedule (${u.singular}-wise)`;
        });
        document.querySelectorAll('.js-payout-col-unit').forEach(el => {
            el.textContent = `(${u.singular})`;
        });
        if (durationMonths) {
            durationMonths.placeholder = u.lower === 'week' ? 'e.g. 20 weeks' : 'e.g. 20';
        }
    }

    function syncForemanMonthMax() {
        if (!foremanCommissionMonth || !durationMonths) return;
        const dur = parseInt(durationMonths.value) || 1;
        foremanCommissionMonth.max = dur;
        const month = parseForemanMonth();
        if (month > dur) {
            foremanCommissionMonth.value = String(dur);
        }
        syncClientWiseForemanFields();
    }

    function syncClientWiseForemanFields() {
        const wrap = document.getElementById('clientWiseForemanFields');
        const amountInput = document.getElementById('clientWiseForemanCommission');
        const collectInput = document.getElementById('clientWiseForemanCollectionMonth');
        const show = parseForemanMonth() === 0;
        if (wrap) wrap.style.display = show ? '' : 'none';
        if (amountInput) {
            amountInput.required = show;
            if (!show) amountInput.removeAttribute('required');
        }
        if (collectInput) {
            const dur = parseInt(durationMonths?.value) || 120;
            collectInput.max = dur;
            collectInput.required = show;
            if (!show) collectInput.removeAttribute('required');
            const collectMonth = parseInt(collectInput.value, 10);
            if (!Number.isNaN(collectMonth) && collectMonth > dur) {
                collectInput.value = String(dur);
            }
        }
        updateForemanCommissionHint();
    }

    function onTypeChange() {
        if (!schemeType) return;
        const type = schemeType.value;
        if (typeHint) typeHint.innerHTML = typeHints[type] || '';

        const isAuction = type === 'auction';
        if (auctionTypeRow) {
            auctionTypeRow.style.display = isAuction ? 'block' : 'none';
        }
        const auctionType = document.getElementById('auctionType');
        if (auctionType) {
            auctionType.required = isAuction;
            if (!isAuction) {
                auctionType.value = 'open';
            }
        }
        if (fixedReturnRow) {
            fixedReturnRow.style.display = (type === 'fixed_return') ? 'block' : 'none';
        }
        if (privateRow) {
            privateRow.style.display = (type === 'group_based') ? 'block' : 'none';
        }

        calcScheme();
    }

    function updateForemanCommissionHint() {
        const hint = document.getElementById('foremanCommissionHint');
        if (!hint) return;
        if (parseForemanMonth() === 0) {
            const per = parseFloat(document.getElementById('clientWiseForemanCommission')?.value) || 0;
            const collect = parseInt(document.getElementById('clientWiseForemanCollectionMonth')?.value, 10) || 0;
            const members = parseInt(totalMembers?.value) || 0;
            const total = per * members;
            const u = currentPeriodUnit();
            if (per > 0 && collect > 0) {
                hint.innerHTML = `Each member pays <strong>₹${per.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong> once in ${u.lower} <strong>${collect}</strong>, on top of the regular installment.
                    Group total = amount × members added${members > 0 ? ` (₹${total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} if ${members} members)` : ''}.`;
            } else {
                hint.textContent = `Enter 0 to use client-wise commission: a one-time amount per member, collected in the ${u.lower} you choose. Installments stay the same.`;
            }
            return;
        }
        const inst = parseFloat(installmentAmount?.value) || calculateDefaultInstallment() || 0;
        const members = parseInt(totalMembers?.value) || 0;
        const total = (inst * members);
        const u = currentPeriodUnit();
        if (inst > 0 && members > 0) {
            hint.innerHTML = `Clients pay <strong>₹${inst.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong> each that ${u.lower}.
                Full collection <strong>₹${total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                (installment × ${members} members) is company/foreman commission — not a member payout.`;
        } else {
            hint.textContent = `Clients still pay the normal installment that ${u.lower}; the full collection (installment × members) is company/foreman commission — not a member payout.`;
        }
    }

    function calculateDefaultInstallment() {
        const cv = parseFloat(chitValue.value) || 0;
        const freq = installmentFrequency.value;
        const dur = parseInt(durationMonths.value) || parseInt(totalMembers.value) || 0;

        if (!cv || !dur) return 0;

        if (freq === 'daily') {
            return cv / (dur * 30);
        }

        return cv / dur;
    }

    function calcScheme(forceInstallment = false) {
        const cv = parseFloat(chitValue.value) || 0;
        const cp = parseFloat(commissionPct.value) || 0;
        const freq = installmentFrequency.value;

        if (freq === 'weekly') {
            freqHint.innerText = 'Weekly chit uses the same workflow as monthly: duration is weeks, and week 1 can be the Foreman Commission period.';
        } else if (freq === 'daily') {
            freqHint.innerText = 'Daily chit: ~30 days per month.';
        } else {
            freqHint.innerText = 'Monthly = standard chit.';
        }
        updateFrequencyLabels();

        const calculatedInstallment = calculateDefaultInstallment();
        if (installmentAmount && calculatedInstallment > 0 && (forceInstallment || !installmentWasManuallyEdited)) {
            installmentAmount.value = calculatedInstallment.toFixed(2);
        }

        if (cv && cp && commissionPreview) {
            commissionPreview.value = '₹' + (cv * cp / 100).toFixed(2);
        }

        updateForemanCommissionHint();
    }

    // Payout for a month = (that month's installment × members) − commission
    function calcMonthPayout(installmentAmt) {
        const members = parseInt(totalMembers.value) || 0;
        const cv = parseFloat(chitValue.value) || 0;
        const cp = parseFloat(commissionPct.value) || 0;
        const commission = cv * cp / 100;
        const pot = (parseFloat(installmentAmt) || 0) * members;
        return Math.max(0, pot - commission).toFixed(2);
    }

    function isNumericPayout(val) {
        if (val === null || val === undefined) return false;
        const s = String(val).trim();
        if (s === '') return false;
        return /^[\d.,]+$/.test(s.replace(/,/g, ''));
    }

    function calcOriginalChitPayoutSchedule() {
        const cv = parseFloat(chitValue.value) || 0;
        const dur = parseInt(durationMonths.value) || 0;
        const cp = parseFloat(commissionPct.value) || 0;
        const foremanMonth = parseForemanMonth();
        const maxDiscInput = document.getElementById('maxDiscountPct');
        const maxDiscPct = parseFloat(maxDiscInput ? maxDiscInput.value : 30) || 30;

        if (cv <= 0 || dur <= 0) return null;

        const commissionAmt = cv * (cp / 100);
        const defaultInstAmt = (
            parseFloat(installmentAmount?.value) ||
            calculateDefaultInstallment()
        ).toFixed(2);

        const maxPayout = Math.max(0, cv - commissionAmt);
        const maxDiscountAmt = cv * (maxDiscPct / 100);
        const minPayout = Math.max(0, cv - commissionAmt - maxDiscountAmt);

        const nonForemanCount = dur > 1 ? dur - 1 : 1;
        const step = nonForemanCount > 1 ? (maxPayout - minPayout) / (nonForemanCount - 1) : 0;

        const schedule = [];
        let nonForemanIndex = 0;

        for (let i = 1; i <= dur; i++) {
            if (foremanMonth > 0 && i === foremanMonth) {
                schedule.push({
                    installment_no: i,
                    installment_amount: defaultInstAmt,
                    payout_amount: FOREMAN_LABEL
                });
            } else {
                let payout = minPayout + (nonForemanIndex * step);
                payout = Math.min(maxPayout, Math.max(minPayout, payout));
                schedule.push({
                    installment_no: i,
                    installment_amount: defaultInstAmt,
                    payout_amount: payout.toFixed(2)
                });
                nonForemanIndex++;
            }
        }

        return schedule;
    }

    function calcFlatPayoutSchedule() {
        const dur = parseInt(durationMonths.value) || 0;
        const foremanMonth = parseForemanMonth();
        const instAmt = (
            parseFloat(installmentAmount?.value) ||
            calculateDefaultInstallment() ||
            0
        ).toFixed(2);
        const payout = calcMonthPayout(instAmt);

        return Array.from({ length: dur }, (_, index) => ({
            installment_no: index + 1,
            installment_amount: instAmt,
            payout_amount: (foremanMonth > 0 && index + 1 === foremanMonth) ? FOREMAN_LABEL : payout
        }));
    }

    // Payout Schedule Table Generation & Update
    // Rows follow duration; installment / payout cells stay empty until entered manually
    // (or loaded from a saved / generated schedule).
    function updatePayoutScheduleTable() {
        const tbody = document.getElementById('payoutScheduleTableBody');
        if (!tbody) return;

        const dur = parseInt(durationMonths.value) || 0;

        const existingRows = [];
        tbody.querySelectorAll('tr').forEach(tr => {
            const instNo = parseInt(tr.dataset.installmentNo);
            const instAmtInput = tr.querySelector('.inst-amt-input');
            const payoutAmtInput = tr.querySelector('.payout-amt-input');
            if (instAmtInput && payoutAmtInput) {
                existingRows[instNo] = {
                    installment_amount: instAmtInput.value,
                    payout_amount: payoutAmtInput.value,
                    installmentManual: instAmtInput.dataset.manual === '1',
                    payoutManual: payoutAmtInput.dataset.manual === '1'
                };
            }
        });

        tbody.innerHTML = '';

        if (dur <= 0) {
            updateTotals();
            return;
        }

        const loadingSavedSchedule = Array.isArray(window.existingPayoutSchedule)
            && window.existingPayoutSchedule.length > 0;

        for (let i = 1; i <= dur; i++) {
            let instAmt = '';
            let payoutAmt = '';
            let installmentManual = false;
            let payoutManual = false;

            if (loadingSavedSchedule && window.existingPayoutSchedule[i - 1]) {
                const prev = window.existingPayoutSchedule[i - 1];
                instAmt = prev.installment_amount ?? '';
                installmentManual = window.forceGeneratedPayoutSchedule !== true;
                if (prev.payout_amount !== undefined && prev.payout_amount !== null && String(prev.payout_amount).trim() !== '') {
                    payoutAmt = prev.payout_amount;
                    payoutManual = window.forceGeneratedPayoutSchedule !== true;
                }
            } else if (existingRows[i]) {
                instAmt = existingRows[i].installment_amount ?? '';
                payoutAmt = existingRows[i].payout_amount ?? '';
                installmentManual = existingRows[i].installmentManual;
                payoutManual = existingRows[i].payoutManual;
            }

            const tr = document.createElement('tr');
            tr.dataset.installmentNo = i;
            const foremanMonth = parseForemanMonth();
            const isForeman = foremanMonth > 0 && i === foremanMonth;
            const chitVal = parseFloat(chitValue?.value) || 0;

            // Foreman month: clients still pay a normal installment; payout is company commission.
            if (isForeman) {
                const savedInst = parseFloat(instAmt);
                if (savedInst > 0 && chitVal > 0
                    && (Math.abs(savedInst - chitVal) < 0.01 || savedInst > chitVal * 0.9)
                ) {
                    // Older data sometimes stored the full chit value here — clear for manual entry.
                    instAmt = installmentAmount?.value || '';
                    installmentManual = false;
                }
                payoutAmt = FOREMAN_LABEL;
                payoutManual = true;
            } else if (payoutAmt === FOREMAN_LABEL || String(payoutAmt).includes('Foreman Commission')) {
                payoutAmt = instAmt !== '' && instAmt !== null
                    ? calcMonthPayout(instAmt)
                    : '';
                payoutManual = false;
            }

            const members = parseInt(totalMembers?.value) || 0;
            const foremanCollection = ((parseFloat(instAmt) || 0) * members).toFixed(2);
            const periodUnit = currentPeriodUnit().singular;
            const monthLabel = isForeman
                ? `<div class="text-success fw-semibold" style="font-size:.68rem;">${FOREMAN_LABEL}</div>`
                : `<div class="text-muted" style="font-size:.7rem;">${periodUnit} ${i}</div>`;
            tr.innerHTML = `
                <td class="text-center align-middle ${isForeman ? 'table-success' : ''}">
                    <span class="fw-semibold">${i}</span>
                    ${monthLabel}
                    <input type="hidden" name="payout_schedule[${i-1}][installment_no]" value="${i}">
                </td>
                <td class="${isForeman ? 'table-success' : ''}">
                    <input type="number" step="0.01" class="form-control form-control-sm inst-amt-input"
                           name="payout_schedule[${i-1}][installment_amount]" value="${instAmt}"
                           data-manual="${installmentManual ? '1' : '0'}"
                           placeholder="Enter amount"
                           title="${isForeman ? 'Per-client installment for foreman ' + periodUnit.toLowerCase() + ' (same as other ' + periodUnit.toLowerCase() + 's)' : periodUnit + ' ' + i + ' installment amount'}">
                    ${isForeman ? `<div class="form-text text-success mb-0" style="font-size:.65rem;">
                        Clients pay this amount · Foreman collection = ₹${Number(foremanCollection).toLocaleString('en-IN', {minimumFractionDigits: 2})} (× ${members} members)
                    </div>` : ''}
                </td>
                <td class="${isForeman ? 'table-success' : ''}">
                    <input type="text" class="form-control form-control-sm payout-amt-input ${isForeman ? 'bg-light' : ''}"
                           name="payout_schedule[${i-1}][payout_amount]" value="${payoutAmt}"
                           data-manual="${payoutManual ? '1' : '0'}"
                           ${isForeman ? 'readonly' : ''}
                           placeholder="${isForeman ? FOREMAN_LABEL : 'Enter payout'}"
                           title="${isForeman ? FOREMAN_LABEL + ' — entire ' + periodUnit.toLowerCase() + ' collection goes to company' : 'Payout for ' + periodUnit.toLowerCase() + ' ' + i}">
                </td>
            `;
            tbody.appendChild(tr);
        }

        window.existingPayoutSchedule = null;
        window.forceGeneratedPayoutSchedule = false;
        updateTotals();
        updateForemanCommissionHint();

        tbody.querySelectorAll('.inst-amt-input').forEach(input => {
            input.addEventListener('input', function () {
                this.dataset.manual = '1';
                const row = this.closest('tr');
                const payoutInput = row.querySelector('.payout-amt-input');
                // Continue auto-syncing only while payout itself has not been manually edited.
                if (payoutInput && payoutInput.dataset.manual !== '1') {
                    payoutInput.value = this.value !== '' ? calcMonthPayout(this.value) : '';
                }
                updateTotals();
            });
        });

        tbody.querySelectorAll('.payout-amt-input').forEach(input => {
            input.addEventListener('input', function () {
                // Numeric and text values can both be intentional manual entries.
                this.dataset.manual = '1';
                updateTotals();
            });
        });
    }

    // The create form has no visible installment field, so the scheme installment
    // mirrors the first filled month of the payout schedule.
    function syncSchemeInstallmentFromSchedule() {
        if (!installmentAmount) return;
        if (installmentAmount.type !== 'hidden' && String(installmentAmount.value).trim() !== '') return;

        const tbody = document.getElementById('payoutScheduleTableBody');
        if (!tbody) return;

        for (const input of tbody.querySelectorAll('.inst-amt-input')) {
            const val = parseFloat(input.value);
            if (val > 0) {
                installmentAmount.value = val.toFixed(2);
                return;
            }
        }
    }

    function updateTotals() {
        const tbody = document.getElementById('payoutScheduleTableBody');
        if (!tbody) return;

        syncSchemeInstallmentFromSchedule();

        let totalInst = 0;
        let totalPayout = 0;

        tbody.querySelectorAll('.inst-amt-input').forEach(input => {
            totalInst += parseFloat(input.value) || 0;
        });

        tbody.querySelectorAll('.payout-amt-input').forEach(input => {
            const val = input.value || '';
            const cleanVal = val.replace(/[^\d.]/g, '');
            totalPayout += parseFloat(cleanVal) || 0;
        });

        document.getElementById('tfootTotalInstallment').innerText = '₹' + totalInst.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('tfootTotalPayout').innerText = '₹' + totalPayout.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    if (schemeType) {
        schemeType.addEventListener('change', onTypeChange);
    }
    if (installmentFrequency) {
        installmentFrequency.addEventListener('change', function () {
            calcScheme();
            updatePayoutScheduleTable();
        });
    }

    if (installmentAmount) {
        installmentAmount.addEventListener('input', function () {
            installmentWasManuallyEdited = true;
            const mainVal = installmentAmount.value;

            const tbody = document.getElementById('payoutScheduleTableBody');
            tbody?.querySelectorAll('.inst-amt-input').forEach(input => {
                if (input.dataset.manual === '1') return;
                input.value = mainVal;

                const payoutInput = input.closest('tr')?.querySelector('.payout-amt-input');
                if (payoutInput && payoutInput.dataset.manual !== '1') {
                    payoutInput.value = mainVal !== '' ? calcMonthPayout(mainVal) : '';
                }
            });
            updateTotals();
        });
    }

    if (btnAutoFillInstallment) {
        btnAutoFillInstallment.addEventListener('click', function () {
            installmentWasManuallyEdited = false;
            calcScheme(true);
            installmentAmount?.dispatchEvent(new Event('input'));
            installmentWasManuallyEdited = false;
        });
    }
    
    if (totalMembers) {
        totalMembers.addEventListener('input', function() {
            if (!durationMonths.dataset.edited) {
                durationMonths.value = totalMembers.value;
            }
            calcScheme();
            updatePayoutScheduleTable();
        });
    }

    if (durationMonths) {
        durationMonths.addEventListener('input', function() {
            durationMonths.dataset.edited = "true";
            syncForemanMonthMax();
            calcScheme();
            updatePayoutScheduleTable();
        });
    }

    if (foremanCommissionMonth) {
        foremanCommissionMonth.addEventListener('input', function () {
            syncForemanMonthMax();
            updatePayoutScheduleTable();
        });
    }

    ['clientWiseForemanCommission', 'clientWiseForemanCollectionMonth'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', updateForemanCommissionHint);
    });

    const btnGenOriginal = document.getElementById('btnGenOriginalChit');
    if (btnGenOriginal) {
        btnGenOriginal.addEventListener('click', function() {
            window.existingPayoutSchedule = calcOriginalChitPayoutSchedule();
            window.forceGeneratedPayoutSchedule = true;
            updatePayoutScheduleTable();
        });
    }

    const btnGenFlat = document.getElementById('btnGenFlatChit');
    if (btnGenFlat) {
        btnGenFlat.addEventListener('click', function() {
            window.existingPayoutSchedule = calcFlatPayoutSchedule();
            window.forceGeneratedPayoutSchedule = true;
            updatePayoutScheduleTable();
        });
    }

    const maxDiscInput = document.getElementById('maxDiscountPct');
    if (maxDiscInput) {
        maxDiscInput.addEventListener('input', function() {
            window.existingPayoutSchedule = calcOriginalChitPayoutSchedule();
            updatePayoutScheduleTable();
        });
    }

    if (typeof window.toggleSchemeRegistrationDetails === 'function') {
        window.toggleSchemeRegistrationDetails();
    }

    [chitValue, commissionPct].forEach(input => {
        if (!input) return;
        input.addEventListener('input', function() {
            calcScheme();
            updatePayoutScheduleTable();
        });
    });

    const downloadTemplateBtn = document.getElementById('formDownloadTemplate');
    if (downloadTemplateBtn) {
        downloadTemplateBtn.addEventListener('click', function () {
            const dur = parseInt(durationMonths.value) || 0;
            if (dur <= 0) {
                alert('Please set a valid duration before downloading the template.');
                return;
            }

            const schemeForm = downloadTemplateBtn.closest('form');
            const params = new URLSearchParams();
            params.append('_token', schemeForm?.querySelector('[name="_token"]')?.value || '');

            [
                'name', 'scheme_type', 'chit_value', 'total_members', 'duration_months',
                'installment_frequency', 'commission_pct', 'installment_amount',
                'foreman_commission_month',
                'client_wise_foreman_commission',
                'client_wise_foreman_collection_month'
            ].forEach(field => {
                const value = schemeForm?.querySelector(`[name="${field}"]`)?.value ?? '';
                if (String(value).trim() !== '') {
                    params.append(field, value);
                }
            });

            document.querySelectorAll('#payoutScheduleTableBody tr').forEach((tr, index) => {
                params.append(`payout_schedule[${index}][installment_no]`, tr.dataset.installmentNo || (index + 1));

                const inst = tr.querySelector('.inst-amt-input')?.value ?? '';
                const payout = tr.querySelector('.payout-amt-input')?.value ?? '';
                // Empty cells are omitted so they stay blank in the printed template.
                if (inst !== '') {
                    params.append(`payout_schedule[${index}][installment_amount]`, inst);
                }
                if (payout !== '') {
                    params.append(`payout_schedule[${index}][payout_amount]`, payout);
                }
            });

            const originalHtml = downloadTemplateBtn.innerHTML;
            downloadTemplateBtn.disabled = true;
            downloadTemplateBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Preparing…';

            fetch(@json(route('chit.schemes.payout-template-pdf')), {
                method: 'POST',
                body: params,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Could not generate the template PDF. Please check the scheme details and try again.');
                    }
                    return response.blob();
                })
                .then(blob => {
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'payout-schedule-template.pdf';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                })
                .catch(error => alert(error.message))
                .finally(() => {
                    downloadTemplateBtn.disabled = false;
                    downloadTemplateBtn.innerHTML = originalHtml;
                });
        });
    }

    const downloadCSVBtn = document.getElementById('formDownloadCSV');
    if (downloadCSVBtn) {
        downloadCSVBtn.addEventListener('click', function() {
            const dur = parseInt(durationMonths.value) || 0;
            if (dur <= 0) {
                alert('Please set a valid duration before downloading.');
                return;
            }
            let csv = [];
            csv.push("No. (Month),Installment Amount (₹),Payout / Disbursement (₹)");
            
            const tbody = document.getElementById('payoutScheduleTableBody');
            tbody.querySelectorAll('tr').forEach(tr => {
                const instNo = tr.dataset.installmentNo;
                const instAmt = tr.querySelector('.inst-amt-input').value;
                const payoutAmt = tr.querySelector('.payout-amt-input').value;
                csv.push(`"${instNo}","${instAmt}","${payoutAmt}"`);
            });

            const csvContent = "data:text/csv;charset=utf-8,\uFEFF" + csv.join("\n");
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "payout_schedule_template.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }

    onTypeChange();
    syncForemanMonthMax();
    syncClientWiseForemanFields();
    if (typeof window.toggleSchemeRegistrationDetails === 'function') {
        window.toggleSchemeRegistrationDetails();
    }
    updatePayoutScheduleTable();
});
</script>
@elseif(isset($mode) && $mode === 'show')
<script>
function downloadCSV() {
    let csv = [];
    csv.push("No. ({{ $scheme->periodUnitLabel() }}),Installment Amount,Payout / Disbursement");

    document.querySelectorAll('#flyerContainer .sf-table tbody tr').forEach(tr => {
        const tds = tr.querySelectorAll('td');
        if (tds.length >= 3) {
            const instNo    = tds[0].innerText.trim();
            const instAmt   = tds[1].innerText.trim().replace(/[₹,]/g, '');
            const payoutAmt = tds[2].innerText.trim().replace(/[₹,]/g, '');
            csv.push(`"${instNo}","${instAmt}","${payoutAmt}"`);
        }
    });

    const csvContent = "data:text/csv;charset=utf-8,\uFEFF" + csv.join("\n");
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "{{ $scheme->name }}_payout_schedule.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.delete-scheme-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Delete Scheme?',
                text: 'This will soft-delete the scheme and move it to the Recycle Bin.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ff3e1d',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) form.submit();
            });
        });
    });
});
</script>
@elseif(isset($mode) && in_array($mode, ['index', 'trash']))
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.delete-scheme-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Delete Scheme?',
                text: 'This will soft-delete the scheme and move it to the Recycle Bin. You can restore it later.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ff3e1d',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) form.submit();
            });
        });
    });

    document.querySelectorAll('.restore-scheme-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Restore Scheme?',
                text: 'Restore this scheme back to the active list?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28c76f',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, restore it!'
            }).then((result) => {
                if (result.isConfirmed) form.submit();
            });
        });
    });

    document.querySelectorAll('.force-delete-scheme-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Delete Permanently?',
                text: 'This cannot be undone. The scheme will be permanently removed.',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#ff3e1d',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete permanently!'
            }).then((result) => {
                if (result.isConfirmed) form.submit();
            });
        });
    });
});
</script>
@endif
@endsection
