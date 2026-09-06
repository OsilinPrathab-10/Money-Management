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

  // Initialize DataTable if available
  const loanHistoryTable = document.getElementById('loanHistoryTable');
  if (
    loanHistoryTable &&
    loanHistoryTable.dataset.hasRows === 'true' &&
    typeof $.fn.DataTable !== 'undefined'
  ) {
    $(loanHistoryTable).DataTable({
      order: [],
      pageLength: 10,
      responsive: true,
      language: {
        search: 'Search:',
        lengthMenu: 'Show _MENU_ entries',
        info: 'Showing _START_ to _END_ of _TOTAL_ entries',
        infoEmpty: 'No entries available',
        infoFiltered: '(filtered from _MAX_ total entries)',
        paginate: {
          first: 'First',
          last: 'Last',
          next: 'Next',
          previous: 'Previous'
        }
      }
    });
  }

  // Document View Button Handler
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

      if (!msgText || !waMsgText) {
        let fallbackMsgText = '';
        if (isKandhuvatti) {
          if (paymentType === 'principal') {
            fallbackMsgText = `Dear ${clientName},\nYour Principal payment of ₹${amountPaid} towards Shanmuga Finance Open Loan Account ${accountNo} has been received successfully.\nRemaining Principal Balance: ₹${remainingBalance}.\nThank you!`;
          } else {
            if (isPartial) {
              fallbackMsgText = `Dear ${clientName},\nYour Partial Interest payment of ₹${amountPaid} towards Shanmuga Finance Open Loan Account ${accountNo} has been received successfully.\nBalance Interest to pay: ₹${emiBalance}.\nRemaining Principal Balance: ₹${remainingBalance}.\nThank you!`;
            } else {
              fallbackMsgText = `Dear ${clientName},\nYour Interest payment of ₹${amountPaid} towards Shanmuga Finance Open Loan Account ${accountNo} has been received successfully.\nRemaining Principal Balance: ₹${remainingBalance}.\nThank you!`;
            }
          }
        } else {
          if (isPartial) {
            fallbackMsgText = `Dear ${clientName},\nYour Partial EMI payment of ₹${amountPaid} towards Shanmuga Finance Loan Account ${accountNo} has been received successfully.\nBalance EMI to pay: ₹${emiBalance}.\nOutstanding Balance: ₹${remainingBalance}.\nThank you!`;
          } else {
            fallbackMsgText = `Dear ${clientName},\nYour EMI payment of ₹${amountPaid} towards Shanmuga Finance Loan Account ${accountNo} has been received successfully.\nOutstanding Balance: ₹${remainingBalance}.\nThank you!`;
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
              <a href="${waUrl}" target="_blank" class="btn btn-success d-flex align-items-center justify-content-center gap-2 py-2" style="background-color: #25D366; border-color: #25D366; color: white; font-weight: 500;">
                <i class="ri-whatsapp-line fs-5"></i> Send WhatsApp Confirmation
              </a>
              
              <a href="${smsUrl}" class="btn btn-info d-flex align-items-center justify-content-center gap-2 py-2" style="background-color: #0088cc; border-color: #0088cc; color: white; font-weight: 500;">
                <i class="ri-message-3-line fs-5"></i> Send Native SMS
              </a>
            </div>
          </div>
        `,
        showCancelButton: false,
        showCloseButton: true,
        confirmButtonText: 'Done & Close',
        customClass: {
          confirmButton: 'btn btn-primary px-5 mt-3'
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

      // Show loading state
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Processing...';

      // Prepare form data
      const formData = new FormData(this);

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
        .then(response => response.json())
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
            text: 'Something went wrong. Please check your connection and try again.',
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
        if (partialPrincipalGroup) {
          partialPrincipalGroup.classList.remove('d-none');
          const partialPrincipalAmountInput = document.getElementById('partialPrincipalAmount');
          if (partialPrincipalAmountInput) {
            partialPrincipalAmountInput.value = '';
          }
        }
        if (partialPaymentAmountLabel) {
          partialPaymentAmountLabel.innerHTML = 'Partial Interest Payment Amount ';
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
        }
        if (partialPaymentAmountLabel) {
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
      const formData = new FormData(this);

      // Validate amount based on active input
      if (!partialPaymentAmount.disabled) {
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
        .then(response => response.json())
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
          showAlert('danger', 'Error', 'An error occurred while processing the partial payment. Please try again.');
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnText;
        });
    });
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
});
