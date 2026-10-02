@extends('layouts/layoutMaster')
@section('title', 'User View - KYC Verification')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
  'resources/assets/vendor/libs/tagify/tagify.scss',
  'resources/assets/vendor/libs/animate-css/animate.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/@form-validation/form-validation.scss',
  'resources/assets/vendor/libs/flatpickr/flatpickr.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
  'resources/assets/vendor/libs/cleave-zen/cleave-zen.js',
  'resources/assets/vendor/libs/tagify/tagify.js',
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/vendor/libs/@form-validation/popular.js',
  'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
  'resources/assets/vendor/libs/@form-validation/auto-focus.js',
  'resources/assets/vendor/libs/flatpickr/flatpickr.js'
])
@endsection

@section('page-script')
@vite([
'resources/assets/js/modal-edit-user.js',
'resources/assets/js/app-user-view.js',
'resources/assets/js/client-view-account.js',
'resources/assets/custom-js/app-kyc-verification.js',
'resources/assets/custom-js/emi-calculator.js',
'resources/assets/custom-js/loan-applications.js',
'resources/assets/custom-js/chit-applications.js',
'resources/assets/custom-js/chit-need-month.js'
])
<script>
document.addEventListener('DOMContentLoaded', function() {
    const reapplyBtn = document.getElementById('btnAllowReapply');
    if (reapplyBtn) {
        reapplyBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Allow this client to re-apply?',
                text: 'The rejected KYC will be archived and reset to pending so the details can be corrected and verified again.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, allow re-apply',
                cancelButtonText: 'Cancel',
                customClass: {
                    confirmButton: 'btn btn-primary me-2',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false,
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch(reapplyBtn.dataset.url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (!data.success) {
                                throw new Error(data.message || 'Could not reopen KYC.');
                            }
                            return data;
                        })
                        .catch(err => {
                            Swal.showValidationMessage(err.message);
                        });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then(result => {
                if (result.isConfirmed && result.value && result.value.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Re-apply Enabled',
                        text: result.value.message,
                        customClass: { confirmButton: 'btn btn-success' },
                        buttonsStyling: false
                    }).then(() => window.location.reload());
                }
            });
        });
    }

    const fetchBtn = document.getElementById('fetchCibilBtn');
    if (fetchBtn) {
        fetchBtn.addEventListener('click', function() {
            const btn = this;
            const icon = btn.querySelector('i');
            btn.disabled = true;
            icon.classList.add('ri-spin');

            // Simulate API Fetch
            setTimeout(() => {
                const randomScore = Math.floor(Math.random() * (850 - 600 + 1)) + 600;
                const badge = document.getElementById('cibilScoreBadge');
                badge.textContent = randomScore;
                badge.className = `badge bg-label-${randomScore >= 700 ? 'success' : 'warning'}`;
                
                icon.classList.remove('ri-spin');
                btn.remove(); // Remove button after fetch

                Swal.fire({
                    title: 'CIBIL Score Fetched!',
                    text: `The CIBIL score for this client is ${randomScore}.`,
                    icon: 'success',
                    customClass: { confirmButton: 'btn btn-primary' }
                });
            }, 1500);
        });
    }

    function checkAndShowApproveButton() {
        const kycSkipped = window.kycSkipped === true;
        const badgeAadhaar = document.getElementById('badge-aadhaar-status');
        const badgePan = document.getElementById('badge-pan-status');
        const badgeBank = document.getElementById('badge-bank-status');

        const isAadhaarVerified = kycSkipped || !badgeAadhaar || badgeAadhaar.textContent.includes('Verified') || badgeAadhaar.textContent.includes('Skipped');
        const isPanVerified = kycSkipped || !badgePan || badgePan.textContent.includes('Verified') || badgePan.textContent.includes('Skipped');
        const isBankVerified = kycSkipped || !badgeBank || badgeBank.textContent.includes('Verified') || badgeBank.textContent.includes('Skipped');

        const missingAlert = document.getElementById('missing-docs-alert');
        const hasMissingDocs = !kycSkipped && (missingAlert !== null);

        if (isAadhaarVerified && isPanVerified && isBankVerified && !hasMissingDocs) {
            const container = document.getElementById('kyc-action-buttons');
            if (container && !container.querySelector('.btn-approve-kyc')) {
                const approveBtn = document.createElement('button');
                approveBtn.type = 'button';
                approveBtn.className = 'btn btn-success me-2 btn-approve-kyc';
                approveBtn.setAttribute('data-bs-toggle', 'modal');
                approveBtn.setAttribute('data-bs-target', '#approveModal');
                approveBtn.innerHTML = '<i class="icon-base ri ri-check-line me-1"></i> Approve KYC';
                container.insertBefore(approveBtn, container.firstChild);

                const skipBtn = document.getElementById('btnSkipKyc');
                if (skipBtn) skipBtn.classList.add('d-none');
            }
        }
    }

    window.kycSkipped = {{ optional($client->kycDetail)->kyc_skipped ? 'true' : 'false' }};

    const kycClientId = "{{ $client->id }}";
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // --- Aadhaar Verification ---
    const btnAadhaarVerify = document.getElementById('btnTriggerAadhaarVerify');
    const aadhaarOtpModalEl = document.getElementById('aadhaarOtpModal');
    const aadhaarOtpForm = document.getElementById('aadhaarOtpForm');
    const btnSubmitAadhaarOtp = document.getElementById('btnSubmitAadhaarOtp');
    const aadhaarOtpInput = document.getElementById('aadhaar_otp_input');
    const aadhaarRequestId = document.getElementById('aadhaar_request_id');
    const aadhaarOtpError = document.getElementById('aadhaarOtpError');

    if (btnAadhaarVerify) {
        btnAadhaarVerify.addEventListener('click', function() {
            btnAadhaarVerify.disabled = true;
            btnAadhaarVerify.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';

            fetch(`/verification/view/kyc/${kycClientId}/verify-aadhaar`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(json => {
                btnAadhaarVerify.disabled = false;
                btnAadhaarVerify.innerHTML = '<i class="ri-shield-keyhole-line me-1"></i> Verify';

                if (json.status && json.data && json.data.request_id) {
                    aadhaarRequestId.value = json.data.request_id;
                    const otpModal = new bootstrap.Modal(aadhaarOtpModalEl);
                    otpModal.show();
                } else {
                    Swal.fire({
                        title: 'Aadhaar OTP Failed',
                        text: json.message || 'Unable to trigger OTP verification.',
                        icon: 'error',
                        customClass: { confirmButton: 'btn btn-primary' }
                    });
                }
            })
            .catch(err => {
                console.error(err);
                btnAadhaarVerify.disabled = false;
                btnAadhaarVerify.innerHTML = '<i class="ri-shield-keyhole-line me-1"></i> Verify';
                Swal.fire({
                    title: 'Connection Error',
                    text: 'Failed to communicate with Aadhaar service.',
                    icon: 'error',
                    customClass: { confirmButton: 'btn btn-primary' }
                });
            });
        });
    }

    if (aadhaarOtpForm) {
        aadhaarOtpForm.addEventListener('submit', function(e) {
            e.preventDefault();
            aadhaarOtpError.classList.add('d-none');
            
            btnSubmitAadhaarOtp.disabled = true;
            btnSubmitAadhaarOtp.querySelector('.spinner-border').classList.remove('d-none');

            fetch(`/verification/view/kyc/${kycClientId}/verify-aadhaar-otp`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    otp: aadhaarOtpInput.value,
                    request_id: aadhaarRequestId.value
                })
            })
            .then(res => res.json())
            .then(json => {
                btnSubmitAadhaarOtp.disabled = false;
                btnSubmitAadhaarOtp.querySelector('.spinner-border').classList.add('d-none');

                if (json.status) {
                    bootstrap.Modal.getInstance(aadhaarOtpModalEl).hide();
                    Swal.fire({
                        title: 'Aadhaar Verified!',
                        text: json.message,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });

                    // Update badge
                    const badgeAadhaar = document.getElementById('badge-aadhaar-status');
                    if (badgeAadhaar) {
                        badgeAadhaar.outerHTML = '<span class="badge bg-label-success" id="badge-aadhaar-status"><i class="ri-checkbox-circle-line me-1"></i> Verified via API</span>';
                    }

                    // Remove buttons
                    ['btn-edit-aadhaar', 'btn-save-aadhaar', 'btn-cancel-aadhaar', 'btnTriggerAadhaarVerify'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.remove();
                    });

                    // Update Name
                    const namePara = document.getElementById('p-aadhaar-name');
                    if (namePara) {
                        namePara.textContent = json.data.name || 'N/A';
                    }

                    // Also place in PAN name automatically
                    const panNamePara = document.getElementById('p-pan-name');
                    if (panNamePara) {
                        panNamePara.textContent = json.data.name || 'N/A';
                    }
                    const panNameInput = document.getElementById('input-pan-name');
                    if (panNameInput) {
                        panNameInput.value = json.data.name || '';
                        panNameInput.defaultValue = json.data.name || '';
                    }

                    checkAndShowApproveButton();
                } else {
                    aadhaarOtpError.textContent = json.message || 'OTP verification failed.';
                    aadhaarOtpError.classList.remove('d-none');
                }
            })
            .catch(err => {
                console.error(err);
                btnSubmitAadhaarOtp.disabled = false;
                btnSubmitAadhaarOtp.querySelector('.spinner-border').classList.add('d-none');
                aadhaarOtpError.textContent = 'Connection error during OTP verification.';
                aadhaarOtpError.classList.remove('d-none');
            });
        });
    }

    // --- PAN Verification ---
    const btnPanVerify = document.getElementById('btnTriggerPanVerify');
    if (btnPanVerify) {
        btnPanVerify.addEventListener('click', function() {
            btnPanVerify.disabled = true;
            btnPanVerify.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Verifying...';

            fetch(`/verification/view/kyc/${kycClientId}/verify-pan`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(json => {
                btnPanVerify.disabled = false;
                btnPanVerify.innerHTML = '<i class="ri-shield-keyhole-line me-1"></i> Verify';

                if (json.status) {
                    Swal.fire({
                        title: 'PAN Verified!',
                        text: `PAN verified successfully for ${json.data.full_name || 'Client'}.`,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });

                    // Update badge
                    const badgePan = document.getElementById('badge-pan-status');
                    if (badgePan) {
                        badgePan.outerHTML = '<span class="badge bg-label-success" id="badge-pan-status"><i class="ri-checkbox-circle-line me-1"></i> Verified via API</span>';
                    }

                    // Remove buttons
                    ['btn-edit-pan', 'btn-save-pan', 'btn-cancel-pan', 'btnTriggerPanVerify'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.remove();
                    });

                    // Update Name
                    const namePara = document.getElementById('p-pan-name');
                    if (namePara) {
                        namePara.textContent = json.data.full_name || 'N/A';
                    }

                    checkAndShowApproveButton();
                } else {
                    Swal.fire({
                        title: 'PAN Verification Failed',
                        text: json.message || 'PAN details could not be verified.',
                        icon: 'error',
                        customClass: { confirmButton: 'btn btn-primary' }
                    });
                }
            })
            .catch(err => {
                console.error(err);
                btnPanVerify.disabled = false;
                btnPanVerify.innerHTML = '<i class="ri-shield-keyhole-line me-1"></i> Verify';
                Swal.fire({
                    title: 'Connection Error',
                    text: 'Failed to communicate with PAN verification service.',
                    icon: 'error',
                    customClass: { confirmButton: 'btn btn-primary' }
                });
            });
        });
    }

    // --- Bank Verification ---
    const btnBankVerify = document.getElementById('btnTriggerBankVerify');
    if (btnBankVerify) {
        btnBankVerify.addEventListener('click', function() {
            btnBankVerify.disabled = true;
            btnBankVerify.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Verifying...';

            fetch(`/verification/view/kyc/${kycClientId}/verify-bank`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(json => {
                btnBankVerify.disabled = false;
                btnBankVerify.innerHTML = '<i class="ri-shield-keyhole-line me-1"></i> Verify';

                if (json.status) {
                    Swal.fire({
                        title: 'Bank Details Verified!',
                        text: `Bank details verified successfully for ${json.data.full_name || 'Client'}. Bank: ${json.data.bank_name || 'N/A'}.`,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });

                    // Update badge
                    const badgeBank = document.getElementById('badge-bank-status');
                    if (badgeBank) {
                        badgeBank.outerHTML = '<span class="badge bg-label-success" id="badge-bank-status"><i class="ri-checkbox-circle-line me-1"></i> Verified via API</span>';
                    }

                    // Remove buttons
                    ['btn-edit-bank', 'btn-save-bank', 'btn-cancel-bank', 'btnTriggerBankVerify'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.remove();
                    });

                    // Update fields
                    if (document.getElementById('p-bank-holder')) {
                        document.getElementById('p-bank-holder').textContent = json.data.full_name || 'N/A';
                    }
                    if (document.getElementById('p-bank-name')) {
                        document.getElementById('p-bank-name').textContent = json.data.bank_name || 'N/A';
                    }
                    if (document.getElementById('p-bank-branch')) {
                        const branchVal = json.data.branch || '';
                        document.getElementById('p-bank-branch').textContent = branchVal || 'Enter manually';
                        const branchInput = document.getElementById('input-bank-branch');
                        if (branchInput && branchVal) {
                            branchInput.value = branchVal;
                        }
                        if (!branchVal) {
                            Swal.fire({
                                title: 'Branch Required',
                                text: 'Bank verified, but branch was not returned by API. Please edit bank details and enter branch manually.',
                                icon: 'info',
                                customClass: { confirmButton: 'btn btn-primary' }
                            });
                        }
                    }

                    checkAndShowApproveButton();
                } else {
                    Swal.fire({
                        title: 'Bank Verification Failed',
                        text: json.message || 'Bank Account details could not be verified.',
                        icon: 'error',
                        customClass: { confirmButton: 'btn btn-primary' }
                    });
                }
            })
            .catch(err => {
                console.error(err);
                btnBankVerify.disabled = false;
                btnBankVerify.innerHTML = '<i class="ri-shield-keyhole-line me-1"></i> Verify';
                Swal.fire({
                    title: 'Connection Error',
                    text: 'Failed to communicate with Bank verification service.',
                    icon: 'error',
                    customClass: { confirmButton: 'btn btn-primary' }
                });
            });
        });
    }

    // --- Inline KYC Editing ---
    document.querySelectorAll('.btn-edit-section').forEach(btn => {
        btn.addEventListener('click', function() {
            const section = this.getAttribute('data-section');
            
            // Toggle buttons
            this.classList.add('d-none');
            document.getElementById(`btn-save-${section}`).classList.remove('d-none');
            document.getElementById(`btn-cancel-${section}`).classList.remove('d-none');
            
            const btnVerify = (section === 'aadhaar') ? document.getElementById('btnTriggerAadhaarVerify') : 
                            (section === 'pan') ? document.getElementById('btnTriggerPanVerify') : 
                            document.getElementById('btnTriggerBankVerify');
            if (btnVerify) btnVerify.classList.add('d-none');
            
            // Toggle fields
            document.querySelectorAll(`.static-field-${section}`).forEach(el => el.classList.add('d-none'));
            document.querySelectorAll(`.edit-field-${section}`).forEach(el => el.classList.remove('d-none'));
        });
    });

    document.querySelectorAll('.btn-cancel-section').forEach(btn => {
        btn.addEventListener('click', function() {
            const section = this.getAttribute('data-section');
            
            // Toggle buttons back
            this.classList.add('d-none');
            document.getElementById(`btn-save-${section}`).classList.add('d-none');
            document.getElementById(`btn-edit-${section}`).classList.remove('d-none');
            
            const btnVerify = (section === 'aadhaar') ? document.getElementById('btnTriggerAadhaarVerify') : 
                            (section === 'pan') ? document.getElementById('btnTriggerPanVerify') : 
                            document.getElementById('btnTriggerBankVerify');
            if (btnVerify) btnVerify.classList.remove('d-none');
            
            // Reset inputs to original attributes
            document.querySelectorAll(`.edit-field-${section}`).forEach(input => {
                input.value = input.defaultValue;
            });
            
            // Toggle fields back
            document.querySelectorAll(`.static-field-${section}`).forEach(el => el.classList.remove('d-none'));
            document.querySelectorAll(`.edit-field-${section}`).forEach(el => el.classList.add('d-none'));
        });
    });

    document.querySelectorAll('.btn-save-section').forEach(btn => {
        btn.addEventListener('click', function() {
            const section = this.getAttribute('data-section');
            const saveBtn = this;
            
            // Gather request data
            let requestData = { section: section };
            if (section === 'aadhaar') {
                requestData.aadhaar_number = document.getElementById('input-aadhaar-number').value.trim();
            } else if (section === 'pan') {
                requestData.pan_number = document.getElementById('input-pan-number').value.trim();
                requestData.pan_name = document.getElementById('input-pan-name').value.trim();
            } else if (section === 'bank') {
                requestData.account_holder_name = document.getElementById('input-bank-holder').value.trim();
                requestData.account_number = document.getElementById('input-bank-account').value.trim();
                requestData.ifsc_code = document.getElementById('input-bank-ifsc').value.trim();
                requestData.bank_name = document.getElementById('input-bank-name').value.trim();
                requestData.branch_name = document.getElementById('input-bank-branch')?.value.trim() || '';
            }
            
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
            
            fetch(`/verification/view/kyc/${kycClientId}/update-fields`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestData)
            })
            .then(res => res.json())
            .then(json => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="ri-save-line me-1"></i> Save';
                
                if (json.status) {
                    // Update static display text and input defaultValue attributes
                    if (section === 'aadhaar') {
                        const num = json.data.aadhaar_number || 'N/A';
                        document.getElementById('p-aadhaar-number').textContent = num;
                        document.getElementById('input-aadhaar-number').defaultValue = num;
                        document.getElementById('input-aadhaar-number').value = num;
                    } else if (section === 'pan') {
                        const num = json.data.pan_number || 'N/A';
                        const name = json.data.pan_name || 'N/A';
                        document.getElementById('p-pan-number').textContent = num;
                        document.getElementById('p-pan-name').textContent = name;
                        document.getElementById('input-pan-number').defaultValue = num;
                        document.getElementById('input-pan-number').value = num;
                        document.getElementById('input-pan-name').defaultValue = name;
                        document.getElementById('input-pan-name').value = name;
                    } else if (section === 'bank') {
                        const holder = json.data.account_holder_name || 'N/A';
                        const acc = json.data.account_number || 'N/A';
                        const ifsc = json.data.ifsc_code || 'N/A';
                        const name = json.data.bank_name || 'N/A';
                        const branch = json.data.branch_name || 'N/A';
                        
                        document.getElementById('p-bank-holder').textContent = holder;
                        document.getElementById('p-bank-account').textContent = acc;
                        document.getElementById('p-bank-ifsc').textContent = ifsc;
                        document.getElementById('p-bank-name').textContent = name;
                        if (document.getElementById('p-bank-branch')) {
                            document.getElementById('p-bank-branch').textContent = branch;
                        }
                        
                        document.getElementById('input-bank-holder').defaultValue = holder;
                        document.getElementById('input-bank-holder').value = holder;
                        document.getElementById('input-bank-account').defaultValue = acc;
                        document.getElementById('input-bank-account').value = acc;
                        document.getElementById('input-bank-ifsc').defaultValue = ifsc;
                        document.getElementById('input-bank-ifsc').value = ifsc;
                        document.getElementById('input-bank-name').defaultValue = name;
                        document.getElementById('input-bank-name').value = name;
                        if (document.getElementById('input-bank-branch')) {
                            document.getElementById('input-bank-branch').defaultValue = branch === 'N/A' ? '' : branch;
                            document.getElementById('input-bank-branch').value = branch === 'N/A' ? '' : branch;
                        }
                    }
                    
                    // Reset UI display
                    saveBtn.classList.add('d-none');
                    document.getElementById(`btn-cancel-${section}`).classList.add('d-none');
                    document.getElementById(`btn-edit-${section}`).classList.remove('d-none');
                    
                    const btnVerify = (section === 'aadhaar') ? document.getElementById('btnTriggerAadhaarVerify') : 
                                    (section === 'pan') ? document.getElementById('btnTriggerPanVerify') : 
                                    document.getElementById('btnTriggerBankVerify');
                    if (btnVerify) {
                        btnVerify.classList.remove('d-none');
                    }
                    
                    document.querySelectorAll(`.static-field-${section}`).forEach(el => el.classList.remove('d-none'));
                    document.querySelectorAll(`.edit-field-${section}`).forEach(el => el.classList.add('d-none'));
                    
                    Swal.fire({
                        title: 'Success!',
                        text: json.message,
                        icon: 'success',
                        customClass: { confirmButton: 'btn btn-primary' }
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: json.message || 'Failed to update fields.',
                        icon: 'error',
                        customClass: { confirmButton: 'btn btn-primary' }
                    });
                }
            })
            .catch(err => {
                console.error(err);
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="ri-save-line me-1"></i> Save';
                Swal.fire({
                    title: 'Connection Error',
                    text: 'Failed to save changes.',
                    icon: 'error',
                    customClass: { confirmButton: 'btn btn-primary' }
                });
            });
        });
    });

    // Inject custom CSS for dynamic file uploading
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .blurred-loading {
                filter: blur(5px);
                opacity: 0.6;
                pointer-events: none;
                transition: filter 0.3s ease, opacity 0.3s ease;
            }
            .upload-spinner-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                background: rgba(59, 130, 246, 0.15); /* Light blue tint */
                backdrop-filter: blur(5px);
                z-index: 99;
                border-radius: 0.5rem;
                animation: fadeIn 0.3s ease;
            }
            @keyframes pulse {
                0%, 100% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.15); opacity: 0.7; }
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
        `)
        .appendTo('head');

    // Trigger submit on file selection
    $(document).on('change', '.kyc-file-input', function() {
        $(this).closest('form').submit();
    });

    // --- Dynamic Document Upload with Blur & Spinner ---
    $(document).on('submit', 'form[enctype="multipart/form-data"]', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const fileInput = form.find('input[type="file"]');
        if (fileInput.length === 0) return;
        
        const fieldName = fileInput.attr('name');
        
        // Find closest wrapper based on the field
        let container = null;
        if (fieldName === 'selfie_image') {
            container = $('#container-selfie_image');
        } else if (fieldName === 'aadhaar_image') {
            container = $('#container-aadhaar_image');
        } else if (fieldName === 'aadhaar_image_back') {
            container = $('#container-aadhaar_image_back');
        } else if (fieldName === 'pan_image') {
            container = $('#container-pan_image');
        } else if (fieldName === 'bank_statement') {
            container = $('#container-bank_statement');
        }
        
        if (!container || container.length === 0) {
            container = form.parent();
        }

        container.css('position', 'relative');
        
        const overlayHTML = `
            <div class="upload-spinner-overlay">
                <div class="d-flex flex-column align-items-center">
                    <i class="ri-upload-cloud-2-line text-primary mb-2" style="font-size: 2.5rem; animation: pulse 1.5s infinite ease-in-out;"></i>
                    <span class="fw-semibold text-primary" style="font-size: 0.85rem;">Uploading...</span>
                </div>
            </div>
        `;
        
        container.append(overlayHTML);
        container.find('> :not(.upload-spinner-overlay)').addClass('blurred-loading');
        
        const formData = new FormData(this);
        
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            success: function(response) {
                container.find('.upload-spinner-overlay').remove();
                container.find('.blurred-loading').removeClass('blurred-loading');
                
                if (response.success) {
                    Swal.fire({
                        title: 'Success',
                        text: response.message,
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    
                    const fileUrl = response.file_url;
                    
                    if (fieldName === 'selfie_image') {
                        container.html(`
                            <img class="img-fluid rounded mb-4" src="${fileUrl}" height="120" width="120" alt="User avatar" style="object-fit: cover;" />
                        `);
                    } else if (fieldName === 'aadhaar_image') {
                        container.html(`
                            <p class="small text-muted mb-1 text-center">Front Side</p>
                            <div class="text-center bg-light rounded-3 p-2 d-flex align-items-center justify-content-center" style="min-height: 200px;">
                              <img src="${fileUrl}" class="img-fluid rounded shadow-sm" style="max-height: 250px; object-fit: contain;" alt="Aadhar Front" />
                            </div>
                        `);
                    } else if (fieldName === 'aadhaar_image_back') {
                        container.html(`
                            <p class="small text-muted mb-1 text-center">Back Side</p>
                            <div class="text-center bg-light rounded-3 p-2 d-flex align-items-center justify-content-center" style="min-height: 200px;">
                              <img src="${fileUrl}" class="img-fluid rounded shadow-sm" style="max-height: 250px; object-fit: contain;" alt="Aadhar Back" />
                            </div>
                        `);
                    } else if (fieldName === 'pan_image') {
                        container.html(`
                            <div class="text-center bg-light rounded-3 p-2 d-flex align-items-center justify-content-center" style="min-height: 200px;">
                              <img src="${fileUrl}" class="img-fluid rounded shadow-sm" style="max-height: 250px; object-fit: contain;" alt="PAN Card" />
                            </div>
                        `);
                    } else if (fieldName === 'bank_statement') {
                        const fileExtension = fileUrl.split('.').pop().toLowerCase();
                        const isPdf = fileExtension === 'pdf';
                        container.html(`
                          <div class="d-flex align-items-center mb-4">
                            <i class="icon-base ri ri-file-text-line icon-40px text-primary me-3"></i>
                            <div>
                              <small class="text-primary text-uppercase fw-semibold d-block">Financial Document</small>
                              <h6 class="mb-0">Bank Statement</h6>
                            </div>
                          </div>
                          <div class="row g-4">
                            <div class="col-12">
                              <div class="d-flex align-items-center justify-content-between p-3 bg-label-primary rounded-3">
                                <div class="d-flex align-items-center gap-3">
                                  <div class="avatar">
                                    <div class="avatar-initial bg-primary rounded-3">
                                      <i class="icon-base ri ri-${isPdf ? 'file-pdf' : 'image'}-line icon-24px"></i>
                                    </div>
                                  </div>
                                  <div>
                                    <h6 class="mb-0">Bank Statement Document</h6>
                                    <small class="text-muted">${isPdf ? 'PDF Document' : 'Image File'}</small>
                                  </div>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                  <a href="${fileUrl}" target="_blank" class="btn btn-sm btn-icon btn-primary rounded-circle shadow-xs" title="View Document" data-bs-toggle="tooltip">
                                    <i class="icon-base ri ri-eye-line fs-5"></i>
                                  </a>
                                  <a href="${fileUrl}" download class="btn btn-sm btn-icon btn-outline-primary rounded-circle shadow-xs" title="Save / Download" data-bs-toggle="tooltip">
                                    <i class="icon-base ri ri-download-line fs-5"></i>
                                  </a>
                                </div>
                              </div>
                            </div>
                          </div>
                        `);
                    }
                    
                    // Update missing documents alert if exists
                    const missingAlert = document.querySelector('.alert-warning');
                    if (missingAlert) {
                        if (response.missing_docs && response.missing_docs.length > 0) {
                            const missingListStr = response.missing_docs.join(', ');
                            const strongEl = missingAlert.querySelector('strong');
                            if (strongEl) {
                                strongEl.textContent = missingListStr;
                            }
                        } else {
                            missingAlert.remove();
                        }
                    }
                    
                    // Check if Approve button should be enabled/shown
                    checkAndShowApproveButton();
                } else {
                    Swal.fire({
                        title: 'Upload Failed',
                        text: response.message || 'Unable to upload file.',
                        icon: 'error'
                    });
                }
            },
            error: function(xhr) {
                container.find('.upload-spinner-overlay').remove();
                container.find('.blurred-loading').removeClass('blurred-loading');
                Swal.fire({
                    title: 'Upload Error',
                    text: xhr.responseJSON?.message || 'Connection lost during file upload.',
                    icon: 'error'
                });
            }
        });
    });
});
</script>
@endsection

@section('content')

<!-- Success/Error Alerts -->
@if(session('success'))
  <div class="alert alert-success alert-dismissible" role="alert">
    <strong>Success!</strong> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if(session('error'))
  <div class="alert alert-danger alert-dismissible" role="alert">
    <strong>Error!</strong> {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div class="nav-align-top mb-0">
    <ul class="nav nav-pills flex-column flex-md-row row-gap-2">
      <li class="nav-item">
        <a class="nav-link active" href="javascript:void(0);">
          <i class="icon-base ri ri-shield-check-line me-1_5"></i>
          KYC
        </a>
      </li>
    </ul>
  </div>
  <div class="d-flex gap-2">
    @if($verificationStatus !== 'verified')
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editKycModal">
      <i class="icon-base ri ri-edit-box-line me-1"></i>
      Add/Edit KYC Details
    </button>
    @endif
    <a href="{{ route('verification-kyc-verification') }}" class="btn btn-outline-secondary">
      <i class="icon-base ri ri-arrow-left-line me-1"></i>
      Back to KYC Verification
    </a>
  </div>
</div>

<div class="row">
  <!-- User Sidebar -->
  <div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
    <!-- User Card -->
    <div class="card mb-6">
      <div class="card-body pt-12">
        <div class="user-avatar-section">
          <div class=" d-flex align-items-center flex-column" id="container-selfie_image">
            @if(optional($client->kycDetail)->selfie_image && substr(optional($client->kycDetail)->selfie_image, 0, 5) === 'data:')
              {{-- Base64 encoded image --}}
              <img class="img-fluid rounded-circle mb-2 border p-1 shadow-sm" src="{{ $client->kycDetail->selfie_image }}" height="110" width="110"
                alt="User avatar" style="object-fit: cover;" />
              <div class="d-flex align-items-center justify-content-center gap-2 mb-3">
                <button type="button" class="btn btn-sm btn-icon btn-primary rounded-circle shadow-xs" onclick="window.open('{{ $client->kycDetail->selfie_image }}', '_blank')" title="View Selfie" data-bs-toggle="tooltip">
                  <i class="ri-eye-line fs-5"></i>
                </button>
                @if($verificationStatus !== 'verified')
                <form action="{{ route('verification-kyc-update', $client->id) }}" method="POST" enctype="multipart/form-data" class="d-inline-block">
                  @csrf
                  <label class="btn btn-sm btn-icon btn-outline-success rounded-circle shadow-xs mb-0 cursor-pointer" title="Upload / Replace Selfie" data-bs-toggle="tooltip">
                    <i class="ri-upload-2-line fs-5"></i>
                    <input type="file" name="selfie_image" class="d-none kyc-file-input" accept="image/*">
                  </label>
                </form>
                @endif
              </div>
            @elseif(optional($client->kycDetail)->selfie_image)
              {{-- File path image --}}
              <img class="img-fluid rounded-circle mb-2 border p-1 shadow-sm" src="{{ asset('storage/' . $client->kycDetail->selfie_image) }}" height="110" width="110"
                alt="User avatar" style="object-fit: cover;" />
              <div class="d-flex align-items-center justify-content-center gap-2 mb-3">
                <a href="{{ asset('storage/' . $client->kycDetail->selfie_image) }}" target="_blank" class="btn btn-sm btn-icon btn-primary rounded-circle shadow-xs" title="View Selfie" data-bs-toggle="tooltip">
                  <i class="ri-eye-line fs-5"></i>
                </a>
                <a href="{{ asset('storage/' . $client->kycDetail->selfie_image) }}" download class="btn btn-sm btn-icon btn-outline-primary rounded-circle shadow-xs" title="Save / Download Selfie" data-bs-toggle="tooltip">
                  <i class="ri-download-line fs-5"></i>
                </a>
                @if($verificationStatus !== 'verified')
                <form action="{{ route('verification-kyc-update', $client->id) }}" method="POST" enctype="multipart/form-data" class="d-inline-block">
                  @csrf
                  <label class="btn btn-sm btn-icon btn-outline-success rounded-circle shadow-xs mb-0 cursor-pointer" title="Upload / Replace Selfie" data-bs-toggle="tooltip">
                    <i class="ri-upload-2-line fs-5"></i>
                    <input type="file" name="selfie_image" class="d-none kyc-file-input" accept="image/*">
                  </label>
                </form>
                @endif
              </div>
            @else
              {{-- Default avatar --}}
              <img class="img-fluid rounded-circle mb-2 border p-1 shadow-sm" src="{{asset('assets/img/avatars/1.png')}}" height="110" width="110"
                alt="User avatar" />
              @if($verificationStatus !== 'verified')
              <div class="mb-3">
                <form action="{{ route('verification-kyc-update', $client->id) }}" method="POST" enctype="multipart/form-data" class="d-inline-block">
                  @csrf
                  <label class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-xs cursor-pointer mb-0">
                    <i class="ri-upload-cloud-2-line me-1"></i> Upload Selfie
                    <input type="file" name="selfie_image" class="d-none kyc-file-input" accept="image/*" required>
                  </label>
                </form>
              </div>
              @endif
            @endif
            <div class="user-info text-center">
              <h5>{{ $client->client_name ?? 'Client Name' }}</h5>
              <span class="badge bg-label-{{ $stats['kyc']['badge'] ?? 'secondary' }} rounded-pill">
                {{ $stats['kyc']['label'] ?? 'Pending' }}
              </span>
            </div>
          </div>
        </div>
        <div class="d-flex justify-content-center justify-content-lg-between flex-wrap flex-lg-nowrap my-6 gap-sm-6 gap-4">
          <div class="d-flex align-items-center gap-4">
            <div class="avatar">
              <div class="avatar-initial bg-label-primary rounded-3">
                <i class="icon-base ri ri-money-dollar-circle-line icon-24px"></i>
              </div>
            </div>
            <div>
              <h5 class="mb-0">{{ $stats['applications'] ?? 0 }}</h5>
              <span class="text-muted small">Applications</span>
            </div>
          </div>
          <div class="d-flex align-items-center gap-4">
            <div class="avatar">
              <div class="avatar-initial bg-label-primary rounded-3">
                <i class="icon-base ri ri-file-text-line icon-24px"></i>
              </div>
            </div>
            <div>
              <h5 class="mb-0">{{ $stats['loans'] ?? 0 }}</h5>
              <span class="text-muted small">Total Loans</span>
            </div>
          </div>
        </div>
        <div class="d-flex flex-column gap-4">
          <div class="border rounded-3 p-4">
            <small class="text-primary text-uppercase fw-semibold d-block mb-3">Identity Details</small>
            <div class="row g-4">
              <div class="col-sm-6">
                <small class="text-muted text-uppercase">Aadhaar Number</small>
                <p class="mb-0 text-heading">{{ $client->aadhaar_number ?? 'N/A' }}</p>
              </div>
              <div class="col-sm-6">
                <small class="text-muted text-uppercase">PAN Number</small>
                <p class="mb-0 text-heading">{{ optional($client->kycDetail)->pan_number ?? 'N/A' }}</p>
              </div>
            </div>
          </div>

          <div class="border rounded-3 p-4">
            <small class="text-primary text-uppercase fw-semibold d-block mb-3">Client Information</small>
            <div class="row g-4">
              <div class="col-sm-6">
                <small class="text-muted text-uppercase">Mobile Number</small>
                <p class="mb-0 text-heading">{{ $client->client_phone ?? 'N/A' }}</p>
              </div>
              <div class="col-12">
                <small class="text-muted text-uppercase">Email</small>
                <p class="mb-0 text-heading" style="word-break: break-all;" title="{{ $client->client_email ?? 'N/A' }}">{{ $client->client_email ?? 'N/A' }}</p>

              </div>
              <!-- <div class="col-sm-6">
                <small class="text-muted text-uppercase">CIBIL Score</small>
                <div class="d-flex align-items-center gap-2 mt-1">
                  <p class="mb-0 text-heading">
                    <span id="cibilScoreBadge" class="badge bg-label-{{ ($client->cibil_score ?? 0) >= 700 ? 'success' : 'warning' }}">{{ $client->cibil_score ?? 'N/A' }}</span>
                  </p>
                  @if(!$client->cibil_score || $client->cibil_score == 'N/A')
                  <button type="button" class="btn btn-sm btn-icon btn-outline-primary rounded-pill" id="fetchCibilBtn" title="Fetch CIBIL Score">
                    <i class="ri-refresh-line"></i>
                  </button>
                  @endif
                </div>
              </div> -->
            </div>
          </div>
          
          @if(in_array($verificationStatus, ['unverified', 'pending']))
            @php
              $kycSkipped = optional($client->kycDetail)->kyc_skipped;
              $canApprove = $kycSkipped || (
                optional($client->kycDetail)->aadhaar_verified &&
                optional($client->kycDetail)->pan_verified &&
                optional($client->kycDetail)->bank_verified &&
                optional($client->kycDetail)->selfie_image &&
                optional($client->kycDetail)->aadhaar_image &&
                optional($client->kycDetail)->aadhaar_image_back
              );
            @endphp
            <!-- Show Approve/Reject buttons while verification is pending -->
            <div class="d-flex flex-column align-items-center gap-3">
                @if($kycSkipped)
                <div class="alert alert-info py-2 px-3 mb-0 w-100 text-center" style="font-size:.85rem;">
                    <i class="ri-skip-forward-line me-1"></i>
                    <strong>KYC Skipped</strong> — Aadhaar, PAN &amp; Bank verification not required.
                    @if(optional($client->kycDetail)->kyc_skip_remarks)
                        <div class="text-muted mt-1">{{ $client->kycDetail->kyc_skip_remarks }}</div>
                    @endif
                </div>
                @endif
                <div class="d-flex justify-content-center flex-wrap gap-2" id="kyc-action-buttons">
                  @if($canApprove)
                    <button type="button" class="btn btn-success btn-approve-kyc" data-bs-toggle="modal" data-bs-target="#approveModal">
                      <i class="icon-base ri ri-check-line me-1"></i>
                      Approve KYC
                    </button>
                  @endif
                  @if(!$kycSkipped)
                    <button type="button" class="btn btn-warning" id="btnSkipKyc" data-bs-toggle="modal" data-bs-target="#skipKycModal">
                      <i class="icon-base ri ri-skip-forward-line me-1"></i>
                      Skip KYC
                    </button>
                  @endif
                  <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                    <i class="icon-base ri ri-close-line me-1"></i>
                    Reject
                  </button>
                </div>
            </div>
          @else
            <!-- Show status badge after decision -->
            <div class="text-center mt-4">
              @if($verificationStatus === 'verified')
                <div class="mb-3">
                  <i class="icon-base ri ri-checkbox-circle-line text-success" style="font-size: 48px;"></i>
                </div>
                <h6 class="mb-2">Verification Approved</h6>
                <span class="badge bg-label-success mb-4">Verified</span>
                
                <div class="card bg-label-primary border-0 shadow-none mt-4 overflow-hidden">
                  <div class="card-body p-4 position-relative">
                    <div class="d-flex align-items-start justify-content-between mb-4">
                      <div class="avatar">
                        <div class="avatar-initial bg-primary rounded-3">
                          <i class="ri-hand-coin-line icon-24px"></i>
                        </div>
                      </div>
                    </div>
                    <h5 class="mb-2">Eligibility Ready</h5>
                    <p class="mb-4 small">KYC is verified. You can now process a new loan or chit application for this client.</p>
                    <div class="d-flex flex-column gap-2">
                      <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#modalApplyLoan">
                        <i class="ri-add-line me-1"></i> Apply for Loan
                      </button>
                      <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#modalApplyChit">
                        <i class="ri-add-line me-1"></i> Apply for Chit
                      </button>
                    </div>
                    </div>
                </div>
                <p class="text-muted mt-4 mb-0 small">KYC has been successfully verified</p>
              @elseif($verificationStatus === 'inactive' || $verificationStatus === 'rejected')
                <div class="mb-3">
                  <i class="icon-base ri ri-close-circle-line text-danger" style="font-size: 48px;"></i>
                </div>
                <h6 class="mb-2 text-danger">Verification Rejected</h6>
                <span class="badge bg-label-danger">Rejected</span>
                <p class="text-danger mt-2 mb-0 small">KYC verification was rejected</p>
                @php
                  $kycAttempts = (int) (optional($client->kycDetail)->attempt_no ?: 1);
                  $canReapply = optional($client->kycDetail)->status === 'rejected' && $kycAttempts < 3;
                @endphp
                @if($canReapply)
                  <button type="button" id="btnAllowReapply" class="btn btn-primary w-100 mt-3"
                          data-url="{{ route('verification-kyc-rekyc', $client->id) }}">
                    <i class="ri-refresh-line me-1"></i> Allow Re-apply
                  </button>
                  <p class="text-muted mt-2 mb-0 small">Attempt {{ $kycAttempts }} of 3 used</p>
                @elseif(optional($client->kycDetail)->status === 'rejected')
                  <p class="text-muted mt-3 mb-0 small">Maximum re-apply attempts (3) reached.</p>
                @endif
              @elseif($verificationStatus === 'active')
                <div class="mb-3">
                  <i class="icon-base ri ri-checkbox-circle-line text-success" style="font-size: 48px;"></i>
                </div>
                <h6 class="mb-2">Account Active</h6>
                <span class="badge bg-label-success">Active</span>
              @elseif($verificationStatus === 'blacklist')
                <div class="mb-3">
                  <i class="icon-base ri ri-error-warning-line text-dark" style="font-size: 48px;"></i>
                </div>
                <h6 class="mb-2">Account Blacklisted</h6>
                <span class="badge bg-label-dark">Blacklist</span>
                <p class="text-muted mt-2 mb-0 small">This account has been blacklisted</p>
              @endif
            </div>
          @endif
        </div>
      </div>
    </div>
    <!-- /User Card -->
  </div>
  <!--/ User Sidebar -->

  <!-- User Content -->
  <div class="col-xl-8 col-lg-7 col-md-7 order-0 order-md-1">
    @if(!$client->kycDetail?->kyc_skipped && (!$client->kycDetail || !$client->kycDetail->selfie_image || !$client->kycDetail->aadhaar_image || !$client->kycDetail->aadhaar_image_back))
      <div class="alert alert-warning alert-dismissible d-flex align-items-center mb-6" role="alert" id="missing-docs-alert">
        <span class="alert-icon text-warning me-2">
          <i class="icon-base ri ri-error-warning-line icon-22px"></i>
        </span>
        <div>
          <h6 class="alert-heading mb-1 fw-bold">Missing Mandatory Documents</h6>
          <span>Selfie, Aadhaar Front, and Aadhaar Back must be uploaded to approve KYC. Missing: 
            <strong>
              @php
                $missing = [];
                if (!$client->kycDetail || !$client->kycDetail->selfie_image) $missing[] = 'Selfie';
                if (!$client->kycDetail || !$client->kycDetail->aadhaar_image) $missing[] = 'Aadhaar Front';
                if (!$client->kycDetail || !$client->kycDetail->aadhaar_image_back) $missing[] = 'Aadhaar Back';
                echo implode(', ', $missing);
              @endphp
            </strong>.
          </span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    @endif

    <!-- KYC Content -->
    <div class="card mb-6">
      <h5 class="card-header">KYC Documents</h5>
      <div class="card-body">
        <div class="row g-4">
          <!-- Aadhar Card -->
          <div class="col-md-6">
            <div class="card h-100 border shadow-none">
              <div class="card-header border-bottom p-0">
                <div class="nav-align-top">
                  <ul class="nav nav-tabs nav-fill" role="tablist">
                    <li class="nav-item">
                      <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-aadhar-details" aria-controls="navs-aadhar-details" aria-selected="true">
                        <i class="ri-file-list-3-line me-1"></i> Details
                      </button>
                    </li>
                    <li class="nav-item">
                      <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-aadhar-document" aria-controls="navs-aadhar-document" aria-selected="false">
                        <i class="ri-image-line me-1"></i> Document
                      </button>
                    </li>
                  </ul>
                </div>
              </div>
              <div class="tab-content border-0 p-0">
                <div class="tab-pane fade show active p-4" id="navs-aadhar-details" role="tabpanel">
                  <div class="d-flex align-items-center mb-4">
                    <img src="{{asset('assets/img/logos/aadhar logo.png')}}" alt="Aadhar Logo" height="40" class="me-3" />
                    <div>
                      <small class="text-primary text-uppercase fw-semibold d-block">Identity Card</small>
                      <h6 class="mb-0">Aadhaar Card</h6>
                    </div>
                  </div>
                  <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                    <div>
                      <small class="text-muted text-uppercase d-block mb-1">Verification Status</small>
                      @if(optional($client->kycDetail)->aadhaar_verified)
                        <span class="badge bg-label-success"><i class="ri-checkbox-circle-line me-1"></i> Verified via API</span>
                      @else
                        <span class="badge bg-label-warning" id="badge-aadhaar-status"><i class="ri-error-warning-line me-1"></i> Pending Verification</span>
                      @endif
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                      @if(!optional($client->kycDetail)->aadhaar_verified)
                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 btn-edit-section" data-section="aadhaar" id="btn-edit-aadhaar">
                          <i class="ri-edit-line me-1"></i> Edit
                        </button>
                        <button type="button" class="btn btn-sm btn-success py-1 px-2 btn-save-section d-none" data-section="aadhaar" id="btn-save-aadhaar">
                          <i class="ri-save-line me-1"></i> Save
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 btn-cancel-section d-none" data-section="aadhaar" id="btn-cancel-aadhaar">
                          Cancel
                        </button>
                        <button type="button" class="btn btn-sm btn-primary py-1 px-2 {{ !$client->aadhaar_number ? 'd-none' : '' }}" id="btnTriggerAadhaarVerify">
                          <i class="ri-shield-keyhole-line me-1"></i> Verify
                        </button>
                      @endif
                    </div>
                  </div>
                  <div class="mb-3">
                    <small class="text-muted text-uppercase">Aadhaar Number</small>
                    <p class="mb-0 text-heading static-field-aadhaar" id="p-aadhaar-number">{{ $client->aadhaar_number ?? 'N/A' }}</p>
                    <input type="text" class="form-control form-control-sm edit-field-aadhaar d-none" id="input-aadhaar-number" value="{{ $client->aadhaar_number }}" placeholder="Enter 12-digit Aadhaar" maxlength="12" />
                  </div>
                  <div>
                    <small class="text-muted text-uppercase">Name</small>
                    <p class="mb-0 text-heading" id="p-aadhaar-name">{{ optional($client->kycDetail)->aadhaar_name ?? 'N/A' }}</p>
                  </div>
                </div>
                <div class="tab-pane fade p-2" id="navs-aadhar-document" role="tabpanel">
                  <div class="row g-2">
                    <div class="col-md-6" id="container-aadhaar_image">
                      <p class="small text-muted mb-1 text-center">Front Side</p>
                      @if(optional($client->kycDetail)->aadhaar_image)
                        <div class="text-center bg-light rounded-3 p-2 d-flex align-items-center justify-content-center" style="min-height: 200px;">
                          <img src="{{ asset('storage/' . $client->kycDetail->aadhaar_image) }}" class="img-fluid rounded shadow-sm" style="max-height: 250px; object-fit: contain;" alt="Aadhaar Front" />
                        </div>
                        <div class="d-flex gap-2 justify-content-center align-items-center mt-2">
                          <a href="{{ asset('storage/' . $client->kycDetail->aadhaar_image) }}" target="_blank" class="btn btn-sm btn-icon btn-primary rounded-circle shadow-xs" title="View Document" data-bs-toggle="tooltip"><i class="ri-eye-line fs-5"></i></a>
                          <a href="{{ asset('storage/' . $client->kycDetail->aadhaar_image) }}" download class="btn btn-sm btn-icon btn-outline-primary rounded-circle shadow-xs" title="Save / Download" data-bs-toggle="tooltip"><i class="ri-download-line fs-5"></i></a>
                          @if($verificationStatus !== 'verified')
                            <form action="{{ route('verification-kyc-update', $client->id) }}" method="POST" enctype="multipart/form-data" class="d-inline-block">
                              @csrf
                              <label class="btn btn-sm btn-icon btn-outline-success rounded-circle shadow-xs mb-0 cursor-pointer" title="Upload / Replace Document" data-bs-toggle="tooltip">
                                <i class="ri-upload-2-line fs-5"></i>
                                <input type="file" name="aadhaar_image" class="d-none kyc-file-input" accept="image/*">
                              </label>
                            </form>
                          @endif
                        </div>
                      @else
                        <div class="text-center py-4 border rounded-3 px-3 bg-light-subtle">
                          <i class="ri-image-add-line text-muted" style="font-size: 36px;"></i>
                          <div class="mt-2 text-danger small fw-semibold"><i class="ri-error-warning-line me-1"></i> Aadhaar Front Missing</div>
                          @if($verificationStatus !== 'verified')
                            <form action="{{ route('verification-kyc-update', $client->id) }}" method="POST" enctype="multipart/form-data" class="mt-2">
                              @csrf
                              <label class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-xs cursor-pointer mb-0">
                                <i class="ri-upload-cloud-2-line me-1"></i> Upload Front
                                <input type="file" name="aadhaar_image" class="d-none kyc-file-input" accept="image/*" required>
                              </label>
                            </form>
                          @endif
                        </div>
                      @endif
                    </div>
                    <div class="col-md-6" id="container-aadhaar_image_back">
                      <p class="small text-muted mb-1 text-center">Back Side</p>
                      @if(optional($client->kycDetail)->aadhaar_image_back)
                        <div class="text-center bg-light rounded-3 p-2 d-flex align-items-center justify-content-center" style="min-height: 200px;">
                          <img src="{{ asset('storage/' . $client->kycDetail->aadhaar_image_back) }}" class="img-fluid rounded shadow-sm" style="max-height: 250px; object-fit: contain;" alt="Aadhaar Back" />
                        </div>
                        <div class="d-flex gap-2 justify-content-center align-items-center mt-2">
                          <a href="{{ asset('storage/' . $client->kycDetail->aadhaar_image_back) }}" target="_blank" class="btn btn-sm btn-icon btn-primary rounded-circle shadow-xs" title="View Document" data-bs-toggle="tooltip"><i class="ri-eye-line fs-5"></i></a>
                          <a href="{{ asset('storage/' . $client->kycDetail->aadhaar_image_back) }}" download class="btn btn-sm btn-icon btn-outline-primary rounded-circle shadow-xs" title="Save / Download" data-bs-toggle="tooltip"><i class="ri-download-line fs-5"></i></a>
                          @if($verificationStatus !== 'verified')
                            <form action="{{ route('verification-kyc-update', $client->id) }}" method="POST" enctype="multipart/form-data" class="d-inline-block">
                              @csrf
                              <label class="btn btn-sm btn-icon btn-outline-success rounded-circle shadow-xs mb-0 cursor-pointer" title="Upload / Replace Document" data-bs-toggle="tooltip">
                                <i class="ri-upload-2-line fs-5"></i>
                                <input type="file" name="aadhaar_image_back" class="d-none kyc-file-input" accept="image/*">
                              </label>
                            </form>
                          @endif
                        </div>
                      @else
                        <div class="text-center py-4 border rounded-3 px-3 bg-light-subtle">
                          <i class="ri-image-add-line text-muted" style="font-size: 36px;"></i>
                          <div class="mt-2 text-danger small fw-semibold"><i class="ri-error-warning-line me-1"></i> Aadhaar Back Missing</div>
                          @if($verificationStatus !== 'verified')
                            <form action="{{ route('verification-kyc-update', $client->id) }}" method="POST" enctype="multipart/form-data" class="mt-2">
                              @csrf
                              <label class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-xs cursor-pointer mb-0">
                                <i class="ri-upload-cloud-2-line me-1"></i> Upload Back
                                <input type="file" name="aadhaar_image_back" class="d-none kyc-file-input" accept="image/*" required>
                              </label>
                            </form>
                          @endif
                        </div>
                      @endif
                    </div>
                  </div>

                </div>
              </div>
            </div>
          </div>
          <!-- /Aadhar Card -->

          <!-- PAN Card -->
          <div class="col-md-6">
            <div class="card h-100 border shadow-none">
              <div class="card-header border-bottom p-0">
                <div class="nav-align-top">
                  <ul class="nav nav-tabs nav-fill" role="tablist">
                    <li class="nav-item">
                      <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pan-details" aria-controls="navs-pan-details" aria-selected="true">
                        <i class="ri-file-list-3-line me-1"></i> Details
                      </button>
                    </li>
                    <li class="nav-item">
                      <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pan-document" aria-controls="navs-pan-document" aria-selected="false">
                        <i class="ri-image-line me-1"></i> Document
                      </button>
                    </li>
                  </ul>
                </div>
              </div>
              <div class="tab-content border-0 p-0">
                <div class="tab-pane fade show active p-4" id="navs-pan-details" role="tabpanel">
                  <div class="d-flex align-items-center mb-4">
                    <img src="{{asset('assets/img/logos/pan logo.png')}}" alt="PAN Logo" height="40" class="me-3" />
                    <div>
                      <small class="text-primary text-uppercase fw-semibold d-block">Identity Card</small>
                      <h6 class="mb-0">PAN Card</h6>
                    </div>
                  </div>
                  <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                    <div>
                      <small class="text-muted text-uppercase d-block mb-1">Verification Status</small>
                      @if(optional($client->kycDetail)->pan_verified)
                        <span class="badge bg-label-success"><i class="ri-checkbox-circle-line me-1"></i> Verified via API</span>
                      @else
                        <span class="badge bg-label-warning" id="badge-pan-status"><i class="ri-error-warning-line me-1"></i> Pending Verification</span>
                      @endif
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                      @if(!optional($client->kycDetail)->pan_verified)
                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 btn-edit-section" data-section="pan" id="btn-edit-pan">
                          <i class="ri-edit-line me-1"></i> Edit
                        </button>
                        <button type="button" class="btn btn-sm btn-success py-1 px-2 btn-save-section d-none" data-section="pan" id="btn-save-pan">
                          <i class="ri-save-line me-1"></i> Save
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 btn-cancel-section d-none" data-section="pan" id="btn-cancel-pan">
                          Cancel
                        </button>
                        <button type="button" class="btn btn-sm btn-primary py-1 px-2 {{ !optional($client->kycDetail)->pan_number ? 'd-none' : '' }}" id="btnTriggerPanVerify">
                          <i class="ri-shield-keyhole-line me-1"></i> Verify
                        </button>
                      @endif
                    </div>
                  </div>
                  <div class="mb-3">
                    <small class="text-muted text-uppercase">PAN Number</small>
                    <p class="mb-0 text-heading static-field-pan" id="p-pan-number">{{ optional($client->kycDetail)->pan_number ?? 'N/A' }}</p>
                    <input type="text" class="form-control form-control-sm edit-field-pan d-none" id="input-pan-number" value="{{ optional($client->kycDetail)->pan_number }}" placeholder="Enter 10-digit PAN" maxlength="10" />
                  </div>
                  <div>
                    <small class="text-muted text-uppercase">Name</small>
                    <p class="mb-0 text-heading static-field-pan" id="p-pan-name">{{ optional($client->kycDetail)->pan_name ?? 'N/A' }}</p>
                    <input type="text" class="form-control form-control-sm edit-field-pan d-none" id="input-pan-name" value="{{ optional($client->kycDetail)->pan_name }}" placeholder="Enter Name on PAN Card" />
                  </div>
                </div>
                <div class="tab-pane fade p-2" id="navs-pan-document" role="tabpanel">
                  <div id="container-pan_image">
                    @if(optional($client->kycDetail)->pan_image)
                      <div class="text-center bg-light rounded-3 p-2 d-flex align-items-center justify-content-center" style="min-height: 200px;">
                        <img src="{{ asset('storage/' . $client->kycDetail->pan_image) }}" class="img-fluid rounded shadow-sm" style="max-height: 250px; object-fit: contain;" alt="PAN Card" />
                      </div>
                      <div class="d-flex gap-2 justify-content-center align-items-center mt-2">
                        <a href="{{ asset('storage/' . $client->kycDetail->pan_image) }}" target="_blank" class="btn btn-sm btn-icon btn-primary rounded-circle shadow-xs" title="View Document" data-bs-toggle="tooltip"><i class="ri-eye-line fs-5"></i></a>
                        <a href="{{ asset('storage/' . $client->kycDetail->pan_image) }}" download class="btn btn-sm btn-icon btn-outline-primary rounded-circle shadow-xs" title="Save / Download" data-bs-toggle="tooltip"><i class="ri-download-line fs-5"></i></a>
                        @if($verificationStatus !== 'verified')
                          <form action="{{ route('verification-kyc-update', $client->id) }}" method="POST" enctype="multipart/form-data" class="d-inline-block">
                            @csrf
                            <label class="btn btn-sm btn-icon btn-outline-success rounded-circle shadow-xs mb-0 cursor-pointer" title="Upload / Replace Document" data-bs-toggle="tooltip">
                              <i class="ri-upload-2-line fs-5"></i>
                              <input type="file" name="pan_image" class="d-none kyc-file-input" accept="image/*">
                            </label>
                          </form>
                        @endif
                      </div>
                    @else
                      <div class="text-center py-4 border rounded-3 px-3 bg-light-subtle">
                        <i class="ri-image-add-line text-muted" style="font-size: 36px;"></i>
                        <div class="mt-2 text-danger small fw-semibold"><i class="ri-error-warning-line me-1"></i> PAN Card Missing</div>
                        @if($verificationStatus !== 'verified')
                          <form action="{{ route('verification-kyc-update', $client->id) }}" method="POST" enctype="multipart/form-data" class="mt-2">
                            @csrf
                            <label class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-xs cursor-pointer mb-0">
                              <i class="ri-upload-cloud-2-line me-1"></i> Upload PAN Card
                              <input type="file" name="pan_image" class="d-none kyc-file-input" accept="image/*" required>
                            </label>
                          </form>
                        @endif
                      </div>
                    @endif
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- /PAN Card -->

          <!-- Bank Details -->
          <div class="col-12">
            <div class="border rounded-3 p-4">
              <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-2">
                <div class="d-flex align-items-center">
                  <i class="icon-base ri ri-bank-line icon-40px text-primary me-3"></i>
                  <div>
                    <small class="text-primary text-uppercase fw-semibold d-block">Financial Information</small>
                    <h6 class="mb-0">Bank Account Details</h6>
                  </div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                  @if(optional($client->kycDetail)->bank_verified)
                    <span class="badge bg-label-success"><i class="ri-checkbox-circle-line me-1"></i> Verified via API</span>
                  @else
                    <span class="badge bg-label-warning me-2" id="badge-bank-status"><i class="ri-error-warning-line me-1"></i> Pending Verification</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 btn-edit-section" data-section="bank" id="btn-edit-bank">
                      <i class="ri-edit-line me-1"></i> Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-success py-1 px-2 btn-save-section d-none" data-section="bank" id="btn-save-bank">
                      <i class="ri-save-line me-1"></i> Save
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 btn-cancel-section d-none" data-section="bank" id="btn-cancel-bank">
                      Cancel
                    </button>
                    <button type="button" class="btn btn-sm btn-primary py-1 px-2 {{ (!optional($client->kycDetail)->account_number || !optional($client->kycDetail)->ifsc_code) ? 'd-none' : '' }}" id="btnTriggerBankVerify">
                      <i class="ri-shield-keyhole-line me-1"></i> Verify
                    </button>
                  @endif
                </div>
              </div>

              <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                  <small class="text-muted text-uppercase">Account Holder Name</small>
                  <p class="mb-0 text-heading static-field-bank" id="p-bank-holder">{{ optional($client->kycDetail)->account_holder_name ?? 'N/A' }}</p>
                  <input type="text" class="form-control form-control-sm edit-field-bank d-none" id="input-bank-holder" value="{{ optional($client->kycDetail)->account_holder_name }}" placeholder="Account Holder Name" />
                </div>
                <div class="col-md-6 col-lg-3">
                  <small class="text-muted text-uppercase">Account Number</small>
                  <p class="mb-0 text-heading static-field-bank" id="p-bank-account">{{ optional($client->kycDetail)->account_number ?? 'N/A' }}</p>
                  <input type="text" class="form-control form-control-sm edit-field-bank d-none" id="input-bank-account" value="{{ optional($client->kycDetail)->account_number }}" placeholder="Account Number" />
                </div>
                <div class="col-md-6 col-lg-3">
                  <small class="text-muted text-uppercase">IFSC Code</small>
                  <p class="mb-0 text-heading static-field-bank" id="p-bank-ifsc">{{ optional($client->kycDetail)->ifsc_code ?? 'N/A' }}</p>
                  <input type="text" class="form-control form-control-sm edit-field-bank d-none" id="input-bank-ifsc" value="{{ optional($client->kycDetail)->ifsc_code }}" placeholder="IFSC Code" />
                </div>
                <div class="col-md-6 col-lg-3">
                  <small class="text-muted text-uppercase">Bank Name</small>
                  <p class="mb-0 text-heading static-field-bank" id="p-bank-name">{{ optional($client->kycDetail)->bank_name ?? 'N/A' }}</p>
                  <input type="text" class="form-control form-control-sm edit-field-bank d-none" id="input-bank-name" value="{{ optional($client->kycDetail)->bank_name }}" placeholder="Bank Name" />
                </div>
                <div class="col-md-6 col-lg-3">
                  <small class="text-muted text-uppercase">Branch Name <span class="text-danger">*</span></small>
                  <p class="mb-0 text-heading static-field-bank" id="p-bank-branch">{{ optional($client->kycDetail)->branch_name ?? 'N/A' }}</p>
                  <input type="text" class="form-control form-control-sm edit-field-bank d-none" id="input-bank-branch" value="{{ optional($client->kycDetail)->branch_name }}" placeholder="Enter branch manually" />
                  <small class="text-muted d-block mt-1">Enter manually if bank API does not return branch.</small>
                </div>
              </div>
            </div>
          </div>
          <!-- /Bank Details -->

          <!-- Bank Statement -->
          <div class="col-12">
            <div class="border rounded-3 p-4" id="container-bank_statement">
              <div class="d-flex align-items-center mb-4">
                <i class="icon-base ri ri-file-text-line icon-40px text-primary me-3"></i>
                <div>
                  <small class="text-primary text-uppercase fw-semibold d-block">Financial Document</small>
                  <h6 class="mb-0">Bank Statement</h6>
                </div>
              </div>

              @if(optional($client->kycDetail)->bank_statement)
                @php
                  $bankStatement = $client->kycDetail->bank_statement;
                  $isBase64 = substr($bankStatement, 0, 5) === 'data:';
                  $fileExtension = '';
                  
                  if ($isBase64) {
                    // Extract file type from base64 data
                    preg_match('/data:([^;]+);/', $bankStatement, $matches);
                    $mimeType = $matches[1] ?? '';
                    $fileExtension = str_contains($mimeType, 'pdf') ? 'pdf' : 'image';
                  } else {
                    // Get extension from file path
                    $fileExtension = strtolower(pathinfo($bankStatement, PATHINFO_EXTENSION));
                  }
                @endphp

                <div class="row g-4">
                  <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between p-3 bg-label-primary rounded-3">
                      <div class="d-flex align-items-center gap-3">
                        <div class="avatar">
                          <div class="avatar-initial bg-primary rounded-3">
                            @if($fileExtension === 'pdf' || str_contains($fileExtension, 'pdf'))
                              <i class="icon-base ri ri-file-pdf-line icon-24px"></i>
                            @else
                              <i class="icon-base ri ri-image-line icon-24px"></i>
                            @endif
                          </div>
                        </div>
                        <div>
                          <h6 class="mb-0">Bank Statement Document</h6>
                          <small class="text-muted">
                            @if($fileExtension === 'pdf' || str_contains($fileExtension, 'pdf'))
                              PDF Document
                            @else
                              Image File
                            @endif
                          </small>
                        </div>
                      </div>
                      <div class="d-flex gap-2 align-items-center">
                        @if($isBase64)
                          <button type="button" class="btn btn-sm btn-icon btn-primary rounded-circle shadow-xs" onclick="viewBankStatement()" title="View Document" data-bs-toggle="tooltip">
                            <i class="icon-base ri ri-eye-line fs-5"></i>
                          </button>
                          <button type="button" class="btn btn-sm btn-icon btn-outline-primary rounded-circle shadow-xs" onclick="downloadBankStatement()" title="Save / Download" data-bs-toggle="tooltip">
                            <i class="icon-base ri ri-download-line fs-5"></i>
                          </button>
                        @else
                          <a href="{{ asset('storage/' . $bankStatement) }}" target="_blank" class="btn btn-sm btn-icon btn-primary rounded-circle shadow-xs" title="View Document" data-bs-toggle="tooltip">
                            <i class="icon-base ri ri-eye-line fs-5"></i>
                          </a>
                          <a href="{{ asset('storage/' . $bankStatement) }}" download class="btn btn-sm btn-icon btn-outline-primary rounded-circle shadow-xs" title="Save / Download" data-bs-toggle="tooltip">
                            <i class="icon-base ri ri-download-line fs-5"></i>
                          </a>
                        @endif
                        @if($verificationStatus !== 'verified')
                          <form action="{{ route('verification-kyc-update', $client->id) }}" method="POST" enctype="multipart/form-data" class="d-inline-block ms-1">
                            @csrf
                            <label class="btn btn-sm btn-icon btn-outline-success rounded-circle shadow-xs mb-0 cursor-pointer" title="Upload / Replace Statement" data-bs-toggle="tooltip">
                              <i class="ri-upload-2-line fs-5"></i>
                              <input type="file" name="bank_statement" class="d-none kyc-file-input" accept="image/*,application/pdf">
                            </label>
                          </form>
                        @endif
                      </div>
                    </div>
                  </div>
                </div>

                @if($isBase64)
                  <script>
                    function viewBankStatement() {
                      const data = @json($bankStatement);
                      window.open(data, '_blank');
                    }

                    function downloadBankStatement() {
                      const data = @json($bankStatement);
                      const link = document.createElement('a');
                      link.href = data;
                      link.download = 'bank_statement_{{ $client->client_name }}.{{ $fileExtension === "pdf" ? "pdf" : "jpg" }}';
                      document.body.appendChild(link);
                      link.click();
                      document.body.removeChild(link);
                    }
                  </script>
                @endif
              @else
                <div class="text-center py-4 border rounded-3 px-3 bg-light-subtle">
                  <i class="icon-base ri ri-file-forbid-line text-muted" style="font-size: 36px;"></i>
                  <div class="mt-2 text-danger small fw-semibold"><i class="ri-error-warning-line me-1"></i> Bank Statement Missing</div>
                  @if($verificationStatus !== 'verified')
                    <form action="{{ route('verification-kyc-update', $client->id) }}" method="POST" enctype="multipart/form-data" class="mt-2">
                      @csrf
                      <label class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-xs cursor-pointer mb-0">
                        <i class="ri-upload-cloud-2-line me-1"></i> Upload Statement
                        <input type="file" name="bank_statement" class="d-none kyc-file-input" accept="image/*,application/pdf" required>
                      </label>
                    </form>
                  @endif
                </div>
              @endif
            </div>
          </div>
          <!-- /Bank Statement -->

          <!-- Employment Documents -->
          @if($client->employeeInformation)
            <div class="col-12">
              <div class="border rounded-3 p-4">
                <div class="d-flex align-items-center mb-4">
                  <i class="icon-base ri ri-briefcase-line icon-40px text-primary me-3"></i>
                  <div>
                    <small class="text-primary text-uppercase fw-semibold d-block">Employment Verification</small>
                    <h6 class="mb-0">
                      @if($client->employeeInformation->employment_type === 'salaried')
                        Payslip Documents
                      @else
                        Business Proof Documents
                      @endif
                    </h6>
                  </div>
                </div>

                @php
                  $documents = [];
                  $documentType = '';
                  
                  if ($client->employeeInformation->employment_type === 'salaried' && !empty($client->employeeInformation->payslip_documents)) {
                    // Laravel auto-decodes JSON columns, check if already an array
                    $documents = is_array($client->employeeInformation->payslip_documents) 
                      ? $client->employeeInformation->payslip_documents 
                      : json_decode($client->employeeInformation->payslip_documents, true) ?? [];
                    $documentType = 'payslip';
                  } elseif ($client->employeeInformation->employment_type === 'self_employed' && !empty($client->employeeInformation->business_proof_documents)) {
                    // Laravel auto-decodes JSON columns, check if already an array
                    $documents = is_array($client->employeeInformation->business_proof_documents) 
                      ? $client->employeeInformation->business_proof_documents 
                      : json_decode($client->employeeInformation->business_proof_documents, true) ?? [];
                    $documentType = 'business_proof';
                  }
                @endphp

                @if(count($documents) > 0)
                  <div class="row g-3">
                    @foreach($documents as $index => $document)
                      @php
                        $isBase64 = substr($document, 0, 5) === 'data:';
                        $fileExtension = '';
                        
                        if ($isBase64) {
                          preg_match('/data:([^;]+);/', $document, $matches);
                          $mimeType = $matches[1] ?? '';
                          $fileExtension = str_contains($mimeType, 'pdf') ? 'pdf' : 'image';
                        } else {
                          $fileExtension = strtolower(pathinfo($document, PATHINFO_EXTENSION));
                        }
                      @endphp

                      <div class="col-12">
                        <div class="d-flex align-items-center justify-content-between p-3 bg-label-primary rounded-3">
                          <div class="d-flex align-items-center gap-3">
                            <div class="avatar">
                              <div class="avatar-initial bg-primary rounded-3">
                                @if($fileExtension === 'pdf' || str_contains($fileExtension, 'pdf'))
                                  <i class="icon-base ri ri-file-pdf-line icon-24px"></i>
                                @else
                                  <i class="icon-base ri ri-image-line icon-24px"></i>
                                @endif
                              </div>
                            </div>
                            <div>
                              <h6 class="mb-0">
                                @if($documentType === 'payslip')
                                  Payslip {{ $index + 1 }}
                                @else
                                  Business Proof {{ $index + 1 }}
                                @endif
                              </h6>
                              <small class="text-muted">
                                @if($fileExtension === 'pdf' || str_contains($fileExtension, 'pdf'))
                                  PDF Document
                                @else
                                  Image File
                                @endif
                              </small>
                            </div>
                          </div>
                          <div class="d-flex gap-2 align-items-center">
                            @if($isBase64)
                              <button type="button" class="btn btn-sm btn-icon btn-primary rounded-circle shadow-xs" onclick="viewEmploymentDoc{{ $index }}()" title="View Document" data-bs-toggle="tooltip">
                                <i class="icon-base ri ri-eye-line fs-5"></i>
                              </button>
                              <button type="button" class="btn btn-sm btn-icon btn-outline-primary rounded-circle shadow-xs" onclick="downloadEmploymentDoc{{ $index }}()" title="Save / Download" data-bs-toggle="tooltip">
                                <i class="icon-base ri ri-download-line fs-5"></i>
                              </button>
                            @else
                              <a href="{{ asset('storage/' . $document) }}" target="_blank" class="btn btn-sm btn-icon btn-primary rounded-circle shadow-xs" title="View Document" data-bs-toggle="tooltip">
                                <i class="icon-base ri ri-eye-line fs-5"></i>
                              </a>
                              <a href="{{ asset('storage/' . $document) }}" download class="btn btn-sm btn-icon btn-outline-primary rounded-circle shadow-xs" title="Save / Download" data-bs-toggle="tooltip">
                                <i class="icon-base ri ri-download-line fs-5"></i>
                              </a>
                            @endif
                          </div>
                        </div>
                      </div>

                      @if($isBase64)
                        <script>
                          function viewEmploymentDoc{{ $index }}() {
                            const data = @json($document);
                            window.open(data, '_blank');
                          }

                          function downloadEmploymentDoc{{ $index }}() {
                            const data = @json($document);
                            const link = document.createElement('a');
                            link.href = data;
                            link.download = '{{ $documentType }}_{{ $index + 1 }}_{{ $client->client_name }}.{{ $fileExtension === "pdf" ? "pdf" : "jpg" }}';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                          }
                        </script>
                      @endif
                    @endforeach
                  </div>
                @else
                  <div class="text-center py-5">
                    <i class="icon-base ri ri-file-forbid-line text-muted" style="font-size: 48px;"></i>
                    <p class="text-muted mt-3 mb-0">
                      @if($client->employeeInformation->employment_type === 'salaried')
                        No payslip documents uploaded
                      @else
                        No business proof documents uploaded
                      @endif
                    </p>
                  </div>
                @endif
              </div>
            </div>
          @endif
          <!-- /Employment Documents -->
        </div>
      </div>
    </div>
    <!-- /KYC Content -->
  </div>
  <!--/ User Content -->
</div>

<!-- Skip KYC Confirmation Modal -->
<div class="modal fade" id="skipKycModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form id="skipKycForm" action="{{ route('verification-kyc-skip', $client->id) }}" method="POST">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Skip KYC Verification</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="text-center mb-4">
            <i class="icon-base ri ri-skip-forward-line text-warning" style="font-size: 48px;"></i>
          </div>
          <h6 class="text-center mb-2">Bypass verification for this client?</h6>
          <p class="text-center text-muted mb-3" style="font-size:.9rem;">
            Aadhaar, PAN, and Bank verification will not be required.
            The <strong>Approve KYC</strong> button will be enabled after skipping.
          </p>
          <div class="mb-0">
            <label for="skip_kyc_remarks" class="form-label fw-medium">Remarks (Optional)</label>
            <textarea name="remarks" id="skip_kyc_remarks" class="form-control" rows="2" placeholder="Reason for skipping KYC verification"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning" id="confirmSkipKycBtn">
            <i class="icon-base ri ri-skip-forward-line me-1"></i> Skip KYC
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Approve Confirmation Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form action="{{ route('verification-kyc-approve', $client->id) }}" method="POST">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCenterTitle">Confirm Approval</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="text-center mb-4">
            <i class="icon-base ri ri-checkbox-circle-line text-success" style="font-size: 48px;"></i>
          </div>
          <h5 class="text-center mb-2">Are you sure?</h5>
          <p class="text-center">Do you really want to approve this KYC verification?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Approve</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Delete/Reject Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form action="{{ route('verification-kyc-reject', $client->id) }}" method="POST">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCenterTitle">Confirm Rejection</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="text-center mb-4">
            <i class="icon-base ri ri-close-circle-line text-danger" style="font-size: 48px;"></i>
          </div>
          <h5 class="text-center mb-2">Are you sure?</h5>
          <p class="text-center mb-4">Do you really want to reject this KYC verification? Client status will be updated to <strong>Inactive</strong>.</p>

          <div class="mb-3">
            <label for="reason" class="form-label fw-medium">Reason for Rejection <span class="text-danger">*</span></label>
            <textarea name="reason" id="reason" class="form-control" rows="3" placeholder="Enter reason for rejection" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            <i class="icon-base ri ri-close-line me-1"></i> Cancel
          </button>
          <button type="submit" class="btn btn-danger">
            <i class="icon-base ri ri-close-circle-line me-1"></i> Reject
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Loan Application Modal -->
@include('admin.clients.modals.modal-apply-loan')

<!-- Chit Application Modal -->
@include('admin.clients.modals.modal-apply-chit')

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-body text-center p-4">
        <div class="mb-4">
          <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle" style="width: 50px; height: 50px;">
            <i class="icon-base ri ri-check-line text-success" style="font-size: 100px;"></i>
          </div>
        </div>
        <h5 class="mb-2" id="successTitle">Success!</h5>
        <p class="text-muted mb-0" id="successMessage">Action completed successfully.</p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Aadhaar OTP Modal -->
<div class="modal fade" id="aadhaarOtpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-bottom py-3">
        <h6 class="modal-title">Enter Aadhaar OTP</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="aadhaarOtpForm">
        <div class="modal-body py-3 text-center">
          <p class="small text-muted mb-3">Please enter the 6-digit OTP sent to the registered mobile number.</p>
          <input type="hidden" id="aadhaar_request_id" name="request_id">
          <div class="mb-3">
            <input type="text" id="aadhaar_otp_input" class="form-control text-center fw-bold fs-4" placeholder="••••••" maxlength="6" required style="letter-spacing: 8px;">
          </div>
          <div id="aadhaarOtpError" class="text-danger small d-none mb-0"></div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitAadhaarOtp">
            <span class="spinner-border spinner-border-sm d-none me-1" role="status"></span>
            Verify
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@include('admin.clients.modals.modal-edit-kyc')

@if(request()->query('edit') === 'true')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var editKycModal = new bootstrap.Modal(document.getElementById('editKycModal'));
    editKycModal.show();
});
</script>
@endif

@endsection
