'use strict';

$(function () {
    const forecloseBtn = $('#forecloseBtn');
    const foreclosureModalElement = document.getElementById('foreclosureModal');
    const foreclosureModal = new bootstrap.Modal(foreclosureModalElement);
    const accountId = forecloseBtn.data('account-id');
    const accountNumber = forecloseBtn.data('account-number') || 'this loan account';

    let currentOutstanding = 0;
    let currentChargesPercent = 0;
    let currentInterestOutstanding = 0;
    let currentForeclosureCharges = 0;
    let currentTotalForeclosure = 0;
    let isOverrideMode = false;

    const roundRupee = (value) => Math.round(parseFloat(value) || 0);

    const formatRupee = (value) =>
        '₹' + roundRupee(value).toLocaleString('en-IN', { maximumFractionDigits: 0 });

    // UI Elements
    const ui = {
        modalTitle: $('#foreclosureModalTitle'),
        eligibilityAlert: $('#eligibilityAlert'),
        overrideSection: $('#overrideSection'),
        overrideTitle: $('#overrideTitle'),
        overrideDesc: $('#overrideDesc'),
        breakdownSection: $('#breakdownSection'),
        breakdownCard: $('#breakdownSection'),
        confirmationSection: $('#confirmationSection'),
        confirmationLabel: $('#confirmationLabel'),
        overrideBtn: $('#overrideBtn'),
        closeBtn: $('#closeBtn'),
        cancelBtn: $('#cancelBtn'),
        confirmBtn: $('#confirmForeclosureBtn'),
        confirmCheck: $('#confirmOverrideCheck'),
        eligibilityInput: $('#modalEligibilityMonths'),
        chargesInput: $('#modalChargesPercentage'),
        extraChargeInput: $('#modalExtraCharge'),
        reasonGroup: $('#foreclosureReasonGroup'),
        reasonInput: $('#foreclosureReason')
    };

    // Reset Modal State
    function resetModalState() {
        ui.modalTitle.text('Foreclose Loan Account');
        ui.eligibilityAlert.removeClass('d-none');
        ui.overrideSection.addClass('d-none');
        ui.overrideTitle.addClass('d-none');
        ui.overrideDesc.addClass('d-none');
        ui.breakdownSection.removeClass('d-none');
        ui.confirmationSection.addClass('d-none');
        ui.confirmCheck.prop('checked', false);
        ui.eligibilityInput.val('');
        ui.chargesInput.val('');
        ui.reasonGroup.addClass('d-none');
        ui.reasonInput.val('');
        isOverrideMode = false;

        // Reset buttons visibility
        ui.overrideBtn.addClass('d-none');
        ui.closeBtn.removeClass('d-none');
        ui.cancelBtn.addClass('d-none');
        ui.confirmBtn.addClass('d-none');
    }

    function updateConfirmButtonState() {
        const checkboxChecked = ui.confirmCheck.is(':checked');
        const hasReason = ui.reasonInput.val().trim().length > 0;
        ui.confirmBtn.prop('disabled', !(checkboxChecked && hasReason));
    }

    // Load foreclosure info
    forecloseBtn.on('click', function () {
        const originalBtnText = forecloseBtn.html();
        forecloseBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Loading...');

        $.ajax({
            url: baseUrl + 'loan/loan-accounts/' + accountId + '/foreclosure-info',
            type: 'GET',
            success: function (data) {
                forecloseBtn.prop('disabled', false).html(originalBtnText);
                resetModalState();

                currentOutstanding = roundRupee(data.outstanding_amount);
                currentInterestOutstanding = roundRupee(data.interest_outstanding);
                currentChargesPercent = parseFloat(data.charges_percentage) || 0;
                currentForeclosureCharges = roundRupee(data.foreclosure_charges);
                currentTotalForeclosure = roundRupee(data.total_amount);

                const unit = data.eligibility_unit || 'months';
                const capUnit = unit.charAt(0).toUpperCase() + unit.slice(1);
                $('.eligibility-unit-text').text(capUnit);

                // Find the breakdown interest item to set the custom interest label
                const interestItem = data.breakdown?.find(item => item.key === 'interest_outstanding');
                if (interestItem && interestItem.label) {
                    $('#interestOutstandingLabel').text(interestItem.label);
                } else {
                    $('#interestOutstandingLabel').text('Interest outstanding');
                }

                // Update Data
                $('#currentEligibility').text(data.eligibility_months);
                $('#currentCharges').text(data.charges_percentage);
                ui.eligibilityInput.val(data.paid_emis_count);
                ui.chargesInput.val(data.charges_percentage + '%');
                ui.extraChargeInput.val('0');
                updateBreakdown(currentOutstanding, currentChargesPercent, 0);

                if (data.has_partial_emi) {
                    // Partially Paid State - Completely Blocked
                    ui.eligibilityAlert.removeClass('alert-success alert-warning').addClass('alert-danger')
                        .html('<i class="icon-base ri ri-close-circle-line me-2"></i><div><strong>Foreclosure Blocked</strong><br><small>Ongoing EMI is partially paid. Foreclosure is not allowed for partially paid ongoing EMI. Please clear the pending EMI amount fully before attempting foreclosure.</small></div>');

                    ui.breakdownSection.addClass('d-none');
                    ui.overrideBtn.addClass('d-none');
                    ui.closeBtn.removeClass('d-none');
                    ui.cancelBtn.addClass('d-none');
                    ui.confirmBtn.addClass('d-none');
                    ui.confirmationSection.addClass('d-none');

                } else if (data.is_eligible) {
                    // Eligible State
                    ui.eligibilityAlert.removeClass('alert-danger').addClass('alert-success')
                        .html('<i class="icon-base ri ri-check-line me-2"></i><div><strong>Eligible for foreclosure</strong><br><small>' + data.paid_emis_count + ' of ' + data.eligibility_months + ' ' + unit + ' paid</small></div>');

                    ui.closeBtn.addClass('d-none');
                    ui.cancelBtn.removeClass('d-none');
                    ui.breakdownSection.removeClass('d-none');

                    // Show confirmation for eligible state
                    ui.confirmationSection.removeClass('d-none');
                    ui.confirmationLabel.text('I confirm to approve for the ' + accountNumber + ' to foreclose');
                    ui.confirmBtn.removeClass('d-none');
                    updateConfirmButtonState();

                } else {
                    // Not Eligible State
                    ui.eligibilityAlert.removeClass('alert-success').addClass('alert-danger')
                        .html('<i class="icon-base ri ri-close-circle-line me-2"></i><div><strong>Not eligible</strong><br><small>Only ' + data.paid_emis_count + ' of ' + data.eligibility_months + ' ' + unit + ' paid</small></div>');

                    ui.breakdownSection.addClass('d-none');
                    ui.overrideBtn.removeClass('d-none');
                    ui.confirmBtn.prop('disabled', true);
                }

                foreclosureModal.show();
            },
            error: function (xhr) {
                forecloseBtn.prop('disabled', false).html(originalBtnText);
                const message = xhr.responseJSON?.message || 'Failed to load foreclosure information';
                showToast('danger', message);
            }
        });
    });

    // Handle Override Button Click
    ui.overrideBtn.on('click', function () {
        ui.modalTitle.text('Foreclose Loan Account (Override)');
        ui.eligibilityAlert.addClass('d-none'); // Hide alert
        ui.overrideSection.removeClass('d-none');
        ui.breakdownSection.removeClass('d-none');
        ui.extraChargeInput.val('0');
        updateBreakdown(currentOutstanding, currentChargesPercent, 0);
        isOverrideMode = true;

        ui.overrideBtn.addClass('d-none');
        ui.closeBtn.addClass('d-none');
        ui.cancelBtn.removeClass('d-none');

        // Show confirmation for override state
        ui.confirmationSection.removeClass('d-none');
        ui.confirmationLabel.text('I confirm that I want to override the foreclosure eligibility and/or charges for this loan account.');
        ui.confirmBtn.removeClass('d-none');
        ui.confirmBtn.prop('disabled', true);
        ui.confirmCheck.prop('checked', false);
        ui.reasonGroup.addClass('d-none');
        ui.reasonInput.val('');
        updateConfirmButtonState();
    });

    // Dynamic Calculation on Extra Charge Input Change
    ui.extraChargeInput.on('input', function () {
        const extraCharge = parseFloat($(this).val()) || 0;
        updateBreakdown(currentOutstanding, currentChargesPercent, extraCharge);
    });

    // Handle Cancel Button (Closes modal)
    ui.cancelBtn.on('click', function () {
        foreclosureModal.hide();
    });

    // Handle Checkbox
    ui.confirmCheck.on('change', function () {
        if (this.checked) {
            ui.reasonGroup.removeClass('d-none');
        } else {
            ui.reasonGroup.addClass('d-none');
            ui.reasonInput.val('');
        }
        updateConfirmButtonState();
    });

    ui.reasonInput.on('input', updateConfirmButtonState);

    // Helper to update breakdown text (amounts rounded to nearest rupee)
    function updateBreakdown(outstanding, percentage, extraChargePercent = 0) {
        currentChargesPercent = percentage;
        const principal = roundRupee(outstanding);
        const interest = roundRupee(currentInterestOutstanding);
        const charges = roundRupee(principal * (percentage / 100));
        const extraPercent = parseFloat(extraChargePercent) || 0;
        const extraCharges = roundRupee(principal * (extraPercent / 100));
        const total = roundRupee(principal + interest + charges + extraCharges);

        currentForeclosureCharges = charges;
        currentTotalForeclosure = total;

        $('#outstandingAmt').text(formatRupee(principal));
        $('#interestOutstandingAmt').text(formatRupee(interest));
        $('#chargesPercent').text(percentage);
        $('#foreclosureCharges').text(formatRupee(charges));
        $('#extraChargeAmt').text(formatRupee(extraCharges));
        $('#totalForeclosureAmt').text(formatRupee(total));
    }

    // Confirm Foreclosure
    ui.confirmBtn.on('click', function () {
        const extraCharge = parseFloat(ui.extraChargeInput.val()) || 0;
        const formData = {
            _token: $('meta[name="csrf-token"]').attr('content'),
            override_mode: isOverrideMode ? 1 : 0,
            extra_charge: isOverrideMode && extraCharge > 0 ? extraCharge : 0,
            foreclosure_notes: ui.reasonInput.val().trim()
        };

        const btn = $(this);
        const originalText = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Processing...');

        $.ajax({
            url: baseUrl + 'loan/loan-accounts/' + accountId + '/foreclose',
            type: 'POST',
            data: formData,
            success: function (response) {
                foreclosureModal.hide();
                showToast('success', response.message);
                setTimeout(() => window.location.reload(), 1500);
            },
            error: function (xhr) {
                const message = xhr.responseJSON?.message || 'Failed to foreclose loan';
                showToast('danger', message);
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Toast Notification
    function showToast(type, message) {
        const toastContainer = document.querySelector('.toast-container') || createToastContainer();
        const toastId = 'toast-' + Date.now();
        let iconClass, bgClass;

        if (type === 'success') {
            iconClass = 'ri-check-line';
            bgClass = 'bg-success';
        } else if (type === 'danger') {
            iconClass = 'ri-close-circle-line';
            bgClass = 'bg-danger';
        } else if (type === 'warning') {
            iconClass = 'ri-alert-line';
            bgClass = 'bg-warning';
        } else if (type === 'info') {
            iconClass = 'ri-information-line';
            bgClass = 'bg-info';
        } else {
            iconClass = 'ri-error-warning-line';
            bgClass = 'bg-danger';
        }

        const toastHTML = `
      <div id="${toastId}" class="bs-toast toast fade show rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
        <div class="toast-header ${bgClass} text-white rounded-5 border-0">
          <i class="icon-base ${iconClass} me-2"></i>
          <div class="me-auto fw-medium">${message}</div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    `;

        toastContainer.insertAdjacentHTML('beforeend', toastHTML);

        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, {
            autohide: true,
            delay: 3000
        });

        toast.show();

        toastElement.addEventListener('hidden.bs.toast', function () {
            toastElement.remove();
        });
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        return container;
    }
    // Regenerate Documents
    $('#regenerateDocsBtn').on('click', function () {
        const btn = $(this);
        const originalText = btn.html();
        const loanId = btn.data('loan-id');

        Swal.fire({
            title: 'Regenerate Documents?',
            text: 'This will recreate all applicable loan documents for this account. Existing documents will be updated.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, regenerate!',
            customClass: {
                confirmButton: 'btn btn-primary me-3 waves-effect waves-light',
                cancelButton: 'btn btn-outline-secondary waves-effect'
            },
            buttonsStyling: false
        }).then(function (result) {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Regenerating...');

                $.ajax({
                    url: baseUrl + 'loan/loan-account/' + loanId + '/regenerate-documents',
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function (response) {
                        showToast('success', response.message);
                        setTimeout(() => window.location.reload(), 1500);
                    },
                    error: function (xhr) {
                        const message = xhr.responseJSON?.message || 'Failed to regenerate documents';
                        showToast('danger', message);
                        btn.prop('disabled', false).html(originalText);
                    }
                });
            }
        });
    });
});
