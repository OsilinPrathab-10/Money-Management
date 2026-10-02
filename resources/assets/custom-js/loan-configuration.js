/**
 * Loan Configuration JavaScript
 * Handles foreclosure and prepayment configuration forms and toggles
 */

document.addEventListener('DOMContentLoaded', function () {
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    // Alert container for session messages
    const alertContainer = document.querySelector('.alert-container');

    // Show alerts from session
    if (alertContainer) {
        const successMessage = alertContainer.getAttribute('data-success');
        const errorMessage = alertContainer.getAttribute('data-error');
        const warningMessage = alertContainer.getAttribute('data-warning');
        const infoMessage = alertContainer.getAttribute('data-info');

        if (successMessage) showToast('success', successMessage);
        if (errorMessage) showToast('danger', errorMessage);
        if (warningMessage) showToast('warning', warningMessage);
        if (infoMessage) showToast('info', infoMessage);
    }

    // Initialize foreclosure configuration
    initForeclosureConfig();

    // Initialize prepayment configuration
    initPrepaymentConfig();

    // Initialize partial payment configuration
    initPartialPaymentConfig();

    // Initialize penalty configuration
    initPenaltyConfig();

    // Initialize penalty charge type dropdown
    initPenaltyChargeTypeUI();

    // Initialize charge type dropdown
    initChargeTypeDropdown();

    // Initialize account prefix configuration
    initAccountPrefixConfig();
});

/**
 * Initialize Foreclosure Configuration
 */
function initForeclosureConfig() {
    const foreclosureForm = document.getElementById('foreclosureConfigForm');
    const foreclosureSwitch = document.getElementById('foreclosureStatus');
    const foreclosureEnabled = document.getElementById('foreclosureEnabled');

    // Form submission
    if (foreclosureForm) {
        foreclosureForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Validate fields if foreclosure is enabled
            if (foreclosureSwitch && foreclosureSwitch.checked) {
                const eligibilityMonths = document.getElementById('eligibilityMonths');
                const eligibilityWeeks = document.getElementById('eligibilityWeeks');
                const eligibilityDays = document.getElementById('eligibilityDays');
                const chargesPercentage = document.getElementById('chargesPercentage');
                const chargesPercentageWeekly = document.getElementById('chargesPercentageWeekly');
                const chargesPercentageDaily = document.getElementById('chargesPercentageDaily');

                const hasEligibility = (eligibilityMonths && eligibilityMonths.value !== '') ||
                                       (eligibilityWeeks && eligibilityWeeks.value !== '') ||
                                       (eligibilityDays && eligibilityDays.value !== '');
                const hasCharges = (chargesPercentage && chargesPercentage.value !== '') ||
                                   (chargesPercentageWeekly && chargesPercentageWeekly.value !== '') ||
                                   (chargesPercentageDaily && chargesPercentageDaily.value !== '');

                if (!hasEligibility || !hasCharges) {
                    showToast('danger', 'Please configure at least one eligibility duration and one charge percentage when foreclosure is enabled');
                    return;
                }
            }

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

            const saveUrl = this.getAttribute('action');

            fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;

                    if (data.success) {
                        showToast('success', data.message || 'Configuration saved successfully');
                        // Set data-has-config to true after successful save
                        if (foreclosureEnabled) {
                            foreclosureEnabled.setAttribute('data-has-config', 'true');
                        }
                    } else {
                        showToast('danger', data.message || 'Failed to save configuration');
                    }
                })
                .catch(error => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    console.error('Error:', error);
                    showToast('danger', 'An error occurred while saving configuration');
                });
        });
    }

    // Toggle switch
    if (foreclosureSwitch && foreclosureEnabled) {
        foreclosureSwitch.addEventListener('change', function () {
            const isChecked = this.checked;

            if (isChecked) {
                const eligibilityMonths = document.getElementById('eligibilityMonths');
                const eligibilityWeeks = document.getElementById('eligibilityWeeks');
                const eligibilityDays = document.getElementById('eligibilityDays');
                const chargesPercentage = document.getElementById('chargesPercentage');
                const chargesPercentageWeekly = document.getElementById('chargesPercentageWeekly');
                const chargesPercentageDaily = document.getElementById('chargesPercentageDaily');

                const hasEligibility = (eligibilityMonths && eligibilityMonths.value !== '') ||
                                       (eligibilityWeeks && eligibilityWeeks.value !== '') ||
                                       (eligibilityDays && eligibilityDays.value !== '');
                const hasCharges = (chargesPercentage && chargesPercentage.value !== '') ||
                                   (chargesPercentageWeekly && chargesPercentageWeekly.value !== '') ||
                                   (chargesPercentageDaily && chargesPercentageDaily.value !== '');

                const hasConfig = foreclosureEnabled.getAttribute('data-has-config') === 'true';

                if (!hasConfig && (!hasEligibility || !hasCharges)) {
                    this.checked = false;
                    foreclosureEnabled.value = '0';
                    showToast('danger', 'Please configure foreclosure settings first before enabling');
                    return;
                }
            }

            foreclosureEnabled.value = isChecked ? '1' : '0';

            const formData = new FormData();
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            formData.append('is_active', isChecked ? '1' : '0');

            const saveUrl = foreclosureForm?.getAttribute('action');
            if (!saveUrl) return;

            fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const statusText = isChecked ? 'Enabled' : 'Disabled';
                        const toastType = isChecked ? 'success' : 'danger';
                        showToast(toastType, `Foreclosure ${statusText}`);

                        // Update badge
                        const badge = document.getElementById('foreclosureStatusBadge');
                        if (badge) {
                            badge.textContent = isChecked ? 'Enabled' : 'Disabled';
                            badge.className = isChecked ? 'badge bg-label-success' : 'badge bg-label-secondary';
                        }
                    } else {
                        foreclosureSwitch.checked = !isChecked;
                        foreclosureEnabled.value = !isChecked ? '1' : '0';
                        showToast('danger', 'Failed to update status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    foreclosureSwitch.checked = !isChecked;
                    foreclosureEnabled.value = !isChecked ? '1' : '0';
                    showToast('danger', 'Failed to update status');
                });
        });
    }
}

/**
 * Initialize Prepayment Configuration
 */
function initPrepaymentConfig() {
    const prepaymentForm = document.getElementById('prepaymentConfigForm');
    const prepaymentSwitch = document.getElementById('prepaymentStatus');
    const prepaymentEnabled = document.getElementById('prepaymentEnabled');
    const prepaymentEligibilityInput = document.getElementById('prepaymentEligibilityMonths');
    const prepaymentChargeValue = document.getElementById('chargeValue');

    // Form submission
    if (prepaymentForm) {
        prepaymentForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Validate fields if prepayment is enabled
            if (prepaymentSwitch && prepaymentSwitch.checked) {
                const prepaymentEligibilityWeeks = document.getElementById('prepaymentEligibilityWeeks');
                const prepaymentEligibilityDays = document.getElementById('prepaymentEligibilityDays');
                const hasEligibility = (prepaymentEligibilityInput && prepaymentEligibilityInput.value !== '') ||
                                       (prepaymentEligibilityWeeks && prepaymentEligibilityWeeks.value !== '') ||
                                       (prepaymentEligibilityDays && prepaymentEligibilityDays.value !== '');
                const hasChargeValue = prepaymentChargeValue && prepaymentChargeValue.value !== '';

                if (!hasEligibility || !hasChargeValue) {
                    showToast('danger', 'Please configure at least one eligibility duration and one prepayment charge value when prepayment is enabled');
                    return;
                }
            }

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

            const saveUrl = this.getAttribute('action');

            fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;

                    if (data.success) {
                        showToast('success', data.message || 'Configuration saved successfully');
                        if (prepaymentEnabled) {
                            prepaymentEnabled.setAttribute('data-has-config', 'true');
                        }
                    } else {
                        showToast('danger', data.message || 'Failed to save configuration');
                    }
                })
                .catch(error => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    console.error('Error:', error);
                    showToast('danger', 'An error occurred while saving configuration');
                });
        });
    }

    // Toggle switch with validation
    if (prepaymentSwitch && prepaymentEnabled) {
        prepaymentSwitch.addEventListener('change', function () {
            const isChecked = this.checked;

            // Check if trying to enable without configuration
            if (isChecked) {
                const prepaymentEligibilityWeeks = document.getElementById('prepaymentEligibilityWeeks');
                const prepaymentEligibilityDays = document.getElementById('prepaymentEligibilityDays');
                const hasEligibility = (prepaymentEligibilityInput && prepaymentEligibilityInput.value !== '') ||
                                       (prepaymentEligibilityWeeks && prepaymentEligibilityWeeks.value !== '') ||
                                       (prepaymentEligibilityDays && prepaymentEligibilityDays.value !== '');
                const hasChargeValue = prepaymentChargeValue && prepaymentChargeValue.value !== '';

                // Check if configuration exists in database
                const hasConfig = prepaymentEnabled.getAttribute('data-has-config') === 'true';

                if (!hasConfig && (!hasEligibility || !hasChargeValue)) {
                    this.checked = false;
                    prepaymentEnabled.value = '0';
                    showToast('danger', 'Please configure prepayment settings first before enabling');
                    return;
                }
            }

            prepaymentEnabled.value = isChecked ? '1' : '0';

            const formData = new FormData();
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            formData.append('is_active', isChecked ? '1' : '0');

            const saveUrl = prepaymentForm?.getAttribute('action');
            if (!saveUrl) return;

            fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const statusText = isChecked ? 'Enabled' : 'Disabled';
                        const toastType = isChecked ? 'success' : 'danger';
                        showToast(toastType, `Prepayment ${statusText}`);
                        // Update data attribute
                        prepaymentEnabled.setAttribute('data-has-config', 'true');

                        // Update badge
                        const badge = document.getElementById('prepaymentStatusBadge');
                        if (badge) {
                            badge.textContent = isChecked ? 'Enabled' : 'Disabled';
                            badge.className = isChecked ? 'badge bg-label-success' : 'badge bg-label-secondary';
                        }
                    } else {
                        prepaymentSwitch.checked = !isChecked;
                        prepaymentEnabled.value = !isChecked ? '1' : '0';
                        showToast('danger', 'Failed to update status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    prepaymentSwitch.checked = !isChecked;
                    prepaymentEnabled.value = !isChecked ? '1' : '0';
                    showToast('danger', 'Failed to update status');
                });
        });
    }
}

/**
 * Initialize Charge Type Dropdown
 */
function initChargeTypeDropdown() {
    const chargeTypeInput = document.getElementById('chargeType');
    const chargeTypeLabel = document.getElementById('chargeTypeLabel');
    const chargeValueHelp = document.getElementById('chargeValueHelp');
    const dropdownItems = document.querySelectorAll('#chargeTypeDropdown + .dropdown-menu .dropdown-item');

    function updateChargeTypeDisplay() {
        if (!chargeTypeInput || !chargeTypeLabel) return;

        const currentType = chargeTypeInput.value;
        if (currentType === 'percentage') {
            chargeTypeLabel.textContent = '%';
            if (chargeValueHelp) chargeValueHelp.textContent = 'Percentage of outstanding principal';
        } else {
            chargeTypeLabel.textContent = '₹';
            if (chargeValueHelp) chargeValueHelp.textContent = 'Fixed charge amount';
        }
    }

    // Handle dropdown item clicks
    dropdownItems.forEach(item => {
        item.addEventListener('click', function (e) {
            e.preventDefault();
            const value = this.getAttribute('data-value');
            const label = this.getAttribute('data-label');

            if (chargeTypeInput) chargeTypeInput.value = value;
            if (chargeTypeLabel) chargeTypeLabel.textContent = label;

            if (value === 'percentage') {
                if (chargeValueHelp) chargeValueHelp.textContent = 'Percentage of outstanding principal';
            } else {
                if (chargeValueHelp) chargeValueHelp.textContent = 'Fixed charge amount';
            }
        });
    });

    // Initialize on page load
    updateChargeTypeDisplay();
}

/**
 * Initialize Partial Payment Configuration
 */
function initPartialPaymentConfig() {
    const partialPaymentForm = document.getElementById('partialPaymentConfigForm');
    const partialPaymentSwitch = document.getElementById('partialPaymentStatus');
    const partialPaymentEnabled = document.getElementById('partialPaymentEnabled');
    const minimumPartialPercentageInput = document.getElementById('minimumPartialPercentage');

    // Form submission
    if (partialPaymentForm) {
        partialPaymentForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

            const saveUrl = this.getAttribute('action');

            fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;

                    if (data.success) {
                        showToast('success', data.message || 'Configuration saved successfully');
                    } else {
                        showToast('danger', data.message || 'Failed to save configuration');
                    }
                })
                .catch(error => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    console.error('Error:', error);
                    showToast('danger', 'An error occurred while saving configuration');
                });
        });
    }

    // Toggle switch with validation
    if (partialPaymentSwitch && partialPaymentEnabled) {
        partialPaymentSwitch.addEventListener('change', function () {
            const isChecked = this.checked;

            // Check if trying to enable without configuration
            if (isChecked) {
                const hasMinimumPercentage = minimumPartialPercentageInput && minimumPartialPercentageInput.value !== '';

                // Check if configuration exists in database
                const hasConfig = partialPaymentEnabled.getAttribute('data-has-config') === 'true';

                if (!hasConfig && !hasMinimumPercentage) {
                    this.checked = false;
                    partialPaymentEnabled.value = '0';
                    showToast('danger', 'Please configure partial payment settings first before enabling');
                    return;
                }
            }

            partialPaymentEnabled.value = isChecked ? '1' : '0';

            const formData = new FormData();
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            formData.append('is_active', isChecked ? '1' : '0');

            const timingSelect = document.getElementById('partialPaymentTiming');
            const penaltySelect = document.getElementById('penaltyCalculationMethod');
            if (minimumPartialPercentageInput && minimumPartialPercentageInput.value !== '') {
                formData.append('minimum_partial_percentage', minimumPartialPercentageInput.value);
            } else if (isChecked) {
                formData.append('minimum_partial_percentage', '10');
            }
            if (timingSelect) {
                formData.append('partial_payment_timing', timingSelect.value);
            }
            if (penaltySelect) {
                formData.append('penalty_calculation_method', penaltySelect.value);
            }

            const saveUrl = partialPaymentForm?.getAttribute('action');
            if (!saveUrl) return;

            fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
                .then(response => {
                    // Check if response is ok (status 200-299)
                    if (!response.ok) {
                        // Parse JSON even for error responses
                        return response.json().then(data => {
                            throw new Error(data.message || 'Failed to update status');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const statusText = isChecked ? 'Enabled' : 'Disabled';
                        const toastType = isChecked ? 'success' : 'danger';
                        showToast(toastType, `Partial Payment ${statusText}`);
                        // Update data attribute
                        partialPaymentEnabled.setAttribute('data-has-config', 'true');

                        // Update badge
                        const badge = document.getElementById('partialPaymentStatusBadge');
                        if (badge) {
                            badge.textContent = isChecked ? 'Enabled' : 'Disabled';
                            badge.className = isChecked ? 'badge bg-label-success' : 'badge bg-label-secondary';
                        }
                    } else {
                        partialPaymentSwitch.checked = !isChecked;
                        partialPaymentEnabled.value = !isChecked ? '1' : '0';
                        showToast('danger', data.message || 'Failed to update status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    partialPaymentSwitch.checked = !isChecked;
                    partialPaymentEnabled.value = !isChecked ? '1' : '0';
                    showToast('danger', error.message || 'Failed to update status');
                });
        });
    }
}


/**
 * Show Toast Notification
 */
function showToast(type, title, message) {
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
        iconClass = 'ri-error-warning-line';
        bgClass = 'bg-warning';
    } else {
        iconClass = 'ri-information-line';
        bgClass = 'bg-info';
    }

    // If no message provided, show title only (no body section)
    const toastHTML = message ? `
      <div id="${toastId}" class="bs-toast toast fade rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
        <div class="toast-header ${bgClass} text-white rounded-top-5 border-0">
          <i class="icon-base ${iconClass} me-2"></i>
          <div class="me-auto fw-medium">${title}</div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body rounded-bottom-3">
          ${message}
        </div>
      </div>
    ` : `
      <div id="${toastId}" class="bs-toast toast fade show rounded-3 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
        <div class="toast-header ${bgClass} text-white rounded-3 border-0">
          <i class="icon-base ${iconClass} me-2"></i>
          <div class="me-auto fw-medium">${title}</div>
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

    // Remove toast element after it's hidden
    toastElement.addEventListener('hidden.bs.toast', function () {
        toastElement.remove();
    });
}

/**
 * Create Toast Container
 */
function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

/**
 * Initialize Penalty Configuration
 */
function initPenaltyConfig() {
    const penaltyForm = document.getElementById('penaltyConfigForm');
    const penaltySwitch = document.getElementById('penaltyStatus');
    const penaltyEnabled = document.getElementById('penaltyEnabled');
    const penaltyChargeValueInput = document.getElementById('penaltyChargeValue');
    const penaltyEligibilityDaysInput = document.getElementById('penaltyEligibilityDays');

    // Form submission
    if (penaltyForm) {
        penaltyForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Validate fields if penalty is enabled
            if (penaltySwitch && penaltySwitch.checked) {
                const hasCharges = penaltyChargeValueInput && penaltyChargeValueInput.value !== '';
                const hasGracePeriod = penaltyEligibilityDaysInput && penaltyEligibilityDaysInput.value !== '';

                if (!hasCharges || !hasGracePeriod) {
                    showToast('danger', 'Please configure penalty amount and grace period when penalty is enabled');
                    return;
                }
            }

            const penaltyChargeTypeSelect = document.getElementById('penaltyChargeType');
            const enteredValue = parseFloat(penaltyChargeValueInput?.value);

            if (penaltyChargeValueInput && penaltyChargeValueInput.value !== '' && isNaN(enteredValue)) {
                penaltyChargeValueInput.classList.add('is-invalid');
                showToast('danger', 'Please enter a valid penalty value');
                return;
            }

            if (penaltyChargeTypeSelect?.value === 'percentage' && enteredValue > 100) {
                penaltyChargeValueInput.classList.add('is-invalid');
                showToast('danger', 'Percentage penalty value cannot exceed 100%');
                return;
            }

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

            const saveUrl = this.getAttribute('action');

            fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;

                    if (data.success) {
                        showToast('success', data.message || 'Configuration saved successfully');
                        if (penaltyEnabled) {
                            penaltyEnabled.setAttribute('data-has-config', 'true');
                        }
                    } else {
                        showToast('danger', data.message || 'Failed to save configuration');
                    }
                })
                .catch(error => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    console.error('Error:', error);
                    showToast('danger', 'An error occurred while saving configuration');
                });
        });
    }

    // Toggle switch with validation
    if (penaltySwitch && penaltyEnabled) {
        penaltySwitch.addEventListener('change', function () {
            const isChecked = this.checked;

            // Check if trying to enable without configuration
            if (isChecked) {
                const hasCharges = penaltyChargeValueInput && penaltyChargeValueInput.value !== '';
                const hasGracePeriod = penaltyEligibilityDaysInput && penaltyEligibilityDaysInput.value !== '';

                // Check if configuration exists in database
                const hasConfig = penaltyEnabled.getAttribute('data-has-config') === 'true';

                if (!hasConfig && (!hasCharges || !hasGracePeriod)) {
                    this.checked = false;
                    penaltyEnabled.value = '0';
                    showToast('danger', 'Please configure penalty settings first before enabling');
                    return;
                }
            }

            penaltyEnabled.value = isChecked ? '1' : '0';

            const formData = new FormData();
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            formData.append('is_active', isChecked ? '1' : '0');

            const penaltyChargeTypeInput = document.getElementById('penaltyChargeType');
            if (penaltyChargeTypeInput) {
                formData.append('penalty_charge_type', penaltyChargeTypeInput.value);
            }
            if (penaltyChargeValueInput && penaltyChargeValueInput.value !== '') {
                formData.append('charge_value', penaltyChargeValueInput.value);
            }
            if (penaltyEligibilityDaysInput && penaltyEligibilityDaysInput.value !== '') {
                formData.append('eligibility_days', penaltyEligibilityDaysInput.value);
            }

            const saveUrl = penaltyForm?.getAttribute('action');
            if (!saveUrl) return;

            fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(data => {
                            throw new Error(data.message || 'Failed to update status');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const statusText = isChecked ? 'Enabled' : 'Disabled';
                        const toastType = isChecked ? 'success' : 'danger';
                        showToast(toastType, `Penalty ${statusText}`);
                        // Update data attribute
                        penaltyEnabled.setAttribute('data-has-config', 'true');

                        // Update badge
                        const badge = document.getElementById('penaltyStatusBadge');
                        if (badge) {
                            badge.textContent = isChecked ? 'Enabled' : 'Disabled';
                            badge.className = isChecked ? 'badge bg-label-success' : 'badge bg-label-secondary';
                        }
                    } else {
                        penaltySwitch.checked = !isChecked;
                        penaltyEnabled.value = !isChecked ? '1' : '0';
                        showToast('danger', data.message || 'Failed to update status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    penaltySwitch.checked = !isChecked;
                    penaltyEnabled.value = !isChecked ? '1' : '0';
                    showToast('danger', error.message || 'Failed to update status');
                });
        });
    }
}

/**
 * Initialize Penalty Charge Type UI
 * Swaps the value label, suffix and hint between fixed amount and percentage.
 */
function initPenaltyChargeTypeUI() {
    const chargeType = document.getElementById('penaltyChargeType');
    const valueLabel = document.getElementById('penaltyChargeValueLabel');
    const valueSuffix = document.getElementById('penaltyChargeValueSuffix');
    const valueHint = document.getElementById('penaltyChargeValueHint');
    const valueInput = document.getElementById('penaltyChargeValue');

    if (!chargeType) return;

    function updatePenaltyChargeTypeUI(resetValue) {
        const isPercentage = chargeType.value === 'percentage';

        if (valueLabel) {
            valueLabel.textContent = isPercentage
                ? 'DEFAULT PENALTY PERCENTAGE (%)'
                : 'DEFAULT PENALTY AMOUNT (₹)';
        }
        if (valueSuffix) {
            valueSuffix.textContent = isPercentage ? '%' : '₹';
        }
        if (valueHint) {
            valueHint.textContent = isPercentage
                ? 'Percentage of the principal in the overdue EMI applied as penalty (max 100)'
                : 'Fixed amount applied on overdue EMIs';
        }

        if (!valueInput) return;

        valueInput.placeholder = isPercentage ? 'e.g. 2.5' : '0.00';

        // A rupee amount carried over as a percentage is almost always wrong and can
        // sit out of range, so start the field empty whenever the type actually changes.
        if (resetValue) {
            valueInput.value = '';
            valueInput.classList.remove('is-invalid');
            valueInput.focus();
        }

        clampPenaltyChargeValue();
    }

    function clampPenaltyChargeValue() {
        if (!valueInput) return;

        const isPercentage = chargeType.value === 'percentage';
        const raw = parseFloat(valueInput.value);
        const tooHigh = isPercentage && !isNaN(raw) && raw > 100;

        valueInput.classList.toggle('is-invalid', tooHigh);

        if (valueHint) {
            valueHint.classList.toggle('text-danger', tooHigh);
            valueHint.classList.toggle('text-muted', !tooHigh);
            if (tooHigh) {
                valueHint.textContent = 'Percentage cannot be more than 100.';
            } else if (isPercentage) {
                valueHint.textContent = 'Percentage of the principal in the overdue EMI applied as penalty (max 100)';
            } else {
                valueHint.textContent = 'Fixed amount applied on overdue EMIs';
            }
        }
    }

    chargeType.addEventListener('change', () => updatePenaltyChargeTypeUI(true));
    valueInput?.addEventListener('input', clampPenaltyChargeValue);
    updatePenaltyChargeTypeUI(false);
}

/**
 * Initialize Account Prefix Configuration
 */
function initAccountPrefixConfig() {
    const prefixForm = document.getElementById('accountPrefixConfigForm');

    if (prefixForm) {
        prefixForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const prefixInput = document.getElementById('accountPrefix');
            if (prefixInput) {
                // Ensure only uppercase
                prefixInput.value = prefixInput.value.toUpperCase();
                
                const val = prefixInput.value;
                if (!val || val.length < 1 || val.length > 4 || !/^[A-Z]+$/.test(val)) {
                    showToast('danger', 'The prefix must be 1 to 4 capital letters (e.g. SDC, MF).');
                    return;
                }
            }

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

            const saveUrl = this.getAttribute('action');

            fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(data => {
                            throw new Error(data.message || 'Failed to save configuration');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;

                    if (data.success) {
                        showToast('success', data.message || 'Configuration saved successfully');
                    } else {
                        showToast('danger', data.message || 'Failed to save configuration');
                    }
                })
                .catch(error => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    console.error('Error:', error);
                    showToast('danger', error.message || 'An error occurred while saving configuration');
                });
        });
    }
}
