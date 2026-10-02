{{-- Group Registration fields. Expects optional $group (for edit mode). --}}
@php
    $regType = old('registration_type', isset($group) ? ($group->registration_type ?? 'non_registered') : 'non_registered');
    $isRegistered = $regType === 'registered';
@endphp

{{-- Group Registration --}}
<div class="col-12 mt-2">
    <div class="card border border-primary border-opacity-25">
        <div class="card-header bg-light py-2">
            <h6 class="mb-0 fw-semibold">
                <i class="ri-government-line me-1 text-primary"></i>Group Registration
            </h6>
            <small class="text-muted">For Registered chit groups, government registration details are required below.</small>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Group Type <span class="text-danger">*</span></label>
                    <select name="registration_type" id="groupRegistrationType" class="form-select" required
                            onchange="window.toggleGroupRegistrationDetails && window.toggleGroupRegistrationDetails()">
                        <option value="non_registered" {{ $regType === 'non_registered' ? 'selected' : '' }}>Non-Registered</option>
                        <option value="registered" {{ $regType === 'registered' ? 'selected' : '' }}>Registered</option>
                    </select>
                    <div class="form-text">Select <strong>Registered</strong> to enter government registration details.</div>
                </div>

                <div id="groupRegistrationDetails" class="col-12"
                     style="{{ $isRegistered ? 'display:block;' : 'display:none;' }}">
                    <div class="border rounded p-3" style="background: rgba(105, 108, 255, 0.08);">
                        <div class="row g-3">
                            <div class="col-12">
                                <h6 class="fw-semibold mb-0 text-primary">
                                    <i class="ri-file-list-3-line me-1"></i>Group Registration Details
                                </h6>
                                <hr class="mt-2 mb-0">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Registration Number <span class="text-danger">*</span></label>
                                <input type="text" name="registration_number" id="groupRegistrationNumber"
                                       class="form-control group-registration-required"
                                       value="{{ old('registration_number', isset($group) ? $group->registration_number : '') }}"
                                       @if($isRegistered) required @endif
                                       placeholder="e.g. TN/CHIT/2024/001"
                                       autocomplete="off">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Registration Date <span class="text-danger">*</span></label>
                                <input type="date" name="registration_date" id="groupRegistrationDate"
                                       class="form-control group-registration-required"
                                       value="{{ old('registration_date', isset($group) && $group->registration_date ? $group->registration_date->format('Y-m-d') : '') }}"
                                       @if($isRegistered) required @endif>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Registering Authority <span class="text-danger">*</span></label>
                                <input type="text" name="registering_authority" id="groupRegisteringAuthority"
                                       class="form-control group-registration-required"
                                       value="{{ old('registering_authority', isset($group) ? $group->registering_authority : 'Registrar of Chits') }}"
                                       @if($isRegistered) required @endif
                                       placeholder="Registrar of Chits"
                                       autocomplete="off">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Registration Office / District <span class="text-danger">*</span></label>
                                <input type="text" name="registration_office" id="groupRegistrationOffice"
                                       class="form-control group-registration-required"
                                       value="{{ old('registration_office', isset($group) ? $group->registration_office : '') }}"
                                       @if($isRegistered) required @endif
                                       placeholder="e.g. Coimbatore"
                                       autocomplete="off">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Registration Certificate Number</label>
                                <input type="text" name="registration_certificate_number" class="form-control"
                                       value="{{ old('registration_certificate_number', isset($group) ? $group->registration_certificate_number : '') }}"
                                       placeholder="Optional"
                                       autocomplete="off">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Registration Valid From</label>
                                <input type="date" name="registration_valid_from" class="form-control"
                                       value="{{ old('registration_valid_from', isset($group) && $group->registration_valid_from ? $group->registration_valid_from->format('Y-m-d') : '') }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Registration Valid Until</label>
                                <input type="date" name="registration_valid_until" class="form-control"
                                       value="{{ old('registration_valid_until', isset($group) && $group->registration_valid_until ? $group->registration_valid_until->format('Y-m-d') : '') }}">
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
    window.toggleGroupRegistrationDetails = function () {
        var typeSelect = document.getElementById('groupRegistrationType');
        var details = document.getElementById('groupRegistrationDetails');
        if (!typeSelect || !details) return;

        var show = typeSelect.value === 'registered';
        details.style.display = show ? 'block' : 'none';

        var inputs = details.querySelectorAll('.group-registration-required');
        for (var i = 0; i < inputs.length; i++) {
            if (show) {
                inputs[i].setAttribute('required', 'required');
            } else {
                inputs[i].removeAttribute('required');
            }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.toggleGroupRegistrationDetails);
    } else {
        window.toggleGroupRegistrationDetails();
    }
})();
</script>
