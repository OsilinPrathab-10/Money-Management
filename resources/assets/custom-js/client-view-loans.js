/**
 * Client View - Loans Tab
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  // Get base URL from data attribute or window object
  let baseUrl = document.documentElement.getAttribute('data-base-url') || window.location.origin;
  if (!baseUrl.endsWith('/')) {
    baseUrl += '/';
  }

  // Get CSRF token
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  // EMI details are now handled by separate page navigation
  // Modal functionality removed as we use direct page links

  // Client Loans Filter Handler (Loan Mode and Loan Type)
  const clientLoanModeFilter = document.getElementById('clientLoanModeFilter');
  const clientLoanTypeFilter = document.getElementById('clientLoanTypeFilter');
  const resetClientLoanFilters = document.getElementById('resetClientLoanFilters');

  function applyClientLoanFilters() {
    const selectedMode = clientLoanModeFilter ? clientLoanModeFilter.value : '';
    const selectedType = clientLoanTypeFilter ? clientLoanTypeFilter.value : '';

    // Filter Applications
    let visibleApps = 0;
    const appRows = document.querySelectorAll('.client-loan-app-row');
    appRows.forEach(row => {
      const mode = row.getAttribute('data-loan-mode') || 'emi';
      const typeId = row.getAttribute('data-loan-type-id') || '';
      const modeMatch = !selectedMode || mode === selectedMode;
      const typeMatch = !selectedType || typeId === selectedType;

      if (modeMatch && typeMatch) {
        row.classList.remove('d-none');
        visibleApps++;
      } else {
        row.classList.add('d-none');
      }
    });

    const emptyAppRow = document.querySelector('.client-filter-empty-app');
    if (emptyAppRow) {
      if (appRows.length > 0 && visibleApps === 0) {
        emptyAppRow.classList.remove('d-none');
      } else {
        emptyAppRow.classList.add('d-none');
      }
    }
    const badgeApps = document.getElementById('badgeTotalApplications');
    if (badgeApps) {
      badgeApps.textContent = `${visibleApps} Total`;
    }

    // Filter Active Loan Accounts
    let visibleActive = 0;
    const accRows = document.querySelectorAll('.client-loan-acc-row');
    accRows.forEach(row => {
      const mode = row.getAttribute('data-loan-mode') || 'emi';
      const typeId = row.getAttribute('data-loan-type-id') || '';
      const modeMatch = !selectedMode || mode === selectedMode;
      const typeMatch = !selectedType || typeId === selectedType;

      if (modeMatch && typeMatch) {
        row.classList.remove('d-none');
        visibleActive++;
      } else {
        row.classList.add('d-none');
      }
    });

    const emptyAccRow = document.querySelector('.client-filter-empty-acc');
    if (emptyAccRow) {
      if (accRows.length > 0 && visibleActive === 0) {
        emptyAccRow.classList.remove('d-none');
      } else {
        emptyAccRow.classList.add('d-none');
      }
    }
    const badgeActive = document.getElementById('badgeActiveLoans');
    if (badgeActive) {
      badgeActive.textContent = `${visibleActive} Active`;
    }

    // Filter Closed Loans
    let visibleClosed = 0;
    const closedRows = document.querySelectorAll('.client-closed-acc-row');
    closedRows.forEach(row => {
      const mode = row.getAttribute('data-loan-mode') || 'emi';
      const typeId = row.getAttribute('data-loan-type-id') || '';
      const modeMatch = !selectedMode || mode === selectedMode;
      const typeMatch = !selectedType || typeId === selectedType;

      if (modeMatch && typeMatch) {
        row.classList.remove('d-none');
        visibleClosed++;
      } else {
        row.classList.add('d-none');
      }
    });

    const emptyClosedRow = document.querySelector('.client-filter-empty-closed');
    if (emptyClosedRow) {
      if (closedRows.length > 0 && visibleClosed === 0) {
        emptyClosedRow.classList.remove('d-none');
      } else {
        emptyClosedRow.classList.add('d-none');
      }
    }
    const badgeClosed = document.getElementById('badgeClosedLoans');
    if (badgeClosed) {
      badgeClosed.textContent = `${visibleClosed} Closed`;
    }
  }

  if (clientLoanModeFilter) {
    clientLoanModeFilter.addEventListener('change', applyClientLoanFilters);
  }
  if (clientLoanTypeFilter) {
    clientLoanTypeFilter.addEventListener('change', applyClientLoanFilters);
  }
  if (resetClientLoanFilters) {
    resetClientLoanFilters.addEventListener('click', function () {
      if (clientLoanModeFilter) clientLoanModeFilter.value = '';
      if (clientLoanTypeFilter) clientLoanTypeFilter.value = '';
      applyClientLoanFilters();
    });
  }
  document.querySelectorAll('.view-document-btn').forEach(button => {
    button.addEventListener('click', function () {
      const loanId = this.getAttribute('data-loan-id');
      const documentType = this.getAttribute('data-document-type');
      const documentName = this.getAttribute('data-document-name');

      // Show loading state
      this.disabled = true;
      this.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Generating...';

      // Check if document can be generated first
      const viewUrl = `${baseUrl}client/loan/${loanId}/document/${documentType}/view`;

      fetch(viewUrl, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(response => {
          if (response.ok && response.headers.get('content-type')?.includes('application/pdf')) {
            // PDF response - open in new tab
            const link = document.createElement('a');
            link.href = viewUrl;
            link.target = '_blank';
            link.rel = 'noopener';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
          } else if (response.status === 404) {
            // Template not found - show error
            return response.json().then(data => {
              showAlert('danger', 'Template Not Found', data.message);
            });
          } else {
            throw new Error('Failed to generate document');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showAlert('danger', 'Error', 'Failed to generate document. Please try again.');
        })
        .finally(() => {
          // Reset button state
          this.disabled = false;
          this.innerHTML = '<i class="icon-base ri ri-eye-line me-1"></i>View';
        });
    });
  });

  // Document Download Button Handler
  document.querySelectorAll('.download-document-btn').forEach(button => {
    button.addEventListener('click', function () {
      const loanId = this.getAttribute('data-loan-id');
      const documentType = this.getAttribute('data-document-type');
      const documentName = this.getAttribute('data-document-name');

      // Show loading state
      this.disabled = true;
      this.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Downloading...';

      // Check if document can be downloaded first
      const downloadUrl = `${baseUrl}client/loan/${loanId}/document/${documentType}/download`;

      fetch(downloadUrl, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(response => {
          if (response.ok && response.headers.get('content-type')?.includes('application/pdf')) {
            // PDF response - trigger download
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = `${documentName}_${new Date().getTime()}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
          } else if (response.status === 404) {
            // Template not found - show error
            return response.json().then(data => {
              showAlert('danger', 'Template Not Found', data.message);
            });
          } else {
            throw new Error('Failed to download document');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showAlert('danger', 'Error', 'Failed to download document. Please try again.');
        })
        .finally(() => {
          // Reset button state
          this.disabled = false;
          this.innerHTML = '<i class="icon-base ri ri-download-line me-1"></i>Download';
        });
    });
  });

  /**
   * Show alert using SweetAlert2
   */
  function showAlert(type, title, message) {
    const finalMessage = message ? message : title;
    const finalTitle = message ? title : (type === 'success' ? 'Success' : 'Error');
    const icon = type === 'danger' ? 'error' : type;
    
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: icon,
        title: finalTitle,
        text: finalMessage,
        confirmButtonText: 'OK'
      });
    } else {
      alert(`${finalTitle}: ${finalMessage}`);
    }
  }

  /**
   * Handle Payment Success with WhatsApp and SMS Redirects
   */
  function handlePaymentSuccess(data, fallbackMsg) {
    if (!data.success) {
      Swal.fire({
        title: 'Error!',
        text: data.message || 'Payment failed',
        icon: 'error',
        customClass: { confirmButton: 'btn btn-primary' }
      });
      return;
    }

    const msg = data.message || fallbackMsg || 'Payment processed successfully.';

    if (data.sms_data) {
      const d = data.sms_data;
      const clientName = d.client_name || 'Client';
      const mobileNo = d.mobile_no || '';
      const accountNo = d.account_no || '';
      const amountPaid = parseFloat(d.amount_paid || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      const remainingBalance = parseFloat(d.remaining_balance || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      const isKandhuvatti = d.loan_mode === 'interest_only';
      const paymentType = d.payment_type || '';

      const isPartial = d.is_partial || false;
      const emiBalance = parseFloat(d.emi_balance || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

      let msgText = d.sms_message || '';
      let waMsgText = d.whatsapp_message || '';

      const companySlogan = d.company_slogan || 'Codepluse Gen PVT Ltd';

      if (!msgText || !waMsgText) {
        let fallbackMsgText = '';
        if (isKandhuvatti) {
          if (paymentType === 'principal') {
            fallbackMsgText = `Dear ${clientName},\nYour Principal payment of ₹${amountPaid} towards ${companySlogan} Open Loan Account ${accountNo} has been received successfully.\nRemaining Principal Balance: ₹${remainingBalance}.\nThank you!`;
          } else {
            if (isPartial) {
              fallbackMsgText = `Dear ${clientName},\nYour Partial Interest payment of ₹${amountPaid} towards ${companySlogan} Open Loan Account ${accountNo} has been received successfully.\nBalance Interest to pay: ₹${emiBalance}.\nRemaining Principal Balance: ₹${remainingBalance}.\nThank you!`;
            } else {
              fallbackMsgText = `Dear ${clientName},\nYour Interest payment of ₹${amountPaid} towards ${companySlogan} Open Loan Account ${accountNo} has been received successfully.\nRemaining Principal Balance: ₹${remainingBalance}.\nThank you!`;
            }
          }
        } else {
          if (isPartial) {
            fallbackMsgText = `Dear ${clientName},\nYour Partial EMI payment of ₹${amountPaid} towards ${companySlogan} Loan Account ${accountNo} has been received successfully.\nBalance EMI to pay: ₹${emiBalance}.\nOutstanding Balance: ₹${remainingBalance}.\nThank you!`;
          } else {
            fallbackMsgText = `Dear ${clientName},\nYour EMI payment of ₹${amountPaid} towards ${companySlogan} Loan Account ${accountNo} has been received successfully.\nOutstanding Balance: ₹${remainingBalance}.\nThank you!`;
          }
        }

        if (!msgText) {
          msgText = fallbackMsgText;
        }
        if (!waMsgText) {
          waMsgText = fallbackMsgText;
          if (d.application_number) {
            const publicToken = btoa(d.application_number);
            const publicLink = `${window.location.origin}/view-schedule/${publicToken}`;
            waMsgText += `\n\nPlease check your EMI Schedule here: ${publicLink}`;
          }
        }
      }

      // Clean phone number (keep only digits)
      let cleanMobile = mobileNo.replace(/\D/g, '');
      if (cleanMobile.length === 10) {
        cleanMobile = '91' + cleanMobile;
      }

      const waUrl = `https://wa.me/${cleanMobile}?text=${encodeURIComponent(waMsgText)}`;

      // Determine iOS or Android separator for native SMS client
      const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
      const smsSeparator = isIOS ? '&' : '?';
      const smsUrl = `sms:+${cleanMobile}${smsSeparator}body=${encodeURIComponent(msgText)}`;

      const titleText = isKandhuvatti && paymentType === 'principal' ? 'Principal Payment Successful!' : 'Payment Successful!';

      const badgeHtml = isPartial 
        ? `<span class="badge bg-label-warning mb-3 fs-6 px-3 py-2"><i class="ri-alert-line me-1"></i>Partially Paid</span>` 
        : `<span class="badge bg-label-success mb-3 fs-6 px-3 py-2"><i class="ri-checkbox-circle-line me-1"></i>Fully Paid</span>`;

      Swal.fire({
        title: titleText,
        icon: 'success',
        html: `
          <div class="py-2 text-center">
            ${badgeHtml}
            <h6 class="text-success mb-3">${msg}</h6>
            <p class="text-muted small mb-4">Send payment confirmation receipt to client number: <strong>+${cleanMobile}</strong></p>
            
            <div class="d-grid gap-2 col-10 mx-auto">
              <button type="button" id="swal-payment-wa-btn" class="btn btn-success d-flex align-items-center justify-content-center gap-2 py-2" style="background-color: #25D366; border-color: #25D366; color: white; font-weight: 500;">
                <i class="ri-whatsapp-line fs-5"></i> Send WhatsApp Confirmation
              </button>
              
              <button type="button" id="swal-payment-sms-btn" class="btn btn-info d-flex align-items-center justify-content-center gap-2 py-2" style="background-color: #0088cc; border-color: #0088cc; color: white; font-weight: 500;">
                <i class="ri-message-3-line fs-5"></i> Send Native SMS
              </button>
            </div>
          </div>
        `,
        showCancelButton: false,
        showCloseButton: true,
        confirmButtonText: 'Done & Close',
        customClass: {
          confirmButton: 'btn btn-primary px-5 mt-3'
        },
        didOpen: (popup) => {
          popup.querySelector('#swal-payment-wa-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            window.open(waUrl, '_blank', 'noopener,noreferrer');
          });
          popup.querySelector('#swal-payment-sms-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            window.location.href = smsUrl;
          });
        }
      }).then(() => {
        window.location.reload();
      });
    } else {
      Swal.fire({
        title: 'Success!',
        text: msg,
        icon: 'success',
        customClass: { confirmButton: 'btn btn-primary' }
      }).then(() => {
        window.location.reload();
      });
    }
  }

  // Handle session flash messages
  const alertContainer = document.querySelector('.alert-container');
  if (alertContainer) {
    const successMessage = alertContainer.getAttribute('data-success');
    const errorMessage = alertContainer.getAttribute('data-error');
    const warningMessage = alertContainer.getAttribute('data-warning');
    const infoMessage = alertContainer.getAttribute('data-info');

    if (successMessage) {
      showAlert('success', 'Success', successMessage);
    }
    if (errorMessage) {
      showAlert('danger', 'Error', errorMessage);
    }
    if (warningMessage) {
      showAlert('warning', 'Warning', warningMessage);
    }
    if (infoMessage) {
      showAlert('info', 'Info', infoMessage);
    }
  }

  const paymentMethodSelect = document.getElementById('paymentMethod');
  const internalBankAccountSelect = document.getElementById('internal_bank_account_id');
  const partialPaymentMethodSelect = document.getElementById('partialPaymentMethod');
  const partialInternalBankAccountSelect = document.getElementById('partial_internal_bank_account_id');

  if (window.BankPaymentFields) {
    window.BankPaymentFields.initGroups([
      {
        methodSelectId: 'paymentMethod',
        bankSelectId: 'internal_bank_account_id',
        bankContainerId: 'bankAccountContainer',
        qrContainerId: 'qrCodeDisplayContainer',
        qrBankNameId: 'qrBankName',
        qrUpiIdId: 'qrUpiId',
        qrImageWrapperId: 'qrCodeImageWrapper',
        bankTransferContainerId: 'bankTransferDetailsContainer',
        bankTransferContentId: 'bankTransferDetailsContent'
      },
      {
        methodSelectId: 'partialPaymentMethod',
        bankSelectId: 'partial_internal_bank_account_id',
        bankContainerId: 'partialBankAccountContainer',
        qrContainerId: 'partialQrCodeDisplayContainer',
        qrBankNameId: 'partialQrBankName',
        qrUpiIdId: 'partialQrUpiId',
        qrImageWrapperId: 'partialQrCodeImageWrapper',
        bankTransferContainerId: 'partialBankTransferDetailsContainer',
        bankTransferContentId: 'partialBankTransferDetailsContent'
      },
      {
        methodSelectId: 'selected_payment_method',
        bankSelectId: 'selected_internal_bank_account_id',
        bankContainerId: 'selectedBankAccountContainer',
        qrContainerId: 'selectedQrCodeDisplayContainer',
        qrBankNameId: 'selectedQrBankName',
        qrUpiIdId: 'selectedQrUpiId',
        qrImageWrapperId: 'selectedQrCodeImageWrapper',
        bankTransferContainerId: 'selectedBankTransferDetailsContainer',
        bankTransferContentId: 'selectedBankTransferDetailsContent'
      }
    ]);
  } else {
    window._bankPaymentGroupsQueue = [
      {
        methodSelectId: 'paymentMethod',
        bankSelectId: 'internal_bank_account_id',
        bankContainerId: 'bankAccountContainer',
        qrContainerId: 'qrCodeDisplayContainer',
        qrBankNameId: 'qrBankName',
        qrUpiIdId: 'qrUpiId',
        qrImageWrapperId: 'qrCodeImageWrapper',
        bankTransferContainerId: 'bankTransferDetailsContainer',
        bankTransferContentId: 'bankTransferDetailsContent'
      },
      {
        methodSelectId: 'partialPaymentMethod',
        bankSelectId: 'partial_internal_bank_account_id',
        bankContainerId: 'partialBankAccountContainer',
        qrContainerId: 'partialQrCodeDisplayContainer',
        qrBankNameId: 'partialQrBankName',
        qrUpiIdId: 'partialQrUpiId',
        qrImageWrapperId: 'partialQrCodeImageWrapper',
        bankTransferContainerId: 'partialBankTransferDetailsContainer',
        bankTransferContentId: 'partialBankTransferDetailsContent'
      },
      {
        methodSelectId: 'selected_payment_method',
        bankSelectId: 'selected_internal_bank_account_id',
        bankContainerId: 'selectedBankAccountContainer',
        qrContainerId: 'selectedQrCodeDisplayContainer',
        qrBankNameId: 'selectedQrBankName',
        qrUpiIdId: 'selectedQrUpiId',
        qrImageWrapperId: 'selectedQrCodeImageWrapper',
        bankTransferContainerId: 'selectedBankTransferDetailsContainer',
        bankTransferContentId: 'selectedBankTransferDetailsContent'
      }
    ];
  }

  // EMI Payment Modal Handler
  document.querySelectorAll('.pay-emi-btn').forEach(button => {
    button.addEventListener('click', function () {
      const emiId = this.getAttribute('data-emi-id');
      const emiNumber = this.getAttribute('data-emi-number');
      const totalAmount = parseFloat(this.getAttribute('data-total-amount'));
      const interestAmount = parseFloat(this.getAttribute('data-interest-amount')) || 0;
      const principalAmount = parseFloat(this.getAttribute('data-principal-amount')) || 0;
      const paidAmount = parseFloat(this.getAttribute('data-paid-amount')) || 0;
      const remainingAmount = parseFloat(this.getAttribute('data-remaining-amount'));
      const penaltyAmount = parseFloat(this.getAttribute('data-penalty-amount'));
      const isKandhuvatti = this.getAttribute('data-is-kandhuvatti') === 'true';

      // Populate modal fields
      document.getElementById('emiId').value = emiId;
      document.getElementById('modalEmiNumber').textContent = emiNumber;
      document.getElementById('totalEmiAmount').value = totalAmount.toFixed(2);
      const penaltyInput = document.getElementById('penaltyAmount');
      if (penaltyInput) {
        penaltyInput.value = penaltyAmount.toFixed(2);
      }

      // Set labels and portions for Kandhuvatti
      const totalLabel = document.getElementById('totalEmiAmountLabel');
      const interestPortionGroup = document.getElementById('interestPortionGroup');
      const principalPortionGroup = document.getElementById('principalPortionGroup');

      if (isKandhuvatti) {
        document.getElementById('payEmiModalLabel').innerHTML = '<i class="icon-base ri ri-money-dollar-circle-line me-2"></i>Pay Interest Cycle #<span id="modalEmiNumber">' + emiNumber + '</span>';
        if (totalLabel) totalLabel.textContent = 'Total Due';
        
        if (interestPortionGroup) {
          document.getElementById('interestPortion').value = interestAmount.toFixed(2);
          interestPortionGroup.classList.remove('d-none');
        }
        if (principalPortionGroup) {
          document.getElementById('principalPortion').value = principalAmount.toFixed(2);
          principalPortionGroup.classList.remove('d-none');
        }

        document.getElementById('principalAmountGroup').classList.add('d-none'); // Hide principal amount in standard Pay button
        document.getElementById('paidAmount').readOnly = true; // Make it non-editable
        document.getElementById('paidAmountHelp').textContent = 'Fixed total amount to pay';
      } else {
        document.getElementById('payEmiModalLabel').innerHTML = '<i class="icon-base ri ri-money-dollar-circle-line me-2"></i>Pay EMI #<span id="modalEmiNumber">' + emiNumber + '</span>';
        if (totalLabel) totalLabel.textContent = 'Total EMI Amount';
        
        if (interestPortionGroup) interestPortionGroup.classList.add('d-none');
        if (principalPortionGroup) principalPortionGroup.classList.add('d-none');

        document.getElementById('principalAmountGroup').classList.add('d-none');
        document.getElementById('paidAmount').readOnly = true;
        document.getElementById('paidAmountHelp').textContent = 'Enter the amount being paid';
      }

      // Set default amount to remaining amount (which already includes penalty in the new logic)
      const defaultAmount = remainingAmount;
      document.getElementById('paidAmount').value = defaultAmount.toFixed(2);
      if (document.getElementById('principalAmount')) {
        document.getElementById('principalAmount').value = '';
      }

      // Set today's date as default
      const today = new Date().toISOString().split('T')[0];
      document.getElementById('paidDate').value = today;

      // Reset payment method and bank account
      if (window.BankPaymentFields) {
        window.BankPaymentFields.resetToInHand('paymentMethod');
      } else if (paymentMethodSelect) {
        paymentMethodSelect.value = 'in_hand';
        paymentMethodSelect.dispatchEvent(new Event('change'));
      }

      // Show modal
      const modalElement = document.getElementById('payEmiModal');
      let modal = bootstrap.Modal.getInstance(modalElement);
      if (!modal) {
        modal = new bootstrap.Modal(modalElement);
      }
      modal.show();
    });
  });

  // EMI Payment Form Submission
  const payEmiForm = document.getElementById('payEmiForm');
  if (payEmiForm) {
    payEmiForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const submitBtn = document.getElementById('submitPaymentBtn');
      const originalBtnText = submitBtn.innerHTML;

      // Validate amount
      const paidAmount = parseFloat(document.getElementById('paidAmount').value);
      if (paidAmount <= 0) {
        showAlert('danger', 'Invalid Amount', 'Please enter a valid amount greater than zero.');
        return;
      }

      let formData;
      try {
        const methodSelect = this.querySelector('select[name="payment_method"]');
        const bankSelect = this.querySelector('select[name="internal_bank_account_id"]');
        formData = window.BankPaymentFields.prepareFormData(this, methodSelect, bankSelect);
      } catch (error) {
        showAlert('danger', 'Validation Error', error.message);
        return;
      }

      // Show loading state
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Processing...';

      // Get CSRF token
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      // Submit payment
      fetch(`${baseUrl}client/loan/emi/pay`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      })
        .then(async response => {
          const data = await response.json();
          if (!response.ok) {
            const validationMsg = data.errors
              ? Object.values(data.errors).flat().join(' ')
              : '';
            throw new Error(data.message || validationMsg || 'Payment failed.');
          }
          return data;
        })
        .then(data => {
          if (data.success) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('payEmiModal'));
            modal.hide();

            // Show success message
            handlePaymentSuccess(data, 'EMI payment has been processed successfully.');
          } else {
            Swal.fire({
              title: 'Error!',
              text: data.message || 'Payment failed',
              icon: 'error',
              customClass: { confirmButton: 'btn btn-primary' }
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
          }
        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire({
            title: 'Error!',
            text: error.message || 'Something went wrong. Please check your connection and try again.',
            icon: 'error',
            customClass: { confirmButton: 'btn btn-primary' }
          });
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnText;
        });
    });
  }

  // Partial Payment Button Handler
  document.querySelectorAll('.partial-payment-btn').forEach(button => {
    button.addEventListener('click', function () {
      const emiId = this.getAttribute('data-emi-id');
      const emiNumber = this.getAttribute('data-emi-number');
      const totalAmount = parseFloat(this.getAttribute('data-total-amount'));
      const interestAmount = parseFloat(this.getAttribute('data-interest-amount')) || 0;
      const paidAmount = parseFloat(this.getAttribute('data-paid-amount')) || 0;
      const principalPaidOnEmi = parseFloat(this.getAttribute('data-principal-amount')) || 0;
      const previousBalance = parseFloat(this.getAttribute('data-previous-balance')) || 0;
      const penaltyAmount = parseFloat(this.getAttribute('data-penalty-amount')) || 0;
      const minPercentage = parseFloat(this.getAttribute('data-min-percentage')) || 10;
      const isKandhuvatti = this.getAttribute('data-is-kandhuvatti') === 'true';
      const outstandingPrincipal = parseFloat(this.getAttribute('data-outstanding-principal')) || 0;
      const penaltyMethod = this.getAttribute('data-penalty-method') || 'emi_amount';

      const openPartialModal = (rules) => {
      if (rules && !rules.allows_partial) {
        showAlert('warning', 'Partial payment not allowed', rules.timing_message || 'Partial payments are not allowed for this EMI at this time.');
        return;
      }

      // Calculate total due and minimum amount (fallback if API unavailable)
      let totalDue;
      if (isKandhuvatti) {
        const interestPaid = Math.max(0, paidAmount - principalPaidOnEmi);
        totalDue = Math.max(0, previousBalance + interestAmount + penaltyAmount - interestPaid);
      } else {
        totalDue = Math.max(0, previousBalance + totalAmount + penaltyAmount - paidAmount);
      }

      let minimumAmount;
      if (rules && rules.is_active) {
        totalDue = rules.outstanding_due ?? totalDue;
        minimumAmount = rules.minimum_partial_amount || 0;
      } else {
        const minBase = penaltyMethod === 'emi_plus_partial_remaining'
          ? totalDue
          : (isKandhuvatti ? (interestAmount + previousBalance) : (totalAmount + previousBalance));
        minimumAmount = Math.ceil((minBase * minPercentage) / 100);
      }

      // Populate modal fields
      document.getElementById('partialEmiId').value = emiId;
      document.getElementById('partialEmiNumber').textContent = emiNumber;

      // Kandhuvatti UI Adjustments
      const principalGroup = document.getElementById('partialPrincipalGroup');
      const totalEmiGroup = document.getElementById('partialTotalEmiGroup');
      const paidAmountGroup = document.getElementById('partialPaidAmountGroup');
      const partialPrincipalGroup = document.getElementById('partialPrincipalAmountGroup');
      const partialPaymentAmountLabel = document.getElementById('partialPaymentAmountLabel');

      // Breakdown Cards Elements
      const interestPortionCard = document.getElementById('partialInterestPortionCard');
      const principalPortionCard = document.getElementById('partialPrincipalPortionCard');
      const remainingInterestCard = document.getElementById('partialRemainingInterestCard');
      const principalPaidCard = document.getElementById('partialPrincipalPaidCard');
      
      if (isKandhuvatti) {
        // Hide standard cards
        if (totalEmiGroup) totalEmiGroup.classList.add('d-none');
        if (paidAmountGroup) paidAmountGroup.classList.add('d-none');

        // Show custom breakdown cards
        if (interestPortionCard) {
          document.getElementById('partialInterestPortionDisplay').textContent = '₹' + interestAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          interestPortionCard.classList.remove('d-none');
        }
        if (principalPortionCard) {
          document.getElementById('partialPrincipalPortionDisplay').textContent = '₹' + (totalAmount - interestAmount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          principalPortionCard.classList.remove('d-none');
        }
        if (remainingInterestCard) {
          const interestPaid = Math.max(0, paidAmount - principalPaidOnEmi);
          const remainingInterestDue = Math.max(0, interestAmount + penaltyAmount - interestPaid);
          document.getElementById('partialRemainingInterestDisplay').textContent = '₹' + remainingInterestDue.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          remainingInterestCard.classList.remove('d-none');
        }
        if (principalPaidCard) {
          document.getElementById('partialPrincipalPaidDisplay').textContent = '₹' + principalPaidOnEmi.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          principalPaidCard.classList.remove('d-none');
        }

        if (principalGroup) {
          principalGroup.classList.remove('d-none');
          const principalDisplay = document.getElementById('partialPrincipalDisplay');
          if (principalDisplay) {
            principalDisplay.textContent = '₹' + outstandingPrincipal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          }
        }
        if (partialPaymentAmountLabel) {
          const container = partialPaymentAmountLabel.closest('.mb-3');
          if(container) container.classList.add('d-none');
          const input = document.getElementById('partialPaymentAmount');
          if(input) { input.removeAttribute('required'); input.value = ''; }
        }
        if (partialPrincipalGroup) {
          partialPrincipalGroup.classList.remove('d-none');
          const partialPrincipalAmountInput = document.getElementById('partialPrincipalAmount');
          if (partialPrincipalAmountInput) {
            partialPrincipalAmountInput.setAttribute('required', 'required');
            partialPrincipalAmountInput.value = '';
          }
        }
      } else {
        // Show standard cards
        if (totalEmiGroup) totalEmiGroup.classList.remove('d-none');
        if (paidAmountGroup) paidAmountGroup.classList.remove('d-none');

        // Hide custom breakdown cards
        if (interestPortionCard) interestPortionCard.classList.add('d-none');
        if (principalPortionCard) principalPortionCard.classList.add('d-none');
        if (remainingInterestCard) remainingInterestCard.classList.add('d-none');
        if (principalPaidCard) principalPaidCard.classList.add('d-none');

        if (principalGroup) {
          principalGroup.classList.add('d-none');
        }
        if (partialPrincipalGroup) {
          partialPrincipalGroup.classList.add('d-none');
          const partialPrincipalAmountInput = document.getElementById('partialPrincipalAmount');
          if (partialPrincipalAmountInput) {
            partialPrincipalAmountInput.removeAttribute('required');
            partialPrincipalAmountInput.value = '';
          }
        }
        if (partialPaymentAmountLabel) {
          const container = partialPaymentAmountLabel.closest('.mb-3');
          if(container) container.classList.remove('d-none');
          const input = document.getElementById('partialPaymentAmount');
          if(input) input.setAttribute('required', 'required');
          partialPaymentAmountLabel.innerHTML = 'Partial Payment Amount <span class="text-danger">*</span>';
        }
      }

      // Display values
      if (document.getElementById('partialTotalEmiDisplay')) {
        document.getElementById('partialTotalEmiDisplay').textContent = '₹' + totalAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
      if (document.getElementById('partialPreviousBalanceDisplay')) {
        document.getElementById('partialPreviousBalanceDisplay').textContent = '₹' + previousBalance.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
      if (document.getElementById('partialPaidAmountDisplay')) {
        document.getElementById('partialPaidAmountDisplay').textContent = '₹' + paidAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
      if (document.getElementById('partialTotalDueDisplay')) {
        document.getElementById('partialTotalDueDisplay').textContent = '₹' + Math.floor(totalDue);
      }

      // Hide/show Previous Balance card based on value
      const prevBalDisp = document.getElementById('partialPreviousBalanceDisplay');
      if (prevBalDisp) {
        const previousBalanceCard = prevBalDisp.closest('.col-md-4') || prevBalDisp.closest('.col-md-6');
        if (previousBalanceCard) {
          previousBalanceCard.style.display = previousBalance === 0 ? 'none' : 'block';
        }
      }

      // Hide/show Already Paid card based on value
      const paidAmtDisp = document.getElementById('partialPaidAmountDisplay');
      if (paidAmtDisp) {
        const paidAmountCard = paidAmtDisp.closest('.col-md-4') || paidAmtDisp.closest('.col-md-6');
        if (paidAmountCard) {
          paidAmountCard.style.display = paidAmount === 0 ? 'none' : 'block';
        }
      }

      // Hidden values
      if (document.getElementById('partialTotalEmi')) document.getElementById('partialTotalEmi').value = totalAmount;
      if (document.getElementById('partialPreviousBalance')) document.getElementById('partialPreviousBalance').value = previousBalance;
      if (document.getElementById('partialPaidAmount')) document.getElementById('partialPaidAmount').value = paidAmount;
      if (document.getElementById('partialTotalDue')) document.getElementById('partialTotalDue').value = totalDue;

      // Set min and max for payment amount
      const paymentInput = document.getElementById('partialPaymentAmount');
      paymentInput.setAttribute('min', minimumAmount);
      paymentInput.setAttribute('max', Math.floor(totalDue));
      paymentInput.value = Math.floor(totalDue);

      // Update help text
      const pctLabel = rules?.minimum_partial_percentage ?? minPercentage;
      const baseLabel = (rules?.penalty_calculation_method || penaltyMethod) === 'emi_plus_partial_remaining'
        ? 'outstanding balance'
        : (isKandhuvatti ? 'cycle interest' : 'EMI amount');
      document.getElementById('partialMinAmountHelp').textContent =
        `Minimum: ₹${minimumAmount} (${pctLabel}% of ${baseLabel})`;

      // Reset payment method and bank account
      if (window.BankPaymentFields) {
        window.BankPaymentFields.resetToInHand('partialPaymentMethod');
      } else if (partialPaymentMethodSelect) {
        partialPaymentMethodSelect.value = 'in_hand';
        partialPaymentMethodSelect.dispatchEvent(new Event('change'));
      }

      // Show modal
      const modalElement = document.getElementById('partialPaymentModal');
      let modal = bootstrap.Modal.getInstance(modalElement);
      if (!modal) {
        modal = new bootstrap.Modal(modalElement);
      }
      modal.show();
      };

      fetch(baseUrl + 'emi/' + emiId + '/partial-payment-rules', {
        headers: { Accept: 'application/json' }
      })
        .then(r => r.json())
        .then(rules => openPartialModal(rules))
        .catch(() => openPartialModal(null));
    });
  });

  // Partial Payment Form Validation & Mutual Exclusivity
  const partialPaymentAmount = document.getElementById('partialPaymentAmount');
  const partialPrincipalAmount = document.getElementById('partialPrincipalAmount');

  if (partialPaymentAmount && partialPrincipalAmount) {
    // Prevent decimal points on keypress
    partialPaymentAmount.addEventListener('keypress', function (e) {
      if (e.which === 46 || e.key === '.') {
        e.preventDefault();
      }
    });

    partialPrincipalAmount.addEventListener('keypress', function (e) {
      if (e.which === 46 || e.key === '.') {
        e.preventDefault();
      }
    });

    partialPaymentAmount.addEventListener('input', function () {
      // Strip any decimal points
      let val = this.value;
      if (val.indexOf('.') !== -1) {
        this.value = val.split('.')[0];
      }

      // Mutual Exclusivity: Typing Interest clears and disables Principal
      if (this.value.trim() !== '') {
        partialPrincipalAmount.value = '';
        partialPrincipalAmount.disabled = true;
        this.required = true;
      } else {
        partialPrincipalAmount.disabled = false;
      }

      // Validation
      const amount = parseFloat(this.value);
      if (isNaN(amount)) {
        this.classList.remove('is-invalid');
        const errorDiv = document.getElementById('partialAmountError');
        if (errorDiv) errorDiv.style.display = 'none';
        return;
      }

      const min = parseFloat(this.getAttribute('min'));
      const max = parseFloat(this.getAttribute('max'));
      const errorDiv = document.getElementById('partialAmountError');

      if (errorDiv) {
        if (amount < min) {
          this.classList.add('is-invalid');
          errorDiv.textContent = `Amount must be at least ₹${min}`;
          errorDiv.style.display = 'block';
        } else if (amount > max) {
          this.classList.add('is-invalid');
          errorDiv.textContent = `Amount cannot exceed ₹${max}`;
          errorDiv.style.display = 'block';
        } else {
          this.classList.remove('is-invalid');
          errorDiv.style.display = 'none';
        }
      }
    });

    partialPrincipalAmount.addEventListener('input', function () {
      // Strip any decimal points
      let val = this.value;
      if (val.indexOf('.') !== -1) {
        this.value = val.split('.')[0];
      }

      // Mutual Exclusivity: Typing Principal clears and disables Interest
      if (this.value.trim() !== '') {
        partialPaymentAmount.value = '';
        partialPaymentAmount.disabled = true;
        partialPaymentAmount.required = false; // Remove required for submission
        partialPaymentAmount.classList.remove('is-invalid');
        const errorDiv = document.getElementById('partialAmountError');
        if (errorDiv) errorDiv.style.display = 'none';
      } else {
        partialPaymentAmount.disabled = false;
        partialPaymentAmount.required = true; // Restore required
      }
    });
  }

  // Partial Payment Form Submit
  const partialPaymentForm = document.getElementById('partialPaymentForm');
  if (partialPaymentForm) {
    partialPaymentForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const submitBtn = document.getElementById('submitPartialPaymentBtn');
      const originalBtnText = submitBtn.innerHTML;

      let formData;
      try {
        const methodSelect = this.querySelector('select[name="payment_method"]');
        const bankSelect = this.querySelector('select[name="internal_bank_account_id"]');
        formData = window.BankPaymentFields.prepareFormData(this, methodSelect, bankSelect);
      } catch (error) {
        showAlert('danger', 'Validation Error', error.message);
        return;
      }

      // Validate amount based on active input
      const isKandhuvatti = !partialPaymentAmount.hasAttribute('required');

      if (!isKandhuvatti && !partialPaymentAmount.disabled) {
        const amount = parseFloat(partialPaymentAmount.value);
        const min = parseFloat(partialPaymentAmount.getAttribute('min'));
        const max = parseFloat(partialPaymentAmount.getAttribute('max'));

        if (isNaN(amount) || amount < min || amount > max) {
          showAlert('danger', 'Invalid Amount', `Please enter an interest payment amount between ₹${min} and ₹${max}`);
          return;
        }

        // Whole number validation
        if (partialPaymentAmount.value.indexOf('.') !== -1) {
          showAlert('danger', 'Invalid Amount', 'Interest payment amount must be a whole number (no decimal values).');
          return;
        }
      } else {
        const principalVal = parseFloat(partialPrincipalAmount.value);
        if (isNaN(principalVal) || principalVal <= 0.001) {
          showAlert('danger', 'Invalid Amount', 'Please enter a valid principal repayment amount greater than zero.');
          return;
        }

        // Whole number validation
        if (partialPrincipalAmount.value.indexOf('.') !== -1) {
          showAlert('danger', 'Invalid Amount', 'Principal repayment amount must be a whole number (no decimal values).');
          return;
        }

        // Check max principal repayment
        const maxPrincipal = parseFloat(document.getElementById('partialPrincipalDisplay')?.textContent.replace(/[^\d.-]/g, '') || '999999999');
        if (principalVal > maxPrincipal + 0.01) {
          showAlert('danger', 'Invalid Amount', `Principal repayment cannot exceed the outstanding principal of ₹${maxPrincipal.toFixed(2)}`);
          return;
        }
      }

      // Disable submit button and show loading
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';

      // Submit via AJAX
      fetch(baseUrl + 'emi/partial-payment', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      })
        .then(async response => {
          const data = await response.json();
          if (!response.ok) {
            const validationMsg = data.errors
              ? Object.values(data.errors).flat().join(' ')
              : '';
            throw new Error(data.message || validationMsg || 'Failed to process partial payment.');
          }
          return data;
        })
        .then(data => {
          if (data.success) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('partialPaymentModal'));
            modal.hide();

            // Show success message
            handlePaymentSuccess(data, 'Partial payment has been processed successfully.');
          } else {
            showAlert('danger', 'Payment Failed', data.message || 'Failed to process partial payment. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showAlert('danger', 'Error', error.message || 'An error occurred while processing the partial payment. Please try again.');
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnText;
        });
    });
  }

  // EMI Schedule page-length (DataTables-style Show entries)
  const emiPerPageSelect = document.getElementById('perPageSelect');
  if (emiPerPageSelect) {
    emiPerPageSelect.addEventListener('change', function () {
      const url = new URL(window.location.href);
      url.searchParams.set('per_page', this.value || '10');
      url.searchParams.set('page', '1');
      window.location.href = url.toString();
    });
  }

  // Pay Selected EMIs
  const emiScheduleToolbar = document.getElementById('emiScheduleToolbar');
  if (emiScheduleToolbar) {
    const loanOutstanding = parseFloat(emiScheduleToolbar.dataset.loanOutstanding) || 0;
    const paySelectedBtn = document.getElementById('btnPaySelectedEmis');
    const paySelectedModal = document.getElementById('paySelectedEmisModal');
    const paySelectedForm = document.getElementById('paySelectedEmisForm');
    const selectedEmiMap = new Map();
    let isSyncingSelectAll = false;
    const loanIdVal = emiScheduleToolbar ? (emiScheduleToolbar.dataset.loanId || '') : '';
    const rawPathDigits = window.location.pathname.replace(/\D/g, '');
    const storageKey = 'selectedEmis_' + (loanIdVal || rawPathDigits || 'default');

    function saveSelectionToStorage() {
      try {
        const arr = Array.from(selectedEmiMap.entries());
        const json = JSON.stringify(arr);
        sessionStorage.setItem(storageKey, json);
        localStorage.setItem(storageKey, json);
      } catch (err) {}
    }

    function loadSelectionFromStorage() {
      try {
        let saved = sessionStorage.getItem(storageKey);
        if (!saved) {
          saved = localStorage.getItem(storageKey);
        }
        if (saved) {
          const arr = JSON.parse(saved);
          if (Array.isArray(arr)) {
            arr.forEach(([id, val]) => {
              if (id && val) {
                selectedEmiMap.set(String(id), val);
              }
            });
          }
        }
      } catch (err) {}
    }

    function clearSelectionStorage() {
      try {
        sessionStorage.removeItem(storageKey);
        localStorage.removeItem(storageKey);
      } catch (err) {}
    }

    function restoreCheckboxStatesFromStorage() {
      isSyncingSelectAll = true;
      getEmiPayCheckboxes().forEach(box => {
        const id = String(box.dataset.emiId);
        if (selectedEmiMap.has(id)) {
          box.checked = true;
        }
      });
      isSyncingSelectAll = false;
    }

    loadSelectionFromStorage();

    function initStoredSelectionState() {
      loadSelectionFromStorage();
      restoreCheckboxStatesFromStorage();
      updatePaySelectedState();
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initStoredSelectionState);
    } else {
      initStoredSelectionState();
    }
    setTimeout(initStoredSelectionState, 100);
    setTimeout(initStoredSelectionState, 400);

    function getEmiPayCheckboxes() {
      if (window.jQuery && $.fn.DataTable && $.fn.DataTable.isDataTable('#emi-schedule')) {
        const dtNodes = $('#emi-schedule').DataTable().$('.emi-pay-checkbox');
        return Array.from(dtNodes);
      }
      return Array.from(document.querySelectorAll('#emi-schedule .emi-pay-checkbox'));
    }

    function getPayableEmiCheckboxes() {
      return getEmiPayCheckboxes().filter(box => box.dataset.payable === '1' || box.dataset.payable === 1);
    }

    function getSelectedEmiData(onlyPayable) {
      const items = [];
      selectedEmiMap.forEach(item => {
        if (onlyPayable && item.status === 'paid') {
          return;
        }
        items.push(item);
      });
      return items;
    }

    function syncSelectAllControls(checked) {
      document.querySelectorAll('.js-select-all-emis').forEach(el => {
        el.checked = checked;
      });
    }

    function openPaySelectedModal() {
      const selectedData = getSelectedEmiData(true);
      if (!selectedData.length) {
        showAlert('info', 'No Unpaid EMIs Selected', 'Select pending, overdue, or partial EMIs to pay.');
        return;
      }

      const emiLabels = selectedData.map(item =>
        `EMI #${item.no} (₹${item.remaining.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })})`
      );
      const total = selectedData.reduce((sum, item) => sum + item.remaining, 0);

      const summary = document.getElementById('selectedEmisSummary');
      const amountInput = document.getElementById('selected_paid_amount');
      const helpText = document.getElementById('selectedPaidAmountHelp');
      if (summary) summary.innerHTML = emiLabels.join('<br>');
      if (amountInput) {
        amountInput.value = total.toFixed(2);
        amountInput.setAttribute('data-full-total', total);
        amountInput.readOnly = true;
      }
      const fullRadio = document.querySelector('input[name="selected_pay_type"][value="full"]');
      if (fullRadio) fullRadio.checked = true;

      if (helpText) {
        helpText.textContent = `Full payment selected. Total due: ₹${total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}.`;
      }

      if (paySelectedModal && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(paySelectedModal).show();
      }
      const bulkBar = document.getElementById('emiScheduleBulkPayBar');
      if (bulkBar) bulkBar.classList.add('d-none');

      if (window.BankPaymentFields) {
        window.BankPaymentFields.resetToInHand('selected_payment_method');
      }
    }

    document.addEventListener('change', function (e) {
      if (e.target && e.target.name === 'selected_pay_type') {
        const mode = e.target.value;
        const amountInput = document.getElementById('selected_paid_amount');
        const helpText = document.getElementById('selectedPaidAmountHelp');
        const fullTotal = parseFloat(amountInput ? amountInput.getAttribute('data-full-total') : 0) || 0;
        if (mode === 'full') {
          if (amountInput) {
            amountInput.value = fullTotal.toFixed(2);
            amountInput.readOnly = true;
          }
          if (helpText) {
            helpText.textContent = `Full payment selected. Total due: ₹${fullTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}.`;
          }
        } else {
          if (amountInput) {
            amountInput.readOnly = false;
            amountInput.focus();
          }
          if (helpText) {
            helpText.textContent = 'Partial payment mode: enter custom amount to allocate across selected EMIs.';
          }
        }
      }
    });

    function updatePaySelectedState() {
      saveSelectionToStorage();
      const allCheckboxes = getEmiPayCheckboxes();
      const selectedData = getSelectedEmiData();
      const total = selectedData.reduce((sum, item) => sum + item.remaining, 0);

      if (paySelectedBtn) {
        paySelectedBtn.disabled = selectedData.length === 0;
        const hasUnpaid = selectedData.some(item => item.status !== 'paid');
        paySelectedBtn.style.display = hasUnpaid ? '' : 'none';
      }

      if (!isSyncingSelectAll) {
        const payableBoxes = Array.from(getPayableEmiCheckboxes()).filter(box => !box.disabled);
        const allChecked = payableBoxes.length > 0 && payableBoxes.every(box => selectedEmiMap.has(String(box.dataset.emiId)));
        syncSelectAllControls(allChecked);
      }

      // Sync Floating Bulk Pay Bar
      const bar = document.getElementById('emiScheduleBulkPayBar');
      if (bar) {
        if (selectedData.length === 0) {
          bar.classList.add('d-none');
          allCheckboxes.forEach(box => box.disabled = false);
        } else {
          bar.classList.remove('d-none');
          const countEl = document.getElementById('emiScheduleBulkSelectedCount');
          const amountEl = document.getElementById('emiScheduleBulkTotalAmount');
          const titleEl = document.getElementById('emiScheduleBulkBarTitle');
          const labelEl = document.getElementById('emiScheduleBulkTotalLabel');

          if (countEl) countEl.textContent = selectedData.length;
          if (amountEl) {
            amountEl.textContent = '₹' + total.toLocaleString('en-IN', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            });
          }

          let hasPaid = false;
          let hasUnpaid = false;
          selectedData.forEach(item => {
            if (item.status === 'paid') {
              hasPaid = true;
            } else {
              hasUnpaid = true;
            }
          });

          if (hasPaid) {
            if (titleEl) titleEl.textContent = 'EMIs Selected for Bulk Undo';
            if (labelEl) labelEl.textContent = 'Total Paid:';
            document.getElementById('emiScheduleBulkPayBtn')?.classList.add('d-none');
            document.getElementById('emiScheduleBulkUndoBtn')?.classList.remove('d-none');
            bar.style.borderTop = '4px solid #ea5455';
          } else {
            if (titleEl) titleEl.textContent = 'EMIs Selected for Bulk Payment';
            if (labelEl) labelEl.textContent = 'Total Overdue:';
            document.getElementById('emiScheduleBulkPayBtn')?.classList.remove('d-none');
            document.getElementById('emiScheduleBulkUndoBtn')?.classList.add('d-none');
            bar.style.borderTop = '4px solid #28c76f';
          }

          allCheckboxes.forEach(box => {
            const id = String(box.dataset.emiId);
            if (!selectedEmiMap.has(id)) {
              const isPaid = box.dataset.status === 'paid';
              if (hasPaid && !isPaid) {
                box.disabled = true;
              } else if (hasUnpaid && isPaid) {
                box.disabled = true;
              } else {
                box.disabled = false;
              }
            }
          });

          // Render selected EMIs inside the container
          const container = document.getElementById('emiScheduleBulkListContainer');
          if (container) {
            let emisHtml = '<div class="row g-2">';
            selectedData.forEach(item => {
              const priceClass = item.status === 'paid' ? 'text-danger' : 'text-success';
              emisHtml += `
                <div class="col-md-4">
                  <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded-3 border">
                    <span class="fw-semibold text-body small">EMI #${item.no}</span>
                    <strong class="${priceClass} small">₹${item.remaining.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                  </div>
                </div>
              `;
            });
            emisHtml += '</div>';
            container.innerHTML = emisHtml;
          }
        }
      }
    }

    document.addEventListener('change', function (e) {
      if (e.target.classList.contains('emi-pay-checkbox')) {
        const id = String(e.target.dataset.emiId);
        if (e.target.checked) {
          selectedEmiMap.set(id, {
            id: id,
            no: e.target.dataset.emiNo,
            status: String(e.target.dataset.status || ''),
            remaining: parseFloat(e.target.dataset.remaining || 0)
          });
        } else {
          selectedEmiMap.delete(id);
        }
        updatePaySelectedState();
      }
    });

    document.addEventListener('change', function (e) {
      if (!e.target.classList.contains('js-select-all-emis') || isSyncingSelectAll) {
        return;
      }

      const checked = e.target.checked;
      isSyncingSelectAll = true;
      getPayableEmiCheckboxes().forEach(box => {
        if (!box.disabled) {
          box.checked = checked;
          const id = String(box.dataset.emiId);
          if (checked) {
            selectedEmiMap.set(id, {
              id: id,
              no: box.dataset.emiNo,
              status: String(box.dataset.status || ''),
              remaining: parseFloat(box.dataset.remaining || 0)
            });
          } else {
            selectedEmiMap.delete(id);
          }
        }
      });
      syncSelectAllControls(checked);
      isSyncingSelectAll = false;
      updatePaySelectedState();
    });

    document.addEventListener('click', function (e) {
      if (e.target.classList.contains('js-select-all-emis')) {
        e.stopPropagation();
      }
    });

    // Handle floating bar buttons
    document.addEventListener('click', function (e) {
      if (e.target.id === 'emiScheduleBulkCancelBtn') {
        selectedEmiMap.clear();
        clearSelectionStorage();
        document.querySelectorAll('.js-select-all-emis').forEach(el => { el.checked = false; });
        getEmiPayCheckboxes().forEach(box => {
          box.checked = false;
          box.disabled = false;
        });
        updatePaySelectedState();
      }
    });

    document.addEventListener('click', function (e) {
      if (e.target.id === 'emiScheduleBulkPayBtn' || e.target.closest('#emiScheduleBulkPayBtn')) {
        openPaySelectedModal();
      }
    });

    document.addEventListener('click', function (e) {
      if (e.target.id === 'emiScheduleBulkUndoBtn' || e.target.closest('#emiScheduleBulkUndoBtn')) {
        if (selectedEmiMap.size === 0) return;

        const emiIds = Array.from(selectedEmiMap.keys());
        const selectedData = getSelectedEmiData();
        let totalSum = selectedData.reduce((sum, item) => sum + item.remaining, 0);

        Swal.fire({
          title: 'Confirm Bulk Undo Payment',
          text: `You are about to undo payments for all ${selectedEmiMap.size} selected EMIs. Total amount to be undone is ₹${totalSum.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}. This will mark these EMIs as pending/overdue!`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, Undo All!',
          cancelButtonText: 'Cancel',
          confirmButtonColor: '#ea5455',
          showLoaderOnConfirm: true,
          preConfirm: () => {
            return fetch(`${baseUrl}emi/repayments/bulk-undo`, {
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
              },
              body: JSON.stringify({ emi_ids: emiIds })
            })
            .then(response => {
              if (!response.ok) {
                return response.json().then(err => { throw new Error(err.message || 'Bulk undo failed.') });
              }
              return response.json();
            })
            .catch(error => {
              Swal.showValidationMessage(`Error: ${error.message}`);
            });
          },
          allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
          if (result.isConfirmed && result.value && result.value.success) {
            Swal.fire({
              title: 'Bulk Undo Successful!',
              text: result.value.message || 'Selected payments successfully undone.',
              icon: 'success',
              confirmButtonColor: '#28c76f'
            }).then(() => {
              selectedEmiIds.clear();
              updatePaySelectedState();
              window.location.reload();
            });
          }
        });
      }
    });

    if (paySelectedBtn) {
      paySelectedBtn.addEventListener('click', function () {
        openPaySelectedModal();
      });
    }

    if (paySelectedForm) {
      paySelectedForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const selectedData = getSelectedEmiData(true);
        if (!selectedData.length) {
          showAlert('info', 'No Unpaid EMIs', 'Select pending, overdue, or partial EMIs to pay.');
          return;
        }

        const amount = parseFloat(document.getElementById('selected_paid_amount')?.value || 0);
        if (isNaN(amount) || amount <= 0) {
          showAlert('danger', 'Invalid Amount', 'Please enter a valid payment amount.');
          return;
        }
        if (amount > loanOutstanding + 0.01) {
          showAlert('danger', 'Amount Too High', `Payment cannot exceed loan outstanding of ₹${loanOutstanding.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}.`);
          return;
        }

        const methodSelectEl = document.getElementById('selected_payment_method');
        const bankSelectEl = document.getElementById('selected_internal_bank_account_id');
        const bankValidationError = window.BankPaymentFields
          ? window.BankPaymentFields.validateBankPayment(methodSelectEl, bankSelectEl, paySelectedForm)
          : null;
        if (bankValidationError) {
          showAlert('danger', 'Validation Error', bankValidationError);
          return;
        }

        const paymentMethod = methodSelectEl?.value || 'in_hand';
        const bankAccountId = bankSelectEl?.value || null;
        const submitBtn = paySelectedForm.querySelector('button[type="submit"]');
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = 'Processing...';
        }

        const emiIds = selectedData.map(item => item.id);

        fetch(baseUrl + 'emi/receipts/pay-selected', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            emi_ids: emiIds,
            paid_amount: amount,
            paid_date: document.getElementById('selected_paid_date')?.value,
            payment_method: paymentMethod,
            internal_bank_account_id: paymentMethod === 'in_hand' ? null : bankAccountId,
            remarks: document.getElementById('selected_remarks')?.value || ''
          })
        })
          .then(r => r.json())
          .then(data => {
            if (submitBtn) {
              submitBtn.disabled = false;
              submitBtn.textContent = 'Confirm Payment';
            }
            if (data.success) {
              clearSelectionStorage();
              selectedEmiMap.clear();
              if (paySelectedModal && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getInstance(paySelectedModal)?.hide();
              }
              handlePaymentSuccess(data, 'Selected EMIs paid successfully.');
            } else {
              showAlert('danger', 'Payment Failed', data.message || 'Payment failed.');
            }
          })
          .catch(() => {
            if (submitBtn) {
              submitBtn.disabled = false;
              submitBtn.textContent = 'Confirm Payment';
            }
            showAlert('danger', 'Error', 'An error occurred while processing the payment.');
          });
      });
    }
  }

  // Handle Admin Undo Payment button clicks (in view-loan-account or client-loan-emi-details)
  document.addEventListener('click', function (e) {
    const undoBtn = e.target.closest('.btn-undo-payment');
    if (undoBtn) {
      e.preventDefault();
      e.stopPropagation();

      const emiId = undoBtn.getAttribute('data-emi-id');
      const instalment = undoBtn.getAttribute('data-instalment') || '';

      Swal.fire({
        title: 'Are you sure?',
        text: `You are about to undo the payment for instalment/cycle #${instalment}. This will reverse all calculated values, restore the balance, and delete related collection entries!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, undo payment!',
        cancelButtonText: 'Cancel',
        input: 'text',
        inputPlaceholder: 'Enter reason to undo...',
        inputAttributes: {
          autocapitalize: 'off'
        },
        preConfirm: (reason) => {
          if (!reason) {
            Swal.showValidationMessage('Please enter a reason to undo the payment.');
            return false;
          }
          return reason;
        }
      }).then((result) => {
        if (result.isConfirmed) {
          // Show loading state
          Swal.fire({
            title: 'Undoing Payment...',
            allowOutsideClick: false,
            didOpen: () => {
              Swal.showLoading();
            }
          });

          const reason = result.value;

          fetch(`${baseUrl}emi/payment/${emiId}/undo`, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': csrfToken,
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ reason: reason })
          })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                Swal.fire({
                  title: 'Success!',
                  text: data.message || 'Payment has been undone successfully.',
                  icon: 'success'
                }).then(() => {
                  window.location.reload();
                });
              } else {
                Swal.fire('Error!', data.message || 'Failed to undo payment.', 'error');
              }
            })
            .catch(error => {
              console.error('Error undoing payment:', error);
              Swal.fire('Error!', 'An error occurred while undoing the payment.', 'error');
            });
        }
      });
    }
  });

  // Handle Open Loan Interest Cycles Generation Form Submit
  const formGenerateCycles = document.getElementById('formGenerateOpenLoanCycles');
  if (formGenerateCycles) {
    formGenerateCycles.addEventListener('submit', function (e) {
      e.preventDefault();

      const countInput = document.getElementById('generate_cycle_count');
      const count = parseInt(countInput ? countInput.value : 0, 10);
      if (!count || count < 1) {
        Swal.fire({
          icon: 'warning',
          title: 'Invalid Input',
          text: 'Please enter a valid number of cycles greater than zero.'
        });
        return;
      }

      const submitBtn = document.getElementById('btnSubmitGenerateCycles');
      const origText = submitBtn ? submitBtn.innerHTML : 'Generate Cycles';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Generating...';
      }

      const url = this.getAttribute('action');
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ cycle_count: count })
      })
      .then(async response => {
        const data = await response.json();
        if (!response.ok) {
          throw new Error(data.message || 'Failed to generate cycles.');
        }
        return data;
      })
      .then(data => {
        const modalEl = document.getElementById('generateCyclesModal');
        if (modalEl) {
          const bsModal = bootstrap.Modal.getInstance(modalEl);
          if (bsModal) {
            bsModal.hide();
          }
        }

        Swal.fire({
          icon: 'success',
          title: 'Cycles Generated!',
          text: data.message || 'Interest cycles have been generated successfully.',
          timer: 1600,
          showConfirmButton: false
        }).then(() => {
          window.location.reload();
        });
      })
      .catch(error => {
        console.error('Error generating cycles:', error);
        Swal.fire({
          icon: 'error',
          title: 'Generation Failed',
          text: error.message || 'An error occurred while generating cycles.'
        });
      })
      .finally(() => {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = origText;
        }
      });
    });
  }
});
