/**
 * Prepayment Modal Handler
 * Handles prepayment button click, modal interactions, and payment processing
 */

(function () {
    'use strict';

    let prepaymentData = null;
    let loanAccountId = null;

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        const prepaymentBtn = document.getElementById('prepaymentBtn');
        const prepaymentModal = new bootstrap.Modal(document.getElementById('prepaymentModal'));
        const prepaymentAmountInput = document.getElementById('prepaymentAmount');
        const confirmCheckbox = document.getElementById('confirmPrepaymentCheck');
        const confirmBtn = document.getElementById('confirmPrepaymentBtn');

        if (!prepaymentBtn) return;

        // Handle prepayment button click
        prepaymentBtn.addEventListener('click', function () {
            loanAccountId = this.dataset.accountId;
            const accountNumber = this.dataset.accountNumber;

            // Reset form
            resetPrepaymentForm();

            // Fetch prepayment info
            fetchPrepaymentInfo(loanAccountId);

            // Show modal
            prepaymentModal.show();
        });

        // Handle amount input change
        if (prepaymentAmountInput) {
            prepaymentAmountInput.addEventListener('input', debounce(function () {
                const amount = parseFloat(this.value);
                if (amount && amount > 0) {
                    fetchPrepaymentBreakdown(loanAccountId, amount);
                } else {
                    resetBreakdown();
                }
            }, 500));
        }

        // Handle confirmation checkbox
        if (confirmCheckbox) {
            confirmCheckbox.addEventListener('change', function () {
                confirmBtn.disabled = !this.checked;
            });
        }

        // Handle confirm button click
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                processPrepayment();
            });
        }
    });

    /**
     * Fetch prepayment information
     */
    function fetchPrepaymentInfo(accountId) {
        fetch(`/loan-accounts/${accountId}/prepayment-info`)
            .then(response => response.json())
            .then(data => {
                prepaymentData = data;
                displayEligibilityStatus(data);
                displayMinMaxAmounts(data);
            })
            .catch(error => {
                console.error('Error fetching prepayment info:', error);
                showAlert('prepaymentEligibilityAlert', 'danger', 'Failed to load prepayment information');
            });
    }

    /**
     * Fetch prepayment breakdown with amount
     */
    function fetchPrepaymentBreakdown(accountId, amount) {
        fetch(`/loan-accounts/${accountId}/prepayment-info?amount=${amount}`)
            .then(response => response.json())
            .then(data => {
                if (data.success === false) {
                    showAlert('prepaymentEligibilityAlert', 'danger', data.message);
                    resetBreakdown();
                } else {
                    displayBreakdown(data);
                }
            })
            .catch(error => {
                console.error('Error fetching prepayment breakdown:', error);
            });
    }

    /**
     * Display eligibility status
     */
    function displayEligibilityStatus(data) {
        const alertDiv = document.getElementById('prepaymentEligibilityAlert');

        if (data.is_eligible) {
            showAlert('prepaymentEligibilityAlert', 'success',
                `✓ Eligible for prepayment (${data.paid_emis_count}/${data.eligibility_months} EMIs completed)`);
        } else {
            showAlert('prepaymentEligibilityAlert', 'warning',
                `Not eligible for prepayment yet. Complete ${data.eligibility_months - data.paid_emis_count} more EMI(s)`);
            // Disable amount input if not eligible
            document.getElementById('prepaymentAmount').disabled = true;
        }
    }

    /**
     * Display min/max amounts
     */
    function displayMinMaxAmounts(data) {
        document.getElementById('minPrepaymentAmount').textContent = `₹${formatNumber(data.min_amount)}`;
        document.getElementById('maxPrepaymentAmount').textContent = `₹${formatNumber(data.max_amount)}`;
    }

    /**
     * Display payment breakdown
     */
    function displayBreakdown(data) {
        document.getElementById('prepaymentOutstanding').textContent = `₹${formatNumber(data.outstanding_amount)}`;
        document.getElementById('prepaymentAmountDisplay').textContent = `₹${formatNumber(data.amount)}`;
        document.getElementById('prepaymentInterest').textContent = `₹${formatNumber(data.interest_portion)}`;
        document.getElementById('prepaymentChargesPercent').textContent = formatNumber(data.prepayment_charge_percentage);
        document.getElementById('prepaymentCharges').textContent = `₹${formatNumber(data.prepayment_charge_amount)}`;
        document.getElementById('prepaymentTotalPayable').textContent = `₹${formatNumber(data.total_payable_amount)}`;
        document.getElementById('prepaymentNewOutstanding').textContent = `₹${formatNumber(data.revised_principal)}`;
    }

    /**
     * Reset breakdown display
     */
    function resetBreakdown() {
        document.getElementById('prepaymentOutstanding').textContent = '₹0.00';
        document.getElementById('prepaymentAmountDisplay').textContent = '₹0.00';
        document.getElementById('prepaymentInterest').textContent = '₹0.00';
        document.getElementById('prepaymentCharges').textContent = '₹0.00';
        document.getElementById('prepaymentTotalPayable').textContent = '₹0.00';
        document.getElementById('prepaymentNewOutstanding').textContent = '₹0.00';
    }

    /**
     * Reset prepayment form
     */
    function resetPrepaymentForm() {
        document.getElementById('prepaymentAmount').value = '';
        document.getElementById('prepaymentAmount').disabled = false;
        document.getElementById('prepaymentPaymentMethod').value = 'cash';
        document.getElementById('prepaymentReference').value = '';
        document.getElementById('prepaymentRemarks').value = '';
        document.getElementById('confirmPrepaymentCheck').checked = false;
        document.getElementById('confirmPrepaymentBtn').disabled = true;
        resetBreakdown();
    }

    /**
     * Process prepayment
     */
    function processPrepayment() {
        const amount = parseFloat(document.getElementById('prepaymentAmount').value);
        const paymentMethod = document.getElementById('prepaymentPaymentMethod').value;
        const paymentReference = document.getElementById('prepaymentReference').value;
        const remarks = document.getElementById('prepaymentRemarks').value;

        if (!amount || amount <= 0) {
            showAlert('prepaymentEligibilityAlert', 'danger', 'Please enter a valid prepayment amount');
            return;
        }

        // Disable button to prevent double submission
        const confirmBtn = document.getElementById('confirmPrepaymentBtn');
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

        // Prepare data
        const formData = {
            amount: amount,
            payment_method: paymentMethod,
            payment_reference: paymentReference,
            remarks: remarks,
            payment_date: new Date().toISOString().split('T')[0]
        };

        // Send request
        fetch(`/loan-accounts/${loanAccountId}/prepayment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(formData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Calculate tenure change
                    const tenureChange = data.data.new_tenure - data.data.old_tenure;
                    const isReduction = tenureChange < 0;
                    const changeValue = Math.abs(tenureChange);

                    const tenureLabel = isReduction
                        ? `<span class="text-success fw-bold"><i class="ri-arrow-down-line"></i> ${changeValue} Months</span>`
                        : tenureChange > 0
                            ? `<span class="text-warning fw-bold"><i class="ri-arrow-up-line"></i> ${changeValue} Months</span>`
                            : `<span class="text-muted">No Change</span>`;

                    let sharingButtons = '';
                    if (data.sms_data) {
                        const sd = data.sms_data;
                        const clientName = sd.client_name || 'Client';
                        const originalMobile = sd.mobile_no || '';
                        
                        // Clean phone number (keep only digits)
                        let cleanMobile = originalMobile.replace(/\D/g, '');
                        if (cleanMobile.length === 10) {
                            cleanMobile = '91' + cleanMobile;
                        }

                        const accountNo = sd.account_no || '';
                        const amountPaid = parseFloat(sd.amount_paid || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        const remainingBalance = parseFloat(sd.remaining_balance || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                        let msgText = sd.sms_message || '';
                        let waMsgText = sd.whatsapp_message || '';

                        if (!msgText || !waMsgText) {
                            const fallbackMsgText = `Dear ${clientName},\nYour Prepayment (Principal payment) of ₹${amountPaid} towards Shanmuga Finance Loan Account ${accountNo} has been received successfully.\nOutstanding Principal Balance: ₹${remainingBalance}.\nThank you!`;
                            if (!msgText) {
                                msgText = fallbackMsgText;
                            }
                            if (!waMsgText) {
                                waMsgText = fallbackMsgText;
                                if (sd.application_number) {
                                    const publicToken = btoa(sd.application_number);
                                    const publicLink = `${window.location.origin}/view-schedule/${publicToken}`;
                                    waMsgText += `\n\nPlease check your EMI Schedule here: ${publicLink}`;
                                }
                            }
                        }

                        // Determine iOS or Android separator for native SMS client
                        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                        const smsSeparator = isIOS ? '&' : '?';

                        const waUrl = `https://wa.me/${cleanMobile}?text=${encodeURIComponent(waMsgText)}`;
                        const smsUrl = `sms:+${cleanMobile}${smsSeparator}body=${encodeURIComponent(msgText)}`;

                        sharingButtons = `
                        <div class="mt-4 pt-2 border-top">
                            <p class="text-muted small mb-2 text-center">Send prepayment confirmation receipt to client number: <strong>+${cleanMobile}</strong></p>
                            <div class="d-grid gap-2">
                              <a href="${waUrl}" target="_blank" class="btn btn-success d-flex align-items-center justify-content-center gap-2 py-2" style="background-color: #25D366; border-color: #25D366; color: white; font-weight: 500;">
                                <i class="ri-whatsapp-line fs-5"></i> Send WhatsApp Confirmation
                              </a>
                              
                              <a href="${smsUrl}" class="btn btn-info d-flex align-items-center justify-content-center gap-2 py-2" style="background-color: #0088cc; border-color: #0088cc; color: white; font-weight: 500;">
                                <i class="ri-message-3-line fs-5"></i> Send Native SMS
                              </a>
                            </div>
                        </div>
                      `;
                    }

                    // Show success message
                    Swal.fire({
                        title: '',
                        html: `
                            <div class="text-start">
                                <div class="text-center mb-4">
                                    <div class="avatar avatar-xl bg-label-success rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                                        <i class="ri-checkbox-circle-line fs-1 text-success"></i>
                                    </div>
                                    <h4 class="mb-1">Prepayment Successful!</h4>
                                    <p class="text-muted">The transaction has been processed successfully.</p>
                                </div>

                                <!-- Payment Summary Box -->
                                <div class="bg-label-secondary p-3 rounded mb-4">
                                    <h6 class="fw-bold mb-3 text-uppercase small text-muted">Transaction Summary</h6>
                                    
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-body">Prepayment Amount</span>
                                        <span class="fw-bold">₹${formatNumber(data.data.prepayment_amount)}</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-body">Charges</span>
                                        <span>₹${formatNumber(data.data.prepayment_charge)}</span>
                                    </div>
                                    <hr class="my-3 border-secondary">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-heading">Total Paid</span>
                                        <span class="h4 mb-0 text-primary fw-bold">₹${formatNumber(data.data.total_payable)}</span>
                                    </div>
                                </div>

                                <!-- Key Metrics Grid -->
                                <div class="row g-3 mb-4">
                                    <div class="col-6">
                                        <div class="p-3 border rounded text-center h-100">
                                            <small class="text-muted d-block mb-1 text-uppercase">New Outstanding</small>
                                            <h5 class="mb-0 text-success fw-bold">₹${formatNumber(data.data.new_principal)}</h5>
                                            <small class="text-muted" style="font-size: 0.75rem">Was: ₹${formatNumber(data.data.old_principal)}</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 border rounded text-center h-100">
                                            <small class="text-muted d-block mb-1 text-uppercase">Tenure Adjustment</small>
                                            <h5 class="mb-0">${tenureLabel}</h5>
                                            <small class="text-muted" style="font-size: 0.75rem">${data.data.old_tenure} ➔ ${data.data.new_tenure} Months</small>
                                        </div>
                                    </div>
                                </div>

                                ${tenureChange > 0 ? `
                                <div class="alert alert-warning py-2 small mb-0 text-start">
                                    <i class="ri-information-line me-1"></i> Tenure adjusted to reflect current outstanding.
                                </div>
                                ` : ''}

                                ${sharingButtons}
                            </div>
                        `,
                        showConfirmButton: true,
                        confirmButtonText: 'Done',
                        buttonsStyling: false, // vital for custom classes to work fully
                        width: '500px',
                        padding: '1.5rem',
                        customClass: {
                            popup: 'rounded-3',
                            confirmButton: 'btn btn-primary btn-lg w-100'
                        }
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    throw new Error(data.message || 'Prepayment failed');
                }
            })
            .catch(error => {
                console.error('Error processing prepayment:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Prepayment Failed',
                    text: error.message || 'An error occurred while processing prepayment',
                    confirmButtonText: 'OK'
                });

                // Re-enable button
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="icon-base ri ri-check-line me-1"></i> Process Prepayment';
            });
    }

    /**
     * Show alert message
     */
    function showAlert(elementId, type, message) {
        const alertDiv = document.getElementById(elementId);
        alertDiv.className = `alert alert-${type} mb-4 d-flex align-items-center`;

        const icon = type === 'success' ? 'ri-checkbox-circle-line' :
            type === 'danger' ? 'ri-error-warning-line' :
                'ri-information-line';

        alertDiv.innerHTML = `
            <i class="icon-base ri ${icon} me-2"></i>
            <span>${message}</span>
        `;
    }

    /**
     * Format number with commas
     */
    function formatNumber(num) {
        if (!num) return '0.00';
        return parseFloat(num).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    /**
     * Debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
})();
