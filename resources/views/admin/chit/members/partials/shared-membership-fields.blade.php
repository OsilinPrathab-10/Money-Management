{{-- Shared Membership fields — expects $clients; optional $member, $group, $prefix --}}
@php
    $oldShared = old('is_shared', null);
    if ($oldShared !== null) {
        $isShared = in_array((string) $oldShared, ['1', 'true', 'on', 'yes'], true);
    } else {
        $isShared = isset($member) ? (bool) $member->is_shared : false;
    }
    // UI-only mode; persisted via is_shared + share_percentage.
    $sharingMode = $isShared ? 'shared' : 'independent';
    $sharingModeName = 'sharing_mode_' . ($prefix ?? 'main');
    $existingShares = old('shares');
    if ($existingShares === null && isset($member)) {
        $existingShares = $member->shares->map(fn ($s) => [
            'client_id' => $s->client_id,
            'ownership_percentage' => (float) $s->ownership_percentage,
        ])->values()->all();
        if (empty($existingShares) && $member->client_id) {
            $existingShares = [['client_id' => $member->client_id, 'ownership_percentage' => 100]];
        }
    }
    if (empty($existingShares)) {
        $existingShares = [
            ['client_id' => old('client_id', isset($member) ? $member->client_id : ''), 'ownership_percentage' => 50],
            ['client_id' => '', 'ownership_percentage' => 50],
        ];
    }
    $chitValue = (float) old('chit_value_hint', (isset($group) ? $group->chit_value : null) ?? (isset($member) ? optional($member->group)->chit_value : 0) ?? 0);
@endphp

@php
    $sharePctValue = old('share_percentage', isset($member) ? ($member->share_percentage ?? 100) : 100);
    if ($sharePctValue === null || $sharePctValue === '') {
        $sharePctValue = 100;
    }
    // Shared mode always uses full seat (100%); independent defaults to 100% on create.
    if ($isShared) {
        $sharePctValue = 100;
    }
    $grpObj = $group ?? (isset($member) ? $member->group : null);
    $baseInstallment = $grpObj ? (float) $grpObj->getInstallmentAmountForMonth(1) : 0;
    $basePayout = $grpObj ? (float) $grpObj->resolvePayoutAmountForMonth(max(2, (int) ($grpObj->current_month ?? 0) + 1)) : 0;
    if ($basePayout <= 0 && $grpObj) {
        $basePayout = (float) $grpObj->resolvePayoutAmountForMonth(2);
    }
@endphp

@php
    $freqValue = old('collection_frequency', isset($member) ? ($member->collection_frequency ?? 'monthly') : 'monthly');
    if (! in_array($freqValue, ['daily', 'weekly', 'monthly'], true)) {
        $freqValue = 'monthly';
    }
@endphp

<div class="mb-3">
    <label class="form-label fw-semibold" for="collectionFrequency{{ $prefix ?? '' }}">Collection Frequency <span class="text-danger">*</span></label>
    <select name="collection_frequency" id="collectionFrequency{{ $prefix ?? '' }}" class="form-select collection-frequency-select" required>
        <option value="monthly" @selected($freqValue === 'monthly')>Monthly (Default)</option>
        <option value="weekly" @selected($freqValue === 'weekly')>Weekly</option>
        <option value="daily" @selected($freqValue === 'daily')>Daily</option>
    </select>
    <div class="form-text text-muted small mt-1">If Weekly/Daily is not chosen, Monthly is used. Weekly = ÷4, Daily = ÷30 of the monthly installment.</div>
</div>

<div class="mb-3 sharing-mode-block" data-prefix="{{ $prefix ?? '' }}">
    <label class="form-label fw-semibold d-block mb-2">Membership Sharing Type <span class="text-danger">*</span></label>
    <div class="d-flex flex-wrap gap-3">
        <div class="form-check">
            <input class="form-check-input sharing-mode-radio" type="radio"
                   name="{{ $sharingModeName }}"
                   id="sharingModeIndependent{{ $prefix ?? '' }}"
                   value="independent"
                   data-sharing-mode="independent"
                   {{ $sharingMode === 'independent' ? 'checked' : '' }}>
            <label class="form-check-label" for="sharingModeIndependent{{ $prefix ?? '' }}">
                Independent Sharing
                <span class="text-muted small d-block">Partial seat (e.g. 60%) or multi-seat (e.g. 500%)</span>
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input sharing-mode-radio" type="radio"
                   name="{{ $sharingModeName }}"
                   id="sharingModeShared{{ $prefix ?? '' }}"
                   value="shared"
                   data-sharing-mode="shared"
                   {{ $sharingMode === 'shared' ? 'checked' : '' }}>
            <label class="form-check-label" for="sharingModeShared{{ $prefix ?? '' }}">
                Sharing Option
                <span class="text-muted small d-block">2+ customers co-own one joint seat</span>
            </label>
        </div>
    </div>
</div>

<div class="mb-3 border rounded p-3 bg-light share-percentage-preview-block independent-sharing-section"
     data-base-installment="{{ $baseInstallment }}"
     data-base-payout="{{ $basePayout }}"
     style="{{ $isShared ? 'display:none;' : '' }}">
    <div class="row g-3 align-items-center">
        <div class="col-md-6 col-12">
            <label class="form-label fw-semibold">Independent Sharing Percentage</label>
            <div class="input-group">
                <input type="number" step="0.01" min="1" max="10000" name="share_percentage" id="sharePercentageInput{{ $prefix ?? '' }}"
                       class="form-control share-percentage-input" value="{{ $sharePctValue }}"
                       placeholder="100" {{ $isShared ? 'disabled' : '' }}>
                <span class="input-group-text">%</span>
            </div>
            <div class="form-text text-muted small mt-1">
                One seat = <strong>100%</strong>. Same client on separate seats = enroll multiple times (A/B/C).
                Or one membership for multiple seats = <strong>500%</strong> for 5 seats (installment &amp; payout scale ×5).
            </div>
        </div>
        <div class="col-md-6 col-12">
            <div class="p-2 border rounded bg-white shadow-sm">
                <div class="d-flex justify-content-between align-items-center text-muted small mb-1">
                    <span>Monthly Installment:</span>
                    <strong class="text-primary" id="previewInstallment{{ $prefix ?? '' }}">₹{{ number_format(($baseInstallment * $sharePctValue) / 100, 2) }}</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center text-muted small mb-1">
                    <span id="previewSplitLabel{{ $prefix ?? '' }}">Collection Amount:</span>
                    <strong class="text-info" id="previewSplitAmount{{ $prefix ?? '' }}">₹{{ number_format(($baseInstallment * $sharePctValue) / 100, 2) }}</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center text-muted small">
                    <span>Eligible Payout:</span>
                    <strong class="text-success" id="previewPayout{{ $prefix ?? '' }}">₹{{ number_format(($basePayout * $sharePctValue) / 100, 2) }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- When Sharing Option is active, always submit full-seat 100% --}}
<input type="hidden" name="share_percentage" class="share-percentage-hidden"
       value="100" {{ $isShared ? '' : 'disabled' }}>

<div class="mb-3 independent-client-section" style="{{ $isShared ? 'display:none;' : '' }}">
    <label class="form-label fw-semibold">Client <span class="text-danger">*</span></label>
    <select name="client_id" class="form-select independent-primary-client shared-primary-client" {{ $isShared ? 'disabled' : 'required' }}>
        <option value="">Select client…</option>
        @foreach($clients as $c)
            <option value="{{ $c->id }}" {{ (string) old('client_id', isset($member) ? $member->client_id : '') === (string) $c->id ? 'selected' : '' }}>
                {{ $c->client_name }} — {{ $c->client_phone }}
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3 shared-membership-block sharing-option-section" data-chit-value="{{ $chitValue }}"
     style="{{ $isShared ? '' : 'display:none;' }}">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div>
            <label class="form-label fw-semibold mb-0">Sharing Option — Multi-Customer Co-Ownership</label>
            <div class="form-text mt-0">2+ customers share a single joint seat. Ownership % must total 100%.</div>
        </div>
        <input type="hidden" name="is_shared" value="0" class="is-shared-off" {{ $isShared ? 'disabled' : '' }}>
        <input type="checkbox" class="d-none shared-membership-toggle" name="is_shared" value="1"
               id="sharedMembershipToggle{{ $prefix ?? '' }}"
               {{ $isShared ? 'checked' : '' }}>
    </div>

    <div class="single-owner-fields d-none">
        {{-- kept for JS compatibility; client field lives in independent-client-section --}}
    </div>

    <div class="shared-owners-fields border rounded p-3 bg-light mt-2" style="{{ $isShared ? '' : 'display:none;' }}">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <strong class="small">Co-owners</strong>
                <div class="form-text mt-0">Ownership % must total <strong>100%</strong>. Installment share = seat EMI × ownership %.</div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary add-share-row">
                <i class="ri-add-line"></i> Add
            </button>
        </div>

        <div class="share-rows">
            @foreach($existingShares as $i => $share)
            <div class="row g-2 align-items-end share-row mb-2">
                <div class="col-md-6">
                    <label class="form-label small mb-1">Customer {{ $i === 0 ? '(Primary)' : '' }}</label>
                    <select name="shares[{{ $i }}][client_id]" class="form-select form-select-sm share-client" {{ $isShared ? 'required' : '' }}>
                        <option value="">Select…</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ (string) ($share['client_id'] ?? '') === (string) $c->id ? 'selected' : '' }}>
                                {{ $c->client_name }} — {{ $c->client_phone }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Ownership %</label>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.01" min="0.01" max="100"
                               name="shares[{{ $i }}][ownership_percentage]"
                               class="form-control share-pct"
                               value="{{ $share['ownership_percentage'] ?? '' }}"
                               {{ $isShared ? 'required' : '' }}>
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Installment Share</label>
                    <input type="text" class="form-control form-control-sm share-amount" readonly tabindex="-1" value="" title="Monthly installment for this co-owner">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-icon btn-outline-danger remove-share-row" title="Remove" {{ $i < 2 ? 'disabled' : '' }}>
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </div>
            </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-between align-items-center mt-2">
            <small class="share-total-label text-muted">Total: <strong class="share-total-pct">0</strong>%</small>
            <small class="share-total-status"></small>
        </div>

        <template class="share-row-template">
            <div class="row g-2 align-items-end share-row mb-2">
                <div class="col-md-6">
                    <label class="form-label small mb-1">Customer</label>
                    <select name="shares[__INDEX__][client_id]" class="form-select form-select-sm share-client">
                        <option value="">Select…</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->client_name }} — {{ $c->client_phone }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Ownership %</label>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.01" min="0.01" max="100"
                               name="shares[__INDEX__][ownership_percentage]"
                               class="form-control share-pct" value="">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Installment Share</label>
                    <input type="text" class="form-control form-control-sm share-amount" readonly tabindex="-1" value="" title="Monthly installment for this co-owner">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-icon btn-outline-danger remove-share-row" title="Remove">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </div>
            </div>
        </template>
    </div>
</div>
