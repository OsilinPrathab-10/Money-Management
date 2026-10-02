{{-- Scheme Registration + Foreman Commission fields. Expects optional $scheme. --}}
@php
    $regType = old('registration_type', isset($scheme) ? ($scheme->registration_type ?? 'non_registered') : 'non_registered');
    $isRegistered = $regType === 'registered';
    $foremanMonth = old('foreman_commission_month', isset($scheme) ? ($scheme->foreman_commission_month ?? 1) : 1);
    $clientWiseAmount = old('client_wise_foreman_commission', isset($scheme) ? $scheme->client_wise_foreman_commission : '');
    $clientWiseMonth = old('client_wise_foreman_collection_month', isset($scheme) ? $scheme->client_wise_foreman_collection_month : '');
@endphp

{{-- Foreman Commission Month --}}
<div class="col-md-4">
    <label class="form-label fw-semibold">Foreman Commission Month <span class="text-danger">*</span></label>
    <input type="number" name="foreman_commission_month" id="foremanCommissionMonth" class="form-control"
           value="{{ $foremanMonth }}" required min="0" max="120">
    <div class="form-text text-muted">Enter 0 for a one-time client-wise amount. Otherwise the selected month’s full collection is company profit.</div>
</div>
<div class="col-md-4" id="clientWiseForemanAmountWrap" style="{{ (int) $foremanMonth === 0 ? '' : 'display:none;' }}">
    <label class="form-label fw-semibold">Client Wise Foreman Commission (₹)</label>
    <input type="number" name="client_wise_foreman_commission" id="clientWiseForemanCommission" class="form-control"
           value="{{ $clientWiseAmount }}" min="0.01" step="0.01" placeholder="Per member, once">
</div>
<div class="col-md-4" id="clientWiseForemanMonthWrap" style="{{ (int) $foremanMonth === 0 ? '' : 'display:none;' }}">
    <label class="form-label fw-semibold">Client Wise Collection Month</label>
    <input type="number" name="client_wise_foreman_collection_month" id="clientWiseForemanCollectionMonth" class="form-control"
           value="{{ $clientWiseMonth }}" min="1" max="120" placeholder="Month to collect">
</div>

{{-- Scheme Registration --}}
<div class="col-12 mt-2">
    <div class="card border border-primary border-opacity-25">
        <div class="card-header bg-light py-2">
            <h6 class="mb-0 fw-semibold">
                <i class="ri-government-line me-1 text-primary"></i>Scheme Registration
            </h6>
            <small class="text-muted">For Registered chits, government registration details are required below.</small>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Scheme Type <span class="text-danger">*</span></label>
                    <select name="registration_type" id="registrationType" class="form-select" required
                            onchange="window.toggleSchemeRegistrationDetails && window.toggleSchemeRegistrationDetails()">
                        <option value="non_registered" {{ $regType === 'non_registered' ? 'selected' : '' }}>Non-Registered</option>
                        <option value="registered" {{ $regType === 'registered' ? 'selected' : '' }}>Registered</option>
                    </select>
                    <div class="form-text">Select <strong>Registered</strong> to enter registration details.</div>
                </div>

                <div id="registrationDetails" class="col-12"
                     style="{{ $isRegistered ? 'display:block;' : 'display:none;' }}">
                    <div class="border rounded p-3" style="background: rgba(105, 108, 255, 0.08);">
                        <div class="row g-3">
                            <div class="col-12">
                                <h6 class="fw-semibold mb-0 text-primary">
                                    <i class="ri-file-list-3-line me-1"></i>Registration Details
                                </h6>
                                <hr class="mt-2 mb-0">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Registration Number <span class="text-danger">*</span></label>
                                <input type="text" name="registration_number" id="registrationNumber"
                                       class="form-control registration-required"
                                       value="{{ old('registration_number', isset($scheme) ? $scheme->registration_number : '') }}"
                                       @if($isRegistered) required @endif
                                       placeholder="e.g. TN/CHIT/2024/001"
                                       autocomplete="off">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Registration Date <span class="text-danger">*</span></label>
                                <input type="date" name="registration_date" id="registrationDate"
                                       class="form-control registration-required"
                                       value="{{ old('registration_date', isset($scheme) && $scheme->registration_date ? $scheme->registration_date->format('Y-m-d') : '') }}"
                                       @if($isRegistered) required @endif>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Registering Authority <span class="text-danger">*</span></label>
                                <input type="text" name="registering_authority" id="registeringAuthority"
                                       class="form-control registration-required"
                                       value="{{ old('registering_authority', isset($scheme) ? $scheme->registering_authority : 'Registrar of Chits') }}"
                                       @if($isRegistered) required @endif
                                       placeholder="Registrar of Chits"
                                       autocomplete="off">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Registration Office / District <span class="text-danger">*</span></label>
                                <input type="text" name="registration_office" id="registrationOffice"
                                       class="form-control registration-required"
                                       value="{{ old('registration_office', isset($scheme) ? $scheme->registration_office : '') }}"
                                       @if($isRegistered) required @endif
                                       placeholder="e.g. Coimbatore"
                                       autocomplete="off">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Registration Certificate Number</label>
                                <input type="text" name="registration_certificate_number" class="form-control"
                                       value="{{ old('registration_certificate_number', isset($scheme) ? $scheme->registration_certificate_number : '') }}"
                                       placeholder="Optional"
                                       autocomplete="off">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Registration Certificate Upload</label>
                                <input type="file" name="registration_certificate" class="form-control"
                                       accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/*">
                                <div class="form-text">PDF / Image (max 5 MB)</div>
                                @if(isset($scheme) && $scheme->registration_certificate_path)
                                    <div class="form-text mb-0">
                                        Current:
                                        <a href="{{ asset('storage/' . $scheme->registration_certificate_path) }}" target="_blank" rel="noopener">
                                            View certificate
                                        </a>
                                    </div>
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Registration Valid From</label>
                                <input type="date" name="registration_valid_from" class="form-control"
                                       value="{{ old('registration_valid_from', isset($scheme) && $scheme->registration_valid_from ? $scheme->registration_valid_from->format('Y-m-d') : '') }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Registration Valid Until</label>
                                <input type="date" name="registration_valid_until" class="form-control"
                                       value="{{ old('registration_valid_until', isset($scheme) && $scheme->registration_valid_until ? $scheme->registration_valid_until->format('Y-m-d') : '') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    window.toggleSchemeRegistrationDetails = function () {
        var typeSelect = document.getElementById('registrationType');
        var details = document.getElementById('registrationDetails');
        if (!typeSelect || !details) return;

        var show = typeSelect.value === 'registered';
        details.style.display = show ? 'block' : 'none';

        var inputs = details.querySelectorAll('.registration-required');
        for (var i = 0; i < inputs.length; i++) {
            if (show) {
                inputs[i].setAttribute('required', 'required');
            } else {
                inputs[i].removeAttribute('required');
            }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.toggleSchemeRegistrationDetails);
    } else {
        window.toggleSchemeRegistrationDetails();
    }
})();
</script>
