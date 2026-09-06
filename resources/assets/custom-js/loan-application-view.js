/**
 * Loan Application View - Status-based Actions
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const applicationId = window.location.pathname.split('/').pop();
  const baseUrl = document.documentElement.getAttribute('data-base-url') || window.location.origin + '/';

  // Initialize Flatpickr for date fields
  const datePickers = document.querySelectorAll('.flatpickr-date');
  if (datePickers.length > 0) {
    datePickers.forEach(picker => {
      flatpickr(picker, {
        monthSelectorType: 'static',
        dateFormat: 'd-m-Y',
        onChange: function(selectedDates, dateStr, instance) {
          if (selectedDates.length > 0) {
            const date = selectedDates[0];
            
            // 1. If this is the disbursement date, dynamically calculate the EMI start date
            if (instance.element.id === 'disbursedAt') {
              const frequency = instance.element.getAttribute('data-frequency') || 'monthly';
              let targetDate = new Date(date);
              if (frequency === 'daily') {
                targetDate.setDate(targetDate.getDate() + 1);
              } else if (frequency === 'weekly') {
                targetDate.setDate(targetDate.getDate() + 7);
              } else {
                targetDate.setMonth(targetDate.getMonth() + 1);
              }
              const emiStartEl = document.getElementById('emiStartDate');
              if (emiStartEl && emiStartEl._flatpickr) {
                emiStartEl._flatpickr.setDate(targetDate, true); // trigger onChange for emiStartDate
              }
            }

            // 2. If this is the EMI start date, update the weekday display name
            if (instance.element.id === 'emi_start_date_step2') {
              const display = document.getElementById('emi_weekday_display_step2');
              if (display) {
                const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                display.textContent = 'Weekday: ' + days[date.getDay()];
              }
            }
            if (instance.element.id === 'emiStartDate') {
              const display = document.getElementById('emi_weekday_display_step3');
              if (display) {
                const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                display.textContent = 'Weekday: ' + days[date.getDay()];
              }
            }

            // 3. Update the emi_day inputs/selects on the page
            const emiDayFields = document.querySelectorAll('[name="emi_day"]');
            const isWeekly = !!document.querySelector('select[name="emi_day"]');
            
            emiDayFields.forEach(field => {
              if (isWeekly) {
                let day = date.getDay();
                if (day === 0) day = 7;
                field.value = day;
                if (window.jQuery && jQuery(field).data('select2')) {
                  jQuery(field).trigger('change');
                }
              } else {
                field.value = date.getDate();
              }
            });
          }
        }
      });
    });
  }

  // Approve Form Handler
  const approveForm = document.getElementById('approveForm');
  if (approveForm) {
    approveForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = {
        approved_amount: document.getElementById('approvedAmount').value,
        approved_tenure_min: document.getElementById('approvedTenureMin').value,
        approved_tenure_max: document.getElementById('approvedTenureMax').value,
        interest_rate: document.getElementById('interestRate').value
      };

      // Show loading state
      const submitBtn = approveForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

      // Send approval request
      fetch(`${baseUrl}loan/loan-applications/${applicationId}/approve`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(formData)
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showToast('success', data.message);
            setTimeout(() => {
              window.location.reload();
            }, 1500);
          } else {
            showToast('danger', 'Error', data.message || 'Failed to approve application');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showToast('danger', 'Error', 'Failed to approve application');
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        });
    });
  }

  // Grace Period & Penalty Preview Handler
  const gracePeriodInput = document.getElementById('gracePeriodDays');
  const penaltyAmountInput = document.getElementById('penaltyAmount');
  const penaltyTypeSelect = document.getElementById('penaltyType');
  const penaltyPreview = document.getElementById('penaltyPreview');

  function updatePenaltyPreview() {
    const graceDays = parseInt(gracePeriodInput?.value || 0);
    const penaltyAmount = parseFloat(penaltyAmountInput?.value || 0);
    const penaltyType = penaltyTypeSelect?.value || 'percentage';

    if (!penaltyPreview) return;

    if (penaltyAmount === 0) {
      penaltyPreview.textContent = 'No penalty will be charged';
      return;
    }

    let previewText = `After ${graceDays} day${graceDays !== 1 ? 's' : ''} grace period, `;

    if (penaltyType === 'percentage') {
      previewText += `${penaltyAmount}% penalty will be charged on overdue EMI amount`;
    } else {
      previewText += `₹${penaltyAmount.toFixed(2)} penalty will be charged per day`;
    }

    penaltyPreview.textContent = previewText;
  }

  // Add event listeners for penalty preview
  if (gracePeriodInput && penaltyAmountInput && penaltyTypeSelect) {
    gracePeriodInput.addEventListener('input', updatePenaltyPreview);
    penaltyAmountInput.addEventListener('input', updatePenaltyPreview);
    penaltyTypeSelect.addEventListener('change', updatePenaltyPreview);

    // Initial preview update
    updatePenaltyPreview();
  }

  // Reject Button Handler
  const confirmRejectBtn = document.getElementById('confirmRejectBtn');
  if (confirmRejectBtn) {
    confirmRejectBtn.addEventListener('click', function () {
      const reason = document.getElementById('loanRejectReason').value;

      // Show loading state
      const originalText = confirmRejectBtn.innerHTML;
      confirmRejectBtn.disabled = true;
      confirmRejectBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

      // Send rejection request
      fetch(`${baseUrl}loan/loan-applications/${applicationId}/reject`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ reason: reason })
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Close reject modal
            const rejectModal = bootstrap.Modal.getInstance(document.getElementById('rejectModal'));
            rejectModal.hide();

            showToast('success', data.message);
            setTimeout(() => {
              window.location.reload();
            }, 1500);
          } else {
            showToast('danger', 'Error', data.message || 'Failed to reject application');
            confirmRejectBtn.disabled = false;
            confirmRejectBtn.innerHTML = originalText;
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showToast('danger', 'Error', 'Failed to reject application');
          confirmRejectBtn.disabled = false;
          confirmRejectBtn.innerHTML = originalText;
        });
    });
  }

  // Disburse Button Handler with modal confirmation
  const disburseBtn = document.getElementById('disburseBtn');
  const disburseModalEl = document.getElementById('disburseModal');
  const confirmDisburseBtn = document.getElementById('confirmDisburseBtn');
  const transactionIdInput = document.getElementById('transactionId');
  const utrNumberInput = document.getElementById('utrNumber');

  if (disburseBtn && disburseModalEl && confirmDisburseBtn) {
    const disburseModal = new bootstrap.Modal(disburseModalEl);

    let disburseOriginalText;
    let confirmOriginalText;

    disburseBtn.addEventListener('click', function () {
      // Debug: Check current form values before showing modal
      const currentGracePeriod = document.getElementById('gracePeriodDays')?.value;
      const currentPenalty = document.getElementById('penaltyAmount')?.value;
      const currentPenaltyType = document.getElementById('penaltyType')?.value;

      console.log('Current form values when disburse clicked:', {
        gracePeriod: currentGracePeriod,
        penalty: currentPenalty,
        penaltyType: currentPenaltyType
      });

      disburseModal.show();
    });

    confirmDisburseBtn.addEventListener('click', function () {
      const transactionIdValue = transactionIdInput?.value.trim();
      const utrNumberValue = utrNumberInput?.value.trim();

      // Get bank account field values
      const bankNameInput = document.getElementById('bankName');
      const accountNumberInput = document.getElementById('accountNumber');
      const holderNameInput = document.getElementById('holderName');
      const accountTypeInput = document.getElementById('accountType');
      const ifscCodeInput = document.getElementById('ifscCode');

      const bankNameValue = bankNameInput?.value.trim();
      const accountNumberValue = accountNumberInput?.value.trim();
      const holderNameValue = holderNameInput?.value.trim();
      const accountTypeValue = accountTypeInput?.value;
      const ifscCodeValue = ifscCodeInput?.value.trim();
      const disbursedAtValue = document.getElementById('disbursedAt')?.value;

      // Validate all required fields
      const isTransactionInvalid = !transactionIdValue;
      const isDisbursedAtInvalid = !disbursedAtValue;
      const isUtrInvalid = !utrNumberValue;
      const isBankNameInvalid = !bankNameValue;
      const isAccountNumberInvalid = !accountNumberValue;
      const isHolderNameInvalid = !holderNameValue;
      const isAccountTypeInvalid = !accountTypeValue;
      const isIfscCodeInvalid = !ifscCodeValue;

      // Apply validation classes
      transactionIdInput?.classList.toggle('is-invalid', isTransactionInvalid);
      utrNumberInput?.classList.toggle('is-invalid', isUtrInvalid);
      bankNameInput?.classList.toggle('is-invalid', isBankNameInvalid);
      accountNumberInput?.classList.toggle('is-invalid', isAccountNumberInvalid);
      holderNameInput?.classList.toggle('is-invalid', isHolderNameInvalid);
      accountTypeInput?.classList.toggle('is-invalid', isAccountTypeInvalid);
      ifscCodeInput?.classList.toggle('is-invalid', isIfscCodeInvalid);

      if (isTransactionInvalid || isUtrInvalid || isBankNameInvalid || isAccountNumberInvalid ||
        isHolderNameInvalid || isAccountTypeInvalid || isIfscCodeInvalid || isDisbursedAtInvalid) {
        showToast('danger', 'Missing Details', 'Please fill in all required fields including Disbursement Date.');
        return;
      }

      disburseOriginalText = disburseOriginalText || disburseBtn.innerHTML;
      confirmOriginalText = confirmOriginalText || confirmDisburseBtn.innerHTML;

      // Show loading state on confirm button
      confirmDisburseBtn.disabled = true;
      confirmDisburseBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

      // Prevent additional clicks on main button
      disburseBtn.disabled = true;
      disburseBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

      // Get grace period and penalty data with better error handling
      const gracePeriodElement = document.getElementById('gracePeriodDays');
      const penaltyElement = document.getElementById('penaltyAmount');
      const penaltyTypeElement = document.getElementById('penaltyType');

      const gracePeriodValue = gracePeriodElement ? gracePeriodElement.value : '0';
      const penaltyValue = penaltyElement ? penaltyElement.value : '0';
      const penaltyTypeValue = penaltyTypeElement ? penaltyTypeElement.value : 'percentage';

      const disbursementData = {
        grace_period_days: parseInt(gracePeriodValue) || 0,
        penalty: parseFloat(penaltyValue) || 0,
        penalty_type: penaltyTypeValue,
        transaction_id: transactionIdValue,
        utr_number: utrNumberValue,
        bank_name: bankNameValue,
        account_number: accountNumberValue,
        holder_name: holderNameValue,
        account_type: accountTypeValue,
        ifsc_code: ifscCodeValue,
        disbursed_at: disbursedAtValue
      };

      fetch(`${baseUrl}loan/loan-applications/${applicationId}/disburse`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(disbursementData)
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Play success sound
            const successSound = new Audio('/assets/audio/disburse-sound.mp3');
            successSound.play().catch(error => {
              console.log('Sound play prevented:', error);
            });

            disburseModal.hide();
            showToast('success', data.message);
            setTimeout(() => {
              window.location.reload();
            }, 1500);
          } else {
            showToast('danger', 'Error', data.message || 'Failed to disburse loan');
            disburseBtn.disabled = false;
            disburseBtn.innerHTML = disburseOriginalText;
            confirmDisburseBtn.disabled = false;
            confirmDisburseBtn.innerHTML = confirmOriginalText;
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showToast('danger', 'Error', 'Failed to disburse loan');
          disburseBtn.disabled = false;
          disburseBtn.innerHTML = disburseOriginalText;
          confirmDisburseBtn.disabled = false;
          confirmDisburseBtn.innerHTML = confirmOriginalText;
        });
    });

    disburseModalEl.addEventListener('hidden.bs.modal', function () {
      confirmDisburseBtn.disabled = false;
      confirmDisburseBtn.innerHTML = confirmOriginalText || confirmDisburseBtn.innerHTML;
      if (!document.body.contains(disburseBtn)) {
        return;
      }
      disburseBtn.disabled = false;
      disburseBtn.innerHTML = disburseOriginalText || disburseBtn.innerHTML;
    });
  }

  // Toast notification function
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
    } else {
      iconClass = 'ri-error-warning-line';
      bgClass = 'bg-danger';
    }

    // If no message provided, show title only (no body section)
    const toastHTML = message
      ? `
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
    `
      : `
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

  function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
  }

  // Video Lazy Loading Handler
  const loadVideoBtn = document.getElementById('loadVideoBtn');
  const videoPlaceholder = document.getElementById('videoPlaceholder');
  const videoPlayer = document.getElementById('videoPlayer');
  const verificationVideo = document.getElementById('verificationVideo');

  if (loadVideoBtn) {
    loadVideoBtn.addEventListener('click', function () {
      const videoUrl = this.getAttribute('data-video-url');

      // Show loading state
      loadVideoBtn.disabled = true;
      loadVideoBtn.innerHTML = '<i class="icon-base ri ri-loader-4-line me-2 spinner-border spinner-border-sm"></i>Loading Video...';

      // Load video sources
      const sources = verificationVideo.querySelectorAll('source');
      sources.forEach(source => {
        const dataSrc = source.getAttribute('data-src');
        if (dataSrc) {
          source.setAttribute('src', dataSrc);
        }
      });

      // Load the video
      verificationVideo.load();

      // Wait for video to be ready
      verificationVideo.addEventListener('loadedmetadata', function () {
        // Hide placeholder
        videoPlaceholder.style.display = 'none';

        // Show video player
        videoPlayer.style.display = 'block';

        // Auto play the video
        verificationVideo.play().catch(error => {
          console.log('Auto-play prevented:', error);
        });
      }, { once: true });

      // Handle loading errors
      verificationVideo.addEventListener('error', function () {
        loadVideoBtn.disabled = false;
        loadVideoBtn.innerHTML = '<i class="icon-base ri ri-error-warning-line me-2"></i>Failed to Load';
        loadVideoBtn.classList.remove('btn-light');
        loadVideoBtn.classList.add('btn-danger');

        // Show error toast
        showToast('danger', 'Video Load Error', 'Failed to load video. Please try again or check the video URL.');
      }, { once: true });
    });
  }
});
